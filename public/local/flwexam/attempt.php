<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

$examid = required_param('examid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/flwexam:viewown', $context);

$exam = $DB->get_record('local_flwexam_exams', [
    'id' => $examid,
    'visible' => 1,
], '*', MUST_EXIST);
$questions = exam_service::get_attempt_questions($examid);
if (!$questions) {
    throw new moodle_exception('noquestions', 'local_flwexam');
}

if (data_submitted() && confirm_sesskey()) {
    $answers = [];
    foreach ($questions as $question) {
        $answers[(int)$question['id']] = optional_param('q' . (int)$question['id'], '', PARAM_TEXT);
    }
    $result = exam_service::submit_learner_attempt($examid, (int)$USER->id, $answers);
    redirect(new moodle_url('/local/flwexam/result.php', ['id' => $result['id']]));
}

$url = new moodle_url('/local/flwexam/attempt.php', ['examid' => $examid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('attempttitle', 'local_flwexam', format_string($exam->name)));
$PAGE->set_heading(get_string('attempttitle', 'local_flwexam', format_string($exam->name)));

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('flwexam-page');
echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('attempttitle', 'local_flwexam', format_string($exam->name)),
    get_string('attemptintro', 'local_flwexam'),
    [
        html_writer::link(
            new moodle_url('/local/flwexam/take.php'),
            get_string('backtoavailableexams', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
    ],
    [
        get_string('questions', 'local_flwexam') => (string)count($questions),
        get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
    ]
);
echo html_writer::tag('p', get_string('unansweredquestionswarning', 'local_flwexam'), ['class' => 'flwexam-muted']);

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'class' => 'flwexam-attempt-form',
    'novalidate' => 'novalidate',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach ($questions as $index => $question) {
    echo html_writer::start_div('flwexam-question-card');
    echo html_writer::div(
        get_string('questionx', 'local_flwexam', $index + 1) . ' · ' .
        s(ucfirst($question['skill'])) . ' · ' . s($question['kpcode']),
        'flwexam-card-label'
    );
    echo html_writer::tag('h4', format_text($question['questiontext'], FORMAT_PLAIN));
    if ($question['qtype'] === 'shortanswer') {
        $id = 'q' . (int)$question['id'];
        echo html_writer::label(get_string('shortanswerresponse', 'local_flwexam'), $id, false, ['class' => 'flwexam-card-label']);
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'class' => 'form-control',
            'name' => 'q' . (int)$question['id'],
            'id' => $id,
        ]);
    } else {
        foreach ($question['options'] as $option) {
            $id = 'q' . (int)$question['id'] . '_' . clean_param($option['key'], PARAM_ALPHANUMEXT);
            echo html_writer::start_div('form-check flwexam-option');
            echo html_writer::empty_tag('input', [
                'type' => 'radio',
                'class' => 'form-check-input',
                'name' => 'q' . (int)$question['id'],
                'id' => $id,
                'value' => clean_param($option['key'], PARAM_ALPHANUMEXT),
            ]);
            echo html_writer::label(s($option['text']), $id, false, ['class' => 'form-check-label']);
            echo html_writer::end_div();
        }
    }
    echo html_writer::end_div();
}

echo html_writer::start_div('flwexam-action-row');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('submitexam', 'local_flwexam'),
]);
echo html_writer::link(new moodle_url('/local/flwexam/take.php'), get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $output->footer();
