<?php

namespace mmikkel\retcon\models;

use Craft;
use craft\base\Model;
use craft\helpers\StringHelper;
use mmikkel\retcon\helpers\RetconHelper;

class RetconSettings extends Model
{

    /**
     * @var string|null
     */
    public $baseTransformPath = '@webroot';

    /**
     * @var string|null
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

    /**
     *
     */
    public function init(): void
    {
        parent::init();
        $baseTransformPath = RetconHelper::parseEnv($this->baseTransformPath);
        if ($baseTransformPath && \is_string($baseTransformPath)) {
            $this->baseTransformPath = $baseTransformPath;
        }
        $baseTransformUrl = RetconHelper::parseEnv($this->baseTransformUrl);
        if ($baseTransformUrl && \is_string($baseTransformUrl)) {
            $this->baseTransformUrl = $baseTransformUrl;
        }
    }

}
