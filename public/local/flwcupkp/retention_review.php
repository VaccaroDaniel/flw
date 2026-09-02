<?php
// Program 3 Gate E3 Retention / Retrieval / Review page.

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

$PAGE->set_url(new moodle_url('/local/flwcupkp/retention_review.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('retentionreviewe3', 'local_flwcupkp'));
$PAGE->set_heading(get_string('retentionreviewe3', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\retention_review_service::status($courseid, $unitcode, $frameworkid, $limit);
$result = null;
$learnerstate = null;
$classsummary = null;
if ($courseid > 0 || $userid > 0) {
    if ($action === 'apply') {
        $result = \local_flwcupkp\local\retention_review_service::apply_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $reason
        );
        $status = \local_flwcupkp\local\retention_review_service::status($courseid, $unitcode, $frameworkid, $limit);
    } else {
        $result = \local_flwcupkp\local\retention_review_service::preview_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit
        );
    }
    if ($userid > 0) {
        $learnerstate = \local_flwcupkp\local\retention_review_service::current_retention_state(
            $userid,
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    }
    if ($courseid > 0) {
        $classsummary = \local_flwcupkp\local\retention_review_service::class_summary(
            $courseid,
            $unitcode,
            $frameworkid,
            min(100, $limit)
        );
    }
}
$history = \local_flwcupkp\local\retention_review_service::recent_rebuild_history($courseid, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('retentionreviewe3', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('retentionreviewe3intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/mastery_state.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('masterystatee2', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/history_evidence.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
]), get_string('historyevidenceadapter', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_e3_render_status($status);
local_flwcupkp_e3_render_filters($courseid, $unitcode, $frameworkid, $userid, $limit, $reason);
if ($result) {
    local_flwcupkp_e3_render_result($result, $courseid, $unitcode, $frameworkid, $userid, $limit, $reason);
} else {
    echo $OUTPUT->notification(get_string('retentionreviewchoosecourse', 'local_flwcupkp'), 'info');
}
if ($learnerstate) {
    local_flwcupkp_e3_render_learner_state($learnerstate);
}
if ($classsummary) {
    local_flwcupkp_e3_render_class_summary($classsummary);
}
local_flwcupkp_e3_render_history($history);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render E3 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_e3_render_status(array $status): void {
    $summary = $status['criteria_summary'];
    $cache = $status['cache'];
    $cards = [
        get_string('retentionreviewe3status', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e3_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('retentionreviewe3criteria', 'local_flwcupkp') => [
            'value' => s($summary['passed'] . '/' . $summary['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $summary['failed']),
        ],
        get_string('retentioncache', 'local_flwcupkp') => [
            'value' => s((string)($cache['state_rows'] ?? 0)),
            'detail' => get_string('retentioncachedetail', 'local_flwcupkp', (object)[
                'ready' => (int)($cache['retention_ready_rows'] ?? 0),
                'missing' => (int)($cache['retention_missing_rows'] ?? 0),
            ]),
        ],
        get_string('reviewdue', 'local_flwcupkp') => [
            'value' => s((string)($cache['review_due_rows'] ?? 0)),
            'detail' => get_string('reviewduedetail', 'local_flwcupkp'),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_e3_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('retentionreviewe3nextgatedetail', 'local_flwcupkp'),
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
function local_flwcupkp_e3_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid, int $limit,
        string $reason): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/retention_review.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_e3_course_options(), 'courseid', $courseid,
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
            local_flwcupkp_e3_learner_options($courseid), 'userid', $userid, false),
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
    echo html_writer::link(new moodle_url('/local/flwcupkp/retention_review.php'), get_string('reset'),
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
function local_flwcupkp_e3_render_result(array $result, int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit, string $reason): void {
    $summary = $result['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('retentionrebuildpreview', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('retentionrebuildintro', 'local_flwcupkp', (object)[
        'mode' => $result['mode'],
        'learners' => $summary['learners'],
        'targets' => $summary['targets_seen'],
        'created' => $summary['created'],
        'changed' => $summary['changed'],
        'due' => $summary['review_due'],
        'relearning' => $summary['relearning'],
    ]));
    echo html_writer::end_tag('div');

    if ($result['mode'] === 'preview' && !empty($result['changes']) &&
            has_capability('local/flwcupkp:manageframeworks', context_system::instance())) {
        echo html_writer::start_tag('form', ['method' => 'post',
            'action' => new moodle_url('/local/flwcupkp/retention_review.php')]);
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
        echo html_writer::tag('button', get_string('applyretentionrebuild', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-success',
        ]);
        echo html_writer::end_tag('form');
    }

    if (empty($result['changes'])) {
        echo html_writer::tag('p', get_string('retentionnochanges', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('status'),
        get_string('learner', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('mastery', 'local_flwcupkp'),
        get_string('currentretention', 'local_flwcupkp'),
        get_string('proposedretention', 'local_flwcupkp'),
        get_string('confidence', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
    ];
    foreach (array_slice($result['changes'], 0, 100) as $row) {
        $table->data[] = [
            local_flwcupkp_e3_badge((string)($row['status'] ?? '')),
            s((string)($row['userid'] ?? '')),
            html_writer::tag('strong', s(($row['targettype'] ?? '') . ':' .
                ($row['target_externalid'] ?? $row['targetid']))) .
                html_writer::tag('div', s((string)($row['target_title'] ?? '')), ['class' => 'local-flwcupkp-muted']),
            s((string)($row['current_mastery_state'] ?? '') . ' ' .
                round((float)($row['current_mastery_score'] ?? 0), 5)),
            s((string)($row['current_retention_state'] ?? '')),
            local_flwcupkp_e3_badge((string)($row['proposed_retention_state'] ?? '')),
            s((string)round((float)($row['proposed_retention_confidence'] ?? 0), 5)),
            s((string)($row['reason'] ?? '')),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render selected learner retention rows.
 *
 * @param array $learnerstate
 */
function local_flwcupkp_e3_render_learner_state(array $learnerstate): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentretentionstate', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentretentionstateintro', 'local_flwcupkp', (object)[
        'states' => $learnerstate['summary']['states'],
        'due' => $learnerstate['summary']['review_due'],
        'uncertain' => $learnerstate['summary']['retention_uncertain'],
        'relearning' => $learnerstate['summary']['relearning'],
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
        get_string('retention', 'local_flwcupkp'),
        get_string('nextreview', 'local_flwcupkp'),
        get_string('lastretrieval', 'local_flwcupkp'),
        get_string('reviewquality', 'local_flwcupkp'),
        get_string('policyversion', 'local_flwcupkp'),
    ];
    foreach (array_slice($learnerstate['states'], 0, 100) as $row) {
        $table->data[] = [
            html_writer::tag('strong', s($row['target']['type'] . ':' . $row['target']['externalid'])) .
                html_writer::tag('div', s($row['target']['title']), ['class' => 'local-flwcupkp-muted']),
            s($row['mastery']['state'] . ' ' . round((float)$row['mastery']['score'], 5)),
            local_flwcupkp_e3_badge($row['retention']['state']) . ' ' .
                s((string)round((float)$row['retention']['confidence'], 5)),
            local_flwcupkp_e3_time($row['retention']['nextreview']),
            local_flwcupkp_e3_time($row['retention']['lastretrieval']),
            s((string)round((float)($row['review_quality']['best_successful_review_quality'] ?? 0), 5)),
            s($row['retention']['policyversion']),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render class retention summary.
 *
 * @param array $classsummary
 */
function local_flwcupkp_e3_render_class_summary(array $classsummary): void {
    $summary = $classsummary['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('classretentionsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classretentionsummaryintro', 'local_flwcupkp', (object)[
        'learners' => $summary['learners'],
        'states' => $summary['states'],
        'retained' => $summary['retained'],
        'due' => $summary['review_due'],
        'uncertain' => $summary['retention_uncertain'],
        'relearning' => $summary['relearning'],
    ]));
    echo html_writer::end_tag('div');

    $table = new html_table();
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('states', 'local_flwcupkp'),
        get_string('retained', 'local_flwcupkp'),
        get_string('reviewdue', 'local_flwcupkp'),
        get_string('retentionuncertain', 'local_flwcupkp'),
        get_string('relearning', 'local_flwcupkp'),
        get_string('status'),
    ];
    foreach (array_slice($classsummary['learners'], 0, 100) as $row) {
        $learner = (array)($row['summary'] ?? []);
        $table->data[] = [
            s((string)($row['userid'] ?? '')),
            s((string)($learner['states'] ?? 0)),
            s((string)($learner['retained'] ?? 0)),
            s((string)($learner['review_due'] ?? 0)),
            s((string)($learner['retention_uncertain'] ?? 0)),
            s((string)($learner['relearning'] ?? 0)),
            isset($row['status']) ? local_flwcupkp_e3_badge((string)$row['status']) : '',
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render rebuild history.
 *
 * @param array $history
 */
function local_flwcupkp_e3_render_history(array $history): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('retentionrebuildhistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('retentionrebuildhistoryintro', 'local_flwcupkp'),
        ['class' => 'local-flwcupkp-muted']);
    if (empty($history)) {
        echo html_writer::tag('p', get_string('retentionrebuildhistoryempty', 'local_flwcupkp'),
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
            s((string)$row['action']),
            s(json_encode($summary, JSON_UNESCAPED_SLASHES)),
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
function local_flwcupkp_e3_course_options(): array {
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
function local_flwcupkp_e3_learner_options(int $courseid): array {
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
 * Render badge.
 *
 * @param string $status
 * @return string
 */
function local_flwcupkp_e3_badge(string $status): string {
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
function local_flwcupkp_e3_time(?int $time): string {
    return $time ? s(userdate($time)) : '';
}
