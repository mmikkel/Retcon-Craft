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
     * @var HTML5
     */
    protected $html5;

    /**
     * RetconDom constructor.
     * @param string $html
     */
    public function __construct($html)
    {
        $libxmlUseInternalErrors = \libxml_use_internal_errors(true);
        $this->html5 = new HTML5();
        $this->doc = new \DOMDocument();
        $this->doc->loadHTML(\mb_convert_encoding($html, 'HTML-ENTITIES', Craft::$app->getView()->getTwig()->getCharset()));
        $this->crawler = new Crawler($this->doc);
        \libxml_use_internal_errors($libxmlUseInternalErrors);
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
    public function getHtml(\DOMNode $node = null)
    {
        return TemplateHelper::raw(\preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $this->html5->saveHTML($node ?: $this->doc)));
    }

}
