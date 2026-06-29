<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('flwaispeaking', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$flwaispeaking = $DB->get_record('flwaispeaking', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/flwaispeaking:submit', $context);

$submission = $DB->get_record('flwaispeaking_submissions', [
    'id' => $submissionid,
    'flwaispeakingid' => $flwaispeaking->id,
    'userid' => $USER->id,
], '*', MUST_EXIST);

$url = new moodle_url('/mod/flwaispeaking/delete.php', [
    'id' => $cm->id,
    'submissionid' => $submission->id,
]);
$returnurl = new moodle_url('/mod/flwaispeaking/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_title(format_string($flwaispeaking->name));
$PAGE->set_heading(format_string($course->fullname));

if (!empty($submission->assessmentid)) {
    $assessment = $DB->get_record('local_flwai_results', ['id' => $submission->assessmentid]);
    if ($assessment && !empty($assessment->teacherconfirmed)) {
        redirect($returnurl, get_string('cannotdeleteconfirmed', 'flwaispeaking'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($confirm) {
    require_sesskey();
    flwaispeaking_delete_submission($flwaispeaking, (int) $submission->id, (int) $USER->id);
    redirect($returnurl, get_string('submissiondeleted', 'flwaispeaking'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$output = $PAGE->get_renderer('core');
echo $output->header();
echo $output->heading(get_string('deletesubmission', 'flwaispeaking'), 2);

$confirmurl = new moodle_url('/mod/flwaispeaking/delete.php', [
    'id' => $cm->id,
    'submissionid' => $submission->id,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);

echo $output->confirm(get_string('confirmdeletesubmission', 'flwaispeaking'), $confirmurl, $returnurl);
echo $output->footer();
