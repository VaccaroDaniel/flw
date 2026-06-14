<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_flwaiassessment\service\result_repository;

$courseid = optional_param('courseid', SITEID, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = $courseid == SITEID ? context_system::instance() : context_course::instance($courseid);
require_capability('local/flwaiassessment:manage', $context);

$url = new moodle_url('/local/flwaiassessment/submit.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('submitassessment', 'local_flwaiassessment'));
$PAGE->set_heading(get_string('submitassessment', 'local_flwaiassessment'));

if (!$DB->get_manager()->table_exists('local_flwai_results')) {
    throw new moodle_exception('pluginnotinstalled', 'local_flwaiassessment');
}

if (data_submitted() && confirm_sesskey()) {
    $userid = required_param('userid', PARAM_INT);
    $skilltype = required_param('skilltype', PARAM_ALPHA);
    $prompttext = required_param('prompttext', PARAM_RAW_TRIMMED);
    $submissiontext = required_param('submissiontext', PARAM_RAW_TRIMMED);
    $processnow = optional_param('processnow', 0, PARAM_BOOL);

    if (!in_array($skilltype, ['writing', 'speaking'])) {
        throw new moodle_exception('invaliddata', 'error');
    }

    if ($courseid != SITEID && !is_enrolled($context, $userid)) {
        throw new moodle_exception('usernotenrolled', 'error');
    }

    $id = result_repository::create_pending([
        'userid' => $userid,
        'courseid' => $courseid,
        'cmid' => 0,
        'activitytype' => 'coursemanual',
        'sourcecomponent' => 'local_flwaiassessment',
        'submissionid' => time(),
        'skilltype' => $skilltype,
        'rawtext' => $skilltype === 'writing' ? $submissiontext : null,
        'transcript' => $skilltype === 'speaking' ? $submissiontext : null,
        'prompttext' => $prompttext,
    ]);

    if ($processnow) {
        $task = new \local_flwaiassessment\task\process_pending();
        $task->execute();
    }

    redirect(
        new moodle_url('/local/flwaiassessment/view.php', ['id' => $id]),
        get_string($processnow ? 'assessmentprocessed' : 'assessmentqueued', 'local_flwaiassessment'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$output = $PAGE->get_renderer('core');
echo $output->header();

$courseoptions = [];
if (has_capability('local/flwaiassessment:manage', context_system::instance())) {
    $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname, shortname');
    foreach ($courses as $availablecourse) {
        $courseoptions[$availablecourse->id] = format_string($availablecourse->fullname);
    }
}

if ($courseoptions) {
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3']);
    echo html_writer::label(get_string('course'), 'courseid', false, ['class' => 'me-2']);
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['id' => 'courseid', 'class' => 'me-2']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('choose'), 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

$users = $courseid == SITEID
    ? $DB->get_records_select('user', 'deleted = 0 AND id > 1', [], 'lastname ASC, firstname ASC', 'id, firstname, lastname, email')
    : get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email', 'u.lastname ASC, u.firstname ASC');

$useroptions = [];
foreach ($users as $user) {
    $label = fullname($user);
    if (!empty($user->email)) {
        $label .= ' (' . $user->email . ')';
    }
    $useroptions[$user->id] = $label;
}

if (!$useroptions) {
    echo $output->notification(get_string('nostudents', 'local_flwaiassessment'), 'warning');
    echo $output->footer();
    exit;
}

$skilloptions = [
    'speaking' => get_string('speaking', 'local_flwaiassessment'),
    'writing' => get_string('writing', 'local_flwaiassessment'),
];

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('student', 'local_flwaiassessment'), 'userid');
echo html_writer::select($useroptions, 'userid', '', false, ['id' => 'userid', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('skilltype', 'local_flwaiassessment'), 'skilltype');
echo html_writer::select($skilloptions, 'skilltype', 'speaking', false, ['id' => 'skilltype', 'class' => 'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('prompttext', 'local_flwaiassessment'), 'prompttext');
echo html_writer::tag('textarea', s(get_string('defaultprompt', 'local_flwaiassessment')), [
    'name' => 'prompttext',
    'id' => 'prompttext',
    'class' => 'form-control',
    'rows' => 3,
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::label(get_string('submissiontext', 'local_flwaiassessment'), 'submissiontext');
echo html_writer::tag('textarea', '', [
    'name' => 'submissiontext',
    'id' => 'submissiontext',
    'class' => 'form-control',
    'rows' => 8,
    'required' => 'required',
    'placeholder' => get_string('submissiontext_help', 'local_flwaiassessment'),
]);
echo html_writer::end_div();

echo html_writer::start_div('form-check mb-3');
echo html_writer::checkbox('processnow', 1, true, get_string('processnow', 'local_flwaiassessment'), [
    'id' => 'processnow',
    'class' => 'form-check-input',
], ['class' => 'form-check-label']);
echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('submitforassessment', 'local_flwaiassessment'),
    'class' => 'btn btn-primary',
]);

echo ' ';
echo html_writer::link(new moodle_url('/local/flwaiassessment/index.php'), get_string('openreview', 'local_flwaiassessment'), [
    'class' => 'btn btn-secondary',
]);

echo html_writer::end_tag('form');
echo $output->footer();
