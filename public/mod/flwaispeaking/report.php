<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('flwaispeaking', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$flwaispeaking = $DB->get_record('flwaispeaking', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/flwaispeaking:viewreports', $context);

$url = new moodle_url('/mod/flwaispeaking/report.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($flwaispeaking->name));
$PAGE->set_heading(format_string($course->fullname));

if (optional_param('refresh', 0, PARAM_BOOL)) {
    require_sesskey();
    flwaispeaking_sync_activity_submissions($flwaispeaking);
    redirect($url, get_string('resultsrefreshed', 'flwaispeaking'), null, \core\output\notification::NOTIFY_SUCCESS);
}

flwaispeaking_sync_activity_submissions($flwaispeaking);

$output = $PAGE->get_renderer('core');
echo $output->header();
echo $output->heading(get_string('allsubmissions', 'flwaispeaking'), 2);

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/flwaispeaking/view.php', ['id' => $cm->id]), format_string($flwaispeaking->name), ['class' => 'btn btn-secondary']) . ' ' .
    html_writer::link(new moodle_url('/mod/flwaispeaking/report.php', ['id' => $cm->id, 'refresh' => 1, 'sesskey' => sesskey()]), get_string('refreshresults', 'flwaispeaking'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

$records = $DB->get_records('flwaispeaking_submissions', ['flwaispeakingid' => $flwaispeaking->id], 'timecreated DESC');

if (!$records) {
    echo $output->notification(get_string('nosubmissions', 'flwaispeaking'), 'info');
    echo $output->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('student', 'flwaispeaking'),
    get_string('attempt', 'flwaispeaking'),
    get_string('status', 'flwaispeaking'),
    get_string('cefr', 'flwaispeaking'),
    get_string('score', 'flwaispeaking'),
    get_string('submitted', 'flwaispeaking'),
    '',
];

foreach ($records as $record) {
    $user = core_user::get_user($record->userid, '*', IGNORE_MISSING);
    $student = $user ? fullname($user) : get_string('notavailable', 'flwaispeaking');
    $link = $record->assessmentid
        ? html_writer::link(new moodle_url('/local/flwaiassessment/view.php', ['id' => $record->assessmentid]), get_string('viewairesult', 'flwaispeaking'))
        : get_string('notavailable', 'flwaispeaking');

    $table->data[] = [
        s($student),
        (int) $record->attemptnumber,
        s($record->status),
        s($record->cefrlevel),
        format_float($record->totalscore, 2),
        userdate($record->timecreated),
        $link,
    ];
}

echo html_writer::table($table);
echo $output->footer();
