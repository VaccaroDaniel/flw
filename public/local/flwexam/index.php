<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
require_capability('local/flwexam:viewown', $context);

$view = optional_param('view', 'available', PARAM_ALPHA);
if (!in_array($view, ['available', 'history'], true)) {
    $view = 'available';
}

$selectedlanguage = optional_param('language', '', PARAM_ALPHANUMEXT);
$selectedlevel = optional_param('cefr', '', PARAM_ALPHANUMEXT);
if ($selectedlanguage === '') {
    $selectedlanguage = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
    $selectedlanguage = $selectedlanguage === 'zh_cn' ? 'zh' : $selectedlanguage;
}

$urlparams = ['view' => $view];
if ($selectedlanguage !== '') {
    $urlparams['language'] = $selectedlanguage;
}
if ($selectedlevel !== '') {
    $urlparams['cefr'] = $selectedlevel;
}

$url = new moodle_url('/local/flwexam/index.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('examcenter', 'local_flwexam'));
$PAGE->set_heading(get_string('examcenter', 'local_flwexam'));
local_flwexam_require_styles();

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$DB->get_manager()->table_exists('local_flwexam_results')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwexam'), 'warning');
    echo $output->footer();
    exit;
}

$history = exam_service::get_history((int)$USER->id);
$filteroptions = exam_service::get_exam_filter_options();
if ($selectedlanguage !== '' && !isset($filteroptions['languages'][$selectedlanguage])) {
    $selectedlanguage = '';
}
if ($selectedlevel !== '' && !isset($filteroptions['levels'][$selectedlevel])) {
    $selectedlevel = '';
}

echo html_writer::start_div('flwexam-page');

$heroactions = [];
if (has_capability('local/flwexam:manageexams', $context)) {
    $heroactions[] = html_writer::link(
        new moodle_url('/local/flwexam/manage.php'),
        get_string('manageexams', 'local_flwexam'),
        ['class' => 'btn btn-secondary flwexam-main-action']
    );
}
if (has_capability('local/flwexam:manageselfexams', $context) ||
        has_capability('local/flwexam:manageofficialexams', $context)) {
    $heroactions[] = html_writer::link(
        new moodle_url('/local/flwexam/sessions.php'),
        get_string('manageexamsessions', 'local_flwexam'),
        ['class' => 'btn btn-secondary flwexam-main-action']
    );
}

echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('examcenter', 'local_flwexam'),
    get_string('examcenterintro', 'local_flwexam'),
    $heroactions,
    [
        get_string('attempts', 'local_flwexam') => (string)count($history),
        get_string('status', 'local_flwexam') => get_string('ready', 'local_flwexam'),
    ]
);

$baseparams = [];
if ($selectedlanguage !== '') {
    $baseparams['language'] = $selectedlanguage;
}
if ($selectedlevel !== '') {
    $baseparams['cefr'] = $selectedlevel;
}
$tabs = [
    'available' => get_string('availableexams', 'local_flwexam'),
    'history' => get_string('myresults', 'local_flwexam'),
];
echo html_writer::start_div('flwexam-center-tabs', ['role' => 'navigation', 'aria-label' => get_string('examcenter', 'local_flwexam')]);
foreach ($tabs as $tab => $label) {
    $classes = 'flwexam-center-tab';
    $attributes = ['class' => $classes];
    if ($tab === $view) {
        $attributes['class'] .= ' is-active';
        $attributes['aria-current'] = 'page';
    }
    echo html_writer::link(new moodle_url('/local/flwexam/index.php', ['view' => $tab] + $baseparams), $label, $attributes);
}
echo html_writer::end_div();

if ($view === 'available') {
    if (!$filteroptions['languages'] || !$filteroptions['levels']) {
        echo html_writer::div(get_string('noavailableexams', 'local_flwexam'), 'alert alert-info');
        echo html_writer::end_div();
        echo $output->footer();
        exit;
    }

    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/local/flwexam/index.php'))->out(false),
        'class' => 'flwexam-filter-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'view', 'value' => 'available']);
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

    $hasfullselection = $selectedlanguage !== '' && $selectedlevel !== '';
    if (!$hasfullselection) {
        echo html_writer::div(get_string('chooseexamfiltersfirst', 'local_flwexam'), 'alert alert-info');
        echo html_writer::end_div();
        echo $output->footer();
        exit;
    }

    $activefilters = [
        'language' => $selectedlanguage,
        'cefr_level' => $selectedlevel,
    ];
    $sessions = exam_service::get_available_sessions((int)$USER->id, $activefilters);
    if ($sessions) {
        echo html_writer::tag('h3', get_string('availableexamsessions', 'local_flwexam'), ['class' => 'flwexam-section-title']);
        echo html_writer::start_div('flwexam-exam-grid');
        foreach ($sessions as $session) {
            echo html_writer::start_div('flwexam-exam-card');
            echo html_writer::div(s($session['session_type_label']), 'flwexam-card-label');
            echo html_writer::tag('h3', s($session['name']));
            echo html_writer::tag('p', s($session['examname']), ['class' => 'flwexam-muted']);
            echo html_writer::start_div('flwexam-summary-grid');
            $details = [
                get_string('language', 'local_flwexam') => exam_service::language_label($session['language']),
                get_string('track', 'local_flwexam') => exam_service::track_label($session['learning_course_category']),
                get_string('cefrlevel', 'local_flwexam') => $session['cefr_level'],
                get_string('questions', 'local_flwexam') => $session['question_count'],
                get_string('attemptsremaining', 'local_flwexam') => max(0, $session['max_attempts'] - $session['attempt_count']),
                get_string('accesscode', 'local_flwexam') => $session['requires_access_code'] ? get_string('required') : get_string('notrequired', 'local_flwexam'),
            ];
            if ($session['branchname'] !== '') {
                $details[get_string('branchname', 'local_flwexam')] = $session['branchname'];
            }
            foreach ($details as $label => $value) {
                echo html_writer::div(
                    html_writer::span(s($label), 'flwexam-card-label') .
                    html_writer::tag('strong', s((string)$value)),
                    'flwexam-mini-card'
                );
            }
            echo html_writer::end_div();
            echo html_writer::start_div('flwexam-action-row');
            echo html_writer::link(
                new moodle_url('/local/flwexam/attempt.php', [
                    'examid' => $session['examid'],
                    'sessionid' => $session['id'],
                ]),
                $session['session_type'] === exam_service::SESSION_TYPE_OFFICIAL
                    ? get_string('startofficialexam', 'local_flwexam')
                    : get_string('startselfexam', 'local_flwexam'),
                ['class' => 'btn btn-primary']
            );
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    $exams = exam_service::get_available_exams($activefilters);
    if (!$exams) {
        if (!$sessions) {
            echo html_writer::div(get_string('nomatchingexams', 'local_flwexam'), 'alert alert-info');
            echo html_writer::end_div();
            echo $output->footer();
            exit;
        }
        echo html_writer::end_div();
        echo $output->footer();
        exit;
    }

    echo html_writer::tag('h3', get_string('matchingexams', 'local_flwexam'), ['class' => 'flwexam-section-title']);
    echo html_writer::start_div('flwexam-exam-grid');
    foreach ($exams as $exam) {
        $latestresult = exam_service::get_latest_result_for_exam((int)$exam['id'], (int)$USER->id);
        echo html_writer::start_div('flwexam-exam-card');
        echo html_writer::div(s($exam['code']), 'flwexam-card-label');
        echo html_writer::tag('h3', s($exam['name']));
        echo html_writer::start_div('flwexam-summary-grid');
        $details = [
            get_string('language', 'local_flwexam') => exam_service::language_label($exam['language']),
            get_string('cefrlevel', 'local_flwexam') => $exam['cefr_level'],
            get_string('questions', 'local_flwexam') => $exam['question_count'],
            get_string('question_source', 'local_flwexam') => $exam['question_source'] === 'moodle_quiz'
                ? get_string('moodlequiz', 'local_flwexam')
                : get_string('flwinternalquestions', 'local_flwexam'),
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
        echo html_writer::start_div('flwexam-action-row');
        if ($exam['question_count'] > 0) {
            if ($exam['question_source'] === 'moodle_quiz' && !empty($exam['quizid'])) {
                $quizinfo = exam_service::get_linked_quiz_info((int)$exam['quizid']);
                if ($quizinfo) {
                    echo $output->single_button(
                        new moodle_url('/mod/quiz/startattempt.php', [
                            'cmid' => (int)$quizinfo['cmid'],
                            'sesskey' => sesskey(),
                        ]),
                        get_string('startexam', 'local_flwexam'),
                        'post',
                        [
                            'class' => 'flwexam-inline-form flwexam-quiz-start-form',
                            'type' => \core\output\single_button::BUTTON_PRIMARY,
                        ]
                    );
                } else {
                    echo html_writer::div(get_string('linkedquiznotavailable', 'local_flwexam'), 'alert alert-warning');
                }
            } else {
                echo html_writer::link(
                    new moodle_url('/local/flwexam/attempt.php', ['examid' => $exam['id']]),
                    get_string('startexam', 'local_flwexam'),
                    ['class' => 'btn btn-primary']
                );
            }
        } else {
            echo html_writer::div(get_string('examhasnoquestions', 'local_flwexam'), 'alert alert-warning');
        }
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
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

if (!$history) {
    echo html_writer::div(get_string('nohistory', 'local_flwexam'), 'alert alert-info');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable flwexam-table';
$table->head = [
    get_string('examname', 'local_flwexam'),
    get_string('language', 'local_flwexam'),
    get_string('cefrlevel', 'local_flwexam'),
    get_string('date', 'local_flwexam'),
    get_string('overallscore', 'local_flwexam'),
    get_string('passstatus', 'local_flwexam'),
    get_string('certificatestatus', 'local_flwexam'),
    get_string('actions', 'local_flwexam'),
];

foreach ($history as $row) {
    $rowactions = [
        html_writer::link(
            new moodle_url('/local/flwexam/result.php', ['id' => $row['id']]),
            get_string('viewresult', 'local_flwexam'),
            ['class' => 'btn btn-secondary btn-sm']
        ),
    ];
    if (!empty($row['verify_code'])) {
        $rowactions[] = html_writer::link(
            new moodle_url('/local/flwexam/verify.php', ['code' => $row['verify_code']]),
            get_string('verifycertificate', 'local_flwexam'),
            ['class' => 'btn btn-primary btn-sm']
        );
    }
    $table->data[] = [
        s($row['examname']),
        s($row['language']),
        s($row['cefr_level']),
        userdate($row['timecreated']),
        local_flwexam_format_score($row['overall_score']),
        s(exam_service::status_label($row['pass_status'])),
        s(exam_service::status_label($row['certificate_status'])),
        implode(' ', $rowactions),
    ];
}

echo html_writer::div(html_writer::table($table), 'flwexam-table-wrap');
echo html_writer::end_div();

echo $output->footer();
