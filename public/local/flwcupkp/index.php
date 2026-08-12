<?php
// Admin landing page for local_flwcupkp.

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

$PAGE->set_url(new moodle_url('/local/flwcupkp/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_flwcupkp'));
$PAGE->set_heading(get_string('pluginname', 'local_flwcupkp'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_flwcupkp'));

$report = \local_flwcupkp\local\audit_service::coverage();

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-dashboard']);
echo html_writer::tag('p', 'C-UP-KP framework browser and operational reports.');
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/setup.php'), get_string('unitsetupwizard', 'local_flwcupkp'),
    ['class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php'), get_string('curriculummanager', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/import_export.php'), get_string('importexport', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/mappings.php'), get_string('mappingmanager', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/trace.php'), get_string('traceabilityreport', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/manual_evidence.php'), get_string('manualevidence', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/sync.php'), get_string('competencysync', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/calibration.php'), get_string('calibrationreport',
    'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/calibration_proposal.php'), get_string('thresholdproposals',
    'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');
echo html_writer::start_tag('ul');
echo html_writer::tag('li', 'Competencies: ' . $report['competencies']);
echo html_writer::tag('li', 'Use Points: ' . $report['use_points']);
echo html_writer::tag('li', 'Knowledge Points: ' . $report['knowledge_points']);
echo html_writer::tag('li', 'Competencies linked to UPs: ' . $report['competencies_linked_to_up_percent'] . '%');
echo html_writer::tag('li', 'UPs linked to KPs: ' . $report['use_points_linked_to_kp_percent'] . '%');
echo html_writer::end_tag('ul');

if (!empty($report['warnings'])) {
    echo $OUTPUT->notification(implode('<br>', array_map('s', $report['warnings'])), 'warning');
}

echo html_writer::end_tag('div');
echo $OUTPUT->footer();
