<?php

/**
 * Proven Expert Ratings bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2022, numero2 - Agentur für digitales Marketing GbR
 */


use Contao\CoreBundle\DataContainer\PaletteManipulator;


$pm = PaletteManipulator::create()
    ->addLegend('proven_expert_ratings_legend', 'global_legend')
    ->addField(['proven_expert_rating_api_id', 'proven_expert_rating_api_key', 'proven_expert_rating_name', 'proven_expert_rating_description', 'proven_expert_rating_image'], 'proven_expert_ratings_legend', PaletteManipulator::POSITION_APPEND);

$pm->applyToPalette('root', 'tl_page');
$pm->applyToPalette('rootfallback', 'tl_page');

$GLOBALS['TL_DCA']['tl_page']['fields']['proven_expert_rating_api_id'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['maxlength'=>64, 'tl_class'=>'w50']
,   'sql'               => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['proven_expert_rating_api_key'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['maxlength'=>64, 'tl_class'=>'w50']
,   'sql'               => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['proven_expert_rating_name'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['maxlength'=>255, 'tl_class'=>'w50']
,   'sql'               => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['proven_expert_rating_description'] = [
    'exclude'           => true
,   'inputType'         => 'text'
,   'eval'              => ['maxlength'=>255, 'tl_class'=>'w50']
,   'sql'               => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['proven_expert_rating_image'] = [
    'exclude'           => true
,   'inputType'         => 'fileTree'
,   'eval'              => ['filesOnly'=>true, 'fieldType'=>'radio', 'extensions'=>'%contao.image.valid_extensions%', 'tl_class'=>'clr']
,   'sql'               => "binary(16) NULL"
];
