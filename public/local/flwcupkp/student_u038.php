<?php
// Student progress page for U038 C-UP-KP evidence.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 124, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/flwcupkp:viewlearnerpath', $context);

if (!$userid) {
    $userid = (int)$USER->id;
}
if ($userid !== (int)$USER->id) {
    require_capability('local/flwcupkp:viewreports', $context);
}

$learner = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
$url = new moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]);
if ($userid !== (int)$USER->id) {
    $url->param('userid', $userid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('studentprogressu038', 'local_flwcupkp'));
$PAGE->set_heading(get_string('studentprogressu038', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$progress = \local_flwcupkp\local\student_report::u038_progress($courseid, $userid);
$summary = $progress['summary'];
$parentsummary = $progress['parent_summary'];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentprogressu038', 'local_flwcupkp'));
echo html_writer::tag('p', s($course->fullname) . ' - ' . fullname($learner), ['class' => 'local-flwcupkp-muted']);
echo \local_flwcupkp\local\visuals::unit_nav($courseid, 'U038', $userid,
    has_capability('local/flwcupkp:viewreports', $context),
    has_capability('local/flwcupkp:override', $context) &&
        \local_flwcupkp\local\performance_service::has_tasks($courseid, 'U038'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-progress-hero']);
echo html_writer::tag('div', get_string('unitprogress', 'local_flwcupkp') . ': ' . (int)$summary['percent'] . '%', [
    'class' => 'local-flwcupkp-progress-title',
]);
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
echo html_writer::tag('span', '', ['style' => 'width: ' . (int)$summary['percent'] . '%']);
echo html_writer::end_tag('div');
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('learningpoints', 'local_flwcupkp') . ': ' . (int)$summary['total']);
echo html_writer::tag('span', get_string('mastered', 'local_flwcupkp') . ': ' . (int)$summary['mastered']);
echo html_writer::tag('span', get_string('needpractice', 'local_flwcupkp') . ': ' . (int)$summary['gaps']);
echo html_writer::tag('span', get_string('withevidence', 'local_flwcupkp') . ': ' . (int)$summary['with_evidence']);
echo html_writer::tag('span', get_string('teacherverified', 'local_flwcupkp') . ': ' . (int)$summary['verified']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo \local_flwcupkp\local\visuals::student_progress_rings($summary, $parentsummary);
echo \local_flwcupkp\local\visuals::hierarchy_map($courseid, 'U038', $userid);

if ($progress['next_recommendation']) {
    $next = $progress['next_recommendation'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-next']);
    echo html_writer::tag('h3', get_string('nextactivity', 'local_flwcupkp'));
    echo html_writer::tag('div', s($next['kp_externalid']) . ' - ' . s($next['kp_title']), ['class' => 'local-flwcupkp-next-title']);
    echo html_writer::tag('p', s($next['next_activity']['reason']));
    if ($next['next_activity']['url']) {
        echo html_writer::link($next['next_activity']['url'], s($next['next_activity']['title']), ['class' => 'btn btn-primary']);
    }
    echo html_writer::end_tag('section');
}

echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-overview']);
echo html_writer::tag('h3', get_string('masteryoverviewu038', 'local_flwcupkp'));
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('competenciesachieved', 'local_flwcupkp') . ': ' .
    (int)$parentsummary['competency_achieved'] . '/' . (int)$parentsummary['competency_total']);
echo html_writer::tag('span', get_string('upsdemonstrated', 'local_flwcupkp') . ': ' .
    (int)$parentsummary['up_demonstrated'] . '/' . (int)$parentsummary['up_total']);
echo html_writer::end_tag('div');

$parenttable = new html_table();
$parenttable->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-parent-table';
$parenttable->head = [
    get_string('targettype', 'local_flwcupkp'),
    get_string('target', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidence', 'local_flwcupkp'),
];

foreach ($progress['parent_rows'] as $row) {
    $statehtml = \local_flwcupkp\local\visuals::state_badge((string)$row['state']);
    if ($row['mastery_score'] !== null) {
        $statehtml .= html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
            format_float((float)$row['mastery_score'], 2), ['class' => 'local-flwcupkp-muted']);
    }

    $evidenceparts = [];
    if ($row['evidence_count'] > 0) {
        $evidenceparts[] = get_string('evidence', 'local_flwcupkp') . ': ' . (int)$row['evidence_count'];
        if ($row['evidence_score'] !== null) {
            $evidenceparts[] = get_string('score', 'local_flwcupkp') . ' ' . format_float((float)$row['evidence_score'], 2);
        }
        if (!empty($row['sourceref'])) {
            $evidenceparts[] = s($row['sourceref']);
        }
        if ($row['evidence_time']) {
            $evidenceparts[] = userdate($row['evidence_time']);
        }
    } else {
        $evidenceparts[] = get_string('noevidenceyet', 'local_flwcupkp');
    }

    $typelabel = $row['targettype'] === 'up' ?
        get_string('usepoint', 'local_flwcupkp') : get_string('competency', 'local_flwcupkp');
    $parenttable->data[] = [
        s($typelabel),
        html_writer::tag('strong', s($row['externalid'])) . html_writer::tag('div', s($row['title'])),
        $statehtml,
        implode(html_writer::empty_tag('br'), $evidenceparts),
    ];
}

if (empty($parenttable->data)) {
    echo $OUTPUT->notification(get_string('noparentrowsu038', 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('parenttargets', 'local_flwcupkp') . ' (' . count($parenttable->data) . ')',
        html_writer::table($parenttable)
    );
}
echo html_writer::end_tag('section');

$table = new html_table();
$table->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-student-table';
$table->head = [
    get_string('lesson', 'local_flwcupkp'),
    get_string('learningpoint', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidence', 'local_flwcupkp'),
    get_string('nextactivity', 'local_flwcupkp'),
];

foreach ($progress['rows'] as $row) {
    $statehtml = \local_flwcupkp\local\visuals::state_badge((string)$row['state']);
    if ($row['mastery_score'] !== null) {
        $statehtml .= html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
            format_float((float)$row['mastery_score'], 2), ['class' => 'local-flwcupkp-muted']);
    }
    if ($row['is_teacher_verified']) {
        $statehtml .= html_writer::tag('div', get_string('teacherverified', 'local_flwcupkp'), ['class' => 'local-flwcupkp-verified']);
    }

    $evidenceparts = [];
    if ($row['evidence_id']) {
        if ($row['attempt_id']) {
            $evidenceparts[] = get_string('attempt', 'local_flwcupkp') . ' ' . (int)$row['attempt_id'];
        }
        if ($row['evidence_score'] !== null) {
            $evidenceparts[] = get_string('score', 'local_flwcupkp') . ' ' . format_float((float)$row['evidence_score'], 2);
        }
        if ($row['evidence_time']) {
            $evidenceparts[] = userdate($row['evidence_time']);
        }
        if ($row['is_teacher_verified'] && !empty($row['verification']['teacher'])) {
            $evidenceparts[] = get_string('teacherverifiedby', 'local_flwcupkp') . ' ' . s($row['verification']['teacher']);
        }
    } else {
        $evidenceparts[] = get_string('noevidenceyet', 'local_flwcupkp');
    }

    $nextactivity = '';
    if ($row['activity_url']) {
        $nextactivity .= html_writer::link($row['activity_url'], s($row['object_title']));
    } else {
        $nextactivity .= s($row['object_title']);
    }
    if ($row['is_gap'] && !empty($row['next_activity']['reason'])) {
        $nextactivity .= html_writer::tag('div', s($row['next_activity']['reason']), ['class' => 'local-flwcupkp-muted']);
    } else if ($row['is_mastered']) {
        $nextactivity .= html_writer::tag('div', get_string('keepgoing', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
    }

    $table->data[] = [
        s($row['lesson']) . html_writer::tag('div', s($row['domain']), ['class' => 'local-flwcupkp-muted']),
        html_writer::tag('strong', s($row['kp_externalid'])) . html_writer::tag('div', s($row['kp_title'])),
        $statehtml,
        implode(html_writer::empty_tag('br'), $evidenceparts),
        $nextactivity,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noprogressrows', 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('learningpointevidence', 'local_flwcupkp') . ' (' . count($table->data) . ')',
        html_writer::table($table)
    );
}

echo $OUTPUT->footer();
