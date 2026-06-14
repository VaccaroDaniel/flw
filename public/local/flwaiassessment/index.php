<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_flwaiassessment\service\result_repository;

require_login();
$context = context_system::instance();
require_capability('local/flwaiassessment:view', $context);

$skilltype = optional_param('skilltype', '', PARAM_ALPHA);
$status = optional_param('status', '', PARAM_ALPHA);

$url = new moodle_url('/local/flwaiassessment/index.php', [
    'skilltype' => $skilltype,
    'status' => $status,
]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewresults', 'local_flwaiassessment'));
$PAGE->set_heading(get_string('reviewresults', 'local_flwaiassessment'));

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$DB->get_manager()->table_exists('local_flwai_results')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwaiassessment'), 'warning');
    echo $output->footer();
    exit;
}

if (has_capability('local/flwaiassessment:manage', $context)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/local/flwaiassessment/submit.php'),
            get_string('newassessment', 'local_flwaiassessment'),
            ['class' => 'btn btn-primary']
        ),
        'mb-3'
    );
}

$skilloptions = [
    '' => get_string('all'),
    'writing' => 'Writing',
    'speaking' => 'Speaking',
];
$statusoptions = [
    '' => get_string('all'),
    result_repository::STATUS_PENDING => get_string('pending', 'local_flwaiassessment'),
    result_repository::STATUS_PROCESSING => get_string('processing', 'local_flwaiassessment'),
    result_repository::STATUS_COMPLETE => get_string('complete', 'local_flwaiassessment'),
    result_repository::STATUS_FAILED => get_string('failed', 'local_flwaiassessment'),
    result_repository::STATUS_NEEDS_INPUT => get_string('needsinput', 'local_flwaiassessment'),
];

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
echo html_writer::select($skilloptions, 'skilltype', $skilltype, false, ['class' => 'me-2']);
echo html_writer::select($statusoptions, 'status', $status, false, ['class' => 'me-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

$results = result_repository::get_results([
    'skilltype' => $skilltype,
    'status' => $status,
]);

if (!$results) {
    echo $output->notification(get_string('noresults', 'local_flwaiassessment'), 'info');
    echo $output->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('student', 'local_flwaiassessment'),
    get_string('skilltype', 'local_flwaiassessment'),
    get_string('activitytype', 'local_flwaiassessment'),
    get_string('status', 'local_flwaiassessment'),
    get_string('cefrlevel', 'local_flwaiassessment'),
    get_string('totalscore', 'local_flwaiassessment'),
    get_string('teacherconfirmed', 'local_flwaiassessment'),
    get_string('timecreated', 'local_flwaiassessment'),
    '',
];

foreach ($results as $result) {
    $user = $result->userid ? core_user::get_user($result->userid, '*', IGNORE_MISSING) : null;
    $student = $user ? fullname($user) : get_string('unknownuser', 'local_flwaiassessment');
    $confirmed = $result->teacherconfirmed
        ? get_string('confirmed', 'local_flwaiassessment')
        : get_string('notconfirmed', 'local_flwaiassessment');

    $table->data[] = [
        s($student),
        s($result->skilltype),
        s($result->activitytype),
        s($result->status),
        s($result->cefrlevel),
        format_float($result->totalscore, 2),
        $confirmed,
        userdate($result->timecreated),
        html_writer::link(
            new moodle_url('/local/flwaiassessment/view.php', ['id' => $result->id]),
            get_string('viewresult', 'local_flwaiassessment')
        ),
    ];
}

echo html_writer::table($table);
echo $output->footer();
