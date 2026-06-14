<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_flwaiassessment\service\result_repository;

require_login();

$id = required_param('id', PARAM_INT);
$result = result_repository::get_result($id);
$context = !empty($result->courseid) && $result->courseid != SITEID
    ? context_course::instance($result->courseid)
    : context_system::instance();
require_capability('local/flwaiassessment:view', $context);
$url = new moodle_url('/local/flwaiassessment/view.php', ['id' => $id]);

if (optional_param('confirm', 0, PARAM_BOOL)) {
    require_capability('local/flwaiassessment:manage', $context);
    require_sesskey();

    $teachercefrlevel = required_param('teachercefrlevel', PARAM_ALPHANUMEXT);
    $teacherscore = required_param('teacherscore', PARAM_FLOAT);
    $teachernote = optional_param('teachernote', '', PARAM_TEXT);

    result_repository::confirm_teacher_review($id, $teachercefrlevel, $teacherscore, $teachernote, (int) $USER->id);
    redirect($url);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('assessmentresult', 'local_flwaiassessment'));
$PAGE->set_heading(get_string('assessmentresult', 'local_flwaiassessment'));

$output = $PAGE->get_renderer('core');
echo $output->header();

$user = $result->userid ? core_user::get_user($result->userid, '*', IGNORE_MISSING) : null;
$student = $user ? fullname($user) : get_string('unknownuser', 'local_flwaiassessment');

$summary = new html_table();
$summary->data = [
    [get_string('student', 'local_flwaiassessment'), s($student)],
    [get_string('skilltype', 'local_flwaiassessment'), s($result->skilltype)],
    [get_string('activitytype', 'local_flwaiassessment'), s($result->activitytype)],
    [get_string('status', 'local_flwaiassessment'), s($result->status)],
    [get_string('cefrlevel', 'local_flwaiassessment'), s($result->cefrlevel)],
    [get_string('totalscore', 'local_flwaiassessment'), format_float($result->totalscore, 2)],
    [get_string('teachercefrlevel', 'local_flwaiassessment'), s($result->teachercefrlevel)],
    [get_string('teacherscore', 'local_flwaiassessment'), format_float($result->teacherscore, 2)],
];
echo html_writer::table($summary);

$sections = [
    'prompttext' => get_string('prompttext', 'local_flwaiassessment'),
    'rawtext' => get_string('rawtext', 'local_flwaiassessment'),
    'transcript' => get_string('transcript', 'local_flwaiassessment'),
    'rubricjson' => get_string('rubric', 'local_flwaiassessment'),
    'weakkpjson' => get_string('weakkps', 'local_flwaiassessment'),
    'recommendjson' => get_string('recommendations', 'local_flwaiassessment'),
    'airesponsejson' => get_string('airesponse', 'local_flwaiassessment'),
    'teachernote' => get_string('teachernote', 'local_flwaiassessment'),
];

foreach ($sections as $field => $heading) {
    if (trim((string) $result->{$field}) === '') {
        continue;
    }
    echo $output->heading($heading, 3);
    echo html_writer::tag('pre', s($result->{$field}), ['class' => 'p-3 bg-light border']);
}

if (has_capability('local/flwaiassessment:manage', $context)) {
    echo $output->heading(get_string('confirmreview', 'local_flwaiassessment'), 3);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);

    echo html_writer::start_div('mb-3');
    echo html_writer::label(get_string('teachercefrlevel', 'local_flwaiassessment'), 'teachercefrlevel');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'teachercefrlevel',
        'id' => 'teachercefrlevel',
        'value' => s($result->teachercefrlevel ?: $result->cefrlevel),
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-3');
    echo html_writer::label(get_string('teacherscore', 'local_flwaiassessment'), 'teacherscore');
    echo html_writer::empty_tag('input', [
        'type' => 'number',
        'step' => '0.01',
        'name' => 'teacherscore',
        'id' => 'teacherscore',
        'value' => s($result->teacherscore ?: $result->totalscore),
        'class' => 'form-control',
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-3');
    echo html_writer::label(get_string('teachernote', 'local_flwaiassessment'), 'teachernote');
    echo html_writer::tag('textarea', s($result->teachernote), [
        'name' => 'teachernote',
        'id' => 'teachernote',
        'class' => 'form-control',
        'rows' => 4,
    ]);
    echo html_writer::end_div();

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('saveconfirmation', 'local_flwaiassessment'),
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}

echo $output->footer();
