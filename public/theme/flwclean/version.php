<?php
// This file is part of Moodle - http://moodle.org/

/**
 * FLW Clean Mode v1 version metadata.
 *
 * @package    theme_flwclean
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'theme_flwclean';
$plugin->version = 2026070100;
$plugin->requires = 2025041400;
$plugin->dependencies = [
    'theme_flwacademy' => ANY_VERSION,
];
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'FLW Clean Mode v1';
