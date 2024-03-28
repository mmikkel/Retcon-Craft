<?php

namespace mmikkel\retcon;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;

use mmikkel\retcon\services\RetconService;
use mmikkel\retcon\twigextensions\RetconTwigExtension;
use mmikkel\retcon\models\RetconSettings;
use mmikkel\retcon\variables\RetconVariable;

use yii\base\Event;

/**
 * @author    Mats Mikkel Rummelhoff
 * @package   Retcon
 * @since     1.0.0
 *
 * @property  RetconService $retcon
 * @property  RetconSettings $settings
 */
class Retcon extends Plugin
{

    /** @inheritdoc */
    public function init()
    {
        parent::init();

        // Register services
        $this->setComponents([
            'retcon' => RetconService::class,
        ]);

        // Add in our Twig extensions
        Craft::$app->getView()->registerTwigExtension(new RetconTwigExtension());

        // Register our variables (this is deprecated in Retcon 3.0.0 and will be removed in Retcon 4)
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('retcon', RetconVariable::class);
            }
        );
    }

    /**
     * @return RetconSettings
     */
    public function getSettings(): RetconSettings
    {
        return new RetconSettings();
    }

}
