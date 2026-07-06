<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$language = optional_param('language', 'en', PARAM_ALPHANUMEXT);
require_login();

$course = get_site();
$context = context_system::instance();
local_flwplacement_require_take_access($context);

$languageoptions = [
    ['code' => 'en', 'value' => 'english', 'label' => 'English'],
    ['code' => 'ru', 'value' => 'russian', 'label' => 'Russian'],
    ['code' => 'zh', 'value' => 'chinese', 'label' => 'Chinese'],
    ['code' => 'ja', 'value' => 'japanese', 'label' => 'Japanese'],
    ['code' => 'de', 'value' => 'german', 'label' => 'German'],
    ['code' => 'fr', 'value' => 'french', 'label' => 'French'],
    ['code' => 'es', 'value' => 'spanish', 'label' => 'Spanish'],
];
$defaultlanguage = 'english';
foreach ($languageoptions as $option) {
    if ($option['code'] === $language || $option['value'] === $language) {
        $defaultlanguage = $option['value'];
        break;
    }
}

$url = new moodle_url('/local/flwplacement/index.php', [
    'language' => $language,
]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('placementtest', 'local_flwplacement'));
$PAGE->set_heading(get_string('placementtest', 'local_flwplacement'));
$PAGE->requires->css(new moodle_url('/local/flwplacement/styles.css'));
$PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/questionBank.js'));
$PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/engine.js'));
$PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/report.js'));
$PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/moodlePlacement.js'));

$config = [
    'userid' => $USER->id,
    'learnerName' => fullname($USER),
    'courseLanguages' => $languageoptions,
    'defaultCourseLanguage' => $defaultlanguage,
    'sesskey' => sesskey(),
    'saveUrl' => (new moodle_url('/local/flwplacement/save.php'))->out(false),
    'reportsUrl' => (new moodle_url('/local/flwplacement/reports.php'))->out(false),
    'exportUrl' => (new moodle_url('/local/flwplacement/export.php'))->out(false),
    'canViewReports' => has_capability('local/flwplacement:viewreports', $context),
];

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$DB->get_manager()->table_exists('local_flwplacement')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwplacement'), 'warning');
    echo $output->footer();
    exit;
}

echo html_writer::tag('script', json_encode($config), [
    'type' => 'application/json',
    'id' => 'flw-placement-config',
]);

echo html_writer::start_div('flw-placement-app', ['id' => 'flw-placement-app']);
echo html_writer::start_div('flw-placement-brand');
echo html_writer::div('Foreign Language World', 'flw-placement-eyebrow');
echo html_writer::tag('h2', get_string('placementtest', 'local_flwplacement'));
echo html_writer::start_div('flw-placement-status');
echo html_writer::span('Ready', 'flw-placement-phase', ['id' => 'flw-placement-phase']);
echo html_writer::tag('strong', '0%', ['id' => 'flw-placement-progress-text']);
echo html_writer::end_div();
echo html_writer::div(html_writer::div('', 'flw-placement-progress-bar', ['id' => 'flw-placement-progress-bar']), 'flw-placement-progress-track');
echo html_writer::end_div();
echo html_writer::div('', 'flw-placement-workspace', ['id' => 'flw-placement-workspace']);
echo html_writer::end_div();

echo $output->footer();
