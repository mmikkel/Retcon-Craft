<?php
declare(strict_types=1);

use craft\rector\SetList as CraftSetList;
use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->import(CraftSetList::CRAFT_CMS_40);
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(LevelSetList::UP_TO_PHP_72);
};
