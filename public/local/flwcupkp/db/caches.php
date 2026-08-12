<?php
// Cache definitions for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'frameworkgraph' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
    'externalwrites' => [
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
