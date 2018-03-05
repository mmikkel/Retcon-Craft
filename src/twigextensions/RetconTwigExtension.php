<?php
/**
 * Retcon plugin for Craft CMS 3.x
 *
 * A collection of powerful Twig filters for modifying HTML
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2017 Mats Mikkel Rummelhoff
 */

namespace mmikkel\retcon\twigextensions;

/**
 * Twig can be extended in many ways; you can add extra tags, filters, tests, operators,
 * global variables, and functions. You can even extend the parser itself with
 * node visitors.
 *
 * http://twig.sensiolabs.org/doc/advanced.html
 *
 * @author    Mats Mikkel Rummelhoff
 * @package   Retcon
 * @since     1.0.0
 */
class RetconTwigExtension extends \Twig_Extension
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Retcon';
    }

    /**
     * @return array|\Twig_Filter[]|\Twig_SimpleFilter[]
     */
    public function getFilters()
    {
        return array_map(function ($method) {
            return new \Twig_SimpleFilter('retcon' . ($method != 'retcon' ? ucfirst($method) : ''), array('mmikkel\retcon\library\RetconApi', $method));
        }, get_class_methods('mmikkel\retcon\library\RetconApi'));
    }
}
