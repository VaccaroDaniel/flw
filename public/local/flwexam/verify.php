<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

$code = optional_param('code', '', PARAM_ALPHANUMEXT);
$context = context_system::instance();

$url = new moodle_url('/local/flwexam/verify.php', ['code' => $code]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('verifycertificate', 'local_flwexam'));
$PAGE->set_heading(get_string('verifycertificate', 'local_flwexam'));

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('flwexam-page');
echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('verifycertificate', 'local_flwexam'),
    get_string('verifyintro', 'local_flwexam')
);

if ($code === '') {
    echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'flwexam-verify-form']);
    echo html_writer::label(get_string('verificationcode', 'local_flwexam'), 'code');
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'code',
        'id' => 'code',
        'class' => 'form-control',
        'required' => 'required',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('verifycertificate', 'local_flwexam'),
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

try {
    $viewerid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;
    $certificate = exam_service::verify_certificate($code, $viewerid);
    echo html_writer::div(
        $certificate['valid'] ? get_string('certificatevalid', 'local_flwexam') : get_string('certificatenotvalid', 'local_flwexam'),
        $certificate['valid'] ? 'alert alert-success' : 'alert alert-warning'
    );

    echo html_writer::start_div('flwexam-summary-grid');
    $fields = [
        get_string('certificateid', 'local_flwexam') => $certificate['certificate_code'],
        get_string('learner', 'local_flwexam') => $certificate['learner_name'],
        get_string('language', 'local_flwexam') => $certificate['language'],
        get_string('track', 'local_flwexam') => $certificate['learning_course_category'],
        get_string('cefrlevel', 'local_flwexam') => $certificate['cefr_level'],
        get_string('issuedate', 'local_flwexam') => userdate($certificate['timeissued']),
        get_string('status', 'local_flwexam') => exam_service::status_label($certificate['status']),
    ];
    foreach ($fields as $label => $value) {
        echo html_writer::div(
            html_writer::span(s($label), 'flwexam-card-label') .
            html_writer::tag('strong', s($value)),
            'flwexam-mini-card'
        );
    }
    echo html_writer::end_div();
} catch (Exception $e) {
    echo $output->notification(get_string('verificationfailed', 'local_flwexam'), 'error');
}

echo html_writer::end_div();
echo $output->footer();
