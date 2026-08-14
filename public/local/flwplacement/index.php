<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwplacement\service\result_repository;

$requestedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$cookielanguage = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
$language = $requestedlanguage !== '' ? $requestedlanguage : ($cookielanguage !== '' ? $cookielanguage : 'en');
$course = get_site();
$context = context_system::instance();
$url = new moodle_url('/local/flwplacement/index.php', [
    'language' => $language,
]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
require_login();
$PAGE->set_title(get_string('placementtest', 'local_flwplacement'));
$PAGE->set_heading(get_string('placementtest', 'local_flwplacement'));
$PAGE->requires->css(new moodle_url('/local/flwplacement/styles.css'));
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
$placementquizid = local_flwplacement_get_quiz_id_for_language($defaultlanguagecode);
$placementquizinfo = $placementquizid > 0 ? local_flwplacement_get_quiz_info($placementquizid) : null;
$usequizplacement = $placementquizid > 0;
$quizsyncerror = null;
$placementtablesinstalled = $DB->get_manager()->table_exists('local_flwplacement');
$forceautostart = optional_param('autostart', 0, PARAM_BOOL)
    || optional_param('flwautostart', 0, PARAM_BOOL)
    || optional_param('flwplacement', 0, PARAM_BOOL);
if ($usequizplacement && $placementquizinfo && isloggedin() && !isguestuser()) {
    local_flwplacement_cleanup_stale_quiz_attempts($placementquizid, (int)$USER->id);
    if ($placementtablesinstalled) {
        try {
            local_flwplacement_save_quiz_result($placementquizid, (int)$USER->id, $defaultlanguagecode, $defaultlanguagelabel);
        } catch (moodle_exception $e) {
            if (($e->errorcode ?? '') !== 'noquizattempttosync') {
                debugging('local_flwplacement auto-sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}

if ($forceautostart && $usequizplacement && $placementquizinfo && !empty($placementquizinfo['cmid']) &&
        !empty($placementquizinfo['issamplecountok'])) {
    redirect(new moodle_url('/mod/quiz/startattempt.php', [
        'cmid' => (int)$placementquizinfo['cmid'],
        'sesskey' => sesskey(),
        'flwplacement' => 1,
        'flwautostart' => 1,
        'flwskippreflight' => 1,
    ]));
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

if ($usequizplacement && data_submitted() && confirm_sesskey() && optional_param('syncquiz', 0, PARAM_BOOL)) {
    try {
        if (!$placementtablesinstalled) {
            throw new moodle_exception('pluginnotinstalled', 'local_flwplacement');
        }
        if (!$placementquizinfo) {
            throw new moodle_exception('linkedquiznotavailable', 'local_flwplacement');
        }
        $resultid = local_flwplacement_save_quiz_result($placementquizid, (int)$USER->id, $defaultlanguagecode, $defaultlanguagelabel);
        redirect(new moodle_url('/local/flwplacement/view.php', ['id' => $resultid]), get_string('quizattemptsynced', 'local_flwplacement'));
    } catch (moodle_exception $e) {
        $quizsyncerror = $e;
    }
}

if (!$usequizplacement) {
    $PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/questionBank.js'));
    $PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/engine.js'));
    $PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/report.js'));
    $PAGE->requires->js(new moodle_url('/local/flwplacement/assets/js/moodlePlacement.js'));
}

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
    'moodleQuizPlacement' => [
        'enabled' => $usequizplacement,
        'quizid' => $placementquizid,
        'name' => $placementquizinfo['name'] ?? '',
        'url' => $placementquizinfo ? $placementquizinfo['url']->out(false) : '',
        'questionCount' => $placementquizinfo['questioncount'] ?? 0,
        'sourceQuestionCount' => $placementquizinfo['sourcequestioncount'] ?? 0,
        'requiredQuestionCount' => $placementquizinfo['requiredquestioncount'] ?? 30,
        'isReady' => !empty($placementquizinfo['issamplecountok']),
    ],
];

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$placementtablesinstalled) {
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
echo html_writer::span($usequizplacement ? get_string('moodlequiz', 'local_flwplacement') : 'Ready', 'flw-placement-phase', ['id' => 'flw-placement-phase']);
echo html_writer::tag('strong', '0%', ['id' => 'flw-placement-progress-text']);
echo html_writer::end_div();
echo html_writer::div(html_writer::div('', 'flw-placement-progress-bar', ['id' => 'flw-placement-progress-bar']), 'flw-placement-progress-track');
echo html_writer::end_div();
if ($usequizplacement) {
    echo html_writer::start_div('flw-placement-workspace flw-placement-quiz-mode', ['id' => 'flw-placement-workspace']);
    if ($quizsyncerror) {
        echo $output->notification($quizsyncerror->getMessage(), 'warning');
    }
    echo html_writer::start_div('flw-placement-card flw-placement-quiz-card');
    echo html_writer::tag('h3', get_string('moodlequizplacement', 'local_flwplacement'));
    echo html_writer::tag('p', get_string('moodlequizplacementintro', 'local_flwplacement'), ['class' => 'flw-placement-muted']);
    if ($placementquizinfo) {
        echo html_writer::start_div('flw-placement-mini-grid');
        $quizdetails = [
            get_string('language', 'moodle') => $defaultlanguagelabel,
            get_string('moodlequiz', 'local_flwplacement') => $placementquizinfo['name'],
            get_string('questioncount', 'local_flwplacement') => (int)$placementquizinfo['questioncount'] .
                ' / ' . (int)$placementquizinfo['requiredquestioncount'],
            'Source-bank questions' => (int)$placementquizinfo['sourcequestioncount'],
        ];
        foreach ($quizdetails as $label => $value) {
            echo html_writer::div(
                html_writer::span(s($label)) .
                html_writer::tag('strong', s($value)),
                'flw-placement-mini-card'
            );
        }
        echo html_writer::end_div();
        echo html_writer::start_div('flw-placement-button-row');
        if (!empty($placementquizinfo['issamplecountok'])) {
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => (new moodle_url('/mod/quiz/startattempt.php'))->out(false),
                'class' => 'flw-placement-inline-form',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => (int)$placementquizinfo['cmid']]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'flwautostart', 'value' => 1]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'flwskippreflight', 'value' => 1]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'flwplacement', 'value' => 1]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'autostart', 'value' => 1]);
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('openmoodlequiz', 'local_flwplacement'),
            ]);
            echo html_writer::end_tag('form');
        } else {
            echo html_writer::div(
                'This Moodle Quiz is linked, but FLW Placement needs exactly ' .
                    (int)$placementquizinfo['requiredquestioncount'] .
                    ' attempt questions. Add random slots from the correct placement question bank, then open the test again.',
                'alert alert-warning'
            );
            $quizcontext = context_module::instance((int)$placementquizinfo['cmid']);
            if (has_capability('mod/quiz:manage', $quizcontext)) {
                echo html_writer::link(
                    new moodle_url('/mod/quiz/edit.php', ['cmid' => (int)$placementquizinfo['cmid']]),
                    'Configure quiz questions',
                    ['class' => 'btn btn-primary']
                );
            }
        }
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url->out(false),
            'class' => 'flw-placement-inline-form',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'syncquiz', 'value' => 1]);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-secondary',
            'value' => get_string('syncquizplacement', 'local_flwplacement'),
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    } else {
        echo html_writer::div(get_string('linkedquiznotavailable', 'local_flwplacement'), 'alert alert-warning');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
} else {
    echo html_writer::div('', 'flw-placement-workspace', ['id' => 'flw-placement-workspace']);
}
echo html_writer::end_div();

echo $output->footer();
