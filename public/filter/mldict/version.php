<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'filter_mldict';
$plugin->version   = 2026061000;
$plugin->requires  = 2024100700; // Moodle 4.5 or later.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0 alpha';
$plugin->dependencies = [
    'local_mldict' => 2026061000,
];
