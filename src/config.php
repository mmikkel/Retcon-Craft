<?php
/**
 * Retcon plugin for Craft CMS 3.x
 *
 * A collection of powerful Twig filters for modifying HTML
 *
 * @link      https://vaersaagod.no
 * @copyright Copyright (c) 2017 Mats Mikkel Rummelhoff
 */

/**
 * Retcon config.php
 *
 * This file exists only as a template for the Retcon settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to '/config' as 'retcon.php'
 * and make your changes there to override default settings.
 *
 * Once copied to '/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    'baseTransformPath' => '@webroot', // Base transform path for images
    'baseTransformUrl' => '@web', // Base transform URL for images
    'useImager' => true, // Use the Imager plugin for transforms (if it's installed)
];
