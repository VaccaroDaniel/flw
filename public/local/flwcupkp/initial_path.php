<?php
// Program 3 Gate A4 Goal-Gap + Initial Personalized Path page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 100, PARAM_INT);

$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
require_login($course ?: null);

global $USER;

$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
$canreport = has_capability('local/flwcupkp:viewreports', $context);
$canviewpath = has_capability('local/flwcupkp:viewlearnerpath', $context);
if (!$canreport && !$canviewpath) {
    require_capability('local/flwcupkp:viewlearnerpath', $context);
}

$targetuserid = $userid;
if (!$canreport) {
    $targetuserid = (int)$USER->id;
}
$limit = max(1, min(500, $limit));

if ($targetuserid > 0 && $targetuserid !== (int)$USER->id && !$canreport) {
    throw new required_capability_exception($context, 'local/flwcupkp:viewreports', 'nopermissions', '');
}

$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
    'limit' => $limit,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/initial_path.php', $baseparams));
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('initialpatha4', 'local_flwcupkp'));
$PAGE->set_heading(get_string('initialpatha4', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\goal_gap_path_service::status($courseid, $unitcode, $frameworkid, $limit);
$path = null;
$patherror = null;
if ($targetuserid > 0) {
    try {
        $path = \local_flwcupkp\local\goal_gap_path_service::learner_path(
            $targetuserid,
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } catch (Throwable $e) {
        $patherror = $e->getMessage();
    }
}
$classsummary = $courseid > 0 && $canreport ?
    \local_flwcupkp\local\goal_gap_path_service::class_summary($courseid, $unitcode, $frameworkid,
        min(100, $limit)) : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('initialpatha4', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('initialpatha4intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_decision.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('adaptivedecisiona3', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/learning_goal.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid > 0 ? $targetuserid : (int)$USER->id,
]), get_string('learninggoala1', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_a4_render_status($status);
local_flwcupkp_a4_render_filters($courseid, $unitcode, $frameworkid, $targetuserid, $limit, $canreport);
if ($patherror !== null) {
    echo $OUTPUT->notification(s($patherror), 'error');
}
if ($path) {
    local_flwcupkp_a4_render_path($path);
} else if (!$canreport) {
    echo $OUTPUT->notification(get_string('initialpathchooselearner', 'local_flwcupkp'), 'info');
}
if ($classsummary) {
    local_flwcupkp_a4_render_class_summary($classsummary);
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render A4 status cards.
 *
 * @param array $status
 */
function local_flwcupkp_a4_render_status(array $status): void {
    $criteria = $status['criteria_summary'];
    $summary = $status['summary'] ?? [];
    $cards = [
        get_string('initialpathstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('initialpathcriteria', 'local_flwcupkp') => [
            'value' => s($criteria['passed'] . '/' . $criteria['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $criteria['failed']),
        ],
        get_string('initialpathpolicy', 'local_flwcupkp') => [
            'value' => s((string)($status['policy']['version'] ?? '')),
            'detail' => get_string('initialpathpolicydetail', 'local_flwcupkp',
                count($status['policy']['gap_dimensions'] ?? [])),
        ],
        get_string('initialpathclassgaps', 'local_flwcupkp') => [
            'value' => s((string)((int)($summary['missing_kp'] ?? 0) + (int)($summary['missing_up'] ?? 0) +
                (int)($summary['missing_competency'] ?? 0) + (int)($summary['blocked_kp'] ?? 0) +
                (int)($summary['blocked_up'] ?? 0) + (int)($summary['blocked_competency'] ?? 0))),
            'detail' => get_string('initialpathclassgapsdetail', 'local_flwcupkp', (object)[
                'missing' => (int)($summary['missing_kp'] ?? 0) + (int)($summary['missing_up'] ?? 0) +
                    (int)($summary['missing_competency'] ?? 0),
                'blocked' => (int)($summary['blocked_kp'] ?? 0) + (int)($summary['blocked_up'] ?? 0) +
                    (int)($summary['blocked_competency'] ?? 0),
            ]),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('initialpathnextgatedetail', 'local_flwcupkp'),
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
 * @param bool $canreport
 */
function local_flwcupkp_a4_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit, bool $canreport): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/initial_path.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_a4_course_options(), 'courseid', $courseid,
            false), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('unit', 'local_flwcupkp') .
        html_writer::select(['' => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::unit_options(), 'unitcode', $unitcode, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('framework', 'local_flwcupkp') .
        html_writer::select([0 => get_string('all', 'local_flwcupkp')] +
            \local_flwcupkp\local\curriculum_manager::framework_options(), 'frameworkid', $frameworkid, false),
        ['class' => 'local-flwcupkp-filter']);
    if ($canreport) {
        echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
            html_writer::select([0 => get_string('alllearners', 'local_flwcupkp')] +
                local_flwcupkp_a4_learner_options($courseid), 'userid', $userid, false),
            ['class' => 'local-flwcupkp-filter']);
    } else {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
    }
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
    echo html_writer::link(new moodle_url('/local/flwcupkp/initial_path.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render a learner initial path.
 *
 * @param array $path
 */
function local_flwcupkp_a4_render_path(array $path): void {
    $summary = $path['goal_gap_analysis']['summary'] ?? [];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentinitialpath', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentinitialpathintro', 'local_flwcupkp', (object)[
        'missing' => (int)($summary['missing_total'] ?? 0),
        'blocked' => (int)($summary['blocked_total'] ?? 0),
        'satisfied' => (int)($summary['satisfied_total'] ?? 0),
    ]));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid']);
    foreach ([
        get_string('pathstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4_badge((string)($path['path_status'] ?? 'unknown')),
            'detail' => get_string('activityresolutionpending', 'local_flwcupkp'),
        ],
        get_string('nexttarget', 'local_flwcupkp') => [
            'value' => s(local_flwcupkp_a4_target_label($path['next_target'] ?? null)),
            'detail' => get_string('initialpathnexttargetdetail', 'local_flwcupkp'),
        ],
        get_string('destination', 'local_flwcupkp') => [
            'value' => s((string)($path['destination']['title'] ?? '')),
            'detail' => s(trim((string)($path['destination']['cefr'] ?? '') . ' ' .
                (string)($path['destination']['flwstage'] ?? ''))),
        ],
        get_string('pathhash', 'local_flwcupkp') => [
            'value' => s(substr((string)($path['explainability']['path_hash'] ?? ''), 0, 12)),
            'detail' => s(substr((string)($path['explainability']['adaptive_decision_hash'] ?? ''), 0, 12)),
        ],
    ] as $label => $card) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s($label));
        echo html_writer::tag('strong', $card['value']);
        echo html_writer::tag('em', $card['detail']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    local_flwcupkp_a4_render_roadmap($path['projected_roadmap'] ?? []);
    local_flwcupkp_a4_render_candidates($path['candidate_next_targets'] ?? []);
    local_flwcupkp_a4_render_gap_tables($path['goal_gap_analysis'] ?? []);
    echo html_writer::end_tag('section');
}

/**
 * Render roadmap rows.
 *
 * @param array $roadmap
 */
function local_flwcupkp_a4_render_roadmap(array $roadmap): void {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('step', 'local_flwcupkp'),
        get_string('stage', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
    ];
    foreach ($roadmap as $step) {
        $table->data[] = [
            (int)($step['step'] ?? 0),
            s((string)($step['stage'] ?? '')),
            s((string)($step['action'] ?? '')),
            s(local_flwcupkp_a4_target_label($step['target'] ?? null)),
            s((string)($step['reason'] ?? ($step['code'] ?? ''))),
        ];
    }
    echo html_writer::tag('h4', get_string('projectedroadmap', 'local_flwcupkp'));
    echo html_writer::table($table);
}

/**
 * Render candidate next targets.
 *
 * @param array $candidates
 */
function local_flwcupkp_a4_render_candidates(array $candidates): void {
    echo html_writer::tag('h4', get_string('candidatenexttargets', 'local_flwcupkp'));
    if (!$candidates) {
        echo html_writer::tag('p', get_string('initialpathnocandidates', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted',
        ]);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('rank', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('reason', 'local_flwcupkp'),
    ];
    foreach ($candidates as $candidate) {
        $table->data[] = [
            (int)($candidate['rank'] ?? 0),
            s((string)($candidate['action'] ?? '')),
            s(local_flwcupkp_a4_target_label($candidate['target'] ?? null)),
            s((string)($candidate['reason'] ?? '')),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render gap buckets.
 *
 * @param array $analysis
 */
function local_flwcupkp_a4_render_gap_tables(array $analysis): void {
    foreach ([
        'missing' => get_string('missingtargets', 'local_flwcupkp'),
        'blocked_by_prerequisite' => get_string('blockedbyprerequisite', 'local_flwcupkp'),
        'satisfied' => get_string('satisfiedtargets', 'local_flwcupkp'),
    ] as $bucket => $label) {
        $rows = local_flwcupkp_a4_gap_rows($analysis[$bucket] ?? []);
        echo html_writer::tag('h4', $label);
        if (!$rows) {
            echo html_writer::tag('p', get_string('none', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
            continue;
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable local-flwcupkp-table';
        $table->head = [
            get_string('type', 'local_flwcupkp'),
            get_string('target', 'local_flwcupkp'),
            get_string('mastery', 'local_flwcupkp'),
            get_string('confidence', 'local_flwcupkp'),
            get_string('retention', 'local_flwcupkp'),
            get_string('details', 'local_flwcupkp'),
        ];
        foreach ($rows as $row) {
            $table->data[] = [
                s((string)$row['target']['type']),
                s(local_flwcupkp_a4_target_label($row['target'])),
                s((string)($row['state']['mastery_state'] ?? '') . ' ' .
                    format_float((float)($row['state']['mastery_score'] ?? 0), 2)),
                s(format_float((float)($row['state']['confidence'] ?? 0), 2)),
                s((string)($row['retention']['state'] ?? '')),
                s(implode(' ', array_slice($row['reasons'] ?? [], 0, 2))),
            ];
        }
        echo html_writer::table($table);
    }
}

/**
 * Flatten gap bucket rows.
 *
 * @param array $bucket
 * @return array
 */
function local_flwcupkp_a4_gap_rows(array $bucket): array {
    $rows = [];
    foreach (['kp', 'up', 'competency'] as $type) {
        foreach (($bucket[$type] ?? []) as $row) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Render class summary.
 *
 * @param array $summary
 */
function local_flwcupkp_a4_render_class_summary(array $summary): void {
    $data = $summary['summary'] ?? [];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('classinitialpathsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classinitialpathsummaryintro', 'local_flwcupkp', (object)[
        'learners' => (int)($data['learners'] ?? 0),
        'ready' => (int)($data['ready_to_work'] ?? 0),
        'blocked' => (int)($data['blocked_by_prerequisite'] ?? 0),
        'destination' => (int)($data['destination_ready'] ?? 0),
    ]), ['class' => 'local-flwcupkp-muted']);

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('pathstatus', 'local_flwcupkp'),
        get_string('nexttarget', 'local_flwcupkp'),
        get_string('missingtargets', 'local_flwcupkp'),
        get_string('blockedbyprerequisite', 'local_flwcupkp'),
    ];
    foreach ($summary['learners'] ?? [] as $learner) {
        $row = $learner['summary'] ?? [];
        $table->data[] = [
            s((string)($learner['learner']['fullname'] ?? $learner['userid'])),
            local_flwcupkp_a4_badge((string)($learner['path_status'] ?? '')),
            s(local_flwcupkp_a4_target_label($learner['next_target'] ?? null)),
            s((string)($row['missing_total'] ?? 0)),
            s((string)($row['blocked_total'] ?? 0)),
        ];
    }
    if ($table->data) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('initialpathnoclassrows', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted',
        ]);
    }
    echo html_writer::end_tag('section');
}

/**
 * Course options.
 *
 * @return array
 */
function local_flwcupkp_a4_course_options(): array {
    $courses = get_courses('all', 'c.fullname ASC', 'c.id,c.fullname,c.shortname');
    $options = [];
    foreach ($courses as $course) {
        if ((int)$course->id === SITEID) {
            continue;
        }
        $context = context_course::instance((int)$course->id, IGNORE_MISSING);
        if (!$context || (!has_capability('local/flwcupkp:viewreports', $context) &&
                !has_capability('local/flwcupkp:viewlearnerpath', $context))) {
            continue;
        }
        $options[(int)$course->id] = format_string($course->fullname);
    }
    return $options;
}

/**
 * Learner options.
 *
 * @param int $courseid
 * @return array
 */
function local_flwcupkp_a4_learner_options(int $courseid): array {
    if ($courseid <= 0) {
        return [];
    }
    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return [];
    }
    $users = get_enrolled_users($context, '', 0,
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
        'u.lastname ASC, u.firstname ASC, u.id ASC', 0, 300, true);
    $options = [];
    foreach ($users as $user) {
        $options[(int)$user->id] = fullname($user);
    }
    return $options;
}

/**
 * Badge helper.
 *
 * @param string $label
 * @return string
 */
function local_flwcupkp_a4_badge(string $label): string {
    $class = 'local-flwcupkp-badge';
    $lower = strtolower($label);
    if (in_array($lower, ['ready', 'destination_ready', 'satisfied'], true)) {
        $class .= ' local-flwcupkp-badge-good';
    } else if (strpos($lower, 'blocked') !== false || strpos($lower, 'failed') !== false) {
        $class .= ' local-flwcupkp-badge-warn';
    }
    return html_writer::tag('span', s($label), ['class' => $class]);
}

/**
 * Human-readable target label.
 *
 * @param array|null $target
 * @return string
 */
function local_flwcupkp_a4_target_label(?array $target): string {
    if (!$target) {
        return get_string('none', 'local_flwcupkp');
    }
    $parts = array_filter([
        strtoupper((string)($target['type'] ?? '')),
        (string)($target['externalid'] ?? ''),
        (string)($target['title'] ?? ''),
    ]);
    return $parts ? implode(' - ', $parts) : get_string('none', 'local_flwcupkp');
}
