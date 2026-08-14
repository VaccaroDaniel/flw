<?php
// Generic student-facing C-UP-KP unit progress page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', 'U038', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);

$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST) : null;
require_login($course);
$context = $course ? context_course::instance($courseid) : context_system::instance();
require_capability('local/flwcupkp:viewlearnerpath', $context);
$canviewreports = has_capability('local/flwcupkp:viewreports', $context);

if ($userid <= 0 || !$canviewreports) {
    $userid = (int)$USER->id;
}

$url = new moodle_url('/local/flwcupkp/student.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
if ($userid !== (int)$USER->id) {
    $url->param('userid', $userid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('unitprogressgeneric', 'local_flwcupkp'));
$PAGE->set_heading(get_string('unitprogressgeneric', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$targets = \local_flwcupkp\local\unit_report::unit_targets($courseid, $unitcode);
$states = \local_flwcupkp\local\unit_report::states($userid, $targets);
$objects = \local_flwcupkp\local\unit_report::objects_by_target($targets, $courseid, $unitcode);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unitprogressgeneric', 'local_flwcupkp') . ': ' . s($unitcode));
echo \local_flwcupkp\local\visuals::unit_nav($courseid, $unitcode, $userid, $canviewreports,
    has_capability('local/flwcupkp:override', $context) &&
        \local_flwcupkp\local\performance_service::has_tasks($courseid, $unitcode));

if ($unitcode === 'U038' && $courseid > 0) {
    echo \local_flwcupkp\local\visuals::nav_link(
        new moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $courseid]),
        get_string('openrichu038progress', 'local_flwcupkp'), ['class' => 'btn btn-primary']);
}

$mastered = 0;
foreach ($targets as $target) {
    $key = \local_flwcupkp\local\unit_report::target_key($target->targettype, (int)$target->targetid);
    $state = $states[$key] ?? null;
    if ($state && in_array($state->masterystate, ['mastered', 'stable', 'transfer_ready', 'achieved', 'sustained'], true)) {
        $mastered++;
    }
}
$total = count($targets);
$percent = $total > 0 ? round(($mastered / $total) * 100) : 0;
echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-progress-hero']);
echo html_writer::tag('div', get_string('unitprogress', 'local_flwcupkp') . ': ' . $percent . '%',
    ['class' => 'local-flwcupkp-progress-title']);
echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-progressbar', 'aria-hidden' => 'true']);
echo html_writer::tag('span', '', ['style' => 'width: ' . $percent . '%']);
echo html_writer::end_tag('div');
echo html_writer::end_tag('section');

echo \local_flwcupkp\local\visuals::progress_rings([
    [
        'label' => get_string('targets', 'local_flwcupkp'),
        'value' => $mastered,
        'total' => $total,
    ],
], get_string('visualprogressrings', 'local_flwcupkp'));
if ($courseid > 0 && $unitcode !== '') {
    echo \local_flwcupkp\local\visuals::hierarchy_map($courseid, $unitcode, $userid);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable local-flwcupkp-table';
$table->head = [
    get_string('type', 'local_flwcupkp'),
    get_string('externalid', 'local_flwcupkp'),
    get_string('title', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidence', 'local_flwcupkp'),
    get_string('learningobjects', 'local_flwcupkp'),
];
foreach ($targets as $target) {
    $key = \local_flwcupkp\local\unit_report::target_key($target->targettype, (int)$target->targetid);
    $state = $states[$key] ?? null;
    $statehtml = $state ? \local_flwcupkp\local\visuals::state_badge((string)$state->masterystate) :
        html_writer::tag('span', get_string('noevidenceyet', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
    if ($state && $state->masteryscore !== null) {
        $statehtml .= html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
            format_float((float)$state->masteryscore, 2), ['class' => 'local-flwcupkp-muted']);
    }
    $table->data[] = [
        s($target->targettype),
        s($target->externalid),
        s($target->title),
        $statehtml,
        $state ? (int)$state->evidencecount : 0,
        local_flwcupkp_generic_object_links($objects[$key] ?? []),
    ];
}

if (!$table->data) {
    echo $OUTPUT->notification(get_string('nogenericunitrows', 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('learningpointevidence', 'local_flwcupkp') . ' (' . count($table->data) . ')',
        html_writer::table($table)
    );
}

echo $OUTPUT->footer();

/**
 * Unit targets from object mappings.
 */
function local_flwcupkp_generic_unit_targets(int $courseid, string $unitcode): array {
    global $DB;

    $params = ['unitcode' => $unitcode];
    $coursesql = '';
    if ($courseid > 0) {
        $coursesql = ' AND (o.courseid = :courseid OR o.courseid IS NULL)';
        $params['courseid'] = $courseid;
    }
    $maps = $DB->get_records_sql(
        "SELECT DISTINCT m.targettype, m.targetid
           FROM {flwcupkp_object_map} m
           JOIN {flwcupkp_object} o ON o.id = m.objectid
          WHERE o.unitcode = :unitcode {$coursesql}
       ORDER BY m.targettype ASC, m.targetid ASC",
        $params
    );
    $targets = [];
    foreach ($maps as $map) {
        $target = local_flwcupkp_generic_target_record($map->targettype, (int)$map->targetid);
        if ($target) {
            $target->targettype = $map->targettype;
            $target->targetid = (int)$map->targetid;
            $targets[] = $target;
        }
    }
    return $targets;
}

/**
 * Target record by type.
 */
function local_flwcupkp_generic_target_record(string $targettype, int $targetid): ?stdClass {
    global $DB;

    $table = $targettype === 'competency' ? 'flwcupkp_comp' : ($targettype === 'up' ? 'flwcupkp_up' : 'flwcupkp_kp');
    return $DB->get_record($table, ['id' => $targetid], 'id, externalid, title', IGNORE_MISSING) ?: null;
}

/**
 * Learner states keyed by target.
 */
function local_flwcupkp_generic_states(int $userid, array $targets): array {
    global $DB;

    $states = [];
    foreach ($targets as $target) {
        $state = $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => $target->targettype,
            'targetid' => $target->targetid,
        ], '*', IGNORE_MISSING);
        if ($state) {
            $states[$target->targettype . ':' . $target->targetid] = $state;
        }
    }
    return $states;
}

/**
 * Objects grouped by target key.
 */
function local_flwcupkp_generic_objects_by_target(array $targets): array {
    global $DB;

    $out = [];
    foreach ($targets as $target) {
        $records = $DB->get_records_sql(
            "SELECT o.*
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE m.targettype = :targettype AND m.targetid = :targetid
           ORDER BY o.lesson ASC, o.externalid ASC",
            ['targettype' => $target->targettype, 'targetid' => $target->targetid]
        );
        $out[$target->targettype . ':' . $target->targetid] = $records;
    }
    return $out;
}

/**
 * Render object links.
 */
function local_flwcupkp_generic_object_links(array $objects): string {
    $links = [];
    foreach ($objects as $object) {
        $label = $object->lesson . ': ' . $object->title;
        if (!empty($object->cmid) && !empty($object->courseid)) {
            $modname = !empty($object->modname) ? $object->modname : local_flwcupkp_generic_modname((int)$object->cmid);
            $links[] = html_writer::link(new moodle_url('/mod/' . $modname . '/view.php',
                ['id' => $object->cmid]), s($label));
        } else {
            $links[] = s($label);
        }
    }
    return implode(html_writer::empty_tag('br'), $links);
}

/**
 * Resolve Moodle module name for a CMID.
 */
function local_flwcupkp_generic_modname(int $cmid): string {
    global $DB;
    $modname = $DB->get_field_sql(
        "SELECT m.name
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE cm.id = :cmid",
        ['cmid' => $cmid]
    );
    return $modname ?: 'page';
}
