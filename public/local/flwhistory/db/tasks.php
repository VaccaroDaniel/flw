<?php
// Scheduled task registration for local_flwhistory.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_flwhistory\task\refresh_capture_coverage',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
        'disabled' => 0,
    ],
];
