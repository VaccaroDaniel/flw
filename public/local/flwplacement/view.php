<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwplacement\service\result_repository;

$id = required_param('id', PARAM_INT);
$result = result_repository::get_result($id);
$course = get_course($result->courseid);
require_login($course);

$context = $result->courseid == SITEID ? context_system::instance() : context_course::instance($result->courseid);
if ($USER->id != $result->userid) {
    require_capability('local/flwplacement:viewreports', $context);
} else {
    local_flwplacement_require_take_access($context);
}

$url = new moodle_url('/local/flwplacement/view.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('viewreport', 'local_flwplacement'));
$PAGE->set_heading(get_string('viewreport', 'local_flwplacement'));
$PAGE->requires->css(new moodle_url('/local/flwplacement/styles.css'));

$decoded = json_decode($result->resultjson ?? '', true);
$skillpercentages = is_array($decoded['skill_percentages'] ?? null) ? $decoded['skill_percentages'] : [];
$weakskills = is_array($decoded['weak_skill_warnings'] ?? null) ? $decoded['weak_skill_warnings'] : [];
$studyrecommendation = $decoded['study_recommendation'] ?? '';
$strongareas = is_array($decoded['strong_areas'] ?? null) ? $decoded['strong_areas'] : [];
$repairareas = is_array($decoded['repair_areas'] ?? null) ? $decoded['repair_areas'] : [];
$learningpath = is_array($decoded['learning_path'] ?? null) ? $decoded['learning_path'] : [];
$supportflags = is_array($decoded['support_flags'] ?? null) ? $decoded['support_flags'] : [];
$status = $decoded['placement_status'] ?? 'confirmed';
$nextcheckpoint = (int)($decoded['next_checkpoint_unit'] ?? 0);

$output = $PAGE->get_renderer('core');
echo $output->header();

echo html_writer::start_div('flw-placement-app flw-placement-report-only');
echo html_writer::start_div('flw-placement-card');
echo html_writer::start_div('flw-placement-report-grid');
echo html_writer::div(
    html_writer::span(get_string('cefrlevel', 'local_flwplacement')) .
    html_writer::tag('strong', s($result->cefrlevel)) .
    html_writer::span(s(format_float($result->weightedscore, 1)) . ' ' . get_string('weightedscore', 'local_flwplacement')),
    'flw-placement-level-badge'
);
echo html_writer::start_div();
echo html_writer::div('Placement report', 'flw-placement-eyebrow');
$user = core_user::get_user($result->userid, '*', IGNORE_MISSING);
echo html_writer::tag('h2', $user ? fullname($user) : s($result->userid));
echo html_writer::tag('p', s($studyrecommendation), ['class' => 'flw-placement-muted']);
echo html_writer::start_div('flw-placement-mini-grid');
echo html_writer::div(html_writer::span(get_string('recommendedcourse', 'local_flwplacement')) . html_writer::tag('strong', s($result->recommendedcourse)), 'flw-placement-mini-card');
echo html_writer::div(html_writer::span(get_string('startingunit', 'local_flwplacement')) . html_writer::tag('strong', s($result->startingunit)), 'flw-placement-mini-card');
if ($nextcheckpoint) {
    echo html_writer::div(html_writer::span('Next checkpoint') . html_writer::tag('strong', s($nextcheckpoint)), 'flw-placement-mini-card');
}
echo html_writer::div(html_writer::span(get_string('confidencescore', 'local_flwplacement')) . html_writer::tag('strong', s($result->confidencescore) . '%'), 'flw-placement-mini-card');
echo html_writer::div(html_writer::span('Status') . html_writer::tag('strong', s($status)), 'flw-placement-mini-card');
echo html_writer::end_div();
if ($status === 'teacher_review_required') {
    echo html_writer::tag('p', 'Teacher review is recommended before this placement is confirmed.', ['class' => 'flw-placement-status-message flw-placement-review']);
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Skill profile');
echo html_writer::start_div('flw-placement-meter-grid');
foreach ($skillpercentages as $skill => $score) {
    echo html_writer::start_div('flw-placement-meter-row');
    echo html_writer::tag('strong', s($skill));
    echo html_writer::div(html_writer::div('', 'flw-placement-meter-fill', ['style' => '--value:' . (int)$score . '%']), 'flw-placement-meter-shell');
    echo html_writer::span((int)$score);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::tag('h3', 'Weak skill warnings');
echo html_writer::start_tag('ul', ['class' => 'flw-placement-warning-list']);
if ($weakskills) {
    foreach ($weakskills as $warning) {
        echo html_writer::tag('li', s($warning['message'] ?? 'Focused review recommended.'));
    }
} else {
    echo html_writer::tag('li', 'Skill balance is strong enough to begin the recommended FLW unit.');
}
echo html_writer::end_tag('ul');

echo html_writer::start_div('flw-placement-question-layout');
echo html_writer::start_div();
echo html_writer::tag('h3', 'Strong areas');
echo html_writer::start_tag('ul', ['class' => 'flw-placement-warning-list']);
if ($strongareas) {
    foreach ($strongareas as $area) {
        echo html_writer::tag('li', s($area));
    }
} else {
    echo html_writer::tag('li', 'No strong area is confirmed yet.');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();

echo html_writer::start_div('flw-placement-side');
echo html_writer::tag('h3', 'Repair plan');
echo html_writer::start_tag('ul', ['class' => 'flw-placement-warning-list']);
if ($repairareas) {
    foreach ($repairareas as $area) {
        echo html_writer::tag('li', s(str_replace('_', ' ', preg_replace('/^needs_/', '', $area))));
    }
} else {
    echo html_writer::tag('li', 'No required repair area.');
}
echo html_writer::end_tag('ul');
echo html_writer::tag('h4', 'Required repair units');
echo html_writer::start_tag('ul', ['class' => 'flw-placement-warning-list']);
$requiredrepair = is_array($learningpath['required_repair_units'] ?? null) ? $learningpath['required_repair_units'] : [];
if ($requiredrepair) {
    foreach ($requiredrepair as $unit) {
        echo html_writer::tag('li', 'Unit ' . s($unit));
    }
} else {
    echo html_writer::tag('li', 'None');
}
echo html_writer::end_tag('ul');
echo html_writer::tag('h4', 'Support flags');
echo html_writer::start_tag('ul', ['class' => 'flw-placement-warning-list']);
foreach ($supportflags as $flag => $enabled) {
    if ($enabled && $flag !== 'teacher_review_recommended') {
        echo html_writer::tag('li', s(str_replace('_', ' ', preg_replace('/^needs_/', '', $flag))));
    }
}
if (!$supportflags || !array_filter($supportflags)) {
    echo html_writer::tag('li', 'None');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('h3', 'Placement JSON');
echo html_writer::tag('pre', s(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)), ['class' => 'flw-placement-json']);
echo html_writer::end_div();
echo html_writer::end_div();

echo $output->footer();
