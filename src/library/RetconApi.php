<?php
/**
 * Created by PhpStorm.
 * User: mmikkel
 * Date: 06/12/2017
 * Time: 18:13
 */

namespace mmikkel\retcon\library;
use mmikkel\retcon\Retcon;

class RetconApi
{

    /**
     * @return string
     * @throws \Exception
     */
    public static function retcon(): string
    {
        $args = func_get_args();
        $html = array_shift($args);
        return Retcon::$plugin->retcon->retcon($html, $args);
    }

    /**
     * @param $html
     * @param $transform
     * @param array $imagerTransformDefaults
     * @param array $imagerConfigOverrides
     * @return \Twig_Markup
     */
    public static function transform($html, $transform, array $imagerTransformDefaults = [], array $imagerConfigOverrides = [])
    {
        return Retcon::$plugin->retcon->transform($html, $transform, $imagerTransformDefaults, $imagerConfigOverrides);
    }

    /**
     * @param $html
     * @param $transforms
     * @param null $sizes
     * @param bool $base64src
     * @param array $imagerTransformDefaults
     * @param array $imagerConfigOverrides
     * @return \Twig_Markup
     */
    public static function srcset($html, $transforms, $sizes = '100w', $base64src = false, $imagerTransformDefaults = [], $imagerConfigOverrides = [])
    {
        return Retcon::$plugin->retcon->srcset($html, $transforms, $sizes, $base64src, $imagerTransformDefaults, $imagerConfigOverrides);
    }

    /**
     * @param $html
     * @param null $className
     * @param null $attributeName
     * @return \Twig_Markup
     */
    public static function lazy($html, $className = null, $attributeName = null)
    {
        return Retcon::$plugin->retcon->lazy($html, $className, $attributeName);
    }

    /**
     * @param $html
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public static function autoAlt($html, $overwrite = false)
    {
        return Retcon::$plugin->retcon->autoAlt($html, $overwrite);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public static function attr($html, $selectors, $attributes, $overwrite = true)
    {
        return Retcon::$plugin->retcon->attr($html, $selectors, $attributes, $overwrite);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $attributes
     * @return \Twig_Markup
     */
    public static function renameAttr($html, $selectors, $attributes)
    {
        return Retcon::$plugin->retcon->renameAttr($html, $selectors, $attributes);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $wrapper
     * @return \Twig_Markup
     */
    public static function wrap($html, $selectors, $wrapper)
    {
        return Retcon::$plugin->retcon->wrap($html, $selectors, $wrapper);
    }

    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public static function unwrap($html, $selectors)
    {
        return Retcon::$plugin->retcon->unwrap($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public static function remove($html, $selectors)
    {
        return Retcon::$plugin->retcon->remove($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @return \Twig_Markup
     */
    public static function only($html, $selectors)
    {
        return Retcon::$plugin->retcon->only($html, $selectors);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $toTag
     * @return \Twig_Markup
     */
    public static function change($html, $selectors, $toTag)
    {
        return Retcon::$plugin->retcon->change($html, $selectors, $toTag);
    }

    /**
     * @param $html
     * @param $selectors
     * @param $toInject
     * @param bool $overwrite
     * @return \Twig_Markup
     */
    public static function inject($html, $selectors, $toInject, $overwrite = false)
    {
        return Retcon::$plugin->retcon->inject($html, $selectors, $toInject, $overwrite);
    }

    /**
     * @param $html
     * @param array $selectors
     * @return \Twig_Markup
     */
    public static function removeEmpty($html)
    {
        return Retcon::$plugin->retcon->removeEmpty($html);
    }

    /**
     * @param $html
     * @param $pattern
     * @param string $replace
     * @return null|string|string[]
     */
    public static function replace($html, $pattern, $replace = '')
    {
        return Retcon::$plugin->retcon->replace($html, $pattern, $replace);
    }

}
