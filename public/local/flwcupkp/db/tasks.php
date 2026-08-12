<?php
// Scheduled tasks for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_flwcupkp\task\recalculate_states',
        'blocking' => 0,
        'minute' => '*/15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_flwcupkp\task\sync_competencies',
        'blocking' => 0,
        'minute' => '10',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_flwcupkp\task\calibration_recalculation',
        'blocking' => 0,
        'minute' => '*/10',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
