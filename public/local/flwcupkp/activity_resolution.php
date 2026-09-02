<?php
// Program 3 Gate A4B Candidate Eligibility + Activity Resolution page.

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

$PAGE->set_url(new moodle_url('/local/flwcupkp/activity_resolution.php', $baseparams));
$PAGE->set_context($context);
if ($course) {
    $PAGE->set_course($course);
}
$PAGE->set_title(get_string('activityresolutiona4b', 'local_flwcupkp'));
$PAGE->set_heading(get_string('activityresolutiona4b', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\candidate_activity_resolution_service::status($courseid, $unitcode, $frameworkid,
    $limit);
$resolution = null;
$resolutionerror = null;
if ($targetuserid > 0) {
    try {
        $resolution = \local_flwcupkp\local\candidate_activity_resolution_service::learner_resolution(
            $targetuserid,
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } catch (Throwable $e) {
        $resolutionerror = $e->getMessage();
    }
}
$classsummary = $courseid > 0 && $canreport ?
    \local_flwcupkp\local\candidate_activity_resolution_service::class_summary($courseid, $unitcode,
        $frameworkid, min(100, $limit)) : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityresolutiona4b', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('activityresolutiona4bintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/initial_path.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('initialpatha4', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/adaptive_decision.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('adaptivedecisiona3', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_a4b_render_status($status);
local_flwcupkp_a4b_render_filters($courseid, $unitcode, $frameworkid, $targetuserid, $limit, $canreport);
if ($resolutionerror !== null) {
    echo $OUTPUT->notification(s($resolutionerror), 'error');
}
if ($resolution) {
    local_flwcupkp_a4b_render_resolution($resolution);
} else if (!$canreport) {
    echo $OUTPUT->notification(get_string('activityresolutionchooselearner', 'local_flwcupkp'), 'info');
}
if ($classsummary) {
    local_flwcupkp_a4b_render_class_summary($classsummary);
}
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Render A4B status cards.
 *
 * @param array $status
 */
function local_flwcupkp_a4b_render_status(array $status): void {
    $criteria = $status['criteria_summary'];
    $summary = $status['summary'] ?? [];
    $cards = [
        get_string('activityresolutionstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4b_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('activityresolutioncriteria', 'local_flwcupkp') => [
            'value' => s($criteria['passed'] . '/' . $criteria['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $criteria['failed']),
        ],
        get_string('activityresolutionpolicy', 'local_flwcupkp') => [
            'value' => s((string)($status['policy']['version'] ?? '')),
            'detail' => get_string('activityresolutionpolicydetail', 'local_flwcupkp',
                count($status['policy']['pipeline'] ?? [])),
        ],
        get_string('eligibleactivities', 'local_flwcupkp') => [
            'value' => s((string)($summary['eligible_activities'] ?? 0)),
            'detail' => get_string('activityresolutionclassdetail', 'local_flwcupkp', (object)[
                'next' => (int)($summary['next_activity_ready'] ?? 0),
                'diagnostic' => (int)($summary['diagnostic_required'] ?? 0),
            ]),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4b_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('activityresolutionnextgatedetail', 'local_flwcupkp'),
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
function local_flwcupkp_a4b_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid,
        int $limit, bool $canreport): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/activity_resolution.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_a4b_course_options(), 'courseid',
            $courseid, false), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
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
                local_flwcupkp_a4b_learner_options($courseid), 'userid', $userid, false),
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
    echo html_writer::link(new moodle_url('/local/flwcupkp/activity_resolution.php'), get_string('reset'),
        ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
}

/**
 * Render one learner's resolution.
 *
 * @param array $resolution
 */
function local_flwcupkp_a4b_render_resolution(array $resolution): void {
    $next = $resolution['next_activity'] ?? null;
    $diagnostic = $resolution['diagnostic'] ?? [];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentactivityresolution', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentactivityresolutionintro', 'local_flwcupkp', (object)[
        'eligible' => (int)($resolution['summary']['eligible_activities'] ?? 0),
        'ineligible' => (int)($resolution['summary']['ineligible_activities'] ?? 0),
    ]));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-cardgrid']);
    foreach ([
        get_string('pathstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4b_badge((string)($resolution['resolution_status'] ?? 'unknown')),
            'detail' => get_string('activityresolutionhardinvariant', 'local_flwcupkp'),
        ],
        get_string('nextactivity', 'local_flwcupkp') => [
            'value' => $next && !empty($next['url']) ?
                html_writer::link($next['url'], s((string)$next['title'])) :
                s(local_flwcupkp_a4b_activity_label($next)),
            'detail' => $next ? s((string)($next['modname'] ?? '') . ' #' . (int)($next['cmid'] ?? 0)) :
                s((string)($diagnostic['code'] ?? '')),
        ],
        get_string('nexttarget', 'local_flwcupkp') => [
            'value' => s(local_flwcupkp_a4b_target_label($resolution['next_target'] ?? null)),
            'detail' => get_string('activityresolutionnexttargetdetail', 'local_flwcupkp'),
        ],
        get_string('fallback', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a4b_badge(!empty($resolution['fallback']['used']) ? 'used' : 'not used'),
            'detail' => s((string)($resolution['fallback']['status'] ?? '')),
        ],
        get_string('resolutionhash', 'local_flwcupkp') => [
            'value' => s(substr((string)($resolution['explainability']['resolution_hash'] ?? ''), 0, 12)),
            'detail' => s(substr((string)($resolution['explainability']['a4_path_hash'] ?? ''), 0, 12)),
        ],
    ] as $label => $card) {
        echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-card']);
        echo html_writer::tag('span', s($label));
        echo html_writer::tag('strong', $card['value']);
        echo html_writer::tag('em', $card['detail']);
        echo html_writer::end_tag('div');
    }
    echo html_writer::end_tag('div');

    if (!empty($diagnostic['required'])) {
        echo html_writer::tag('div', s(($diagnostic['code'] ?? '') . ': ' . ($diagnostic['message'] ?? '')), [
            'class' => 'alert alert-warning',
        ]);
    }

    local_flwcupkp_a4b_render_target_resolutions($resolution['target_resolutions'] ?? []);
    local_flwcupkp_a4b_render_activities($resolution['eligible_activities'] ?? [], true);
    local_flwcupkp_a4b_render_activities($resolution['ineligible_activities'] ?? [], false);
    local_flwcupkp_a4b_render_roadmap($resolution['projected_roadmap'] ?? []);
    echo html_writer::end_tag('section');
}

/**
 * Render candidate target resolution rows.
 *
 * @param array $resolutions
 */
function local_flwcupkp_a4b_render_target_resolutions(array $resolutions): void {
    echo html_writer::tag('h4', get_string('targetresolutions', 'local_flwcupkp'));
    if (!$resolutions) {
        echo html_writer::tag('p', get_string('activityresolutionnotargets', 'local_flwcupkp'), [
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
        get_string('eligibleactivities', 'local_flwcupkp'),
        get_string('details', 'local_flwcupkp'),
    ];
    foreach ($resolutions as $row) {
        $table->data[] = [
            (int)($row['candidate_rank'] ?? 0),
            s((string)($row['candidate_action'] ?? '')),
            s(local_flwcupkp_a4b_target_label($row['target'] ?? null)),
            local_flwcupkp_a4b_badge((string)count($row['eligible_activities'] ?? [])),
            s(implode(', ', array_slice($row['blocking_codes'] ?? [], 0, 4))),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render activity rows.
 *
 * @param array $activities
 * @param bool $eligible
 */
function local_flwcupkp_a4b_render_activities(array $activities, bool $eligible): void {
    echo html_writer::tag('h4', get_string($eligible ? 'eligibleactivities' : 'ineligibleactivities',
        'local_flwcupkp'));
    if (!$activities) {
        echo html_writer::tag('p', get_string($eligible ? 'activityresolutionnoeligible' :
            'activityresolutionnoineligible', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('activity', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('role', 'local_flwcupkp'),
        get_string('moodleavailability', 'local_flwcupkp'),
        get_string('details', 'local_flwcupkp'),
    ];
    foreach ($activities as $activity) {
        $label = local_flwcupkp_a4b_activity_label($activity);
        $table->data[] = [
            !empty($activity['url']) && $eligible ?
                html_writer::link($activity['url'], s($label)) :
                s($label),
            s(local_flwcupkp_a4b_target_label($activity['target'] ?? null)),
            s((string)($activity['role'] ?? '')),
            local_flwcupkp_a4b_badge((string)($activity['status'] ?? 'unknown')),
            $eligible ? s(local_flwcupkp_a4b_check_summary($activity['checks'] ?? [])) :
                s(local_flwcupkp_a4b_blocking_summary($activity)),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render enriched roadmap.
 *
 * @param array $roadmap
 */
function local_flwcupkp_a4b_render_roadmap(array $roadmap): void {
    echo html_writer::tag('h4', get_string('projectedroadmap', 'local_flwcupkp'));
    if (!$roadmap) {
        echo html_writer::tag('p', get_string('none', 'local_flwcupkp'), ['class' => 'local-flwcupkp-muted']);
        return;
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('step', 'local_flwcupkp'),
        get_string('stage', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('activity', 'local_flwcupkp'),
        get_string('details', 'local_flwcupkp'),
    ];
    foreach ($roadmap as $step) {
        $activity = $step['activity'] ?? null;
        $table->data[] = [
            (int)($step['step'] ?? 0),
            s((string)($step['stage'] ?? '')),
            s(local_flwcupkp_a4b_target_label($step['target'] ?? null)),
            $activity && !empty($activity['url']) ?
                html_writer::link($activity['url'], s(local_flwcupkp_a4b_activity_label($activity))) :
                s(local_flwcupkp_a4b_activity_label($activity)),
            s((string)($step['activity_resolution'] ?? '') . ' ' . (string)($step['reason'] ?? '')),
        ];
    }
    echo html_writer::table($table);
}

/**
 * Render class summary.
 *
 * @param array $summary
 */
function local_flwcupkp_a4b_render_class_summary(array $summary): void {
    $data = $summary['summary'] ?? [];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::tag('h3', get_string('classactivityresolutionsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classactivityresolutionsummaryintro', 'local_flwcupkp', (object)[
        'learners' => (int)($data['learners'] ?? 0),
        'next' => (int)($data['next_activity_ready'] ?? 0),
        'diagnostic' => (int)($data['diagnostic_required'] ?? 0),
        'fallback' => (int)($data['fallback_used'] ?? 0),
    ]), ['class' => 'local-flwcupkp-muted']);

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-table';
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('pathstatus', 'local_flwcupkp'),
        get_string('nextactivity', 'local_flwcupkp'),
        get_string('nexttarget', 'local_flwcupkp'),
        get_string('details', 'local_flwcupkp'),
    ];
    foreach ($summary['learners'] ?? [] as $learner) {
        $activity = $learner['next_activity'] ?? null;
        $table->data[] = [
            s((string)($learner['learner']['fullname'] ?? $learner['userid'])),
            local_flwcupkp_a4b_badge((string)($learner['resolution_status'] ?? '')),
            $activity && !empty($activity['url']) ?
                html_writer::link($activity['url'], s(local_flwcupkp_a4b_activity_label($activity))) :
                s(local_flwcupkp_a4b_activity_label($activity)),
            s(local_flwcupkp_a4b_target_label($learner['next_target'] ?? null)),
            s((string)($learner['diagnostic']['code'] ?? ($learner['fallback']['status'] ?? ''))),
        ];
    }
    if ($table->data) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('activityresolutionnoclassrows', 'local_flwcupkp'), [
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
function local_flwcupkp_a4b_course_options(): array {
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
function local_flwcupkp_a4b_learner_options(int $courseid): array {
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
function local_flwcupkp_a4b_badge(string $label): string {
    $class = 'local-flwcupkp-badge';
    $lower = strtolower($label);
    if (in_array($lower, ['ready', 'eligible', 'next_activity_ready', 'destination_ready', 'not used'], true) ||
            is_numeric($label)) {
        $class .= ' local-flwcupkp-badge-good';
    } else if (strpos($lower, 'blocked') !== false || strpos($lower, 'diagnostic') !== false ||
            strpos($lower, 'ineligible') !== false || $lower === 'used') {
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
function local_flwcupkp_a4b_target_label(?array $target): string {
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

/**
 * Human-readable activity label.
 *
 * @param array|null $activity
 * @return string
 */
function local_flwcupkp_a4b_activity_label(?array $activity): string {
    if (!$activity) {
        return get_string('none', 'local_flwcupkp');
    }
    $parts = array_filter([
        (string)($activity['title'] ?? ''),
        (string)($activity['object_externalid'] ?? ''),
    ]);
    return $parts ? implode(' - ', $parts) : get_string('none', 'local_flwcupkp');
}

/**
 * Compact check summary.
 *
 * @param array $checks
 * @return string
 */
function local_flwcupkp_a4b_check_summary(array $checks): string {
    $passed = 0;
    $warnings = 0;
    foreach ($checks as $check) {
        if (($check['status'] ?? '') === 'passed') {
            $passed++;
        } else if (($check['status'] ?? '') === 'warning') {
            $warnings++;
        }
    }
    return $passed . ' passed' . ($warnings ? ', ' . $warnings . ' warning(s)' : '');
}

/**
 * Compact blocking summary.
 *
 * @param array $activity
 * @return string
 */
function local_flwcupkp_a4b_blocking_summary(array $activity): string {
    $codes = $activity['blocking_codes'] ?? [];
    if (!$codes) {
        return get_string('none', 'local_flwcupkp');
    }
    $messages = array_map(static function(array $reason): string {
        return ($reason['code'] ?? '') . ': ' . ($reason['message'] ?? '');
    }, $activity['blocking_reasons'] ?? []);
    return $messages ? implode(' ', array_slice($messages, 0, 2)) : implode(', ', array_slice($codes, 0, 4));
}
