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

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\helpers\ArrayHelper;
use craft\helpers\Template as TemplateHelper;

use mmikkel\retcon\helpers\RetconHelper;
use mmikkel\retcon\library\RetconDom;

use Symfony\Component\DomCrawler\Crawler;

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
     * @param $html
     * @param mixed ...$args
     * @return mixed|string|null
     * @throws Exception
     */
    public function retcon($html, ...$args)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
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

        return $html;

    }

    /**
     * Applies an image transform to all images (or all nodes matching the passed selector(s))
     *
     * @param $html
     * @param $transform
     * @param string|string[]|null $selector
     * @param array|null $imagerTransformDefaults
     * @param array|null $imagerConfigOverrides
     * @return string|\Twig\Markup|null
     * @throws Exception
     * @throws \craft\errors\ImageException
     * @throws \craft\errors\ImageTransformException
     * @throws \craft\errors\SiteNotFoundException
     */
    public function transform($html, $transform, $selector = null, ?array $imagerTransformDefaults = null, ?array $imagerConfigOverrides = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        if (empty($selector)) {
            $selector = 'img';
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
        }

        $transform = RetconHelper::getImageTransform($transform);

        if (!$transform) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param $transforms
     * @param string|string[]|null $selector
     * @param string|string[]|null $sizes
     * @param bool|null $base64src
     * @param array|null $imagerTransformDefaults
     * @param array|null $imagerConfigOverrides
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws Exception
     * @throws \craft\errors\SiteNotFoundException
     */
    public function srcset($html, $transforms, $selector = null, $sizes = null, ?bool $base64src = null, ?array $imagerTransformDefaults = null, ?array $imagerConfigOverrides = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
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
            return TemplateHelper::raw($html);
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
            return $html;
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
                $node->setAttribute('src', RetconHelper::getBase64Pixel($width ?? 1, $height ?? 11));
            }
        }

        return $dom->getHtml();

    }

    /**
     * Prepares all images (or all nodes matching the selector(s) passed) by swapping out the `src` attribute with a base64 encoded, transparent SVG. The original source will be retained in a data attribute
     *
     * @param $html
     * @param string|string[]|null $selector
     * @param string|null $className
     * @param string|null $attributeName
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws Exception
     * @throws \craft\errors\SiteNotFoundException
     */
    public function lazy($html, $selector = null, ?string $className = null, ?string $attributeName = null)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
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
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[] $selector
     * @param string $field
     * @param bool $overwrite
     * @return \Twig\Markup|\Twig\Markup
     * @throws \craft\errors\SiteNotFoundException
     */
    public function autoAlt($html, $selector = 'img', string $field = 'title', bool $overwrite = false)
    {

        if (!RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
        }

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if ($node->getAttribute('alt') && !$overwrite) {
                continue;
            }
            $src = $node->getAttribute('src');
            if (!$src) {
                continue;
            }
            $elementId = RetconHelper::getElementIdFromRef($src);
            /** @var Element $element */
            $element = $elementId ? Craft::$app->getElements()->getElementById($elementId) : null;
            $alt = null;
            if ($element) {
                $alt = $element->$field ?: $element->title ?: null;
            }
            if (!$alt) {
                $imageSourcePathinfo = \pathinfo($src);
                $alt = $imageSourcePathinfo['filename'] ?? '';
            }
            $node->setAttribute('alt', $alt);
        }

        return $dom->getHtml();

    }

    /**
     * Adds (to) or replaces one or many attributes for one or many selectors
     *
     * @param $html
     * @param string|string[] $selector
     * @param array $attributes
     * @param string|bool $overwrite (true, false, "prepend" or "append")
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function attr($html, $selector, array $attributes, $overwrite = true)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        if (empty($attributes)) {
            return TemplateHelper::raw($html);
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[] $selector
     * @param array $attributes
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function renameAttr($html, $selector, array $attributes)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[] $selector
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function remove($html, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[] $selector
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function only($html, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw('');
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
     * @param $html
     * @param string|string[] $selector
     * @param string $toTag
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function change($html, $selector, string $toTag)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
                $newElement->appendChild($fragment);
                $node->parentNode->replaceChild($newElement, $node);
            }
        }

        return $dom->getHtml();

    }

    /**
     * Wraps all nodes matching the given selector(s) in a container
     *
     * @param $html
     * @param string|string[] $selector
     * @param string $container
     * @return string|\Twig\Markup|null
     * @throws \DOMException
     * @throws \craft\errors\SiteNotFoundException
     */
    public function wrap($html, $selector, string $container)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[] $selector
     * @return string|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function unwrap($html, $selector)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
        }

        $doc = $dom->getDoc();

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $parentNode = $node->parentNode;
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
     * @param $html
     * @param string|string[] $selector
     * @param string $toInject
     * @param bool $overwrite
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function inject($html, $selector, string $toInject, bool $overwrite = false)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = $dom->filter($selector);

        if (empty($nodes)) {
            return TemplateHelper::raw($html);
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
     * @param $html
     * @param string|string[]|null $selector
     * @param bool $removeBr
     * @return string|\Twig\Markup|\Twig\Markup|null
     * @throws \craft\errors\SiteNotFoundException
     */
    public function removeEmpty($html, $selector = null, bool $removeBr = false)
    {

        if (!$html = RetconHelper::getHtmlFromParam($html)) {
            return $html;
        }

        $dom = new RetconDom($html);
        $nodes = null;

        if ($selector) {
            $nodes = $dom->filter($selector, false);
            if (empty($nodes)) {
                return TemplateHelper::raw($html);
            }
        }

        /** @var Crawler $crawler */
        $crawler = $nodes ?? $dom->getCrawler();

        $xpathQuery = $removeBr ? '//*[not(normalize-space())]' : '//*[not(self::br)][not(normalize-space())]';

        $crawler->filterXPath($xpathQuery)->each(function (Crawler $crawler) {
            if (($node = $crawler->getNode(0)) === null || !$node->parentNode instanceof \DOMNode) {
                return;
            }
            $node->parentNode->removeChild($node);
        });

        return $dom->getHtml();
    }

}
