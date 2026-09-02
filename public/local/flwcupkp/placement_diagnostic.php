<?php
// Program 3 Gate A2 Placement + Diagnostic + Cold Start page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);
$action = optional_param('action', 'preview', PARAM_ALPHA);
$reason = optional_param('reason', '', PARAM_TEXT);

require_login();
$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
require_capability('local/flwcupkp:viewreports', $context);
if ($action === 'apply') {
    require_sesskey();
    require_capability('local/flwcupkp:manageframeworks', $systemcontext);
}

$limit = max(1, min(500, $limit));
$offset = max(0, $offset);
$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
    'limit' => $limit,
    'offset' => $offset,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/placement_diagnostic.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('placementdiagnostica2', 'local_flwcupkp'));
$PAGE->set_heading(get_string('placementdiagnostica2', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\placement_diagnostic_service::status($courseid, $unitcode, $frameworkid, $limit);
$result = null;
$learnerstate = null;
$classsummary = null;
if ($courseid > 0) {
    if ($action === 'apply') {
        $result = \local_flwcupkp\local\placement_diagnostic_service::apply_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $offset,
            $reason
        );
        $status = \local_flwcupkp\local\placement_diagnostic_service::status($courseid, $unitcode, $frameworkid,
            $limit);
    } else {
        $result = \local_flwcupkp\local\placement_diagnostic_service::preview_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $offset
        );
    }
    $classsummary = \local_flwcupkp\local\placement_diagnostic_service::class_summary(
        $courseid,
        $unitcode,
        $frameworkid,
        min(100, $limit)
    );
}
if ($userid > 0) {
    $learnerstate = \local_flwcupkp\local\placement_diagnostic_service::current_placement(
        $userid,
        $courseid,
        $unitcode,
        $frameworkid,
        min(100, $limit)
    );
}
$history = \local_flwcupkp\local\placement_diagnostic_service::recent_reprocess_history($courseid, $unitcode, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementdiagnostica2', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('placementdiagnostica2intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/learning_goal.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
]), get_string('learninggoala1', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/history_evidence.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('historyevidenceadapter', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_a2_render_status($status);
local_flwcupkp_a2_render_filters($courseid, $unitcode, $frameworkid, $userid, $limit, $offset, $reason);
if ($result) {
    local_flwcupkp_a2_render_result($result, $courseid, $unitcode, $frameworkid, $userid, $limit, $offset, $reason);
} else {
    echo $OUTPUT->notification(get_string('placementdiagnosticchoosecourse', 'local_flwcupkp'), 'info');
}
if ($learnerstate) {
    local_flwcupkp_a2_render_learner_state($learnerstate);
}
if ($classsummary) {
    local_flwcupkp_a2_render_class_summary($classsummary);
}
local_flwcupkp_a2_render_history($history);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render A2 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_a2_render_status(array $status): void {
    $summary = $status['summary'];
    $criteria = $status['criteria_summary'];
    $source = $status['source_adapter'];
    $cards = [
        get_string('placementdiagnosticstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a2_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('placementdiagnosticcriteria', 'local_flwcupkp') => [
            'value' => s($criteria['passed'] . '/' . $criteria['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $criteria['failed']),
        ],
        get_string('placementdiagnosticstates', 'local_flwcupkp') => [
            'value' => s((string)($summary['records'] ?? 0)),
            'detail' => local_flwcupkp_a2_state_summary($summary['states'] ?? []),
        ],
        get_string('historyv1placements', 'local_flwcupkp') => [
            'value' => s((string)($source['course_placement_fact_total'] ?? 0)),
            'detail' => get_string('placementdiagnosticdetail', 'local_flwcupkp', (object)[
                'learners' => (int)($summary['learners_with_state'] ?? 0),
                'evidence' => (int)($summary['evidence_links'] ?? 0),
            ]),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a2_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('placementdiagnosticnextgatedetail', 'local_flwcupkp'),
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
 * Render filters.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $userid
 * @param int $limit
 * @param int $offset
 * @param string $reason
 */
function local_flwcupkp_a2_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid, int $limit,
        int $offset, string $reason): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/placement_diagnostic.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_a2_course_options(), 'courseid', $courseid,
            false), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
        html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
        html_writer::select([0 => get_string('alllearners', 'local_flwcupkp')] +
            local_flwcupkp_a2_learner_options($courseid), 'userid', $userid, false),
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
    echo html_writer::link(new moodle_url('/local/flwcupkp/placement_diagnostic.php'), get_string('reset'),
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
 * @param int $userid
 * @param int $limit
 * @param int $offset
 * @param string $reason
 */
function local_flwcupkp_a2_render_result(array $result, int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit, int $offset, string $reason): void {
    $summary = $result['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('placementdiagnosticreprocess', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('placementdiagnosticreprocessintro', 'local_flwcupkp', (object)[
        'mode' => $result['mode'],
        'records' => (int)($summary['records_seen'] ?? 0),
        'dimensions' => (int)($summary['dimensions_assessed'] ?? 0),
        'planned' => (int)($summary['evidence_planned'] ?? 0),
        'created' => (int)($summary['evidence_created'] ?? 0),
        'existing' => (int)($summary['evidence_existing'] ?? 0),
    ]));
    echo html_writer::end_tag('div');

    if (($result['mode'] ?? '') === 'preview' && !empty($result['plans']) &&
            has_capability('local/flwcupkp:manageframeworks', context_system::instance())) {
        echo html_writer::start_tag('form', ['method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/placement_diagnostic.php')]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frameworkid', 'value' => $frameworkid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'limit', 'value' => max(1, min(500, $limit))]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'offset', 'value' => max(0, $offset)]);
        echo html_writer::tag('label', get_string('reason', 'local_flwcupkp') .
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'reason',
                'value' => $reason,
                'maxlength' => 160,
                'class' => 'form-control',
            ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-reason']);
        echo html_writer::tag('button', get_string('applyplacementdiagnostic', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-success',
        ]);
        echo html_writer::end_tag('form');
    }

    $table = new html_table();
    $table->head = [
        get_string('status'),
        get_string('learner', 'local_flwcupkp'),
        get_string('placementdiagnosticstate', 'local_flwcupkp'),
        get_string('placementpolicycase', 'local_flwcupkp'),
        get_string('assesseddimension', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('score', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
    ];
    foreach (array_slice($result['plans'] ?? [], 0, 100) as $row) {
        $table->data[] = [
            local_flwcupkp_a2_badge((string)($row['status'] ?? '')),
            s((string)($row['userid'] ?? '')),
            local_flwcupkp_a2_badge((string)($row['policy_state'] ?? '')),
            s((string)($row['policy_case'] ?? '')),
            s((string)($row['dimension'] ?? '')),
            s(($row['targettype'] ?? '') . ':' . ($row['targetid'] ?? '')),
            s((string)round((float)($row['normalizedscore'] ?? 0), 5)),
            s((string)round((float)($row['confidence'] ?? 0), 5)),
        ];
    }
    if (empty($table->data)) {
        echo html_writer::tag('p', get_string('placementdiagnosticnoplanrows', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
    } else {
        echo html_writer::table($table);
    }
    echo html_writer::end_tag('section');
}

/**
 * Render selected learner placement state.
 *
 * @param array $learnerstate
 */
function local_flwcupkp_a2_render_learner_state(array $learnerstate): void {
    $state = $learnerstate['state'] ?? [];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('currentplacementdiagnostic', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentplacementdiagnosticintro', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']);
    if (!$state) {
        echo html_writer::tag('p', get_string('placementdiagnosticempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }
    $facts = [
        get_string('placementdiagnosticstate', 'local_flwcupkp') => local_flwcupkp_a2_badge(
            (string)($state['policystate'] ?? 'NOT_TAKEN')
        ),
        get_string('placementpolicycase', 'local_flwcupkp') => s((string)($state['policycase'] ??
            ($state['diagnostic']['policy_case'] ?? ''))),
        get_string('currentlevel', 'local_flwcupkp') => s((string)($state['currentlevel'] ?? '')),
        get_string('score', 'local_flwcupkp') => s((string)($state['score'] ?? '')),
        get_string('confidence', 'local_flwcupkp') => s((string)($state['confidence'] ?? '')),
        get_string('createdevidence', 'local_flwcupkp') => s((string)count($state['evidenceids'] ?? [])),
    ];
    echo html_writer::start_tag('dl', ['class' => 'local-flwcupkp-cm4-definition-list']);
    foreach ($facts as $label => $value) {
        echo html_writer::tag('dt', s($label));
        echo html_writer::tag('dd', $value);
    }
    echo html_writer::end_tag('dl');
    echo html_writer::end_tag('section');
}

/**
 * Render class summary.
 *
 * @param array $classsummary
 */
function local_flwcupkp_a2_render_class_summary(array $classsummary): void {
    $summary = $classsummary['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('classplacementdiagnosticsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classplacementdiagnosticsummaryintro', 'local_flwcupkp', (object)[
        'records' => (int)($summary['records'] ?? 0),
        'learners' => (int)($summary['learners_with_state'] ?? 0),
        'notknown' => (int)($summary['not_taken_or_unknown'] ?? 0),
    ]));
    echo html_writer::end_tag('div');

    $table = new html_table();
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('placementdiagnosticstate', 'local_flwcupkp'),
        get_string('sourcecategory', 'local_flwcupkp'),
        get_string('currentlevel', 'local_flwcupkp'),
        get_string('assesseddimensions', 'local_flwcupkp'),
        get_string('createdevidence', 'local_flwcupkp'),
        get_string('time'),
    ];
    foreach (array_slice($classsummary['states'], 0, 100) as $row) {
        $table->data[] = [
            s((string)($row['userid'] ?? '')),
            local_flwcupkp_a2_badge((string)($row['policystate'] ?? '')),
            s((string)($row['sourcecategory'] ?? '')),
            s((string)($row['currentlevel'] ?? '')),
            s((string)count($row['assessed_dimensions'] ?? [])),
            s((string)count($row['evidenceids'] ?? [])),
            local_flwcupkp_a2_time($row['placementtime'] ?? 0),
        ];
    }
    if (empty($table->data)) {
        echo html_writer::tag('p', get_string('placementdiagnosticempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
    } else {
        echo html_writer::table($table);
    }
    echo html_writer::end_tag('section');
}

/**
 * Render reprocess audit history.
 *
 * @param array $history
 */
function local_flwcupkp_a2_render_history(array $history): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('placementdiagnosticreprocesshistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('placementdiagnosticreprocesshistoryintro', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']);
    if (!$history) {
        echo html_writer::tag('p', get_string('placementdiagnosticreprocesshistoryempty', 'local_flwcupkp'),
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
        $table->data[] = [
            userdate((int)$row['timecreated']),
            s((string)$row['action']),
            s(json_encode($row['details']['summary'] ?? $row['details'], JSON_UNESCAPED_SLASHES)),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Course options.
 *
 * @return array
 */
function local_flwcupkp_a2_course_options(): array {
    global $DB;
    $courses = $DB->get_records_menu('course', null, 'fullname ASC', 'id, fullname', 0, 500);
    unset($courses[SITEID]);
    return $courses;
}

/**
 * Learner options.
 *
 * @param int $courseid
 * @return array
 */
function local_flwcupkp_a2_learner_options(int $courseid): array {
    if ($courseid <= 0) {
        return [];
    }
    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return [];
    }
    $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email',
        'u.lastname ASC, u.firstname ASC', 0, 500, true);
    $options = [];
    foreach ($users as $user) {
        $options[(int)$user->id] = fullname($user) . ' (' . s($user->email) . ')';
    }
    return $options;
}

/**
 * Render compact state summary.
 *
 * @param array $states
 * @return string
 */
function local_flwcupkp_a2_state_summary(array $states): string {
    $parts = [];
    foreach ($states as $state => $count) {
        if ((int)$count > 0) {
            $parts[] = $state . ': ' . (int)$count;
        }
    }
    return $parts ? implode(' / ', $parts) : get_string('placementdiagnosticempty', 'local_flwcupkp');
}

/**
 * Render badge.
 *
 * @param string $status
 * @return string
 */
function local_flwcupkp_a2_badge(string $status): string {
    return html_writer::tag('span', s($status), [
        'class' => 'local-flwcupkp-foundation-badge local-flwcupkp-foundation-badge-' .
            preg_replace('/[^a-z0-9]+/', '', strtolower($status)),
    ]);
}

/**
 * Render an optional timestamp.
 *
 * @param int|null $time
 * @return string
 */
function local_flwcupkp_a2_time(?int $time): string {
    return $time ? s(userdate($time)) : '';
}
