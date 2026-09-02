<?php
// H6 teacher history analytics dashboard.

require_once(__DIR__ . '/../../config.php');

use local_flwhistory\local\teacher_analytics_renderer;
use local_flwhistory\local\teacher_analytics_service;

$courseid = required_param('courseid', PARAM_INT);
$limit = optional_param('limit', 25, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$url = new moodle_url('/local/flwhistory/teacher.php', [
    'courseid' => $courseid,
    'limit' => $limit,
    'offset' => $offset,
]);
$context = context_course::instance($courseid);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('teacheranalyticstitle', 'local_flwhistory'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/flwhistory/styles.css'));

$dashboard = teacher_analytics_service::teacher_dashboard_for_request($courseid, [
    'limit' => $limit,
    'offset' => $offset,
]);

$baseurl = new moodle_url('/local/flwhistory/teacher.php', [
    'courseid' => $courseid,
    'limit' => $dashboard['pagination']['limit'],
    'offset' => $dashboard['pagination']['offset'],
]);

echo $OUTPUT->header();
echo teacher_analytics_renderer::render($dashboard, $baseurl);
echo $OUTPUT->footer();
