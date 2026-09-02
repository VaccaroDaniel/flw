<?php
// Program 3 Gate E1 History V1 evidence adapter page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$reason = optional_param('reason', '', PARAM_TEXT);
$facttypes = optional_param_array('facttypes', [], PARAM_ALPHANUMEXT);
if (!$facttypes) {
    $facttypes = ['attempts', 'completion'];
}

require_login();
$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
require_capability('local/flwcupkp:viewreports', $context);
if ($action === 'apply') {
    require_sesskey();
    require_capability('local/flwcupkp:manageframeworks', $systemcontext);
}

$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'limit' => max(1, min(500, $limit)),
    'offset' => max(0, $offset),
];
foreach ($facttypes as $facttype) {
    $baseparams['facttypes'][] = $facttype;
}

$PAGE->set_url(new moodle_url('/local/flwcupkp/history_evidence.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('historyevidenceadapter', 'local_flwcupkp'));
$PAGE->set_heading(get_string('historyevidenceadapter', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\history_evidence_adapter::status($courseid, $unitcode, $frameworkid, $limit);
$result = null;
if ($courseid > 0) {
    if ($action === 'apply') {
        $result = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $facttypes,
            $limit,
            $offset,
            $reason
        );
        $status = \local_flwcupkp\local\history_evidence_adapter::status($courseid, $unitcode, $frameworkid, $limit);
    } else {
        $result = \local_flwcupkp\local\history_evidence_adapter::preview_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $facttypes,
            $limit,
            $offset
        );
    }
}
$history = \local_flwcupkp\local\history_evidence_adapter::recent_reprocess_history($courseid, $unitcode, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('historyevidenceadapter', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('historyevidenceadapterintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/management.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('cm4management', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/evidence_sync.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]), get_string('evidencesynchealth', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e1-shell']);
local_flwcupkp_e1_render_status($status);
local_flwcupkp_e1_render_filters($courseid, $unitcode, $frameworkid, $limit, $offset, $facttypes, $reason);
if ($result) {
    local_flwcupkp_e1_render_result($result, $courseid, $unitcode, $frameworkid, $limit, $offset, $facttypes, $reason);
} else {
    echo $OUTPUT->notification(get_string('historyevidencechoosecourse', 'local_flwcupkp'), 'info');
}
local_flwcupkp_e1_render_history($history);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render E1 readiness cards.
 *
 * @param array $status
 */
function local_flwcupkp_e1_render_status(array $status): void {
    $summary = $status['criteria_summary'];
    $source = $status['source_adapter']['history_totals'] ?? [];
    $cards = [
        get_string('historyevidencestatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e1_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('historyevidencecriteria', 'local_flwcupkp') => [
            'value' => s($summary['passed'] . '/' . $summary['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $summary['failed']),
        ],
        get_string('historyevidenceattempts', 'local_flwcupkp') => [
            'value' => s((string)($source['attempts'] ?? 0)),
            'detail' => get_string('historyevidenceattemptsdetail', 'local_flwcupkp'),
        ],
        get_string('historyevidencecompletion', 'local_flwcupkp') => [
            'value' => s((string)($source['completion'] ?? 0)),
            'detail' => get_string('historyevidencecompletiondetail', 'local_flwcupkp'),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e1_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('historyevidencenextgatedetail', 'local_flwcupkp'),
        ],
    ];

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid local-flwcupkp-cm4-cardgrid']);
    foreach ($cards as $label => $card) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s($label));
        echo html_writer::tag('strong', $card['value']);
        echo html_writer::tag('em', s((string)$card['detail']));
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    if (!empty($status['findings'])) {
        echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel']);
        echo html_writer::tag('h3', get_string('findings', 'local_flwcupkp'));
        echo html_writer::alist(array_map(static function(array $finding): string {
            return s(($finding['code'] ?? 'finding') . ': ' . ($finding['message'] ?? ''));
        }, array_slice($status['findings'], 0, 8)));
        echo html_writer::end_tag('section');
    }
}

/**
 * Render scope/action form.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $limit
 * @param int $offset
 * @param array $facttypes
 * @param string $reason
 */
function local_flwcupkp_e1_render_filters(int $courseid, string $unitcode, int $frameworkid, int $limit, int $offset,
        array $facttypes, string $reason): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/history_evidence.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_e1_course_options(), 'courseid', $courseid,
            false), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
        html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('limit', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'limit',
            'value' => max(1, min(500, $limit)),
            'min' => 1,
            'max' => 500,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-limit-filter']);
    echo html_writer::tag('label', get_string('offset', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'offset',
            'value' => max(0, $offset),
            'min' => 0,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-limit-filter']);
    echo html_writer::start_tag('fieldset', ['class' => 'local-flwcupkp-e1-facttypes']);
    echo html_writer::tag('legend', get_string('historyevidencefacts', 'local_flwcupkp'));
    foreach (['attempts' => 'historyevidenceattempts', 'completion' => 'historyevidencecompletion'] as $value => $string) {
        echo html_writer::tag('label',
            html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'facttypes[]',
                'value' => $value,
                'checked' => in_array($value, $facttypes, true) ? 'checked' : null,
            ]) . s(get_string($string, 'local_flwcupkp')),
            ['class' => 'local-flwcupkp-e1-checkbox']
        );
    }
    echo html_writer::end_tag('fieldset');
    echo html_writer::tag('label', get_string('reason', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'reason',
            'value' => $reason,
            'maxlength' => 160,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-reason']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'preview']);
    echo html_writer::tag('button', get_string('preview', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::link(new moodle_url('/local/flwcupkp/history_evidence.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render preview/apply result.
 *
 * @param array $result
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $limit
 * @param int $offset
 * @param array $facttypes
 * @param string $reason
 */
function local_flwcupkp_e1_render_result(array $result, int $courseid, string $unitcode, int $frameworkid, int $limit,
        int $offset, array $facttypes, string $reason): void {
    $summary = $result['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('historyevidenceresult', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('historyevidenceresultintro', 'local_flwcupkp', (object)[
        'mode' => $result['mode'],
        'seen' => $summary['records_seen'],
        'planned' => $summary['planned'],
        'created' => $summary['created'],
        'existing' => $summary['existing'],
        'unresolved' => $summary['unresolved'],
        'skipped' => $summary['skipped'],
    ]));
    echo html_writer::end_tag('div');

    if ($result['mode'] === 'preview' && !empty($result['plans']) &&
            has_capability('local/flwcupkp:manageframeworks', context_system::instance())) {
        echo html_writer::start_tag('form', ['method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/history_evidence.php')]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frameworkid', 'value' => $frameworkid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'limit', 'value' => max(1, min(500, $limit))]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'offset', 'value' => max(0, $offset)]);
        foreach ($facttypes as $facttype) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'facttypes[]', 'value' => $facttype]);
        }
        echo html_writer::tag('label', get_string('reason', 'local_flwcupkp') .
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'reason',
                'value' => $reason,
                'maxlength' => 160,
                'class' => 'form-control',
            ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-reason']);
        echo html_writer::tag('button', get_string('historyevidenceapply', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-success',
        ]);
        echo html_writer::end_tag('form');
    }

    local_flwcupkp_e1_render_plan_table($result['plans']);
    local_flwcupkp_e1_render_fact_table(get_string('historyevidenceunresolved', 'local_flwcupkp'),
        $result['unresolved']);
    local_flwcupkp_e1_render_fact_table(get_string('historyevidenceskipped', 'local_flwcupkp'), $result['skipped']);
    local_flwcupkp_e1_render_fact_table(get_string('historyevidencerejectedmaps', 'local_flwcupkp'),
        $result['rejectedmaps']);
    echo html_writer::end_tag('section');
}

/**
 * Render planned/created/existing evidence rows.
 *
 * @param array $plans
 */
function local_flwcupkp_e1_render_plan_table(array $plans): void {
    if (!$plans) {
        echo html_writer::tag('p', get_string('historyevidencenoplans', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('status'),
        get_string('historyevidencefact', 'local_flwcupkp'),
        get_string('historyevidencesource', 'local_flwcupkp'),
        get_string('learner', 'local_flwcupkp'),
        get_string('historyevidenceobject', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('score', 'local_flwcupkp'),
    ];
    foreach (array_slice($plans, 0, 100) as $plan) {
        $table->data[] = [
            local_flwcupkp_e1_badge($plan['status'] ?? ''),
            s($plan['facttype'] ?? ''),
            html_writer::tag('code', s($plan['history_source_key'] ?? '')) .
                html_writer::tag('div', s('CM ' . ($plan['cmid'] ?? '')), ['class' => 'local-flwcupkp-muted']),
            s((string)($plan['userid'] ?? '')),
            html_writer::tag('strong', s($plan['object_externalid'] ?? '')) .
                html_writer::tag('div', s($plan['object_title'] ?? ''), ['class' => 'local-flwcupkp-muted']),
            s(($plan['targettype'] ?? '') . ':' . ($plan['targetid'] ?? '')),
            s((string)round((float)($plan['normalizedscore'] ?? 0), 5)),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render unresolved/skipped/rejected rows.
 *
 * @param string $title
 * @param array $rows
 */
function local_flwcupkp_e1_render_fact_table(string $title, array $rows): void {
    if (!$rows) {
        return;
    }

    echo html_writer::tag('h4', s($title), ['class' => 'local-flwcupkp-e1-subtitle']);
    $table = new html_table();
    $table->head = [
        get_string('reason', 'local_flwcupkp'),
        get_string('historyevidencefact', 'local_flwcupkp'),
        get_string('historyevidencesource', 'local_flwcupkp'),
        get_string('learner', 'local_flwcupkp'),
        get_string('historyevidenceidentity', 'local_flwcupkp'),
        get_string('detail', 'local_flwcupkp'),
    ];
    foreach (array_slice($rows, 0, 100) as $row) {
        $detail = [];
        foreach (['objectid', 'mapid', 'targettype', 'targetid', 'message'] as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                $detail[] = $key . ': ' . $row[$key];
            }
        }
        $table->data[] = [
            s($row['reason'] ?? ''),
            s($row['facttype'] ?? ''),
            html_writer::tag('code', s($row['history_source_key'] ?? '')),
            s((string)($row['userid'] ?? '')),
            s('CM ' . ($row['cmid'] ?? '') . ' ' . ($row['activityid'] ?? '') . ' ' .
                ($row['assessmentid'] ?? '')),
            s(implode(' | ', $detail)),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render recent repair/reprocess history.
 *
 * @param array $history
 */
function local_flwcupkp_e1_render_history(array $history): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('historyevidencehistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('historyevidencehistoryintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if (!$history) {
        echo html_writer::tag('p', get_string('historyevidencehistoryempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('time'),
        get_string('action'),
        get_string('summary', 'local_flwcupkp'),
    ];
    foreach ($history as $row) {
        $summary = $row['details']['summary'] ?? [];
        $table->data[] = [
            userdate((int)$row['timecreated']),
            s($row['action']),
            s($summary ? json_encode($summary, JSON_UNESCAPED_SLASHES) :
                json_encode($row['details'], JSON_UNESCAPED_SLASHES)),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render a small status badge.
 *
 * @param string $value
 * @return string
 */
function local_flwcupkp_e1_badge(string $value): string {
    $class = 'local-flwcupkp-status-' . preg_replace('/[^a-z0-9]+/', '-',
        strtolower((string)$value));
    return html_writer::tag('span', s($value), ['class' => 'badge ' . $class]);
}

/**
 * Course options for mapped C-UP-KP courses.
 *
 * @return array
 */
function local_flwcupkp_e1_course_options(): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname
           FROM {course} c
           JOIN {flwcupkp_object} o ON o.courseid = c.id
          WHERE c.id <> :siteid
       ORDER BY c.fullname ASC",
        ['siteid' => SITEID]
    );
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = format_string($record->fullname) . ' (' .
            format_string($record->shortname) . ')';
    }
    return $options;
}
