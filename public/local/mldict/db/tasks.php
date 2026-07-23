<?php
// Scheduled tasks for local_mldict.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_mldict\\task\\refresh_dictionary_payload',
        'blocking' => 0,
        'minute' => '*/30',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
