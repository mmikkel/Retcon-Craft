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

use craft\base\Component;
use craft\errors\ImageException;
use craft\errors\ImageTransformException;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\Template as TemplateHelper;

use mmikkel\retcon\helpers\RetconHelper;
use mmikkel\retcon\library\RetconDom;

use Symfony\Component\DomCrawler\Crawler;

use Twig\Markup;

use yii\base\Exception;

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
     * @param $input
     * @param mixed ...$args
     * @return mixed|string|null
     * @throws Exception
     */
    public function retcon($input, ...$args)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        if (empty($args)) {
            throw new Exception('No filter method or callbacks defined');
        }

        $ops = \is_array($args[0]) ? $args[0] : [$args];

        foreach ($ops as $op) {

            $args = \is_array($op) ? $op : [$op];
            $filter = \array_shift($args);

            if (!\method_exists($this, $filter)) {
                throw new Exception("Retcon filter \"$filter\" does not exist");
            }

            $html = \call_user_func_array([$this, $filter], \array_merge([$html], $args));

        }

        return \Craft::$app->getElements()->parseRefs((string)$html);

    }

    /**
     * Applies an image transform to all images (or all nodes matching the passed selector(s))
     *
     * @param $input
     * @param $transform
     * @param string|string[]|null $selector
     * @param array|null $imagerTransformDefaults
     * @param array|null $imagerConfigOverrides
     * @return string|Markup|null
     * @throws Exception
     * @throws ImageException
     * @throws ImageTransformException
     * @throws SiteNotFoundException
     */
    public function transform($input, $transform, $selector = null, ?array $imagerTransformDefaults = null, ?array $imagerConfigOverrides = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $transform = RetconHelper::getImageTransform($transform);

        if (!$transform) {
            return $input;
        }

        if (empty($selector)) {
            $selector = 'img';
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            $src = $node->getAttribute('src');
            if (!$src) {
                continue;
            }

            if (!$transformedImage = RetconHelper::getTransformedImage($src, $transform, $imagerTransformDefaults, $imagerConfigOverrides)) {
                continue;
            }

            $node->setAttribute('src', $transformedImage->url);

            // Should we set width and height attributes?
            $nodeWidth = (int)$node->getAttribute('width');
            $nodeHeight = (int)$node->getAttribute('height');

            if ($nodeWidth || $nodeHeight) {
                continue;
            }

            $transformedImageDimensions = RetconHelper::getImageDimensions($node) ?? [];

            $width = $transformedImageDimensions['width'] ?? null;
            $height = $transformedImageDimensions['height'] ?? null;

            if (!$width || !$height) {
                continue;
            }

            $node->setAttribute('width', $width);
            $node->setAttribute('height', $height);

        }

        return $dom->getHtml();

    }

    /**
     * Creates a srcset attribute for all images (or all nodes matching the selector(s) passed) with the proper transforms
     *
     * @param $input
     * @param $transforms
     * @param string|string[]|null $selector
     * @param string|string[]|null $sizes
     * @param bool|null $base64src
     * @param array|null $imagerTransformDefaults
     * @param array|null $imagerConfigOverrides
     * @return string|Markup|null
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function srcset($input, $transforms, $selector = null, $sizes = null, ?bool $base64src = null, ?array $imagerTransformDefaults = null, ?array $imagerConfigOverrides = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        if (empty($selector)) {
            $selector = 'img';
        }

        if (empty($sizes)) {
            $sizes = '100w';
        }

        if (!is_bool($base64src)) {
            $base64src = false;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        // Get transforms
        if (!\is_array($transforms)) {
            $transforms = [$transforms];
        }

        $transforms = \array_reduce($transforms, static function ($carry, $transform) {
            $transform = RetconHelper::getImageTransform($transform);
            if ($transform) {
                $carry[] = $transform;
            }
            return $carry;
        }, []);

        if (empty($transforms)) {
            return $input;
        }

        // Get sizes attribute
        if ($sizes && \is_array($sizes)) {
            $sizes = \implode(', ', $sizes);
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            $src = $node->getAttribute('src');
            if (!$src) {
                continue;
            }

            // Get transformed images
            $transformedImages = \array_reduce($transforms, static function ($carry, $transform) use ($src, $imagerTransformDefaults, $imagerConfigOverrides) {
                if ($transformedImage = RetconHelper::getTransformedImage($src, $transform, $imagerTransformDefaults, $imagerConfigOverrides)) {
                    $carry[] = $transformedImage;
                }
                return $carry;
            }, []);

            if (empty($transformedImages)) {
                continue;
            }

            // Add srcset attribute
            $node->setAttribute('srcset', RetconHelper::getSrcsetAttribute($transformedImages));

            // Add sizes attribute
            if ($sizes) {
                $node->setAttribute('sizes', $sizes);
            }

            $node->setAttribute('src', RetconHelper::parseRef($src));

            // Set dimensions?
            $nodeWidth = (int)$node->getAttribute('width');
            $nodeHeight = (int)$node->getAttribute('height');

            if (($nodeWidth && $nodeHeight) && !$base64src) {
                continue;
            }

            $dimensions = RetconHelper::getImageDimensions($node) ?? [];
            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;

            // Set width and height attributes
            if (!$nodeWidth && !$nodeHeight && $width && $height) {
                $node->setAttribute('width', $width);
                $node->setAttribute('height', $height);
            }

            if ($base64src) {
                $node->setAttribute('src', RetconHelper::getBase64Pixel($width ?? 1, $height ?? 1));
            }
        }

        return $dom->getHtml();

    }

    /**
     * Prepares all images (or all nodes matching the selector(s) passed) by swapping out the `src` attribute with a base64 encoded, transparent SVG. The original source will be retained in a data attribute
     *
     * @param $input
     * @param string|string[]|null $selector
     * @param string|null $className
     * @param string|null $attributeName
     * @return string|Markup|null
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function lazy($input, $selector = null, ?string $className = null, ?string $attributeName = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        if (empty($selector)) {
            $selector = 'img';
        }

        if (is_null($className)) {
            $className = 'lazyload';
        }

        if (empty($attributeName)) {
            $attributeName = 'src';
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $attributeName = "data-{$attributeName}";

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            $imageClasses = \explode(' ', $node->getAttribute('class'));
            $imageClasses[] = $className;

            $node->setAttribute('class', \trim(\implode(' ', $imageClasses)));
            $node->setAttribute($attributeName, RetconHelper::parseRef($node->getAttribute('src')));

            $dimensions = RetconHelper::getImageDimensions($node) ?? [];

            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;

            $nodeWidth = (int)$node->getAttribute('width');
            $nodeHeight = (int)$node->getAttribute('height');

            if ($width && $height && !$nodeWidth && !$nodeHeight) {
                $node->setAttribute('width', $width);
                $node->setAttribute('height', $height);
            }

            $node->setAttribute('src', RetconHelper::getBase64Pixel($width ?? 1, $height ?? 1));

        }

        return $dom->getHtml();

    }

    /**
     * Attempts to auto-generate alternative text for all images (or all elements matching the $selector attribute).
     *
     * @param $input
     * @param string|string[] $selector
     * @param string|null $field
     * @param bool $overwrite
     * @param bool $allowFilenameAsAltText TODO default to false in Retcon 3.0
     * @return Markup|string
     * @throws SiteNotFoundException
     */
    public function autoAlt($input, $selector = 'img', string $field = null, bool $overwrite = false, bool $allowFilenameAsAltText = true)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $isCraft4 = version_compare(\Craft::$app->getVersion(), '4.0.0', '>=');

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if ($node->getAttribute('alt') && !$overwrite) {
                continue;
            }
            $src = $node->getAttribute('src');
            if (!$src || StringHelper::isBase64($src) || StringHelper::startsWith($src, 'data:')) {
                continue;
            }
            $alt = null;
            if ($asset = RetconHelper::getAssetFromRef($src)) {
                if ($field) {
                    $alt = $asset->$field ?? '';
                }
                if (!$alt && $isCraft4) {
                    $alt = $asset->alt ?? '';
                }
                $alt = $alt ?: $asset->title;
            }
            // TODO: Stop using the filename as alt text in Retcon 3.0!
            if (!$alt && $allowFilenameAsAltText) {
                $alt = \pathinfo($src, PATHINFO_FILENAME);
            }
            $node->setAttribute('alt', $alt ?: '');
        }

        return $dom->getHtml();

    }

    /**
     * Adds (to) or replaces one or many attributes for one or many selectors
     *
     * @param $input
     * @param string|string[] $selector
     * @param array $attributes
     * @param string|bool $overwrite (true, false, "prepend" or "append")
     * @return Markup
     * @throws SiteNotFoundException
     */
    public function attr($input, $selector, array $attributes, $overwrite = true)
    {

        if (empty($attributes) || !$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        if ($overwrite === false) {
            $overwrite = 'append';
        } elseif ($overwrite !== true && !\in_array($overwrite, ['append', 'prepend'])) {
            $overwrite = true;
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            foreach ($attributes as $key => $value) {
                if ($overwrite !== true && $key !== 'id' && !\is_bool($value) && (!\is_array($value) || !ArrayHelper::isAssociative($value)) && $currentValue = $node->getAttribute($key)) {
                    $value = \is_array($value) ? $value : [$value];
                    if ($overwrite === 'append') {
                        $value = \array_merge([$currentValue], $value);
                    } elseif ($overwrite === 'prepend') {
                        $value[] = $currentValue;
                    }
                }
                $normalizedAttributes = RetconHelper::getNormalizedDomNodeAttributeValues($key, $value);
                foreach ($normalizedAttributes as $attributeName => $attributeValue) {
                    if ($attributeValue === false || $attributeValue === null) {
                        $node->removeAttribute($attributeName);
                    } elseif ($attributeValue === true) {
                        $node->setAttribute($attributeName, '');
                    } else {
                        $node->setAttribute($attributeName, $attributeValue ?? '');
                    }
                }
            }
        }

        return $dom->getHtml();

    }

    /**
     * Renames attributes for matching selector(s)
     *
     * @param $input
     * @param string|string[] $selector
     * @param array $attributes
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function renameAttr($input, $selector, array $attributes)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            foreach ($attributes as $existingAttributeName => $desiredAttributeName) {
                if (!$desiredAttributeName || $existingAttributeName === $desiredAttributeName || !$node->hasAttribute($existingAttributeName)) {
                    continue;
                }
                $attributeValue = $node->getAttribute($existingAttributeName);
                $node->removeAttribute($existingAttributeName);
                $node->setAttribute($desiredAttributeName, $attributeValue);
            }
        }

        return $dom->getHtml();

    }

    /**
     * Remove all elements matching given selector(s)
     *
     * @param $input
     * @param string|string[] $selector
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function remove($input, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }

        return $dom->getHtml();

    }

    /**
     * Remove everything except nodes matching given selector(s)
     *
     * @param $input
     * @param string|string[] $selector
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function only($input, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return '';
        }

        $doc = $dom->getDoc();
        $fragment = $doc->createDocumentFragment();

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $fragment->appendChild($node);
        }

        if ($doc->firstChild instanceof \DOMNode && $doc->firstChild->parentNode instanceof \DOMNode) {
            $doc->firstChild->parentNode->replaceChild($fragment, $doc->firstChild);
        }

        return $dom->getHtml();

    }

    /**
     * Changes tag type/name for given selector(s). Can also remove tags (whilst retaining their contents) by passing `false` for the $toTag parameter
     *
     * @param $input
     * @param string|string[] $selector
     * @param string $toTag
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function change($input, $selector, string $toTag)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $doc = $dom->getDoc();

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            // Deep copy the (inner) element
            $fragment = $doc->createDocumentFragment();
            while ($node->childNodes->length > 0) {
                $fragment->appendChild($node->childNodes->item(0));
            }
            // Remove or change the tag?
            if (!$toTag) {
                // Remove it chief
                $node->parentNode->replaceChild($fragment, $node);
            } else {
                // Ch-ch-changes
                $newElement = $node->ownerDocument->createElement($toTag);
                foreach ($node->attributes as $attribute) {
                    $newElement->setAttribute($attribute->nodeName, $attribute->nodeValue);
                }
                if ($fragment->childNodes->length) {
                    $newElement->appendChild($fragment);
                }
                $node->parentNode->replaceChild($newElement, $node);
            }
        }

        return $dom->getHtml();

    }

    /**
     * Wraps all nodes matching the given selector(s) in a container
     *
     * @param $input
     * @param string|string[] $selector
     * @param string $container
     * @return string|Markup|null
     * @throws \DOMException
     * @throws SiteNotFoundException
     */
    public function wrap($input, $selector, string $container)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $doc = $dom->getDoc();

        // Get wrapper
        $container = RetconHelper::getSelectorObject($container);
        $container->tag = $container->tag === '*' ? 'div' : $container->tag;
        $containerNode = $doc->createElement($container->tag);

        if ($container->attribute) {
            $containerNode->setAttribute($container->attribute, $container->attributeValue);
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $containerClone = $containerNode->cloneNode(true);
            $node->parentNode->replaceChild($containerClone, $node);
            $containerClone->appendChild($node);
        }

        return $dom->getHtml();

    }

    /**
     * Removes the parent of all nodes matching given selector(s), retaining all child nodes
     *
     * @param $input
     * @param string|string[] $selector
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function unwrap($input, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $doc = $dom->getDoc();

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $parentNode = $node->parentNode;

            if (!$parentNode || $parentNode->nodeName === 'retcon') {
                continue;
            }

            $fragment = $doc->createDocumentFragment();

            while ($parentNode->childNodes->length > 0) {
                $fragment->appendChild($parentNode->childNodes->item(0));
            }

            $parentNode->parentNode->replaceChild($fragment, $parentNode);
        }

        return $dom->getHtml();

    }

    /**
     * Injects string value (could be HTML!) into all nodes matching given selector(s)
     *
     * @param $input
     * @param string|string[] $selector
     * @param string $toInject
     * @param bool $overwrite
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function inject($input, $selector, string $toInject, bool $overwrite = false)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        $doc = $dom->getDoc();

        // What are we trying to inject, exactly?
        if (\preg_match("/<[^<]+>/", $toInject, $matches) != 0) {
            // Injected content is HTML
            $fragment = $doc->createDocumentFragment();
            $fragment->appendXML($toInject);
            $injectNode = $fragment->childNodes->item(0);
        } else {
            $textNode = $doc->createTextNode($toInject);
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            if (!$overwrite) {
                if (isset($injectNode)) {
                    $node->appendChild($doc->importNode($injectNode->cloneNode(true), true));
                } elseif (isset($textNode)) {
                    $node->appendChild($textNode->cloneNode());
                }
            } elseif (isset($injectNode)) {
                $node->nodeValue = "";
                $node->appendChild($doc->importNode($injectNode->cloneNode(true), true));
            } elseif ($toInject) {
                $node->nodeValue = $toInject;
            }

        }

        return $dom->getHtml();

    }

    /**
     * Removes empty nodes matching given selector(s), or all empty nodes if no selector
     *
     * @param $input
     * @param string|string[]|null $selector
     * @param bool $removeBr
     * @return string|Markup|null
     * @throws SiteNotFoundException
     */
    public function removeEmpty($input, $selector = null, bool $removeBr = false)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        $dom = new RetconDom($html);
        $nodes = null;

        if ($selector) {
            $nodes = $dom->filter($selector, false);
            if (empty($nodes)) {
                return $input;
            }
        }

        /** @var Crawler $crawler */
        $crawler = $nodes ?? $dom->getCrawler();

        // Exclude self-closing tags (and some other tags that typically doesn't have *content*) from being removed
        $excludedTags = [
            'area',
            'base',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'keygen',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
            'svg',
            'iframe',
            'object',
        ];

        // Retain linebreaks too?
        if (!$removeBr) {
            $excludedTags[] = 'br';
        }

        $excludedTagsQuery = '//' . implode('|//', $excludedTags);

        $crawler->filterXPath('//*[not(normalize-space())]')->each(function (Crawler $crawler) use ($excludedTagsQuery) {
            if (
                ($node = $crawler->getNode(0)) === null ||
                !$node->parentNode instanceof \DOMNode ||
                $crawler->filterXPath($excludedTagsQuery)->getNode(0) ||
                @$crawler->closest('svg')
            ) {
                return;
            }
            $node->parentNode->removeChild($node);
        });

        return $dom->getHtml();
    }

    /**
     * Sets width and height attributes for image nodes, if they are missing
     *
     * @param $input
     * @param string|string[]|null $selector
     * @return string|Markup|null
     * @throws Exception
     */
    public function dimensions($input, $selector = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($input)) {
            return $input;
        }

        if (empty($selector)) {
            $selector = 'img';
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return $input;
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {

            $nodeWidth = (int)$node->getAttribute('width');
            $nodeHeight = (int)$node->getAttribute('height');

            if ($nodeWidth && $nodeHeight) {
                continue;
            }

            $dimensions = RetconHelper::getImageDimensions($node) ?? [];

            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;

            if (!$width || !$height) {
                continue;
            }

            $node->setAttribute('width', $width);
            $node->setAttribute('height', $height);

        }

        return $dom->getHtml();

    }

}
