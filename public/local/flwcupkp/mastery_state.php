<?php
// Program 3 Gate E2 Mastery + Confidence + Current Learner State page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);
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
$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
    'limit' => $limit,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/mastery_state.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('masterystatee2', 'local_flwcupkp'));
$PAGE->set_heading(get_string('masterystatee2', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\mastery_state_service::status($courseid, $unitcode, $frameworkid, $limit);
$result = null;
$learnerstate = null;
$classsummary = null;
if ($courseid > 0 || $userid > 0) {
    if ($action === 'apply') {
        $result = \local_flwcupkp\local\mastery_state_service::apply_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $reason
        );
        $status = \local_flwcupkp\local\mastery_state_service::status($courseid, $unitcode, $frameworkid, $limit);
    } else {
        $result = \local_flwcupkp\local\mastery_state_service::preview_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit
        );
    }
    if ($userid > 0) {
        $learnerstate = \local_flwcupkp\local\mastery_state_service::current_learner_state(
            $userid,
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    }
    if ($courseid > 0) {
        $classsummary = \local_flwcupkp\local\mastery_state_service::class_summary(
            $courseid,
            $unitcode,
            $frameworkid,
            min(100, $limit)
        );
    }
}
$history = \local_flwcupkp\local\mastery_state_service::recent_rebuild_history($courseid, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('masterystatee2', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('masterystatee2intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/history_evidence.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('historyevidenceadapter', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/management.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('cm4management', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_e2_render_status($status);
local_flwcupkp_e2_render_filters($courseid, $unitcode, $frameworkid, $userid, $limit, $reason);
if ($result) {
    local_flwcupkp_e2_render_result($result, $courseid, $unitcode, $frameworkid, $userid, $limit, $reason);
} else {
    echo $OUTPUT->notification(get_string('masterystatechoosecourse', 'local_flwcupkp'), 'info');
}
if ($learnerstate) {
    local_flwcupkp_e2_render_learner_state($learnerstate);
}
if ($classsummary) {
    local_flwcupkp_e2_render_class_summary($classsummary);
}
local_flwcupkp_e2_render_history($history);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render E2 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_e2_render_status(array $status): void {
    $summary = $status['criteria_summary'];
    $cache = $status['cache'];
    $cards = [
        get_string('masterystatee2status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e2_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('masterystatee2criteria', 'local_flwcupkp') => [
            'value' => s($summary['passed'] . '/' . $summary['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $summary['failed']),
        ],
        get_string('masterystatecache', 'local_flwcupkp') => [
            'value' => s((string)($cache['state_rows'] ?? 0)),
            'detail' => get_string('masterystatecachedetail', 'local_flwcupkp', (object)[
                'ready' => (int)($cache['metadata_ready_rows'] ?? 0),
                'missing' => (int)($cache['metadata_missing_rows'] ?? 0),
            ]),
        ],
        get_string('historyv1evidence', 'local_flwcupkp') => [
            'value' => s((string)($cache['history_v1_evidence_rows'] ?? 0)),
            'detail' => get_string('masterystatehistorydetail', 'local_flwcupkp'),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e2_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('masterystatee2nextgatedetail', 'local_flwcupkp'),
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
 * @param string $reason
 */
function local_flwcupkp_e2_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid, int $limit,
        string $reason): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/mastery_state.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_e2_course_options(), 'courseid', $courseid,
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
            local_flwcupkp_e2_learner_options($courseid), 'userid', $userid, false),
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
    echo html_writer::link(new moodle_url('/local/flwcupkp/mastery_state.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render rebuild result.
 *
 * @param array $result
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $userid
 * @param int $limit
 * @param string $reason
 */
function local_flwcupkp_e2_render_result(array $result, int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit, string $reason): void {
    $summary = $result['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('masterystaterebuildpreview', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('masterystaterebuildintro', 'local_flwcupkp', (object)[
        'mode' => $result['mode'],
        'learners' => $summary['learners'],
        'targets' => $summary['targets_seen'],
        'created' => $summary['created'],
        'changed' => $summary['changed'],
        'metadata' => $summary['metadata_refreshed'],
        'manual' => $summary['manual_overrides'],
    ]));
    echo html_writer::end_tag('div');

    if ($result['mode'] === 'preview' && !empty($result['changes']) &&
            has_capability('local/flwcupkp:manageframeworks', context_system::instance())) {
        echo html_writer::start_tag('form', ['method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/mastery_state.php')]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frameworkid', 'value' => $frameworkid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'limit', 'value' => max(1, min(500, $limit))]);
        echo html_writer::tag('label', get_string('reason', 'local_flwcupkp') .
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'reason',
                'value' => $reason,
                'maxlength' => 160,
                'class' => 'form-control',
            ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-reason']);
        echo html_writer::tag('button', get_string('applymasterystaterebuild', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-success',
        ]);
        echo html_writer::end_tag('form');
    }

    if (empty($result['changes'])) {
        echo html_writer::tag('p', get_string('masterystatenochanges', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('status'),
        get_string('learner', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('currentvalue', 'local_flwcupkp'),
        get_string('proposedvalue', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
    ];
    foreach (array_slice($result['changes'], 0, 100) as $row) {
        $table->data[] = [
            local_flwcupkp_e2_badge((string)($row['status'] ?? '')),
            s((string)($row['userid'] ?? '')),
            html_writer::tag('strong', s(($row['targettype'] ?? '') . ':' . ($row['target_externalid'] ?? $row['targetid']))) .
                html_writer::tag('div', s((string)($row['target_title'] ?? '')), ['class' => 'local-flwcupkp-muted']),
            s((string)($row['current_state'] ?? '') . ' ' . (string)($row['current_score'] ?? '')),
            s((string)($row['proposed_state'] ?? '') . ' ' . round((float)($row['proposed_score'] ?? 0), 5)),
            s((string)round((float)($row['proposed_confidence'] ?? 0), 5)),
            s((string)($row['reason'] ?? '')),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render selected learner current state rows.
 *
 * @param array $learnerstate
 */
function local_flwcupkp_e2_render_learner_state(array $learnerstate): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentlearnerstate', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentlearnerstateintro', 'local_flwcupkp', (object)[
        'states' => $learnerstate['summary']['states'],
        'strong' => $learnerstate['summary']['strong_states'],
        'stale' => $learnerstate['summary']['stale_or_missing_cache'],
    ]));
    echo html_writer::end_tag('div');

    if (empty($learnerstate['states'])) {
        echo html_writer::tag('p', get_string('noprogressrows', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('target', 'local_flwcupkp'),
        get_string('mastery', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
        get_string('state'),
        get_string('trend', 'local_flwcupkp'),
        get_string('evidence', 'local_flwcupkp'),
        get_string('policyversion', 'local_flwcupkp'),
    ];
    foreach (array_slice($learnerstate['states'], 0, 100) as $row) {
        $table->data[] = [
            html_writer::tag('strong', s($row['target']['type'] . ':' . $row['target']['externalid'])) .
                html_writer::tag('div', s($row['target']['title']), ['class' => 'local-flwcupkp-muted']),
            s($row['mastery']['state'] . ' ' . round((float)$row['mastery']['score'], 5)),
            local_flwcupkp_e2_badge($row['confidence']['label']) . ' ' .
                s((string)round((float)$row['confidence']['score'], 5)),
            local_flwcupkp_e2_badge($row['status']),
            s($row['trend']),
            s((string)$row['evidence']['count'] . ' / H1 ' . (string)$row['evidence']['history_v1']),
            html_writer::tag('code', s($row['policyversion'])),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render class summary.
 *
 * @param array $classsummary
 */
function local_flwcupkp_e2_render_class_summary(array $classsummary): void {
    $summary = $classsummary['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('classcurrentstatesummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classcurrentstatesummaryintro', 'local_flwcupkp', (object)[
        'learners' => $summary['learners'],
        'states' => $summary['state_rows'],
        'strong' => $summary['strong_states'],
        'low' => $summary['low_confidence'],
        'stale' => $summary['stale_or_missing_cache'],
    ]));
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('section');
}

/**
 * Render rebuild audit history.
 *
 * @param array $history
 */
function local_flwcupkp_e2_render_history(array $history): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('masterystaterebuildhistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('masterystaterebuildhistoryintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if (!$history) {
        echo html_writer::tag('p', get_string('masterystaterebuildhistoryempty', 'local_flwcupkp'),
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
 * Render a compact badge.
 *
 * @param string $value
 * @return string
 */
function local_flwcupkp_e2_badge(string $value): string {
    $class = 'local-flwcupkp-status-' . preg_replace('/[^a-z0-9]+/', '-',
        strtolower((string)$value));
    return html_writer::tag('span', s($value), ['class' => 'badge ' . $class]);
}

/**
 * Course options for mapped C-UP-KP courses.
 *
 * @return array
 */
function local_flwcupkp_e2_course_options(): array {
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

/**
 * Learner select options.
 *
 * @param int $courseid
 * @return array
 */
function local_flwcupkp_e2_learner_options(int $courseid): array {
    if ($courseid <= 0) {
        return [];
    }
    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return [];
    }
    $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email',
        'u.lastname ASC, u.firstname ASC', 0, 200, true);
    $options = [];
    foreach ($users as $user) {
        $options[(int)$user->id] = fullname($user) . ' (' . s($user->email) . ')';
    }
    return $options;
}
