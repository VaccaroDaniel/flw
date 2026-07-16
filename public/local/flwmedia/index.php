<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$language = optional_param('language', '', PARAM_ALPHANUMEXT);
if ($language === '') {
    $language = optional_param('lang', '', PARAM_ALPHANUMEXT);
}
if ($language === '') {
    $language = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
}
if ($language === '') {
    $language = get_user_preferences('flw_learning_language', 'en');
}

$language = clean_param($language, PARAM_ALPHANUMEXT);
$language = \local_flwmedia\manager::normalize_language($language);
$defaultmode = optional_param('mode', 'watch', PARAM_ALPHA);

$context = local_flwmedia_require_practice_access();

$url = new moodle_url('/local/flwmedia/index.php', [
    'language' => $language,
    'mode' => $defaultmode,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('practicepage', 'local_flwmedia'));
$PAGE->set_heading(get_string('practicepage', 'local_flwmedia'));

echo $OUTPUT->header();
if (has_capability('local/flwmedia:manage', $context)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/flwmedia/manage.php', ['language' => $language]),
            get_string('managepractice', 'local_flwmedia'),
            ['class' => 'btn btn-secondary']
        ),
        'mb-3'
    );
}
echo local_flwmedia_render_hub($language, '', $defaultmode);
echo $OUTPUT->footer();
