<?php
/**
 * Retcon plugin for Craft CMS 3.x
 *
 * A collection of powerful Twig filters for modifying HTML
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2017 Mats Mikkel Rummelhoff
 */

namespace mmikkel\retcon\variables;

use mmikkel\retcon\Retcon;

use Craft;

/**
 * Retcon Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.retcon }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Mats Mikkel Rummelhoff
 * @package   Retcon
 * @since     1.0.0
 */
class RetconVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Whatever you want to output to a Twig template can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.retcon.exampleVariable }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.retcon.exampleVariable(twigValue) }}
     *
     * @param null $optional
     * @return string
     */
    public function exampleVariable($optional = null)
    {
        $result = "And away we go to the Twig template...";
        if ($optional) {
            $result = "I'm feeling optional today...";
        }
        return $result;
    }
}
