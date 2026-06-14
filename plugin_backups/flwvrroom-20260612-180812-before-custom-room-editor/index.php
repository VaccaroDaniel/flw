<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/flwvrroom/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'flwvrroom'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'flwvrroom'));

$instances = get_all_instances_in_course('flwvrroom', $course);

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('noinstances', 'flwvrroom'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('cefrlevel', 'flwvrroom'),
        get_string('scenario', 'flwvrroom'),
    ];

    foreach ($instances as $instance) {
        $link = html_writer::link(new moodle_url('/mod/flwvrroom/view.php', ['id' => $instance->coursemodule]), format_string($instance->name));
        $table->data[] = [$link, s($instance->cefrlevel), s($instance->scenario)];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
