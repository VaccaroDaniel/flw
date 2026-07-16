<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

$id = required_param('id', PARAM_INT);

require_login();

$context = context_system::instance();
$result = exam_service::get_result_package($id, (int)$USER->id, true);

$url = new moodle_url('/local/flwexam/result.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('viewresult', 'local_flwexam'));
$PAGE->set_heading(get_string('viewresult', 'local_flwexam'));

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('flwexam-page');
echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('viewresult', 'local_flwexam'),
    $result['examname'],
    [
        html_writer::link(
            new moodle_url('/local/flwexam/index.php'),
            get_string('backtohistory', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
    ],
    [
        get_string('overallscore', 'local_flwexam') => local_flwexam_format_score($result['overall_score']),
        get_string('passstatus', 'local_flwexam') => exam_service::status_label($result['pass_status']),
    ]
);

echo html_writer::start_div('flwexam-result-summary');
echo html_writer::tag('h3', s($result['examname']));
echo html_writer::start_div('flwexam-summary-grid');
$summary = [
    get_string('learner', 'local_flwexam') => $result['learnername'],
    get_string('language', 'local_flwexam') => $result['language'],
    get_string('track', 'local_flwexam') => $result['learning_course_category'],
    get_string('cefrlevel', 'local_flwexam') => $result['cefr_level'],
    get_string('overallscore', 'local_flwexam') => local_flwexam_format_score($result['overall_score']),
    get_string('passstatus', 'local_flwexam') => exam_service::status_label($result['pass_status']),
    get_string('certificatestatus', 'local_flwexam') => exam_service::status_label($result['certificate_status']),
    get_string('date', 'local_flwexam') => userdate($result['timecreated']),
];
foreach ($summary as $label => $value) {
    echo html_writer::div(
        html_writer::span(s($label), 'flwexam-card-label') .
        html_writer::tag('strong', s($value)),
        'flwexam-mini-card'
    );
}
echo html_writer::end_div();

if (!empty($result['verify_code'])) {
    echo html_writer::link(
        new moodle_url('/local/flwexam/verify.php', ['code' => $result['verify_code']]),
        get_string('verifycertificate', 'local_flwexam'),
        ['class' => 'btn btn-primary']
    );
}
echo html_writer::end_div();

echo html_writer::tag('h4', get_string('skillscores', 'local_flwexam'));
$skilltable = new html_table();
$skilltable->attributes['class'] = 'generaltable flwexam-table';
$skilltable->head = [
    get_string('skill', 'local_flwexam'),
    get_string('score', 'local_flwexam'),
    get_string('passed', 'local_flwexam'),
];
foreach ($result['skills'] as $skill) {
    $skilltable->data[] = [
        s($skill['skill']),
        local_flwexam_format_score($skill['score']),
        $skill['passed'] ? get_string('yes') : get_string('no'),
    ];
}
echo html_writer::table($skilltable);

echo html_writer::tag('h4', get_string('kpgates', 'local_flwexam'));
$kptable = new html_table();
$kptable->attributes['class'] = 'generaltable flwexam-table';
$kptable->head = [
    get_string('kpcode', 'local_flwexam'),
    get_string('score', 'local_flwexam'),
    get_string('passed', 'local_flwexam'),
    get_string('critical', 'local_flwexam'),
];
foreach ($result['kp_results'] as $kp) {
    $kptable->data[] = [
        s($kp['kpcode']),
        local_flwexam_format_score($kp['score']),
        $kp['passed'] ? get_string('yes') : get_string('no'),
        $kp['critical'] ? get_string('yes') : get_string('no'),
    ];
}
echo html_writer::table($kptable);

if (!empty($result['decision']['failures'])) {
    echo html_writer::tag('h4', get_string('certificateblockedby', 'local_flwexam'));
    echo html_writer::alist(array_map('s', $result['decision']['failures']), ['class' => 'flwexam-warning-list']);
}

if (!empty($result['private'])) {
    echo html_writer::tag('h4', get_string('privatecontrols', 'local_flwexam'));
    echo html_writer::start_div('flwexam-summary-grid');
    echo html_writer::div(
        html_writer::span(get_string('integritystatus', 'local_flwexam'), 'flwexam-card-label') .
        html_writer::tag('strong', s(exam_service::status_label($result['private']['integrity_status']))),
        'flwexam-mini-card'
    );
    echo html_writer::div(
        html_writer::span(get_string('moderationstatus', 'local_flwexam'), 'flwexam-card-label') .
        html_writer::tag('strong', s(exam_service::status_label($result['private']['moderation_status']))),
        'flwexam-mini-card'
    );
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $output->footer();
