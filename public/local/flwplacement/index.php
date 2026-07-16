<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwplacement\service\result_repository;

$requestedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$cookielanguage = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
$language = $requestedlanguage !== '' ? $requestedlanguage : ($cookielanguage !== '' ? $cookielanguage : 'en');
require_login();

$course = get_site();
$context = context_system::instance();
$PAGE->set_context($context);
local_flwplacement_require_take_access($context);

$languageoptions = [
    ['code' => 'en', 'value' => 'english', 'label' => 'English', 'categorynames' => ['English']],
    ['code' => 'ru', 'value' => 'russian', 'label' => 'Russian', 'categorynames' => ['Russian']],
    ['code' => 'zh', 'value' => 'chinese', 'label' => 'Chinese', 'categorynames' => ['Chinese', '汉语']],
    ['code' => 'de', 'value' => 'german', 'label' => 'German', 'categorynames' => ['German']],
    ['code' => 'ja', 'value' => 'japanese', 'label' => 'Japanese', 'categorynames' => ['Japanese']],
    ['code' => 'fr', 'value' => 'french', 'label' => 'French', 'categorynames' => ['French']],
    ['code' => 'es', 'value' => 'spanish', 'label' => 'Spanish', 'categorynames' => ['Spanish']],
];
$language = core_text::strtolower($language);
$language = $language === 'zh_cn' ? 'zh' : $language;
$defaultlanguage = 'english';
$defaultlanguagelabel = 'English';
$defaultlanguagecode = 'en';
$languagecategory = null;
foreach ($languageoptions as $option) {
    if ($option['code'] === $language || $option['value'] === $language) {
        $defaultlanguage = $option['value'];
        $defaultlanguagelabel = $option['label'];
        $defaultlanguagecode = $option['code'];
        foreach ($option['categorynames'] as $categoryname) {
            $languagecategory = $DB->get_record('course_categories', [
                'parent' => 0,
                'name' => $categoryname,
            ], 'id, name', IGNORE_MULTIPLE);
            if ($languagecategory) {
                break;
            }
        }
        break;
    }
}
$language = $defaultlanguagecode;
if (!$languagecategory) {
    $languagecategory = $DB->get_record('course_categories', [
        'parent' => 0,
        'name' => $defaultlanguagelabel,
    ], 'id, name', IGNORE_MULTIPLE);
}

$targetworldoptions = [];
if ($languagecategory) {
    $subcategories = $DB->get_records('course_categories', [
        'parent' => $languagecategory->id,
        'visible' => 1,
    ], 'sortorder, name', 'id, name');
    foreach ($subcategories as $subcategory) {
        $targetworldoptions[] = [
            'value' => clean_param(core_text::strtolower($subcategory->name), PARAM_ALPHANUMEXT),
            'label' => format_string($subcategory->name),
            'categoryid' => (int)$subcategory->id,
        ];
    }
}
if (!$targetworldoptions) {
    $targetworldoptions[] = [
        'value' => 'selfstudy',
        'label' => 'Self Study',
        'categoryid' => 0,
    ];
}

$latestprofile = null;
if ($DB->get_manager()->table_exists('local_flwplacement_profile')) {
    $profile = result_repository::get_latest_profile($USER->id);
    if ($profile) {
        $latestprofile = [
            'course' => $profile->coursekey,
            'overall_cefr' => $profile->overallcefr,
            'recommended_start_unit' => (int)$profile->recommendedstartunit,
            'next_checkpoint_unit' => (int)$profile->nextcheckpointunit,
            'placement_confidence' => (float)$profile->placementconfidence,
            'placement_status' => $profile->placementstatus,
            'skill_levels' => json_decode($profile->skilllevelsjson ?? '[]', true) ?: [],
            'support_flags' => json_decode($profile->supportflagsjson ?? '[]', true) ?: [],
            'learning_path' => json_decode($profile->learningpathjson ?? '[]', true) ?: [],
            'latest_result_id' => (int)$profile->latestresultid,
            'timemodified' => (int)$profile->timemodified,
        ];
    }
}

$url = new moodle_url('/local/flwplacement/index.php', [
    'language' => $defaultlanguagecode,
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
    'selectedLearningLanguageCode' => $defaultlanguagecode,
    'defaultCourseLanguage' => $defaultlanguage,
    'learningLanguageLabel' => $defaultlanguagelabel,
    'learningLanguageCategoryId' => $languagecategory ? (int)$languagecategory->id : 0,
    'targetWorldOptions' => $targetworldoptions,
    'sesskey' => sesskey(),
    'saveUrl' => (new moodle_url('/local/flwplacement/save.php'))->out(false),
    'reportsUrl' => (new moodle_url('/local/flwplacement/reports.php'))->out(false),
    'exportUrl' => (new moodle_url('/local/flwplacement/export.php'))->out(false),
    'canViewReports' => has_capability('local/flwplacement:viewreports', $context),
    'latestPlacementProfile' => $latestprofile,
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
echo html_writer::start_div('flw-placement-brand-copy');
echo html_writer::div('Foreign Language World', 'flw-placement-eyebrow');
echo html_writer::tag('h2', get_string('placementtest', 'local_flwplacement'));
echo html_writer::end_div();
echo html_writer::start_div('flw-placement-status');
echo html_writer::span('Ready', 'flw-placement-phase', ['id' => 'flw-placement-phase']);
echo html_writer::tag('strong', '0%', ['id' => 'flw-placement-progress-text']);
echo html_writer::end_div();
echo html_writer::div(html_writer::div('', 'flw-placement-progress-bar', ['id' => 'flw-placement-progress-bar']), 'flw-placement-progress-track');
echo html_writer::end_div();
echo html_writer::div('', 'flw-placement-workspace', ['id' => 'flw-placement-workspace']);
echo html_writer::end_div();

echo $output->footer();
