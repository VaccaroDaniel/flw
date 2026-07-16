<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
require_capability('local/flwexam:viewown', $context);

$url = new moodle_url('/local/flwexam/index.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('examhistory', 'local_flwexam'));
$PAGE->set_heading(get_string('examhistory', 'local_flwexam'));

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$DB->get_manager()->table_exists('local_flwexam_results')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwexam'), 'warning');
    echo $output->footer();
    exit;
}

$history = exam_service::get_history((int)$USER->id);

echo html_writer::start_div('flwexam-page');
$actions = [
    html_writer::link(
        new moodle_url('/local/flwexam/take.php'),
        get_string('takeexam', 'local_flwexam'),
        ['class' => 'btn btn-primary flwexam-main-action']
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
    get_string('examhistory', 'local_flwexam'),
    get_string('examintro', 'local_flwexam'),
    $actions,
    [
        get_string('attempts', 'local_flwexam') => (string)count($history),
        get_string('status', 'local_flwexam') => $history ? get_string('ready', 'local_flwexam') : get_string('nohistory', 'local_flwexam'),
    ]
);

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
    get_string('track', 'local_flwexam'),
    get_string('cefrlevel', 'local_flwexam'),
    get_string('date', 'local_flwexam'),
    get_string('overallscore', 'local_flwexam'),
    get_string('passstatus', 'local_flwexam'),
    get_string('certificatestatus', 'local_flwexam'),
    get_string('actions', 'local_flwexam'),
];

foreach ($history as $row) {
    $actions = [
        html_writer::link(
            new moodle_url('/local/flwexam/result.php', ['id' => $row['id']]),
            get_string('viewresult', 'local_flwexam'),
            ['class' => 'btn btn-secondary btn-sm']
        ),
    ];
    if (!empty($row['verify_code'])) {
        $actions[] = html_writer::link(
            new moodle_url('/local/flwexam/verify.php', ['code' => $row['verify_code']]),
            get_string('verifycertificate', 'local_flwexam'),
            ['class' => 'btn btn-primary btn-sm']
        );
    }
    $table->data[] = [
        s($row['examname']),
        s($row['language']),
        s($row['learning_course_category']),
        s($row['cefr_level']),
        userdate($row['timecreated']),
        local_flwexam_format_score($row['overall_score']),
        s(exam_service::status_label($row['pass_status'])),
        s(exam_service::status_label($row['certificate_status'])),
        implode(' ', $actions),
    ];
}

echo html_writer::table($table);
echo html_writer::end_div();

echo $output->footer();
