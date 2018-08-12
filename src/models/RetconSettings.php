<?php

namespace mmikkel\retcon\models;

use Craft;
use craft\base\Model;
use craft\helpers\StringHelper;

class RetconSettings extends Model
{

    /**
     * @var string|bool|null
     */
    public $baseTransformPath = '@webroot';

    /**
     * @var string|bool|null
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

        $baseTransformPath = Craft::getAlias($this->baseTransformPath);
        if ($baseTransformPath) {
            $this->baseTransformPath = $baseTransformPath;
        }

        $baseTransformUrl = Craft::getAlias($this->baseTransformUrl);
        if ($baseTransformUrl) {
            $this->baseTransformUrl = $baseTransformUrl;
        }
    }

}
