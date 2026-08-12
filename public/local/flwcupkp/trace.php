<?php
// C-UP-KP traceability report.

require_once(__DIR__ . '/../../config.php');

$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 200, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);

$url = new moodle_url('/local/flwcupkp/trace.php');
foreach (['frameworkid' => $frameworkid, 'unitcode' => $unitcode, 'userid' => $userid] as $name => $value) {
    if ($value !== 0 && $value !== '') {
        $url->param($name, $value);
    }
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('traceabilityreport', 'local_flwcupkp'));
$PAGE->set_heading(get_string('traceabilityreport', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('traceabilityreport', 'local_flwcupkp'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/curriculum.php', ['frameworkid' => $frameworkid]),
    get_string('curriculummanager', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'), get_string('pluginname', 'local_flwcupkp'),
    ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/trace.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
    html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
        \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
    html_writer::empty_tag('input', ['type' => 'number', 'name' => 'userid', 'value' => $userid > 0 ? $userid : '']),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/trace.php'), get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

local_flwcupkp_trace_render($frameworkid, $unitcode, $userid, max(1, min(1000, $limit)));

echo $OUTPUT->footer();

/**
 * Render the traceability table.
 *
 * @param int $frameworkid
 * @param string $unitcode
 * @param int $userid
 * @param int $limit
 */
function local_flwcupkp_trace_render(int $frameworkid, string $unitcode, int $userid, int $limit): void {
    global $DB;

    $where = [];
    $params = [];
    if ($frameworkid > 0) {
        $where[] = 'c.frameworkid = :frameworkid';
        $params['frameworkid'] = $frameworkid;
    }
    if ($unitcode !== '') {
        $where[] = 'o.unitcode = :unitcode';
        $params['unitcode'] = $unitcode;
    }
    $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT c.id AS competencyid,
                   c.externalid AS competencyexternalid,
                   c.title AS competencytitle,
                   c.cefr AS competencycefr,
                   u.id AS upid,
                   u.externalid AS upexternalid,
                   u.title AS uptitle,
                   u.cefr AS upkeepfr,
                   kp.id AS kpid,
                   kp.externalid AS kpexternalid,
                   kp.title AS kptitle,
                   kp.domain AS kpdomain,
                   kp.cefr AS kpcefr,
                   o.id AS objectid,
                   o.externalid AS objectexternalid,
                   o.title AS objecttitle,
                   o.unitcode,
                   o.lesson,
                   o.objecttype,
                   o.cmid,
                   m.name AS modname
              FROM {flwcupkp_comp_up} cu
              JOIN {flwcupkp_comp} c ON c.id = cu.competencyid
              JOIN {flwcupkp_up} u ON u.id = cu.upid
              JOIN {flwcupkp_up_kp} uk ON uk.upid = u.id
              JOIN {flwcupkp_kp} kp ON kp.id = uk.kpid
         LEFT JOIN {flwcupkp_object_map} om ON om.targettype = 'kp' AND om.targetid = kp.id
         LEFT JOIN {flwcupkp_object} o ON o.id = om.objectid
         LEFT JOIN {course_modules} cm ON cm.id = o.cmid
         LEFT JOIN {modules} m ON m.id = cm.module
             {$wheresql}
          ORDER BY c.externalid ASC, u.externalid ASC, kp.externalid ASC, o.lesson ASC, o.externalid ASC";

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-trace-table';
    $table->head = [
        get_string('competency', 'local_flwcupkp'),
        get_string('usepoint', 'local_flwcupkp'),
        get_string('knowledgepoint', 'local_flwcupkp'),
        get_string('activity', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
        get_string('learnerstate', 'local_flwcupkp'),
    ];

    $shown = 0;
    $recordset = $DB->get_recordset_sql($sql, $params);
    foreach ($recordset as $row) {
        if ($shown >= $limit) {
            break;
        }
        $table->data[] = [
            local_flwcupkp_trace_target('competency', (int)$row->competencyid, $row->competencyexternalid,
                $row->competencytitle, $row->competencycefr),
            local_flwcupkp_trace_target('up', (int)$row->upid, $row->upexternalid, $row->uptitle, $row->upkeepfr),
            local_flwcupkp_trace_target('kp', (int)$row->kpid, $row->kpexternalid, $row->kptitle,
                $row->kpcefr . ' / ' . $row->kpdomain),
            local_flwcupkp_trace_object($row),
            local_flwcupkp_trace_evidence((int)$row->objectid, (int)$row->kpid, $userid),
            local_flwcupkp_trace_state((int)$row->competencyid, (int)$row->upid, (int)$row->kpid, $userid),
        ];
        $shown++;
    }
    $recordset->close();

    if (!$table->data) {
        $table->data[] = [get_string('nographrows', 'local_flwcupkp'), '', '', '', '', ''];
    }

    echo html_writer::table($table);
}

/**
 * Render a linked C-UP-KP target.
 */
function local_flwcupkp_trace_target(string $type, int $id, string $externalid, string $title, string $meta): string {
    $link = html_writer::link(new moodle_url('/local/flwcupkp/edit_entity.php', ['type' => $type, 'id' => $id]),
        s($externalid));
    return $link . html_writer::tag('div', s($title), ['class' => 'local-flwcupkp-trace-title']) .
        html_writer::tag('small', s($meta), ['class' => 'local-flwcupkp-muted']);
}

/**
 * Render a learning object.
 */
function local_flwcupkp_trace_object(\stdClass $row): string {
    if (empty($row->objectid)) {
        return html_writer::tag('span', get_string('missingactivity', 'local_flwcupkp'), ['class' => 'badge badge-warning']);
    }
    $label = s($row->objectexternalid) . html_writer::tag('div', s($row->objecttitle),
        ['class' => 'local-flwcupkp-trace-title']);
    $meta = trim((string)$row->unitcode . ' ' . (string)$row->lesson . ' ' . (string)$row->objecttype);
    if (!empty($row->cmid) && !empty($row->modname)) {
        $label .= html_writer::link(new moodle_url('/mod/' . $row->modname . '/view.php', ['id' => (int)$row->cmid]),
            'CMID ' . (int)$row->cmid, ['class' => 'local-flwcupkp-chip']);
    }
    return $label . html_writer::tag('small', s($meta), ['class' => 'local-flwcupkp-muted']);
}

/**
 * Render evidence count and latest timestamp.
 */
function local_flwcupkp_trace_evidence(int $objectid, int $kpid, int $userid): string {
    global $DB;

    if ($objectid <= 0) {
        return get_string('noevidenceyet', 'local_flwcupkp');
    }
    $where = 'objectid = :objectid AND targettype = :targettype AND targetid = :targetid';
    $params = ['objectid' => $objectid, 'targettype' => 'kp', 'targetid' => $kpid];
    if ($userid > 0) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $count = $DB->count_records_select('flwcupkp_evidence', $where, $params);
    $latest = $DB->get_field_sql('SELECT MAX(timecreated) FROM {flwcupkp_evidence} WHERE ' . $where, $params);
    $html = html_writer::tag('strong', (string)(int)$count);
    if ($latest) {
        $html .= html_writer::tag('div', userdate((int)$latest), ['class' => 'local-flwcupkp-muted']);
    }
    return $html;
}

/**
 * Render learner or class state summary.
 */
function local_flwcupkp_trace_state(int $competencyid, int $upid, int $kpid, int $userid): string {
    global $DB;

    if ($userid > 0) {
        $parts = [];
        foreach (['competency' => $competencyid, 'up' => $upid, 'kp' => $kpid] as $type => $id) {
            $state = $DB->get_record('flwcupkp_state', ['userid' => $userid, 'targettype' => $type, 'targetid' => $id],
                '*', IGNORE_MISSING);
            $parts[] = s($type . ': ' . ($state->masterystate ?? 'none'));
        }
        return implode(html_writer::empty_tag('br'), $parts);
    }

    $count = $DB->count_records('flwcupkp_state', ['targettype' => 'kp', 'targetid' => $kpid]);
    $strong = $DB->count_records_select('flwcupkp_state',
        "targettype = :targettype AND targetid = :targetid AND masterystate IN ('independent_use', 'mastered', 'review_due')",
        ['targettype' => 'kp', 'targetid' => $kpid]);
    return html_writer::tag('strong', (string)(int)$strong) . ' / ' . (int)$count .
        html_writer::tag('div', get_string('strongstatecount', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
}
