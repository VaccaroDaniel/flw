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
        'data-strings' => json_encode(local_flwmedia_get_hub_strings(), JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * Return localized strings used by the JavaScript Practice hub.
 *
 * @return array
 */
function local_flwmedia_get_hub_strings(): array {
    return [
        'all' => get_string('all', 'local_flwmedia'),
        'audio' => get_string('audio', 'local_flwmedia'),
        'correct' => get_string('correct', 'local_flwmedia'),
        'dictate' => get_string('modedictate', 'local_flwmedia'),
        'flwpractice' => get_string('flwpractice', 'local_flwmedia'),
        'item' => get_string('item', 'local_flwmedia'),
        'items' => get_string('items', 'local_flwmedia'),
        'listen' => get_string('modelisten', 'local_flwmedia'),
        'loaderror' => get_string('loaderror', 'local_flwmedia'),
        'loadingpractice' => get_string('loadingpractice', 'local_flwmedia'),
        'nopracticemedia' => get_string('nopracticemedia', 'local_flwmedia'),
        'practicepage' => get_string('practicepage', 'local_flwmedia'),
        'record' => get_string('record', 'local_flwmedia'),
        'recording' => get_string('recording', 'local_flwmedia'),
        'recordingnotsupported' => get_string('recordingnotsupported', 'local_flwmedia'),
        'recordingsaved' => get_string('recordingsaved', 'local_flwmedia'),
        'read' => get_string('moderead', 'local_flwmedia'),
        'readingcompleted' => get_string('readingcompleted', 'local_flwmedia'),
        'searcharia' => get_string('searcharia', 'local_flwmedia'),
        'searchpractice' => get_string('searchpractice', 'local_flwmedia'),
        'scoreprefix' => get_string('scoreprefix', 'local_flwmedia'),
        'speak' => get_string('modespeak', 'local_flwmedia'),
        'text' => get_string('text', 'local_flwmedia'),
        'tryanotherfilter' => get_string('tryanotherfilter', 'local_flwmedia'),
        'type' => get_string('type', 'local_flwmedia'),
        'video' => get_string('video', 'local_flwmedia'),
        'watch' => get_string('modewatch', 'local_flwmedia'),
        'wordoverlapsuffix' => get_string('wordoverlapsuffix', 'local_flwmedia'),
        'microphonenotgranted' => get_string('microphonenotgranted', 'local_flwmedia'),
    ];
}
