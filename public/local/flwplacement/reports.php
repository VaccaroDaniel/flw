<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use local_flwplacement\service\result_repository;

require_login();

$course = get_site();
$context = context_system::instance();
require_capability('local/flwplacement:viewreports', $context);

$url = new moodle_url('/local/flwplacement/reports.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('placementreports', 'local_flwplacement'));
$PAGE->set_heading(get_string('placementreports', 'local_flwplacement'));

$output = $PAGE->get_renderer('core');
echo $output->header();

if (!$DB->get_manager()->table_exists('local_flwplacement')) {
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwplacement'), 'warning');
    echo $output->footer();
    exit;
}

echo html_writer::div(
    html_writer::link(new moodle_url('/local/flwplacement/index.php'), get_string('takeplacement', 'local_flwplacement'), ['class' => 'btn btn-primary']) .
    ' ' .
    html_writer::link(new moodle_url('/local/flwplacement/export.php'), get_string('downloadquestionbank', 'local_flwplacement'), ['class' => 'btn btn-secondary']),
    'mb-3'
);

$results = result_repository::get_results();
if (!$results) {
    echo $output->notification(get_string('noresults', 'local_flwplacement'), 'info');
    echo $output->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('learner', 'local_flwplacement'),
    get_string('cefrlevel', 'local_flwplacement'),
    get_string('recommendedcourse', 'local_flwplacement'),
    get_string('startingunit', 'local_flwplacement'),
    get_string('confidencescore', 'local_flwplacement'),
    get_string('timecreated', 'local_flwplacement'),
    '',
];

foreach ($results as $result) {
    $user = core_user::get_user($result->userid, '*', IGNORE_MISSING);
    $table->data[] = [
        $user ? fullname($user) : s($result->userid),
        s($result->cefrlevel),
        s($result->recommendedcourse),
        s($result->startingunit),
        s($result->confidencescore) . '%',
        userdate($result->timecreated),
        html_writer::link(new moodle_url('/local/flwplacement/view.php', ['id' => $result->id]), get_string('viewreport', 'local_flwplacement')),
    ];
}

echo html_writer::table($table);
echo $output->footer();
