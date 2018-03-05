<?php
/**
 * Retcon plugin for Craft CMS 3.x
 *
 * A collection of powerful Twig filters for modifying HTML
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2017 Mats Mikkel Rummelhoff
 */

namespace mmikkel\retcon\services;

use mmikkel\retcon\library\RetconDom;
use mmikkel\retcon\library\RetconHelper;

use Craft;
use craft\base\Component;

/**
 * Retcon Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Mats Mikkel Rummelhoff
 * @package   Retcon
 * @since     1.0.0
 */
class RetconService extends Component
{

    /**
     * @param $html
     * @param $args
     * @return mixed
     * @throws \Exception
     */
    public function retcon($html, $args)
    {

        if (!$html) {
            return $html;
        }

        if (empty($args)) {
            throw new \Exception(Craft::t('No filter method or callbacks defined'));
        }

        $calls = is_array($args[0]) ? $args[0] : array($args);

        foreach ($calls as $call) {

            $args = is_array($call) ? $call : array($call);

            $filter = array_shift($args);

            if (!method_exists($this, $filter)) {
                throw new \Exception(Craft::t('Undefined filter method {filter}', [
                    'filter' => $filter,
                ]));
            }

            $html = call_user_func_array(array($this, $filter), array_merge(array($html), $args));

        }

        return $html;

    }

    /*
    * transform
    *
    * Apply an image transform to all images.
    *
    * @html String
    *
    * @transform Mixed
    * Named (String) or inline transform (Array)
    *
    */


    /**
     * @param $html
     * @param string|array $transform
     * @param array $imagerTransformDefaults
     * @param array $imagerConfigOverrides
     * @return \Twig_Markup
     * @throws \aelvan\imager\exceptions\ImagerException
     * @throws \craft\errors\AssetTransformException
     * @throws \craft\errors\ImageException
     * @throws \yii\base\Exception
     */
    public function transform($html, $transform, array $imagerTransformDefaults = [], array $imagerConfigOverrides = [])
    {

        if (!$html) {
            return $html;
        }

        $doc = new RetconDom($html);
        $docImages = $doc->getElementsByTagName('img');

        if (!$docImages) {
            return $html;
        }

        $transform = RetconHelper::getImageTransform($transform);

        if (!$transform) {
            return $html;
        }

        // Transform images
        foreach ($docImages as $docImage) {

            $src = $docImage->getAttribute('src');

            if (!$src) {
                continue;
            }

            $transformedImage = RetconHelper::getTransformedImage($src, $transform, $imagerTransformDefaults, $imagerConfigOverrides);

            if (!$transformedImage) {
                continue;
            }

            $docImage->setAttribute('src', $transformedImage->url);

            if ($docImage->getAttribute('width')) {
                $docImage->setAttribute('width', $transformedImage->width);
            }
            if ($docImage->getAttribute('height')) {
                $docImage->setAttribute('height', $transformedImage->height);
            }

        }

        return $doc->getHtml();

    }

    /*
    * srcset
    *
    *
    * @html String
    *
    * @transform Mixed
    * Named (String) or inline transform (Array)
    *
    */
    /**
     * @param $html
     * @param $transforms
     * @param string $sizes
     * @param bool $base64src
     * @param null $transformDefaults
     * @param null $configOverrides
     * @return \Twig_Markup
     * @throws \aelvan\imager\exceptions\ImagerException
     * @throws \craft\errors\AssetTransformException
     * @throws \craft\errors\ImageException
     * @throws \yii\base\Exception
     */
    public function srcset($html, $transforms, $sizes = '100w', $base64src = false, $transformDefaults = null, $configOverrides = null)
    {

        if (!$html) {
            return $html;
        }

        // Get images
        $doc = new RetconDom($html);
        $docImages = $doc->getElementsByTagName('img');

        if (!$docImages) {
            return $html;
        }

        // Get transforms
        if (!\is_array($transforms)) {
            $transforms = [$transforms];
        }

        $temp = [];
        foreach ($transforms as $transform) {
            $transform = RetconHelper::getImageTransform($transform);
            if ($transform) {
                $temp[] = $transform;
            }
        }
        if (empty($temp)) {
            return $html;
        }
        $transforms = $temp;

        // Get sizes attribute
        if ($sizes) {
            $sizes = !\is_array($sizes) ? [$sizes] : $sizes;
        }

        // Add srcset attribute to images
        foreach ($docImages as $docImage) {

            $imageUrl = Craft::$app->getElements()->parseRefs($docImage->getAttribute('src'));

            if (!$imageUrl) {
                continue;
            }

            // Get transformed images
            $transformedImages = [];
            foreach ($transforms as $transform) {
                $transformedImage = RetconHelper::getTransformedImage($imageUrl, $transform, $transformDefaults, $configOverrides);
                if ($transformedImage) {
                    $transformedImages[] = $transformedImage;
                }
            }
            if (empty($transformedImages)) {
                continue;
            }

            // Add srcset attribute
            $docImage->setAttribute('srcset', RetconHelper::getSrcsetAttribute($transformedImages));

            // Add sizes attribute
            if ($sizes) {
                $docImage->setAttribute('sizes', implode(', ', $sizes));
            }

            // Swap out the src for a base64 encoded SVG
            if ($base64src) {
                $dimensions = RetconHelper::getImageDimensions($docImage);
                $width = $dimensions ? $dimensions['width'] : null;
                $height = $dimensions ? $dimensions['height'] : null;
                $docImage->setAttribute('src', RetconHelper::getBase64Pixel($width, $height));
            }

        }

        return $doc->getHtml();

    }

    /*
    * lazy
    *
    * Replaces the src attribute with a base64 encoded, transparent SVG
    * The original source will be retained in a data attribute
    *
    * @className String
    * Class for lazy images (optional, default "lazy")
    *
    * @attributeName String
    * Name of data attribute for original source (optional, default "original")
    *
    */
    /**
     * @param $html
     * @param null $className
     * @param null $attributeName
     * @return \Twig_Markup
     * @throws \yii\base\Exception
     */
    public function lazy($html, $className = null, $attributeName = null)
    {

        if (!$html) {
            return $html;
        }

        $doc = new RetconDom($html);

        if (!$docImages = $doc->getElementsByTagName('img')) {
            return $html;
        }

        $attributeName = 'data-' . ($attributeName ?: 'original');
        $className = $className ?: 'lazy';

        foreach ($docImages as $docImage) {
            $imageClasses = \explode(' ', $docImage->getAttribute('class'));
            $imageClasses[] = $className;
            $dimensions = RetconHelper::getImageDimensions($docImage);
            $width = $dimensions ? $dimensions['width'] : null;
            $height = $dimensions ? $dimensions['height'] : null;
            $docImage->setAttribute('class', \trim(\implode(' ', $imageClasses)));
            $docImage->setAttribute($attributeName, $docImage->getAttribute('src'));
            $docImage->setAttribute('src', RetconHelper::getBase64Pixel($width, $height));
            $docImage->setAttribute('width', $width);
            $docImage->setAttribute('height', $height);
        }

        return $doc->getHtml();

    }

    /*
    * autoAlt
    *
    * Adds filename as alt attribute for images missing alternative text. Optionally overwrite alt attribute for all images
    *
    * @overwrite Boolean
    * Overwrite existing alt attributes (optional, default false)
    *
    */
    /**
     * @param $html
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public function autoAlt($html, $overwrite = false)
    {

        if (!$html) {
            return $html;
        }

        $doc = new RetconDom($html);

        if (!$docImages = $doc->getElementsByTagName('img')) {
            return $html;
        }

        foreach ($docImages as $docImage) {

            $alt = $docImage->getAttribute('alt');

            if (!$alt || strlen($alt) === 0) {
                $imageSource = $docImage->getAttribute('src');
                $imageSourcePathinfo = \pathinfo($imageSource);
                $docImage->setAttribute('alt', $imageSourcePathinfo['filename']);
            }

        }

        return $doc->getHtml();

    }

    /*
    * attr
    *
    * Adds or replaces one or many attributes for one or many selectors
    *
    * @selectors Mixed
    * String or Array of strings
    *
    * @attributes Array
    * Associative array of attribute names and values
    *
    * @overwrite Boolean
    * Overwrites existing attribute values (optional, true)
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public function attr($html, $selectors, $attributes, $overwrite = true)
    {

        if (!$html) {
            return $html;
        }

        $selectors = is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        foreach ($selectors as $selector) {

            // Get all matching selectors, and add/replace attributes
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            foreach ($elements as $element) {

                foreach ($attributes as $key => $value) {

                    // Add or remove?
                    if (!$value) {

                        $element->removeAttribute($key);

                    } else if ($value === true) {

                        $element->setAttribute($key, '');

                    } else {

                        if (!$overwrite && $key !== 'id') {
                            $attributeValues = \explode(' ', $element->getAttribute($key));
                            if (!\in_array($value, $attributeValues)) {
                                $attributeValues[] = $value;
                            }
                        } else {
                            $attributeValues = array($value);
                        }

                        $element->setAttribute($key, \trim(\implode(' ', $attributeValues)));
                    }

                }

            }

        }

        return $doc->getHtml();

    }

    /*
    * renameAttr
    *
    * Renames attributes for matching selectors
    *
    * @selectors Mixed
    * String or Array of strings
    *
    * @attributes Array
    * Associative array of attribute names and desired names
    *
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @return \Twig_Markup
     */
    public function renameAttr($html, $selectors, $attributes)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        foreach ($selectors as $selector) {

            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            foreach ($elements as $element) {

                foreach ($attributes as $existingAttributeName => $desiredAttributeName) {

                    if (!$desiredAttributeName || $existingAttributeName === $desiredAttributeName || !$element->hasAttribute($existingAttributeName)) {
                        continue;
                    }

                    $attributeValue = $element->getAttribute($existingAttributeName);
                    $element->removeAttribute($existingAttributeName);
                    $element->setAttribute($desiredAttributeName, $attributeValue);

                }

            }

        }

        return $doc->getHtml();

    }

    /*
    * remove
    *
    * Remove all elements matching given selector(s)
    *
    * @selectors Mixed
    * String or Array of strings
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public function remove($html, $selectors)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        foreach ($selectors as $selector) {

            // Get all matching selectors, and remove them
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            $numElements = $elements->length;

            for ($i = $numElements - 1; $i >= 0; --$i) {
                $element = $elements->item($i);
                $element->parentNode->removeChild($element);
            }

        }

        return $doc->getHtml();

    }

    /*
    * only
    *
    * Remove everything except elements matching given selector(s)
    *
    * @selectors Mixed
    * String or Array of strings
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public function only($html, $selectors)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);
        $fragment = $doc->createDocumentFragment();

        foreach ($selectors as $selector) {

            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            foreach ($elements as $element) {
                $fragment->appendChild($element);
            }

        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $body->parentNode->replaceChild($fragment, $body);

        return $doc->getHtml();

    }

    /*
    * change
    *
    * Changes tag type/name for given selector(s)
    *
    * @selectors Mixed
    * String or Array of strings
    *
    * @toTag String/Boolean
    * Tag type matching elements will be converted to, e.g. "span"
    * Pass `false` to remove tag, retaining content
    */
    /**
     * @param $html
     * @param $selectors
     * @param $toTag
     * @return \Twig_Markup
     */
    public function change($html, $selectors, $toTag)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        foreach ($selectors as $selector) {

            // Get all matching selectors, and add/replace attributes
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            $numElements = $elements->length;

            for ($i = $numElements - 1; $i >= 0; --$i) {

                $element = $elements->item($i);

                // Deep copy the (inner) element
                $fragment = $doc->createDocumentFragment();
                while($element->childNodes->length > 0) {
                    $fragment->appendChild($element->childNodes->item(0));
                }

                // Remove or change the tag?
                if (!$toTag) {

                    // Remove it chief
                    $element->parentNode->replaceChild($fragment, $element);

                } else {

                    // Ch-ch-changes
                    $newElement = $element->ownerDocument->createElement($toTag);
                    foreach ($element->attributes as $attribute) {
                        $newElement->setAttribute($attribute->nodeName, $attribute->nodeValue);
                    }
                    $newElement->appendChild($fragment);
                    $element->parentNode->replaceChild($newElement, $element);

                }

            }

        }

        return $doc->getHtml();

    }

    /*
    * wrap
    *
    * Wraps one or many selectors
    *
    * @selectors Mixed
    * String or Array of strings
    *
    * @wrapper String
    * Element to create as wrapper, e.g. "div.wrapper"
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @param $wrapper
     * @return \Twig_Markup
     */
    public function wrap($html, $selectors, $wrapper)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        // Get wrapper
        $wrapper = RetconHelper::getSelectorObject($wrapper);
        $wrapper->tag = $wrapper->tag === '*' ? 'div' : $wrapper->tag;
        $wrapperNode = $doc->createElement($wrapper->tag);

        if ($wrapper->attribute) {
            $wrapperNode->setAttribute($wrapper->attribute, $wrapper->attributeValue);
        }

        foreach ($selectors as $selector) {

            // Get all matching selectors, and add/replace attributes
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            $numElements = $elements->length;

            for ($i = $numElements - 1; $i >= 0; --$i) {

                $element = $elements->item($i);
                $wrapperClone = $wrapperNode->cloneNode(true);
                $element->parentNode->replaceChild($wrapperClone, $element);
                $wrapperClone->appendChild($element);

            }

        }

        return $doc->getHtml();

    }

    /*
    * unwrap
    *
    * Removes the parent of given selector(s), retaining all child nodes
    *
    * @selectors Mixed
    * String or Array of strings
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public function unwrap($html, $selectors)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        foreach ($selectors as $selector) {

            // Get all matching selectors, and add/replace attributes
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            $numElements = $elements->length;

            for ($i = $numElements - 1; $i >= 0; --$i) {

                $element = $elements->item($i);
                $parentNode = $element->parentNode;
                $fragment = $doc->createDocumentFragment();

                while ($parentNode->childNodes->length > 0) {
                    $fragment->appendChild($parentNode->childNodes->item(0));
                }

                $parentNode->parentNode->replaceChild($fragment, $parentNode);

            }

        }

        return $doc->getHtml();

    }

    /*
    * inject
    *
    * Injects string value into all elements matching given selector(s)
    *
    * @selectors Mixed
    * String or Array of strings
    *
    * @toInject String
    * Content to inject
    *
    */
    /**
     * @param $html
     * @param $selectors
     * @param $toInject
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public function inject($html, $selectors, $toInject, $overwrite = false)
    {

        if (!$html) {
            return $html;
        }

        $selectors = \is_array($selectors) ? $selectors : [$selectors];

        $doc = new RetconDom($html);

        // What are we trying to inject, exactly?
        if (\preg_match("/<[^<]+>/", $toInject, $matches) != 0) {
            // Injected content is HTML
            $fragmentDoc = new RetconDom('<div id="injectWrapper">' . $toInject . '</div>');
            $injectNode = $fragmentDoc->getElementById('injectWrapper')->childNodes->item(0);
        } else {
            $textNode = $doc->createTextNode("{$toInject}");
        }

        foreach ($selectors as $selector) {

            // Get all matching selectors, and add/replace attributes
            if (!$elements = $doc->getElementsBySelector($selector)) {
                continue;
            }

            $numElements = $elements->length;

            for ($i = $numElements - 1; $i >= 0; --$i) {

                $element = $elements->item($i);

                if (!$overwrite) {

                    if (isset($injectNode)) {
                        $element->appendChild($doc->importNode($injectNode->cloneNode(true), true));
                    } else {
                        $element->appendChild($textNode->cloneNode());
                    }

                } else {

                    if (isset($injectNode)) {
                        $element->nodeValue = "";
                        $element->appendChild($doc->importNode($injectNode->cloneNode(true), true));
                    } else {
                        $element->nodeValue = $toInject;
                    }

                }

            }

        }

        return $doc->getHtml();

    }

    /*
    * removeEmpty
    *
    * Removes empty nodes
    *
    * @selectors Mixed
    * String or Array of strings
    *
    */
    /**
     * @param $html
     * @return \Twig_Markup
     */
    public function removeEmpty($html)
    {

        if (!$html) {
            return $html;
        }

        $doc = new RetconDom($html);
        $xpath = $doc->getXPath();

        while (($nodes = $xpath->query('//*[not(*) and not(@*) and not(text()[normalize-space()])]')) && $nodes->length) {
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return $doc->getHtml();

    }

    /**
     * @param $html
     * @param $pattern
     * @param string $replace
     * @return null|string|string[]
     */
    public function replace($html, $pattern, $replace = '')
    {
        if (!$html) {
            return $html;
        }
        return preg_replace($pattern, $replace, $html);
    }

}
