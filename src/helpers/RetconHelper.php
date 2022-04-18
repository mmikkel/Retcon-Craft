<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:24
 */

namespace mmikkel\retcon\helpers;

use aelvan\imager\Imager;
use spacecatninja\imagerx\ImagerX;

use mmikkel\retcon\models\RetconSettings;
use mmikkel\retcon\Retcon;

use Craft;
use craft\base\Image;
use craft\base\PluginInterface;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use craft\helpers\Image as ImageHelper;
use craft\helpers\ImageTransforms;
use craft\helpers\StringHelper;
use craft\helpers\Template as TemplateHelper;
use craft\helpers\UrlHelper;
use craft\models\AssetTransform;

use yii\base\Exception;
use yii\helpers\Json;

class RetconHelper
{

    /**
     * @var array
     */
    protected static $transforms = [];

    /**
     * @param $value
     * @return null|string
     */
    public static function getHtmlFromParam($value)
    {
        $html = (string)$value;
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
    public static function getBase64Pixel($width = 1, $height = 1)
    {
        return "data:image/svg+xml;charset=utf-8," . \rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width $height'/>");
    }

    /**
     * @param string|array $transform
     * @return array|AssetTransform|mixed|null
     * @throws \craft\errors\AssetTransformException
     */
    public static function getImageTransform($transform)
    {

        /** @var Imager|ImagerX|PluginInterface $imagerPlugin */
        $imagerPlugin = RetconHelper::getImagerPlugin();
        $useImager = (bool) $imagerPlugin;

        $isCraft4 = \version_compare(Craft::$app->getVersion(), '4.0', '>=');

        if (\is_string($transform)) {

            // Named transform
            $transformName = $transform;

            if (isset(self::$transforms[$transformName])) {
                return self::$transforms[$transformName];
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

            self::$transforms[$transformName] = $transform;

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
     * @param string|array $transform
     * @param array $imagerTransformDefaults
     * @param array $imagerConfigOverrides
     * @return object|bool
     * @throws Exception
     * @throws \aelvan\imager\exceptions\ImagerException
     * @throws \craft\errors\ImageException
     */
    public static function getTransformedImage(string $src, $transform, array $imagerTransformDefaults = [], array $imagerConfigOverrides = [])
    {

        $imageUrl = Craft::$app->getElements()->parseRefs($src);

        // If we can use Imager, we need to do minimal work
        /** @var Imager $imagerPlugin */
        $imagerPlugin = self::getImagerPlugin();
        if ($imagerPlugin) {
            return $imagerPlugin->imager->transformImage($imageUrl, $transform, $imagerTransformDefaults, $imagerConfigOverrides);
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
        $transformHandle = property_exists($transform, 'handle') && $transform->handle !== null && $transform->handle ? $transform->handle : null;
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
            return false;
        }

        // Is image local?
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal) {
            // Non-local images not supported – use Imager!
            return false;
        }

        // Build filename/path
        $imageTransformedFilename = self::fixSlashes($imagePathInfo['filename'] . '.' . ($transformFormat ?: $imagePathInfo['extension']));
        $imageTransformedFolder = self::fixSlashes($basePath . $imagePathInfo['dirname'] . '/_' . $transformHandle);
        $imageTransformedPath = self::fixSlashes($imageTransformedFolder . '/' . $imageTransformedFilename);

        // Exit if local file doesn't exist
        $isDevMode = Craft::$app->getConfig()->getGeneral()->devMode;
        $imagePath = RetconHelper::fixSlashes($basePath . '/' . $imageUrlInfo['path']);

        if (!\file_exists($imagePath)) {
            if ($isDevMode) {
                throw new Exception(Craft::t('retcon', 'Image {path} not found', [
                    'path' => $imagePath,
                ]));
            }
            return false;
        }

        // We can haz folder?
        FileHelper::createDirectory($imageTransformedFolder);

        // Transform image
        if (!\file_exists($imageTransformedPath)) {

            /** @var Image $image */
            $image = Craft::$app->getImages()->loadImage($imagePath);

            switch ($transformMode) {
                case 'crop':
                    $image->scaleAndCrop($transform->width, $transform->height, true, $transform->position);
                    break;
                case 'fit':
                    $image->scaleToFit($transform->width, $transform->height, true);
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

        $imageTransformedUrl = self::fixSlashes(\str_replace($basePath, $baseUrl, $imageTransformedPath));

        return (object)[
            'url' => $imageTransformedUrl,
            'width' => $transformWidth,
            'height' => $transformHeight,
        ];

    }

    /**
     * @param array $images
     * @param string $descriptor
     * @return mixed
     */
    public static function getSrcsetAttribute(array $images, $descriptor = 'w')
    {
        $sizes = [];
        foreach ($images as $image) {
            $sizes[] = $image->url . ' ' . $image->width . $descriptor;
        }
        return \implode(', ', $sizes);
    }

    /**
     * @param \DOMNode $img
     * @return array|null
     * @throws Exception
     */
    public static function getImageDimensions(\DOMNode $img)
    {

        $width = $img->getAttribute('width') ?: null;
        $height = $img->getAttribute('height') ?: null;

        if ($width && $height) {
            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        $imageUrl = (string)RetconHelper::parseRef($img->getAttribute('src'));
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

        $imageAbsolutePath = self::fixSlashes($basePath . '/' . $imagePath);
        if (!\file_exists($imageAbsolutePath) || \is_dir($imageAbsolutePath)) {
            return null;
        }

        [$width, $height] = \getimagesize($imageAbsolutePath);

        return [
            'width' => $width,
            'height' => $height,
        ];

    }

    /**
     * @param $selector
     * @return object
     */
    public static function getSelectorObject($selector)
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
    public static function parseRef(string $value)
    {
        return TemplateHelper::raw(Craft::$app->getElements()->parseRefs($value, Craft::$app->getSites()->getCurrentSite()->id));
    }

    /**
     * @param string $ref
     * @return int|null
     */
    public static function getElementIdFromRef(string $ref)
    {
        if ($ref[0] !== '{' || $ref[strlen($ref) - 1] !== '}') {
            return null;
        }
        $refSegments = \explode(':', \strtr($ref, ['{' => '', '}' => '']));
        if (\count($refSegments) !== 3 || !($id = (int)$refSegments[1] ?? null)) {
            return null;
        }
        return $id;
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
        return $pluginsService->getPlugin('imager') ?? $pluginsService->getPlugin('imager-x');
    }

    /**
     * @param string $key
     * @param array $attributes
     * @return array
     */
    public static function getNormalizedDomNodeAttributeValues(string $key, array $attributes): array
    {

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
            if (is_bool($value)) {
                $return[$name] = $value;
            } elseif (\is_array($value)) {
                if (\in_array($name, Html::$dataAttributes)) {
                    foreach ($value as $n => $v) {
                        $attribute = "$name-$n";
                        if (is_bool($v)) {
                            $return[$attribute] = $v;
                        } elseif (is_array($v)) {
                            $return[$attribute] = Json::htmlEncode($v);
                        } else {
                            $return[$attribute] = Html::encode($v);
                        }
                    }
                } elseif ($name === 'class') {
                    if (empty($value)) {
                        continue;
                    }
                    if (Html::$normalizeClassAttribute === true && count($value) > 1) {
                        // removes duplicate classes
                        $value = explode(' ', implode(' ', $value));
                        $value = array_unique($value);
                    }
                    $return[$name] = Html::encode(implode(' ', $value));
                } elseif ($name === 'style') {
                    if (empty($value)) {
                        continue;
                    }
                    $return[$name] = Html::encode(Html::cssStyleFromArray($value));
                } else {
                    $return[$name] = Json::htmlEncode($value);
                }
            } elseif ($value !== null) {
                $return[$name] = Html::encode($value);
            }
        }

        return $return;
    }

}