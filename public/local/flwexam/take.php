<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
require_capability('local/flwexam:viewown', $context);

$selectedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$selectedtrack = optional_param('track', '', PARAM_ALPHANUMEXT);
$selectedlevel = optional_param('cefr', '', PARAM_ALPHANUMEXT);
if ($selectedlanguage === '') {
    $selectedlanguage = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
    $selectedlanguage = $selectedlanguage === 'zh_cn' ? 'zh' : $selectedlanguage;
}

$urlparams = [];
if ($selectedlanguage !== '') {
    $urlparams['language'] = $selectedlanguage;
}
if ($selectedtrack !== '') {
    $urlparams['track'] = $selectedtrack;
}
if ($selectedlevel !== '') {
    $urlparams['cefr'] = $selectedlevel;
}

$url = new moodle_url('/local/flwexam/take.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('takeexam', 'local_flwexam'));
$PAGE->set_heading(get_string('takeexam', 'local_flwexam'));

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('flwexam-page');
$actions = [
    html_writer::link(
        new moodle_url('/local/flwexam/index.php'),
        get_string('viewhistory', 'local_flwexam'),
        ['class' => 'btn btn-secondary flwexam-main-action']
    ),
];
if (has_capability('local/flwexam:manageexams', $context)) {
    $actions[] = html_writer::link(
        new moodle_url('/local/flwexam/manage.php'),
        get_string('manageexams', 'local_flwexam'),
        ['class' => 'btn btn-secondary flwexam-main-action']
    );
}
echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('takeexam', 'local_flwexam'),
    get_string('takeexamintro', 'local_flwexam'),
    $actions,
    [
        get_string('language', 'local_flwexam') => $selectedlanguage !== '' ? $selectedlanguage : get_string('selectlanguage', 'local_flwexam'),
        get_string('cefrlevel', 'local_flwexam') => $selectedlevel !== '' ? $selectedlevel : get_string('selectcefrlevel', 'local_flwexam'),
    ]
);

if (!$DB->get_manager()->table_exists('local_flwexam_questions')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwexam'), 'warning');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$filteroptions = exam_service::get_exam_filter_options();
if (!$filteroptions['languages'] || !$filteroptions['levels']) {
    echo html_writer::div(get_string('noavailableexams', 'local_flwexam'), 'alert alert-info');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

if ($selectedlanguage !== '' && !isset($filteroptions['languages'][$selectedlanguage])) {
    $selectedlanguage = '';
}
$trackoptions = $selectedlanguage !== '' ? exam_service::get_track_options_for_language($selectedlanguage) : [];
if ($selectedtrack !== '' && !isset($trackoptions[$selectedtrack])) {
    $selectedtrack = '';
}
if ($selectedlevel !== '' && !isset($filteroptions['levels'][$selectedlevel])) {
    $selectedlevel = '';
}

$alltrackoptions = [];
foreach (array_keys($filteroptions['languages']) as $languagecode) {
    $alltrackoptions[$languagecode] = exam_service::get_track_options_for_language($languagecode);
}

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/flwexam/take.php'))->out(false),
    'class' => 'flwexam-filter-form',
]);
echo html_writer::start_div('flwexam-filter-head');
echo html_writer::span('', 'flwexam-filter-icon', ['aria-hidden' => 'true']);
echo html_writer::start_div('flwexam-filter-copy');
echo html_writer::tag('h3', get_string('findexams', 'local_flwexam'));
echo html_writer::tag('p', get_string('chooseexamfiltersfirst', 'local_flwexam'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('flwexam-filter-grid');
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('chooselanguage', 'local_flwexam'), 'flwexam-language');
echo html_writer::select(
    $filteroptions['languages'],
    'language',
    $selectedlanguage,
    ['' => get_string('selectlanguage', 'local_flwexam')],
    ['id' => 'flwexam-language', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('choosetrack', 'local_flwexam'), 'flwexam-track');
echo html_writer::select(
    $trackoptions,
    'track',
    $selectedtrack,
    ['' => get_string('selecttrack', 'local_flwexam')],
    ['id' => 'flwexam-track', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('choosecefrlevel', 'local_flwexam'), 'flwexam-cefr');
echo html_writer::select(
    $filteroptions['levels'],
    'cefr',
    $selectedlevel,
    ['' => get_string('selectcefrlevel', 'local_flwexam')],
    ['id' => 'flwexam-cefr', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('findexams', 'local_flwexam'),
]);
echo html_writer::end_tag('form');
echo html_writer::script(
    '(function() {' .
    'var tracksByLanguage = ' . json_encode($alltrackoptions) . ';' .
    'var languageSelect = document.getElementById("flwexam-language");' .
    'var trackSelect = document.getElementById("flwexam-track");' .
    'var placeholder = ' . json_encode(get_string('selecttrack', 'local_flwexam')) . ';' .
    'if (!languageSelect || !trackSelect) { return; }' .
    'function refreshTracks() {' .
    'var selectedTrack = trackSelect.value;' .
    'var tracks = tracksByLanguage[languageSelect.value] || {};' .
    'trackSelect.innerHTML = "";' .
    'var first = document.createElement("option");' .
    'first.value = "";' .
    'first.textContent = placeholder;' .
    'trackSelect.appendChild(first);' .
    'Object.keys(tracks).forEach(function(value) {' .
    'var option = document.createElement("option");' .
    'option.value = value;' .
    'option.textContent = tracks[value];' .
    'if (value === selectedTrack) { option.selected = true; }' .
    'trackSelect.appendChild(option);' .
    '});' .
    'if (!tracks[selectedTrack]) { trackSelect.value = ""; }' .
    '}' .
    'languageSelect.addEventListener("change", refreshTracks);' .
    '})();'
);

$hasfullselection = $selectedlanguage !== '' && $selectedtrack !== '' && $selectedlevel !== '';
if (!$hasfullselection) {
    echo html_writer::div(get_string('chooseexamfiltersfirst', 'local_flwexam'), 'alert alert-info');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$exams = exam_service::get_available_exams([
    'language' => $selectedlanguage,
    'learning_course_category' => $selectedtrack,
    'cefr_level' => $selectedlevel,
]);
if (!$exams) {
    echo html_writer::div(get_string('nomatchingexams', 'local_flwexam'), 'alert alert-info');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

echo html_writer::tag('h3', get_string('matchingexams', 'local_flwexam'));
echo html_writer::start_div('flwexam-exam-grid');
foreach ($exams as $exam) {
    echo html_writer::start_div('flwexam-exam-card');
    echo html_writer::div(s($exam['code']), 'flwexam-card-label');
    echo html_writer::tag('h3', s($exam['name']));
    echo html_writer::start_div('flwexam-summary-grid');
    $details = [
        get_string('language', 'local_flwexam') => exam_service::language_label($exam['language']),
        get_string('track', 'local_flwexam') => exam_service::track_label($exam['learning_course_category']),
        get_string('cefrlevel', 'local_flwexam') => $exam['cefr_level'],
        get_string('questions', 'local_flwexam') => $exam['question_count'],
        get_string('requiredthreshold', 'local_flwexam') => local_flwexam_format_score($exam['required_threshold']),
        get_string('requiredskillfloor', 'local_flwexam') => local_flwexam_format_score($exam['required_skill_floor']),
    ];
    foreach ($details as $label => $value) {
        echo html_writer::div(
            html_writer::span(s($label), 'flwexam-card-label') .
            html_writer::tag('strong', s($value)),
            'flwexam-mini-card'
        );
    }
    echo html_writer::end_div();
    if ($exam['question_count'] > 0) {
        echo html_writer::link(
            new moodle_url('/local/flwexam/attempt.php', ['examid' => $exam['id']]),
            get_string('startexam', 'local_flwexam'),
            ['class' => 'btn btn-primary']
        );
    } else {
        echo html_writer::div(get_string('examhasnoquestions', 'local_flwexam'), 'alert alert-warning');
    }
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();

echo $output->footer();
