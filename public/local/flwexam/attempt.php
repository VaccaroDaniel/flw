<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

$examid = required_param('examid', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$accesscode = optional_param('accesscode', '', PARAM_TEXT);

require_login();

$context = context_system::instance();
require_capability('local/flwexam:viewown', $context);

$exam = $DB->get_record('local_flwexam_exams', [
    'id' => $examid,
    'visible' => 1,
], '*', MUST_EXIST);

$urlparams = ['examid' => $examid];
if ($sessionid > 0) {
    $urlparams['sessionid'] = $sessionid;
}
$url = new moodle_url('/local/flwexam/attempt.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('attempttitle', 'local_flwexam', format_string($exam->name)));
$PAGE->set_heading(get_string('attempttitle', 'local_flwexam', format_string($exam->name)));
local_flwexam_require_styles();

$session = null;
$questionlimit = 20;
$needsaccesscode = false;
if ($sessionid > 0) {
    $session = exam_service::get_session($sessionid);
    if ((int)$session->examid !== $examid) {
        throw new moodle_exception('sessionnotavailable', 'local_flwexam');
    }
    $questionlimit = max(1, min(30, (int)$session->questioncount));
    $needsaccesscode = trim((string)$session->accesscode) !== '' && trim($accesscode) === '';
    if (!$needsaccesscode) {
        exam_service::require_can_attempt_session($session, (int)$USER->id, $accesscode);
    }
}

$output = $PAGE->get_renderer('core');
if ($needsaccesscode) {
    echo $output->header();
    echo html_writer::start_div('flwexam-page');
    echo local_flwexam_render_hero(
        get_string('exam', 'local_flwexam'),
        get_string('sessionaccessrequired', 'local_flwexam'),
        get_string('sessionaccessrequiredintro', 'local_flwexam'),
        [
            html_writer::link(
                new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
                get_string('backtoexamcenter', 'local_flwexam'),
                ['class' => 'btn btn-secondary flwexam-main-action']
            ),
        ],
        [
            get_string('examname', 'local_flwexam') => format_string($exam->name),
            get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
        ]
    );
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'flwexam-filter-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::start_div('form-group');
    echo html_writer::label(get_string('accesscode', 'local_flwexam'), 'flwexam-accesscode');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'accesscode',
        'id' => 'flwexam-accesscode',
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::end_div();
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('continue'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

if (!empty($exam->quizid)) {
    global $SESSION;

    if (!isset($SESSION->local_flwexam_pending_quiz_sessions) ||
            !is_array($SESSION->local_flwexam_pending_quiz_sessions)) {
        $SESSION->local_flwexam_pending_quiz_sessions = [];
    }
    $pendingkey = (int)$exam->quizid . ':' . (int)$exam->id;
    if ($session) {
        $SESSION->local_flwexam_pending_quiz_sessions[$pendingkey] = [
            'examid' => (int)$exam->id,
            'quizid' => (int)$exam->quizid,
            'sessionid' => (int)$session->id,
            'timecreated' => time(),
        ];
    } else {
        unset($SESSION->local_flwexam_pending_quiz_sessions[$pendingkey]);
    }

    $quizinfo = exam_service::get_linked_quiz_info((int)$exam->quizid);
    if ($quizinfo && !empty($quizinfo['cmid']) && !empty($quizinfo['issamplecountok'])) {
        exam_service::cleanup_stale_quiz_attempts((int)$exam->quizid, (int)$USER->id);
        redirect(new moodle_url('/mod/quiz/startattempt.php', [
            'cmid' => (int)$quizinfo['cmid'],
            'sesskey' => sesskey(),
            'flwskippreflight' => 1,
            'flwautostart' => 1,
            'flwexam' => 1,
            'examid' => (int)$exam->id,
        ]));
    }

    $latestresult = exam_service::get_latest_result_for_exam($examid, (int)$USER->id);
    echo $output->header();
    echo html_writer::start_div('flwexam-page');
    echo local_flwexam_render_hero(
        get_string('exam', 'local_flwexam'),
        get_string('attempttitle', 'local_flwexam', format_string($exam->name)),
        get_string('quizbackedexamintro', 'local_flwexam'),
        [
            html_writer::link(
                new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
                get_string('backtoexamcenter', 'local_flwexam'),
                ['class' => 'btn btn-secondary flwexam-main-action']
            ),
        ],
        [
            get_string('questions', 'local_flwexam') => $quizinfo
                ? (string)$quizinfo['questioncount'] . ' / ' . exam_service::QUIZ_EXAM_ATTEMPT_QUESTION_COUNT
                : '0 / ' . exam_service::QUIZ_EXAM_ATTEMPT_QUESTION_COUNT,
            get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
            get_string('question_source', 'local_flwexam') => get_string('moodlequiz', 'local_flwexam'),
        ]
    );

    if (!$quizinfo) {
        echo $output->notification(get_string('linkedquiznotavailable', 'local_flwexam'), 'warning');
    } else {
        echo html_writer::start_div('flwexam-question-card flwexam-quiz-launch-card');
        echo html_writer::div(get_string('moodlequiz', 'local_flwexam'), 'flwexam-card-label');
        echo html_writer::tag('h3', s($quizinfo['name']));
        echo html_writer::tag(
            'p',
            'This Moodle Quiz is linked, but FLW Exam needs exactly ' .
                exam_service::QUIZ_EXAM_ATTEMPT_QUESTION_COUNT .
                ' attempt questions. Add random slots from the correct language/level question bank, then open the exam again.',
            ['class' => 'flwexam-muted']
        );
        echo html_writer::tag(
            'p',
            'Current attempt questions: ' . (int)$quizinfo['questioncount'] .
                '. Source-bank questions detected: ' . (int)$quizinfo['sourcequestioncount'] . '.',
            ['class' => 'flwexam-muted']
        );
        echo html_writer::start_div('flwexam-action-row');
        $quizcontext = context_module::instance((int)$quizinfo['cmid']);
        if (has_capability('mod/quiz:manage', $quizcontext)) {
            echo html_writer::link(
                new moodle_url('/mod/quiz/edit.php', ['cmid' => (int)$quizinfo['cmid']]),
                'Configure quiz questions',
                ['class' => 'btn btn-primary']
            );
        }
        echo html_writer::link(
            new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
            get_string('backtoexamcenter', 'local_flwexam'),
            ['class' => 'btn btn-secondary']
        );
        if ($latestresult) {
            echo html_writer::link(
                new moodle_url('/local/flwexam/result.php', ['id' => $latestresult['id']]),
                get_string('viewlatestresult', 'local_flwexam'),
                ['class' => 'btn btn-secondary']
            );
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$submittedquestionids = optional_param('questionids', '', PARAM_SEQUENCE);
$questionids = array_values(array_filter(array_map('intval', explode(',', $submittedquestionids))));
$questions = $questionids
    ? exam_service::get_attempt_questions($examid, $questionlimit, $questionids)
    : exam_service::get_attempt_questions($examid, $questionlimit);
if (!$questions) {
    throw new moodle_exception('noquestions', 'local_flwexam');
}

if (data_submitted() && confirm_sesskey()) {
    $answers = [];
    foreach ($questions as $question) {
        $answers[(int)$question['id']] = optional_param('q' . (int)$question['id'], '', PARAM_TEXT);
    }
    $result = exam_service::submit_learner_attempt($examid, (int)$USER->id, $answers, $sessionid, $questionids, $accesscode);
    redirect(new moodle_url('/local/flwexam/result.php', ['id' => $result['id']]));
}

echo $output->header();

echo html_writer::start_div('flwexam-page');
echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('attempttitle', 'local_flwexam', format_string($exam->name)),
    get_string('attemptintro', 'local_flwexam'),
    [
        html_writer::link(
            new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
            get_string('backtoexamcenter', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
    ],
    [
        get_string('questions', 'local_flwexam') => (string)count($questions),
        get_string('cefrlevel', 'local_flwexam') => $exam->cefrlevel,
        get_string('examdelivery', 'local_flwexam') => $session ? exam_service::session_type_label($session->sessiontype) : get_string('selfexamsession', 'local_flwexam'),
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
if ($accesscode !== '') {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'accesscode', 'value' => s($accesscode)]);
}
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'questionids',
    'value' => implode(',', array_map(static fn($question): int => (int)$question['id'], $questions)),
]);

foreach ($questions as $index => $question) {
    echo html_writer::start_div('flwexam-question-card');
    echo html_writer::div(
        get_string('questionx', 'local_flwexam', $index + 1) . ' · ' .
        s(exam_service::skill_label($question['skill'])) . ' · ' . s($question['kpcode']),
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
echo html_writer::link(new moodle_url('/local/flwexam/index.php', ['view' => 'available']), get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $output->footer();
