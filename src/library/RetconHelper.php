<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:24
 */

namespace mmikkel\retcon\library;

use mmikkel\retcon\models\RetconSettings;
use mmikkel\retcon\Retcon;

use Craft;
use craft\base\Image;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\models\AssetTransform;

use yii\base\Exception;

class RetconHelper
{

    /**
     * @var array
     */
    protected static $transforms = [];
    /**
     * @var array
     */
    protected static $allowedTransformExtensions = ['jpg', 'png', 'gif'];

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

        return (object) $selector;

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

        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();
        $useImager = $settings->useImager;

        if (\is_string($transform)) {

            // Named transform
            $transformName = $transform;

            if (isset(self::$transforms[$transformName])) {
                return self::$transforms[$transformName];
            }

            $transform = Craft::$app->getAssetTransforms()->getTransformByHandle($transform);

            if ($useImager && $transform) {
                $transform = $transform->getAttributes();
            }

            self::$transforms[$transformName] = $transform;

            return $transform;

        } else if (\is_array($transform)) {

            // Template transform
            return $useImager ? $transform : Craft::$app->getAssetTransforms()->normalizeTransform($transform);

        }

        return null;
    }

    /**
     * @param string $src
     * @param $transform
     * @param array $imagerTransformDefaults
     * @param array $imagerConfigOverrides
     * @return object|array|bool
     * @throws Exception
     * @throws \aelvan\imager\exceptions\ImagerException
     * @throws \craft\errors\ImageException
     */
    public static function getTransformedImage(string $src, $transform, array $imagerTransformDefaults = [], array $imagerConfigOverrides = [])
    {

        $imageUrl = Craft::$app->getElements()->parseRefs($src);
        $imageUrlInfo = \parse_url($imageUrl);

        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();

        // If we can use Imager, we need to minimal work
        if ($settings->useImager) {
            /** @var \aelvan\imager\Imager $imagerPlugin */
            $imagerPlugin = Craft::$app->plugins->getPlugin('imager');
            return $imagerPlugin->imager->transformImage($imageUrl, $transform, $imagerTransformDefaults, $imagerConfigOverrides);
        }

        // Normalize the transform
        $transformWidth = $transform->width ?: 'AUTO';
        $transformHeight = $transform->height ?: 'AUTO';
        $transformMode = $transform->mode ?: 'crop';
        $transformPosition = $transform->position ?: 'center-center';
        $transformQuality = $transform->quality ?: Craft::$app->getConfig()->getGeneral()->defaultImageQuality ?: 90;
        $transformFormat = $transform->format ?: null;

        // Set format to jpg if we dont have Imagick installed
        if ($transformFormat !== 'jpg' && !Craft::$app->getImages()->getIsImagick()) {
            $transformFormat = 'jpg';
        }

        // Create transform handle if missing
        $transformHandle = isset($transform->handle) && $transform->handle ? $transform->handle : null;
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
        $basePath = $settings->baseTransformPath;
        $baseUrl = $settings->baseTransformUrl;
        $siteUrl = UrlHelper::siteUrl();

        $host = \parse_url($siteUrl, PHP_URL_HOST);

        $imagePathInfo = \pathinfo($imageUrlInfo['path'] ?? '');

        // Check extension
        if (!\in_array(\strtolower($imagePathInfo['extension']), self::$allowedTransformExtensions)) {
            return false;
        }

        // Is image local?
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal) {
            // Non-local images not supported – use Imager!
            return false;
        }

        $useAbsoluteUrl = rtrim($baseUrl, '/') !== rtrim($siteUrl, '/') || strpos($imageUrl, 'http') > -1 ? true : false;

        // Build filename/path
        $imageTransformedFilename = self::fixSlashes($imagePathInfo['filename'] . '.' . ($transformFormat ?: $imagePathInfo['extension']));
        $imageTransformedFolder = self::fixSlashes($basePath . $imagePathInfo['dirname'] . '/_' . $transformHandle);
        $imageTransformedPath = self::fixSlashes($imageTransformedFolder . '/' . $imageTransformedFilename);

        // Exit if local file doesn't exist
        if (!\file_exists($basePath . $imageUrlInfo['path'])) {
            return false;
        }

        // We can haz folder?
        FileHelper::createDirectory($imageTransformedFolder);

        // Transform image
        if (!\file_exists($imageTransformedPath)) {

            $docImagePath = self::fixSlashes($basePath . $imageUrlInfo['path']);

            /** @var Image $image */
            $image = Craft::$app->getImages()->loadImage($docImagePath);

            if (!$image) {
                return false;
            }

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

            $image->saveAs($imageTransformedPath);
        }

        $imageTransformedUrl = self::fixSlashes(str_replace($basePath, ($useAbsoluteUrl ? $baseUrl : ''), $imageTransformedPath));

        return (object) [
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
     * @return array|bool|null
     * @throws \yii\base\Exception
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

        $imageUrl = $img->getAttribute('src');

        if (!$imageUrl) {
            return false;
        }

        /** @var RetconSettings $settings */
        $settings = Retcon::$plugin->getSettings();

        $basePath = $settings->baseTransformPath;
        $siteUrl = UrlHelper::siteUrl();
        $host = \parse_url($siteUrl, PHP_URL_HOST);
        $imageUrlInfo = \parse_url($imageUrl);
        $imagePath = self::fixSlashes($basePath . $imageUrlInfo['path']);
        $imageIsLocal = !(isset($imageUrlInfo['host']) && $imageUrlInfo['host'] !== $host);

        if (!$imageIsLocal || !\file_exists($imagePath)) {
            return null;
        }

        list($width, $height) = \getimagesize($imagePath);

        return [
            'width' => $width,
            'height' => $height,
        ];

    }

    /**
     * @param string $str
     * @return null|string|string[]
     */
    public static function fixSlashes(string $str)
    {
        return preg_replace('~(^|[^:])//+~', '\\1/', $str);
    }

}
