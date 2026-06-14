<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/flwaispeaking/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'flwaispeaking'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'flwaispeaking'), 2);

$instances = get_all_instances_in_course('flwaispeaking', $course);
if (!$instances) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'flwaispeaking')), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('name')];
foreach ($instances as $instance) {
    $table->data[] = [
        html_writer::link(new moodle_url('/mod/flwaispeaking/view.php', ['id' => $instance->coursemodule]), format_string($instance->name)),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
