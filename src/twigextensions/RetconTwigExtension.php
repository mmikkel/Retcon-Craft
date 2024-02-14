<?php

namespace mmikkel\retcon\twigextensions;

use mmikkel\retcon\Retcon;
use mmikkel\retcon\services\RetconService;

/**
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
            return new \Twig\TwigFilter($filterName, [Retcon::getInstance()->retcon, $method], ['is_safe' => ['html']]);
        }, $methods);
    }
}
