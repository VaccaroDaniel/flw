<?php
// Cache definitions for local_mldict.

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'dictionary_payload' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 0,
    ],
];
