<?php
// Hook callbacks for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [
            \local_flwcupkp\local\output_hooks::class,
            'before_standard_head_html_generation',
        ],
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [
            \local_flwcupkp\local\output_hooks::class,
            'before_footer_html_generation',
        ],
    ],
];
