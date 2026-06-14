<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'flwacademy';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];

$THEME->scss = function($theme) {
    return theme_flwacademy_get_main_scss_content($theme);
};

$THEME->prescsscallback = 'theme_flwacademy_get_pre_scss';
$THEME->extrascsscallback = 'theme_flwacademy_get_extra_scss';

$THEME->layouts = [
    'base' => ['file' => 'drawers.php', 'regions' => []],
    'standard' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'course' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre', 'options' => ['langmenu' => true]],
    'coursecategory' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'incourse' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'frontpage' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre', 'options' => ['nonavbar' => true]],
    'admin' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'mydashboard' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre', 'options' => ['nonavbar' => true]],
    'mypublic' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'login' => ['file' => 'login.php', 'regions' => []],
    'popup' => ['file' => 'columns1.php', 'regions' => []],
    'frametop' => ['file' => 'columns1.php', 'regions' => []],
    'embedded' => ['file' => 'embedded.php', 'regions' => []],
    'maintenance' => ['file' => 'maintenance.php', 'regions' => []],
    'print' => ['file' => 'columns1.php', 'regions' => []],
    'redirect' => ['file' => 'embedded.php', 'regions' => []],
    'report' => ['file' => 'drawers.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
    'secure' => ['file' => 'secure.php', 'regions' => ['side-pre'], 'defaultregion' => 'side-pre'],
];

$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_DEFAULT;
$THEME->haseditswitch = true;
