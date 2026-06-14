<?php
// List all FLW VR Room instances in a course.

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/flwvrroom/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'flwvrroom'));
$PAGE->set_heading($course->fullname);

$instances = get_all_instances_in_course('flwvrroom', $course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'flwvrroom'));

if (!$instances) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'flwvrroom')), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name'), get_string('cefrlevel', 'flwvrroom'), get_string('scenario', 'flwvrroom')];
foreach ($instances as $instance) {
    $url = new moodle_url('/mod/flwvrroom/view.php', ['id' => $instance->coursemodule]);
    $table->data[] = [html_writer::link($url, format_string($instance->name)), s($instance->cefrlevel), s($instance->scenario)];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
