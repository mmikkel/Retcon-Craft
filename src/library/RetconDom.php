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

class RetconDom extends \DOMDocument
{

    /**
     * @var
     */
    private $outputEncoding;
    /**
     * @var
     */
    private $xpath;

    /**
     * RetconDom constructor.
     * @param bool $html
     */
    public function __construct($html = false)
    {

        parent::__construct();

        \libxml_use_internal_errors(true);

        $this->outputEncoding = Craft::$app->getView()->getTwig()->getCharset();

        if ($html) {
            $this->loadHtml($html);
        }

        $this->preserveWhiteSpace = false;

    }

    /**
     * @param string $selectorStr
     * @return bool|\DOMNodeList
     */
    public function getElementsBySelector(string $selectorStr)
    {

        $selector = RetconHelper::getSelectorObject($selectorStr);

        // ID or class
        if ($selector->attribute) {

            $xpath = $this->getXPath();

            $query = '//' . $selector->tag . '[contains(concat(" ",@' . $selector->attribute . '," "), " ' . $selector->attributeValue . ' ")]';

            $elements = $xpath->query($query);

        } else {

            $elements = $this->getElementsByTagName($selector->tag);

        }

        return $elements && $elements->length > 0 ? $elements : false;

    }

    /**
     * @param string $html
     * @param null $options
     * @return bool|void
     */
    public function loadHtml($html, $options = null)
    {
        parent::loadHTML(\mb_convert_encoding($html, 'HTML-ENTITIES', $this->outputEncoding));
        $this->normalize();
    }

    /**
     * @return \Twig_Markup
     */
    public function getHtml()
    {
        return Template::raw(\preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', parent::saveHTML()));
    }

    /**
     * @return \DomXPath
     */
    public function getXPath()
    {
        if (!isset($this->xpath)) {
            $this->xpath = new \DomXPath($this);
        }
        return $this->xpath;
    }

}
