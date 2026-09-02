<?php
// Program 3 Gate A3 Adaptive Decision Policy V1 page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);

require_login();
$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
require_capability('local/flwcupkp:viewreports', $context);

$limit = max(1, min(500, $limit));
$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
    'limit' => $limit,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/adaptive_decision.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('adaptivedecisiona3', 'local_flwcupkp'));
$PAGE->set_heading(get_string('adaptivedecisiona3', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\adaptive_decision_policy_service::status($courseid, $unitcode, $frameworkid, $limit);
$learnerdecision = null;
$classsummary = null;
if ($userid > 0) {
    $learnerdecision = \local_flwcupkp\local\adaptive_decision_policy_service::learner_decision(
        $userid,
        $courseid,
        $unitcode,
        $frameworkid,
        $limit
    );
}
if ($courseid > 0) {
    $classsummary = \local_flwcupkp\local\adaptive_decision_policy_service::class_summary(
        $courseid,
        $unitcode,
        $frameworkid,
        min(100, $limit)
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adaptivedecisiona3', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('adaptivedecisiona3intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/placement_diagnostic.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
]), get_string('placementdiagnostica2', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/mastery_state.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
]), get_string('masterystatee2', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/retention_review.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $userid,
]), get_string('retentionreviewe3', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_a3_render_status($status);
local_flwcupkp_a3_render_filters($courseid, $unitcode, $frameworkid, $userid, $limit);
local_flwcupkp_a3_render_policy($status['policy'] ?? []);
if ($learnerdecision) {
    local_flwcupkp_a3_render_learner_decision($learnerdecision);
} else if ($courseid <= 0) {
    echo $OUTPUT->notification(get_string('adaptivedecisionchoosecourse', 'local_flwcupkp'), 'info');
}
if ($classsummary) {
    local_flwcupkp_a3_render_class_summary($classsummary);
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render A3 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_a3_render_status(array $status): void {
    $criteria = $status['criteria_summary'];
    $summary = $status['summary'] ?? [];
    $urgency = $summary['urgency'] ?? ['urgent' => 0, 'attention' => 0];
    $cards = [
        get_string('adaptivedecisionstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a3_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('adaptivedecisioncriteria', 'local_flwcupkp') => [
            'value' => s($criteria['passed'] . '/' . $criteria['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $criteria['failed']),
        ],
        get_string('adaptivedecisionpolicy', 'local_flwcupkp') => [
            'value' => s((string)($status['policy']['version'] ?? '')),
            'detail' => get_string('adaptivedecisionpolicydetail', 'local_flwcupkp',
                count($status['policy']['decision_states'] ?? [])),
        ],
        get_string('adaptivedecisionattention', 'local_flwcupkp') => [
            'value' => s((string)((int)($urgency['urgent'] ?? 0) + (int)($urgency['attention'] ?? 0))),
            'detail' => get_string('adaptivedecisionattentiondetail', 'local_flwcupkp', (object)[
                'urgent' => (int)($urgency['urgent'] ?? 0),
                'attention' => (int)($urgency['attention'] ?? 0),
            ]),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a3_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('adaptivedecisionnextgatedetail', 'local_flwcupkp'),
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
 */
function local_flwcupkp_a3_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/adaptive_decision.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_a3_course_options(), 'courseid', $courseid,
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
            local_flwcupkp_a3_learner_options($courseid), 'userid', $userid, false),
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
    echo html_writer::tag('button', get_string('preview', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_decision.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render policy matrix.
 *
 * @param array $policy
 */
function local_flwcupkp_a3_render_policy(array $policy): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('adaptivedecisionpolicy', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('adaptivedecisionpolicyintro', 'local_flwcupkp'), [
        'class' => 'local-flwcupkp-muted',
    ]);
    $rows = '';
    foreach (($policy['decision_states'] ?? []) as $code => $state) {
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', s((string)$code)) .
            html_writer::tag('td', s((string)($state['action'] ?? ''))) .
            html_writer::tag('td', s((string)($state['rule'] ?? '')))
        );
    }
    echo html_writer::tag('table',
        html_writer::tag('thead', html_writer::tag('tr',
            html_writer::tag('th', get_string('code', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('action', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('rule', 'local_flwcupkp'))
        )) . html_writer::tag('tbody', $rows),
        ['class' => 'generaltable local-flwcupkp-table']
    );
    echo html_writer::end_tag('section');
}

/**
 * Render one learner decision.
 *
 * @param array $decision
 */
function local_flwcupkp_a3_render_learner_decision(array $decision): void {
    $selected = $decision['decision'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentadaptivedecision', 'local_flwcupkp'));
    echo html_writer::tag('p', s((string)$selected['rule']));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
    echo html_writer::tag('span', get_string('decision', 'local_flwcupkp'));
    echo html_writer::tag('strong', local_flwcupkp_a3_badge((string)$selected['code']));
    echo html_writer::tag('em', s((string)$selected['action']));
    echo html_writer::end_tag('div');
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
    echo html_writer::tag('span', get_string('nexttarget', 'local_flwcupkp'));
    echo html_writer::tag('strong', s(local_flwcupkp_a3_target_label($decision['next_target'] ?? null)));
    echo html_writer::tag('em', get_string('activityresolutionpending', 'local_flwcupkp'));
    echo html_writer::end_tag('div');
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
    echo html_writer::tag('span', get_string('destination', 'local_flwcupkp'));
    echo html_writer::tag('strong', s((string)($decision['destination']['title'] ?? '')));
    echo html_writer::tag('em', s(trim((string)($decision['destination']['cefr'] ?? '') . ' ' .
        (string)($decision['destination']['flwstage'] ?? ''))));
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');

    $rows = '';
    foreach (($decision['projected_roadmap'] ?? []) as $step) {
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', s((string)($step['step'] ?? ''))) .
            html_writer::tag('td', s((string)($step['code'] ?? ''))) .
            html_writer::tag('td', s((string)($step['action'] ?? ''))) .
            html_writer::tag('td', s(local_flwcupkp_a3_target_label($step['target'] ?? null)))
        );
    }
    echo html_writer::tag('table',
        html_writer::tag('thead', html_writer::tag('tr',
            html_writer::tag('th', '#') .
            html_writer::tag('th', get_string('code', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('action', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('target', 'local_flwcupkp'))
        )) . html_writer::tag('tbody', $rows),
        ['class' => 'generaltable local-flwcupkp-table']
    );
    echo html_writer::tag('p', s(get_string('decisionhash', 'local_flwcupkp') . ': ' .
        (string)($decision['explainability']['decision_hash'] ?? '')), ['class' => 'local-flwcupkp-muted']);
    echo html_writer::end_tag('section');
}

/**
 * Render class summary.
 *
 * @param array $classsummary
 */
function local_flwcupkp_a3_render_class_summary(array $classsummary): void {
    $summary = $classsummary['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('classadaptivedecisionsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classadaptivedecisionsummaryintro', 'local_flwcupkp', (object)[
        'learners' => (int)($summary['learners'] ?? 0),
        'urgent' => (int)($summary['urgency']['urgent'] ?? 0),
        'attention' => (int)($summary['urgency']['attention'] ?? 0),
        'next' => (int)($summary['urgency']['next'] ?? 0),
        'ready' => (int)($summary['urgency']['ready'] ?? 0),
    ]), ['class' => 'local-flwcupkp-muted']);

    $rows = '';
    foreach (array_slice($classsummary['learners'] ?? [], 0, 100) as $row) {
        $decision = $row['decision'] ?? [];
        $rows .= html_writer::tag('tr',
            html_writer::tag('td', s((string)($row['learner']['fullname'] ?? $row['userid'] ?? ''))) .
            html_writer::tag('td', local_flwcupkp_a3_badge((string)($decision['code'] ?? ''))) .
            html_writer::tag('td', s((string)($decision['urgency'] ?? ''))) .
            html_writer::tag('td', s(local_flwcupkp_a3_target_label($row['next_target'] ?? null))) .
            html_writer::tag('td', s((string)($row['destination']['title'] ?? ''))) .
            html_writer::tag('td', s((string)($row['decision_hash'] ?? '')))
        );
    }
    echo html_writer::tag('table',
        html_writer::tag('thead', html_writer::tag('tr',
            html_writer::tag('th', get_string('learner', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('decision', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('urgency', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('nexttarget', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('destination', 'local_flwcupkp')) .
            html_writer::tag('th', get_string('decisionhash', 'local_flwcupkp'))
        )) . html_writer::tag('tbody', $rows),
        ['class' => 'generaltable local-flwcupkp-table']
    );
    echo html_writer::end_tag('section');
}

/**
 * Render a badge.
 *
 * @param string $value
 * @return string
 */
function local_flwcupkp_a3_badge(string $value): string {
    return html_writer::span(s($value), 'badge badge-light local-flwcupkp-foundation-badge');
}

/**
 * Human label for a target.
 *
 * @param array|null $target
 * @return string
 */
function local_flwcupkp_a3_target_label(?array $target): string {
    if (!$target) {
        return get_string('none', 'local_flwcupkp');
    }
    $label = (string)($target['externalid'] ?? '');
    if ($label === '') {
        $label = (string)($target['title'] ?? '');
    }
    if ($label === '') {
        $label = (string)($target['type'] ?? '') . ':' . (string)($target['id'] ?? '');
    }
    return strtoupper((string)($target['type'] ?? '')) . ' ' . $label;
}

/**
 * Course options.
 *
 * @return array
 */
function local_flwcupkp_a3_course_options(): array {
    global $DB;

    $options = [];
    foreach ($DB->get_records('course', null, 'fullname ASC', 'id, fullname, shortname', 0, 300) as $course) {
        if ((int)$course->id === SITEID) {
            continue;
        }
        $options[(int)$course->id] = format_string($course->fullname) . ' (' . format_string($course->shortname) . ')';
    }
    return $options;
}

/**
 * Learner options for a course.
 *
 * @param int $courseid
 * @return array
 */
function local_flwcupkp_a3_learner_options(int $courseid): array {
    $context = $courseid > 0 ? context_course::instance($courseid, IGNORE_MISSING) : null;
    if (!$context) {
        return [];
    }
    $options = [];
    foreach (get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email',
            'u.lastname ASC, u.firstname ASC, u.id ASC', 0, 300, true) as $user) {
        $options[(int)$user->id] = fullname($user) . ' (' . s($user->email) . ')';
    }
    return $options;
}
