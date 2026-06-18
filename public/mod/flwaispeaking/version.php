<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_flwaispeaking';
$plugin->version = 2026061501;
$plugin->requires = 2025100600; // Moodle 5.1 or later.
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.2.0-alpha';
$plugin->dependencies = [
    'local_flwaiassessment' => 2026061400,
];
