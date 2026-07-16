<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Render an FLW media hub placeholder and queue the AMD initializer.
 *
 * Teachers can paste the same markup into Moodle content areas. The first
 * argument is now the FLW learning language; a numeric first argument is kept
 * as a legacy course id for older calls.
 *
 * @param mixed $language Language code, or legacy course id.
 * @param string $unitcode FLW unit code such as REW2_U001.
 * @param string $defaultmode Initial mode.
 * @param int $courseid Optional legacy course filter.
 * @return string HTML for the hub container.
 */
function local_flwmedia_render_hub($language = 'en', string $unitcode = '', string $defaultmode = 'watch', int $courseid = 0): string {
    global $PAGE;

    if (is_number($language)) {
        $courseid = (int)$language;
        $language = 'en';
    }

    $language = clean_param((string)$language, PARAM_ALPHANUMEXT);
    $language = $language !== '' ? $language : 'en';
    $unitcode = clean_param($unitcode, PARAM_ALPHANUMEXT);
    $defaultmode = clean_param($defaultmode, PARAM_ALPHA);
    $courseid = clean_param($courseid, PARAM_INT);

    $PAGE->requires->js_call_amd('local_flwmedia/mediahub', 'initAll');

    return html_writer::div('', 'flwmedia-hub', [
        'data-language' => $language,
        'data-courseid' => $courseid,
        'data-unitcode' => $unitcode,
        'data-defaultmode' => $defaultmode,
    ]);
}
