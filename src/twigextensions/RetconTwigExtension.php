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

use mmikkel\retcon\Retcon;
use mmikkel\retcon\services\RetconService;

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
class RetconTwigExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Retcon';
    }

    /**
     * @return array|\Twig\TwigFilter[]|\Twig\TwigFilter[]
     */
    public function getFilters()
    {
        // Generate Twig filters from all public methods in the RetconService class
        $class = new \ReflectionClass(RetconService::class);
        $methods = \array_reduce($class->getMethods(\ReflectionMethod::IS_PUBLIC), function ($carry, $method) {
            if ($method->class === RetconService::class) {
                $carry[] = $method->name;
            }
            return $carry;
        }, []);
        return \array_map(function ($method) {
            $filterName = 'retcon' . ($method != 'retcon' ? \ucfirst($method) : '');
            return new \Twig\TwigFilter($filterName, [Retcon::$plugin->retcon, $method], ['is_safe' => ['html']]);
        }, $methods);
    }
}
