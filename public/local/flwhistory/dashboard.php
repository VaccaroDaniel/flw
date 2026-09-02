<?php
// H5 learner history and grade history dashboard.

require_once(__DIR__ . '/../../config.php');

use local_flwhistory\local\dashboard_renderer;
use local_flwhistory\local\dashboard_service;

$courseid = required_param('courseid', PARAM_INT);
$requesteduserid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 10, PARAM_INT);
$attemptoffset = optional_param('attemptoffset', 0, PARAM_INT);
$gradeoffset = optional_param('gradeoffset', 0, PARAM_INT);
$historyoffset = optional_param('historyoffset', 0, PARAM_INT);
$activityoffset = optional_param('activityoffset', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$urlparams = [
    'courseid' => $courseid,
    'limit' => $limit,
    'attemptoffset' => $attemptoffset,
    'gradeoffset' => $gradeoffset,
    'historyoffset' => $historyoffset,
    'activityoffset' => $activityoffset,
];
if ($requesteduserid > 0) {
    $urlparams['userid'] = $requesteduserid;
}
$url = new moodle_url('/local/flwhistory/dashboard.php', $urlparams);
$context = context_course::instance($courseid);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('dashboardtitle', 'local_flwhistory'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(new moodle_url('/local/flwhistory/styles.css'));

$dashboard = dashboard_service::learner_dashboard_for_request($courseid, $requesteduserid, [
    'limit' => $limit,
    'attemptoffset' => $attemptoffset,
    'gradeoffset' => $gradeoffset,
    'historyoffset' => $historyoffset,
    'activityoffset' => $activityoffset,
]);

$baseurl = new moodle_url('/local/flwhistory/dashboard.php', [
    'courseid' => $courseid,
    'userid' => $dashboard['userid'],
    'limit' => $dashboard['pagination']['limit'],
    'attemptoffset' => $dashboard['pagination']['attemptoffset'],
    'gradeoffset' => $dashboard['pagination']['gradeoffset'],
    'historyoffset' => $dashboard['pagination']['historyoffset'],
    'activityoffset' => $dashboard['pagination']['activityoffset'],
]);

echo $OUTPUT->header();
echo dashboard_renderer::render($dashboard, $baseurl);
echo $OUTPUT->footer();
