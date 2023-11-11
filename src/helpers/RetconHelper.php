<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:24
 */

namespace mmikkel\retcon\helpers;

use aelvan\imager\Imager;

use mmikkel\retcon\models\RetconTransformedImage;

use spacecatninja\imagerx\ImagerX;

use mmikkel\retcon\models\RetconSettings;
use mmikkel\retcon\Retcon;

use Craft;
use craft\base\PluginInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use craft\helpers\Image as ImageHelper;
use craft\helpers\ImageTransforms;
use craft\helpers\StringHelper;
use craft\helpers\Template as TemplateHelper;
use craft\helpers\UrlHelper;

use craft\redactor\FieldData as RedactorFieldData;
use craft\htmlfield\HtmlFieldData;

use Twig\Markup;

use yii\base\Exception;
use yii\helpers\Json;

class RetconHelper
{

    /**
     * @var array
     */
    protected static $transforms = [];

    /**
     * @param mixed $value
     * @return null|string
     */
    public static function getHtmlFromParam($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if ($value instanceof RedactorFieldData || $value instanceof HtmlFieldData) {
            $html = $value->getRawContent();
        } else {
            $html = (string)$value;
        }
        if (!\preg_replace('/\s+/', '', $value)) {
            return null;
        }
        return $html;
    }

    /**
     * @param int $width
     * @param int $height
     * @return string
     */
    public static function getBase64Pixel(int $width = 1, int $height = 1): string
    {
        return "data:image/svg+xml;charset=utf-8," . \rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width $height'/>");
    }

    /**
     * @param mixed $transform
     * @return mixed
     * @throws \craft\errors\AssetTransformException
     * @throws \craft\errors\ImageTransformException
     */
    public static function getImageTransform($transform)
    {

        /** @var Imager|ImagerX|PluginInterface $imagerPlugin */
        $imagerPlugin = static::getImagerPlugin();
        $useImager = (bool)$imagerPlugin;

        $isCraft4 = \version_compare(Craft::$app->getVersion(), '4.0', '>=');

        if (\is_string($transform)) {

            // Named transform
            $transformName = $transform;

            if (isset(static::$transforms[$transformName])) {
                return static::$transforms[$transformName];
            }

            if ($isCraft4) {
                $transform = Craft::$app->getImageTransforms()->getTransformByHandle($transform);
            } else {
                $transform = Craft::$app->getAssetTransforms()->getTransformByHandle($transform);
            }

            if ($useImager) {
                if ($transform) {
                    $transform = $transform->getAttributes(['width', 'height', 'format', 'mode', 'position', 'interlace', 'quality']);
                } else {
                    $transform = $transformName;
                }
            }

            static::$transforms[$transformName] = $transform;

            return $transform;

        }

        if ($useImager) {
            return $transform;
        }

        if ($isCraft4) {
            return ImageTransforms::normalizeTransform($transform);
        }

        return Craft::$app->getAssetTransforms()->normalizeTransform($transform);
    }

    /**
     * @param string $src
     * @param $transform
     * @param array|null $imagerTransformDefaults
     * @param array|null $imagerConfigOverrides
     * @return RetconTransformedImage|null
     * @throws Exception
     * @throws \craft\errors\ImageException
     * @throws \spacecatninja\imagerx\exceptions\ImagerException
     */
    public static function getTransformedImage(string $src, $transform, ?array $imagerTransformDefaults = null, ?array $imagerConfigOverrides = null): ?RetconTransformedImage
    {

        // TODO: In Retcon 3.0, we should try to get the asset via RetconHelper::getAssetFromRef(), and transform that directly
        // I.e. via `$asset->getUrl($transform)` or by passing the asset to Imager
        $imageUrl = Craft::$app->getElements()->parseRefs($src);

        // If we can use Imager, we need to do minimal work
        $imagerPlugin = static::getImagerPlugin();
        if ($imagerPlugin) {
            if ($extension = strtok(strtolower(/** @scrutinizer ignore-type */ pathinfo($imageUrl, PATHINFO_EXTENSION)), '?')) {
                $safeFileFormats =  $imagerPlugin->imager::getConfig()->safeFileFormats ?? null;
                if (!is_array($safeFileFormats) || empty($safeFileFormats)) {
                    $safeFileFormats = null;
                }
                $safeFileFormats = $safeFileFormats ?? ['jpg', 'jpeg', 'gif', 'png'];
                $safeFileFormats = array_map(static function (string $extension) {
                    return strtolower($extension);
                }, $safeFileFormats);
                if (!in_array($extension, $safeFileFormats)) {
                    return null;
                }
            }
            /** @var Imager|ImagerX $imagerPlugin */
            $transformedImage = $imagerPlugin->imager->transformImage($imageUrl, $transform, $imagerTransformDefaults ?? [], $imagerConfigOverrides ?? []);
            if (empty($transformedImage)) {
                return null;
            }
            if (is_array($transformedImage)) {
                $transformedImage = $transformedImage[0] ?? null;
            }
            return new RetconTransformedImage([
                'url' => $transformedImage->getUrl(),
                'width' => $transformedImage->getWidth(),
                'height' => $transformedImage->getHeight(),
            ]);
        }

        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();

        if (!$settings->baseTransformPath || !\is_string($settings->baseTransformPath)) {
            throw new Exception('No base transform URL found in settings. Please add a valid path to the `baseTransformPath` setting in /config/retcon.php');
        }

        if (!$settings->baseTransformUrl || !\is_string($settings->baseTransformUrl)) {
            throw new Exception('No base transform URL found in settings. Please add a valid URL to the `baseTransformUrl` setting in /config/retcon.php');
        }

        $imageUrlInfo = \parse_url($imageUrl);

        $transform = (object)$transform;

        // Normalize the transform
        $transformWidth = $transform->width ?? 'AUTO';
        $transformHeight = $transform->height ?? 'AUTO';
        $transformMode = $transform->mode ?? 'crop';
        $transformPosition = $transform->position ?? 'center-center';
        $transformQuality = $transform->quality ?? Craft::$app->getConfig()->getGeneral()->defaultImageQuality ?? 90;
        $transformFormat = $transform->format ?? null;

        // Set format to jpg if we dont have Imagick installed
        if ($transformFormat !== 'jpg' && !Craft::$app->getImages()->getIsImagick()) {
            $transformFormat = 'jpg';
        }

        // Create transform handle if missing
        $transformHandle = property_exists($transform, 'handle') && $transform->handle ? $transform->handle : null;
        if (!$transformHandle) {
            $transformFilenameAttributes = [
                $transformWidth . 'x' . $transformHeight,
                $transformMode,
                $transformPosition,
                $transformQuality
            ];
            $transformHandle = \implode('_', $transformFilenameAttributes);
        }

        // Get basepaths and URLs
        $basePath = StringHelper::ensureRight($settings->baseTransformPath, '/');
        $baseUrl = StringHelper::ensureRight($settings->baseTransformUrl, '/');
        $siteUrl = StringHelper::ensureRight(UrlHelper::siteUrl(), '/');

        $host = \parse_url($siteUrl, PHP_URL_HOST);

        $imagePathInfo = \pathinfo($imageUrlInfo['path'] ?? '');

        // Check extension
        $allowedExtensions = ImageHelper::webSafeFormats();
        if (!isset($imagePathInfo['extension']) || !\in_array(\strtolower($imagePathInfo['extension']), $allowedExtensions)) {
            return null;
        }

        // Is image local?
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal) {
            // Non-local images not supported – use Imager!
            return null;
        }

        // Build filename/path
        $imageTransformedFilename = static::fixSlashes($imagePathInfo['filename'] . '.' . ($transformFormat ?: $imagePathInfo['extension']));
        $imageTransformedFolder = static::fixSlashes($basePath . $imagePathInfo['dirname'] . '/_' . $transformHandle);
        $imageTransformedPath = static::fixSlashes($imageTransformedFolder . '/' . $imageTransformedFilename);

        // Exit if local file doesn't exist
        $isDevMode = YII_DEBUG;
        $imagePath = static::fixSlashes($basePath . '/' . $imageUrlInfo['path']);

        if (!\file_exists($imagePath)) {
            if ($isDevMode) {
                throw new Exception(Craft::t('retcon', 'Image {path} not found', [
                    'path' => $imagePath,
                ]));
            }
            return null;
        }

        // We can haz folder?
        FileHelper::createDirectory($imageTransformedFolder);

        // Transform image
        if (!\file_exists($imageTransformedPath)) {

            $image = Craft::$app->getImages()->loadImage($imagePath);
            $upscaleImages = Craft::$app->getConfig()->getGeneral()->upscaleImages;

            switch ($transformMode) {
                case 'crop':
                    $image->scaleAndCrop($transform->width, $transform->height, $upscaleImages, $transform->position);
                    break;
                case 'fit':
                    $image->scaleToFit($transform->width, $transform->height, $upscaleImages);
                    break;
                default:
                    $image->resize($transform->width, $transform->height);
            }

            $success = $image->saveAs($imageTransformedPath);

            if (!$success && $isDevMode) {
                throw new Exception(Craft::t('retcon', 'Unable to save image {path} to {savePath}', [
                    'path' => $imagePath,
                    'savePath' => $imageTransformedPath,
                ]));
            }

        }

        $imageTransformedUrl = static::fixSlashes(\str_replace($basePath, $baseUrl, $imageTransformedPath));

        return new RetconTransformedImage([
            'url' => $imageTransformedUrl,
            'width' => $transformWidth,
            'height' => $transformHeight,
        ]);

    }

    /**
     * @param array $images
     * @param string $descriptor
     * @return string
     */
    public static function getSrcsetAttribute(array $images, string $descriptor = 'w'): string
    {
        $sizes = [];
        foreach ($images as $image) {
            $sizes[] = $image->url . ' ' . $image->width . $descriptor;
        }
        return \implode(', ', $sizes);
    }

    /**
     * Get image dimensions for an img DOM node
     *
     * @param \DOMNode $img
     * @return int[]|null
     * @throws Exception
     */
    public static function getImageDimensions(\DOMNode $img): ?array
    {

        $width = (int)($img->getAttribute('width') ?: null);
        $height = (int)($img->getAttribute('height') ?: null);

        if ($width && $height) {
            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        $imageUrl = (string)static::parseRef($img->getAttribute('src'));
        if ($imageUrl === '' || $imageUrl === '0') {
            return null;
        }

        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();
        $basePath = $settings->baseTransformPath;
        $siteUrl = UrlHelper::siteUrl();
        $host = \parse_url($siteUrl, PHP_URL_HOST);

        $imageUrlInfo = \parse_url($imageUrl);
        $imagePath = $imageUrlInfo['path'] ?? null;
        if (!$imagePath) {
            return null;
        }

        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);
        if (!$imageIsLocal) {
            return null;
        }

        $imageAbsolutePath = static::fixSlashes($basePath . '/' . $imagePath);

        if (!\file_exists($imageAbsolutePath) || \is_dir($imageAbsolutePath)) {
            return null;
        }

        $imageSize = \getimagesize($imageAbsolutePath) ?: [];

        if (empty($imageSize)) {
            return null;
        }

        $width = (int)($imageSize[0] ?? null);
        $height = (int)($imageSize[1] ?? null);

        if (!$width || !$height) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];

    }

    /**
     * @param $selector
     * @return object
     */
    public static function getSelectorObject($selector): object
    {

        $delimiters = array('id' => '#', 'class' => '.');

        $selectorStr = \preg_replace('/\s+/', '', $selector);

        $selector = array(
            'tag' => $selector,
            'attribute' => false,
            'attributeValue' => false,
        );

        // Check for class or ID
        foreach ($delimiters as $attribute => $indicator) {

            if (\strpos($selectorStr, $indicator) > -1) {

                $temp = \explode($indicator, $selectorStr);

                $selector['tag'] = $temp[0] !== '' ? $temp[0] : '*';

                if (($attributeValue = $temp[\count($temp) - 1]) !== '') {
                    $selector['attribute'] = $attribute;
                    $selector['attributeValue'] = $attributeValue;
                }

                break;

            }

        }

        return (object)$selector;

    }

    /**
     * @param string $str
     * @return null|string|string[]
     */
    public static function fixSlashes(string $str)
    {
        return preg_replace('~(^|[^:])//+~', '\\1/', $str);
    }

    /**
     * @param string $value
     * @return \Twig\Markup|\Twig\Markup
     * @throws \craft\errors\SiteNotFoundException
     */
    public static function parseRef(string $value): string
    {
        return TemplateHelper::raw(Craft::$app->getElements()->parseRefs($value, Craft::$app->getSites()->getCurrentSite()->id));
    }

    /**
     * @param string $ref
     * @return Asset|null
     */
    public static function getAssetFromRef(string $ref): ?Asset
    {
        if ($ref[0] !== '{' || $ref[strlen($ref) - 1] !== '}') {
            return null;
        }
        $refSegments = \explode(':', \strtr($ref, ['{' => '', '}' => '']));
        if (count($refSegments) < 2 || $refSegments[0] !== 'asset' || !($id = (int)$refSegments[1] ?? null)) {
            return null;
        }
        $asset = Asset::find()->id($id)->one();
        if (!$asset instanceof Asset) {
            return null;
        }
        return $asset;
    }

    /**
     * @return Imager|ImagerX|null
     */
    public static function getImagerPlugin()
    {
        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();
        if (!$settings->useImager) {
            return null;
        }
        $pluginsService = Craft::$app->getPlugins();
        $imagerPlugin = $pluginsService->getPlugin('imager-x') ?? $pluginsService->getPlugin('imager');
        if (!$imagerPlugin instanceof ImagerX && !$imagerPlugin instanceof Imager) {
            return null;
        }
        return $imagerPlugin;
    }

    /**
     * @param string $key
     * @param string|bool|array|null $attributes
     * @return array
     */
    public static function getNormalizedDomNodeAttributeValues(string $key, $attributes = null): array
    {

        if ($attributes instanceof Markup) {
            $attributes = (string)$attributes;
        }

        $attributes = Html::normalizeTagAttributes([$key => $attributes]);

        if (count($attributes) > 1) {
            $sorted = [];
            foreach (Html::$attributeOrder as $name) {
                if (isset($attributes[$name])) {
                    $sorted[$name] = $attributes[$name];
                }
            }
            $attributes = array_merge($sorted, $attributes);
        }

        $return = [];
        foreach ($attributes as $name => $value) {
            if (is_bool($value) || $value === null) {
                $return[$name] = $value;
            } elseif (\is_array($value)) {
                if (\in_array($name, Html::$dataAttributes)) {
                    foreach ($value as $n => $v) {
                        $attribute = "$name-$n";
                        if (is_bool($v) || $v === null) {
                            $return[$attribute] = $v;
                        } elseif (is_array($v)) {
                            $return[$attribute] = Json::htmlEncode($v);
                        } else {
                            $return[$attribute] = $v;
                        }
                    }
                } elseif ($name === 'class') {
                    if (empty($value)) {
                        continue;
                    }
                    if (count($value) > 1) {
                        // removes duplicate classes
                        $value = explode(' ', implode(' ', $value));
                        $value = array_unique($value);
                    }
                    $return[$name] = \trim(implode(' ', $value));
                } elseif ($name === 'style') {
                    if (empty($value)) {
                        continue;
                    }
                    $return[$name] = Html::cssStyleFromArray($value);
                } else {
                    $return[$name] = Json::htmlEncode($value);
                }
            } else {
                $return[$name] = $value;
            }
        }

        return $return;
    }

    /**
     * @param string|null $value
     * @return bool|string|null
     */
    public static function parseEnv(?string $value)
    {
        if (\version_compare(Craft::$app->getVersion(), '3.7.29', '<')) {
            return Craft::parseEnv($value);
        }
        return App::parseEnv($value);
    }

}
