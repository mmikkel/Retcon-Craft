<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:23
 */

namespace mmikkel\retcon\library;

use Craft;
use craft\helpers\Template;
use craft\redactor\FieldData;

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
     * @var bool
     */
    protected $stripDoctype;

    /**
     * RetconDom constructor.
     * @param string $html
     */
    public function __construct($html)
    {
        if ($html instanceof FieldData && $rawContent = $html->getRawContent()) {
            $html = $rawContent;
        }
        $libxmlUseInternalErrors = \libxml_use_internal_errors(true);
        $content = \mb_convert_encoding($html, 'HTML-ENTITIES', Craft::$app->getView()->getTwig()->getCharset());
        $this->doc = new \DOMDocument();
        $this->doc->loadHTML("<html>$content</html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->crawler = new Crawler($this->doc);
        $this->html5 = new HTML5([
            'encode_entities' => false,
        ]);
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
     * @param bool $asArray
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
     * @return Crawler
     */
    public function getCrawler(): Crawler
    {
        return $this->crawler;
    }

    /**
     * @return \Twig\Markup|\Twig\Markup
     * @throws \craft\errors\SiteNotFoundException
     */
    public function getHtml()
    {
        $html = $this->html5->saveHTML($this->doc);
        $html = \preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $html);
        return Template::raw(Craft::$app->getElements()->parseRefs((string)$html));
    }

}
