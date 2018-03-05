<?php

namespace mmikkel\retcon\models;

use Yii;
use Craft;
use craft\base\Model;

class RetconSettings extends Model
{

    /**
     * @var string
     */
    public $baseTransformPath = '@webroot';

    /**
     * @var string
     */
    public $baseTransformUrl = '/';

    /**
     * @var bool
     */
    public $useImager = true;

    /**
     *
     */
    public function init()
    {
        $this->baseTransformPath = \rtrim(Yii::getAlias($this->baseTransformPath), '/') . '/';
        $this->baseTransformUrl = \rtrim(Yii::getAlias($this->baseTransformUrl), '/') . '/';
        $this->useImager = $this->useImager && Craft::$app->getPlugins()->getPlugin('imager');
    }

}
