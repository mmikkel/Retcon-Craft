<?php

namespace mmikkel\retcon\variables;

use Craft;

use mmikkel\retcon\Retcon;
use mmikkel\retcon\services\RetconService;

use yii\base\UnknownMethodException;

/**
 * @deprecated 3.0.0 Using the craft.retcon variable is discouraged and Twig filters should be used instead
 */
class RetconVariable
{
    public function __call($name, $arguments)
    {
        $class = new \ReflectionClass(RetconService::class);
        $methods = \array_reduce($class->getMethods(\ReflectionMethod::IS_PUBLIC), function ($carry, $method) {
            if ($method->class === RetconService::class) {
                $carry[] = $method->name;
            }
            return $carry;
        }, []);
        if (!in_array($name, $methods, true)) {
            throw new UnknownMethodException('Unknown method: ' . $name);
        }
        $filterName = 'retcon' . ucfirst($name);
        Craft::$app->getDeprecator()->log(__METHOD__ . '_' . $name, "The `craft.retcon.$name` variable is deprecated. Use the `|$filterName` Twig filter instead.");
        return Retcon::getInstance()->retcon->$name(...$arguments);
    }
}
