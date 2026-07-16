<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [
            \local_mldict\local\floating_dictionary::class,
            'before_standard_head_html_generation',
        ],
    ],
    [
        'hook' => \core\hook\output\after_standard_main_region_html_generation::class,
        'callback' => [
            \local_mldict\local\floating_dictionary::class,
            'after_standard_main_region_html_generation',
        ],
    ],
];
