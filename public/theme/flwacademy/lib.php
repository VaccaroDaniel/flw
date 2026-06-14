<?php
defined('MOODLE_INTERNAL') || die();

function theme_flwacademy_get_main_scss_content($theme): string {
    global $CFG;

    $scss = '';

    $boostdefault = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    if (is_readable($boostdefault)) {
        $scss .= file_get_contents($boostdefault);
    }

    $post = $CFG->dirroot . '/theme/flwacademy/scss/post.scss';
    if (is_readable($post)) {
        $scss .= "\n\n" . file_get_contents($post);
    }

    return $scss;
}

function theme_flwacademy_get_pre_scss($theme): string {
    $emerald = get_config('theme_flwacademy', 'emerald') ?: '#0F9D7A';
    $orange = get_config('theme_flwacademy', 'orange') ?: '#FF8A00';
    $purple = get_config('theme_flwacademy', 'purple') ?: '#7B4DFF';
    $pink = get_config('theme_flwacademy', 'pink') ?: '#E05280';
    $cream = get_config('theme_flwacademy', 'cream') ?: '#FFFDF8';
    $radius = get_config('theme_flwacademy', 'radius') ?: '1.1rem';

    return "
$primary: {$emerald};
$secondary: {$purple};
$success: {$emerald};
$info: {$purple};
$warning: {$orange};
$danger: {$pink};
$body-bg: {$cream};
$link-color: {$emerald};
$border-radius: {$radius};
$border-radius-lg: calc({$radius} + .25rem);
$btn-border-radius: 999px;
$card-border-radius: calc({$radius} + .2rem);
";
}

function theme_flwacademy_get_extra_scss($theme): string {
    return get_config('theme_flwacademy', 'extrascss') ?: '';
}
