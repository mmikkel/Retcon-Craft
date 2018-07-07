<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:23
 */

namespace mmikkel\retcon\library;

use Craft;
use craft\helpers\Template as TemplateHelper;

use Masterminds\HTML5;
use Symfony\Component\DomCrawler\Crawler;

class RetconDom
{

    /**
     * @var \DOMDocument
     */
    protected $doc;
    /**
     * @var Crawler
     */
    protected $crawler;

    /**
     * RetconDom constructor.
     * @param string $html
     */
    public function __construct($html)
    {
        $this->doc = new \DOMDocument();
        $libxmlUseInternalErrors = \libxml_use_internal_errors(true);
        $this->doc->loadHTML(\mb_convert_encoding($html, 'HTML-ENTITIES', Craft::$app->getView()->getTwig()->getCharset()));
        \libxml_use_internal_errors($libxmlUseInternalErrors);
        $this->crawler = new Crawler($this->doc);
    }

    /**
     * @param string|array $selector
     * @param bool $asArray
     * @return array|Crawler
     */
    public function filter($selector, bool $asArray = true)
    {
        if (\is_array($selector)) {
            $selector = \implode(',', $selector);
        }
        $nodes = $this->crawler->filter($selector);
        if (!$asArray) {
            return $nodes;
        }
        return $nodes->each(function (Crawler $node) {
            return $node->getNode(0);
        });
    }

    /**
     * @param string $selector
     * @param string $selector
     * @return array|Crawler
     */
    public function filterXPath(string $selector, bool $asArray = true)
    {
        $nodes = $this->crawler->filterXPath($selector);
        if (!$asArray) {
            return $nodes;
        }
        return $nodes->each(function (Crawler $node) {
            return $node->getNode(0);
        });
    }

    /**
     * @return \DOMDocument
     */
    public function getDoc(): \DOMDocument
    {
        return $this->doc;
    }

    /**
     * @return \Twig_Markup
     */
    public function getHtml()
    {
        $html5 = new HTML5();
        return TemplateHelper::raw(\preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $html5->saveHTML($this->doc)));
    }

}
