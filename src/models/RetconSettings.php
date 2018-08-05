<?php

namespace mmikkel\retcon\models;

use Craft;
use craft\base\Model;
use craft\helpers\StringHelper;

class RetconSettings extends Model
{

    /**
     * @var string
     */
    public $baseTransformPath = '@webroot';

    /**
     * @var string
     */
    public $baseTransformUrl = '@web';

    /**
     * @var bool
     */
    public $useImager = true;

    /**
     * Settings constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $config = \array_merge($config, Craft::$app->getConfig()->getConfigFromFile('retcon'));
        parent::__construct($config);
        if (!empty($config)) {
            \Yii::configure($this, $config);
        }
        $this->init();
    }

    public function init()
    {
        parent::init();
        $this->useImager = $this->useImager && Craft::$app->getPlugins()->getPlugin('imager');
        $this->baseTransformPath = Craft::getAlias($this->baseTransformPath);
        $this->baseTransformUrl = Craft::getAlias($this->baseTransformUrl);
    }

}
