<?php
// Program 3 Gate A1 competency-centered learning goal page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$limit = optional_param('limit', 20, PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);
$source = optional_param('source', 'STUDENT', PARAM_ALPHA);
$reason = optional_param('reason', '', PARAM_TEXT);

require_login();

global $USER;

$systemcontext = context_system::instance();
$context = $courseid > 0 ? (context_course::instance($courseid, IGNORE_MISSING) ?: $systemcontext) : $systemcontext;
$targetuserid = $userid > 0 ? $userid : (int)$USER->id;
$limit = max(1, min(100, $limit));

local_flwcupkp_a1_require_view($targetuserid, $courseid, $context);

$message = null;
$messagetype = 'success';
if ($action === 'save') {
    require_sesskey();
    $source = local_flwcupkp_a1_authorized_source($targetuserid, $courseid, $context, $source);
    $data = [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'title' => optional_param('title', '', PARAM_TEXT),
        'desiredprofile' => optional_param('desiredprofile', '', PARAM_RAW),
        'competencyids' => optional_param_array('competencyids', [], PARAM_INT),
        'upids' => optional_param_array('upids', [], PARAM_INT),
        'kpids' => optional_param_array('kpids', [], PARAM_INT),
        'cefr' => optional_param('cefr', '', PARAM_ALPHANUMEXT),
        'flwstage' => optional_param('flwstage', '', PARAM_ALPHANUMEXT),
        'purpose' => optional_param('purpose', '', PARAM_TEXT),
        'priorityskills' => optional_param('priorityskills', '', PARAM_RAW),
        'targetdate' => optional_param('targetdate', '', PARAM_TEXT),
        'weeklytarget' => optional_param('weeklytarget', '', PARAM_RAW),
        'source' => $source,
        'status' => optional_param('status', 'active', PARAM_ALPHA),
    ];
    try {
        $save = \local_flwcupkp\local\learning_goal_service::save_goal($targetuserid, $data, $source, $reason);
        $message = get_string($save['status'] === 'unchanged' ? 'learninggoalunchanged' : 'learninggoalsaved',
            'local_flwcupkp');
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messagetype = 'error';
    }
}

$baseparams = [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
    'limit' => $limit,
];

$PAGE->set_url(new moodle_url('/local/flwcupkp/learning_goal.php', $baseparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('learninggoala1', 'local_flwcupkp'));
$PAGE->set_heading(get_string('learninggoala1', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$status = \local_flwcupkp\local\learning_goal_service::status($courseid, $unitcode, $frameworkid, $limit);
$current = \local_flwcupkp\local\learning_goal_service::current_goal($targetuserid, $courseid, $unitcode, $frameworkid,
    $limit);
$classsummary = $courseid > 0 && has_capability('local/flwcupkp:viewreports', $context) ?
    \local_flwcupkp\local\learning_goal_service::class_summary($courseid, $unitcode, $frameworkid, 100) : null;
$options = \local_flwcupkp\local\learning_goal_service::goal_options($courseid, $unitcode, $frameworkid, '', 200);
$history = \local_flwcupkp\local\learning_goal_service::recent_goal_history($courseid, 20);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('learninggoala1', 'local_flwcupkp'));
echo html_writer::tag('p', get_string('learninggoala1intro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-cm4-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo html_writer::link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/retention_review.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
    'frameworkid' => $frameworkid,
    'userid' => $targetuserid,
]), get_string('retentionreviewe3', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/student.php', [
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]), get_string('studentprogress', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

if ($message !== null) {
    echo $OUTPUT->notification($message, $messagetype);
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-cm4-shell local-flwcupkp-e2-shell']);
local_flwcupkp_a1_render_status($status);
local_flwcupkp_a1_render_filters($courseid, $unitcode, $frameworkid, $targetuserid, $limit, $context);
local_flwcupkp_a1_render_current_goal($current);
local_flwcupkp_a1_render_form($current, $options, $courseid, $unitcode, $frameworkid, $targetuserid, $source, $context);
if ($classsummary) {
    local_flwcupkp_a1_render_class_summary($classsummary);
}
local_flwcupkp_a1_render_history($history);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

/**
 * Require view permission for selected learner goal.
 *
 * @param int $targetuserid
 * @param int $courseid
 * @param context $context
 */
function local_flwcupkp_a1_require_view(int $targetuserid, int $courseid, context $context): void {
    global $USER;

    if ((int)$USER->id === $targetuserid) {
        require_capability('local/flwcupkp:viewlearnerpath', $context);
        return;
    }

    require_capability('local/flwcupkp:viewreports', $context);
}

/**
 * Return a source the actor may write.
 *
 * @param int $targetuserid
 * @param int $courseid
 * @param context $context
 * @param string $requestedsource
 * @return string
 */
function local_flwcupkp_a1_authorized_source(int $targetuserid, int $courseid, context $context,
        string $requestedsource): string {
    global $USER;

    $systemcontext = context_system::instance();
    $source = \local_flwcupkp\local\learning_goal_service::normalize_source($requestedsource);
    if ($source === 'INSTITUTION') {
        require_capability('local/flwcupkp:manageframeworks', $systemcontext);
        return $source;
    }
    if ((int)$USER->id !== $targetuserid || $source === 'TEACHER') {
        if (has_capability('local/flwcupkp:manageframeworks', $systemcontext)) {
            return $source;
        }
        require_capability('local/flwcupkp:override', $context);
        return $source;
    }

    require_capability('local/flwcupkp:viewlearnerpath', $context);
    return 'STUDENT';
}

/**
 * Render status cards.
 *
 * @param array $status
 */
function local_flwcupkp_a1_render_status(array $status): void {
    $summary = $status['summary'];
    $criteria = $status['criteria_summary'];
    $cards = [
        get_string('learninggoalstatus', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a1_badge($status['status'] ?? 'unknown'),
            'detail' => $status['contract']['version'] ?? '',
        ],
        get_string('learninggoalcriteria', 'local_flwcupkp') => [
            'value' => s($criteria['passed'] . '/' . $criteria['total']),
            'detail' => get_string('historyevidencecriteriadetail', 'local_flwcupkp', $criteria['failed']),
        ],
        get_string('learninggoals', 'local_flwcupkp') => [
            'value' => s((string)($summary['goals'] ?? 0)),
            'detail' => get_string('learninggoalsdetail', 'local_flwcupkp', (object)[
                'active' => (int)($summary['active'] ?? 0),
                'versions' => (int)($summary['versions'] ?? 0),
            ]),
        ],
        get_string('learninggoalsources', 'local_flwcupkp') => [
            'value' => s((string)array_sum($summary['sources'] ?? [])),
            'detail' => implode(' / ', array_map(static function(string $source, int $count): string {
                return $source . ': ' . $count;
            }, array_keys($summary['sources'] ?? []), array_values($summary['sources'] ?? []))),
        ],
        get_string('foundationnextgate', 'local_flwcupkp') => [
            'value' => local_flwcupkp_a1_badge($status['next_allowed_gate'] ?? 'unknown'),
            'detail' => get_string('learninggoala1nextgatedetail', 'local_flwcupkp'),
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
 * Render scope filters.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $userid
 * @param int $limit
 * @param context $context
 */
function local_flwcupkp_a1_render_filters(int $courseid, string $unitcode, int $frameworkid, int $userid, int $limit,
        context $context): void {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/flwcupkp/learning_goal.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-cm4-filters local-flwcupkp-e1-filters',
    ]);
    echo html_writer::tag('label', get_string('course') .
        html_writer::select([0 => get_string('choose')] + local_flwcupkp_a1_course_options(), 'courseid', $courseid,
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
        html_writer::select(local_flwcupkp_a1_learner_options($courseid, $userid, $context), 'userid', $userid,
            false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('limit', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'limit',
            'value' => $limit,
            'min' => 1,
            'max' => 100,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-limit-filter']);
    echo html_writer::tag('button', get_string('show', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}

/**
 * Render current goal.
 *
 * @param array $current
 */
function local_flwcupkp_a1_render_current_goal(array $current): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('currentlearninggoal', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('currentlearninggoalintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    if (empty($current['goal'])) {
        echo html_writer::tag('p', get_string('learninggoalempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }

    $goal = $current['goal'];
    $destination = $goal['destination'];
    echo html_writer::start_tag('dl', ['class' => 'local-flwcupkp-a1-goal']);
    local_flwcupkp_a1_dtdd(get_string('title', 'local_flwcupkp'), $goal['title']);
    local_flwcupkp_a1_dtdd(get_string('source', 'local_flwcupkp'), $goal['source']);
    local_flwcupkp_a1_dtdd(get_string('status'), $goal['status']);
    local_flwcupkp_a1_dtdd(get_string('version', 'local_flwcupkp'), (string)$goal['currentversion']);
    local_flwcupkp_a1_dtdd(get_string('desiredprofile', 'local_flwcupkp'),
        local_flwcupkp_a1_profile_text($destination['desired_profile']));
    local_flwcupkp_a1_dtdd(get_string('priorityskills', 'local_flwcupkp'),
        implode(', ', $destination['priorityskills']));
    local_flwcupkp_a1_dtdd(get_string('cefr', 'local_flwcupkp'), $goal['cefr']);
    local_flwcupkp_a1_dtdd(get_string('flwstage', 'local_flwcupkp'), $goal['flwstage']);
    local_flwcupkp_a1_dtdd(get_string('purpose', 'local_flwcupkp'), $goal['purpose']);
    local_flwcupkp_a1_dtdd(get_string('targetdate', 'local_flwcupkp'),
        !empty($goal['targetdate']) ? userdate($goal['targetdate'], get_string('strftimedate')) : '');
    local_flwcupkp_a1_dtdd(get_string('weeklytarget', 'local_flwcupkp'), (string)$goal['weeklytarget']);
    echo html_writer::end_tag('dl');

    echo local_flwcupkp_a1_target_list(get_string('competencies', 'local_flwcupkp'),
        $destination['labels']['competencies']);
    echo local_flwcupkp_a1_target_list(get_string('usepoints', 'local_flwcupkp'),
        $destination['labels']['use_points']);
    echo local_flwcupkp_a1_target_list(get_string('knowledgepoints', 'local_flwcupkp'),
        $destination['labels']['knowledge_points']);

    if (!empty($current['versions'])) {
        echo html_writer::tag('h4', get_string('learninggoalversionhistory', 'local_flwcupkp'));
        $table = new html_table();
        $table->head = [
            get_string('version', 'local_flwcupkp'),
            get_string('source', 'local_flwcupkp'),
            get_string('title', 'local_flwcupkp'),
            get_string('timemodified', 'local_flwcupkp'),
            get_string('reason', 'local_flwcupkp'),
        ];
        foreach ($current['versions'] as $version) {
            $table->data[] = [
                s((string)$version['version']),
                s($version['source']),
                s($version['title']),
                userdate((int)$version['timecreated']),
                s($version['changecomment']),
            ];
        }
        echo html_writer::table($table);
    }
    echo html_writer::end_tag('section');
}

/**
 * Render goal form.
 *
 * @param array $current
 * @param array $options
 * @param int $courseid
 * @param string $unitcode
 * @param int $frameworkid
 * @param int $userid
 * @param string $source
 * @param context $context
 */
function local_flwcupkp_a1_render_form(array $current, array $options, int $courseid, string $unitcode,
        int $frameworkid, int $userid, string $source, context $context): void {
    $goal = $current['goal'] ?? [];
    $destination = $goal['destination'] ?? [
        'desired_profile' => [],
        'competencyids' => [],
        'upids' => [],
        'kpids' => [],
        'priorityskills' => [],
    ];
    $caninstitution = has_capability('local/flwcupkp:manageframeworks', context_system::instance());
    $sourceoptions = ['STUDENT' => 'STUDENT'];
    if (has_capability('local/flwcupkp:override', $context) || $caninstitution) {
        $sourceoptions['TEACHER'] = 'TEACHER';
    }
    if ($caninstitution) {
        $sourceoptions['INSTITUTION'] = 'INSTITUTION';
    }

    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('setlearninggoal', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('setlearninggoalintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/flwcupkp/learning_goal.php'),
        'class' => 'local-flwcupkp-foundation-filters local-flwcupkp-a1-form',
    ]);
    foreach ([
        'sesskey' => sesskey(),
        'action' => 'save',
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'frameworkid' => $frameworkid,
        'userid' => $userid,
    ] as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }

    echo html_writer::tag('label', get_string('title', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'title',
            'value' => $goal['title'] ?? '',
            'maxlength' => 255,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('source', 'local_flwcupkp') .
        html_writer::select($sourceoptions, 'source', $goal['source'] ?? $source, false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('status') .
        html_writer::select([
            'active' => get_string('active', 'local_flwcupkp'),
            'paused' => get_string('paused', 'local_flwcupkp'),
            'completed' => get_string('completed', 'local_flwcupkp'),
            'archived' => get_string('archived', 'local_flwcupkp'),
        ], 'status', $goal['status'] ?? 'active', false), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('cefr', 'local_flwcupkp') .
        html_writer::select(['' => get_string('none')] + array_combine(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
            ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']), 'cefr', $goal['cefr'] ?? '', false),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('flwstage', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'flwstage',
            'value' => $goal['flwstage'] ?? '',
            'maxlength' => 80,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('targetdate', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'targetdate',
            'value' => !empty($goal['targetdate']) ? date('Y-m-d', (int)$goal['targetdate']) : '',
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('weeklytarget', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'number',
            'step' => '0.25',
            'min' => 0,
            'max' => 168,
            'name' => 'weeklytarget',
            'value' => $goal['weeklytarget'] ?? '',
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('purpose', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'purpose',
            'value' => $goal['purpose'] ?? '',
            'maxlength' => 255,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('priorityskills', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'priorityskills',
            'value' => implode(', ', $destination['priorityskills'] ?? []),
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);
    echo html_writer::tag('label', get_string('desiredprofile', 'local_flwcupkp') .
        html_writer::tag('textarea', s(local_flwcupkp_a1_profile_text($destination['desired_profile'] ?? [])), [
            'name' => 'desiredprofile',
            'rows' => 4,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-a1-wide']);
    echo html_writer::tag('label', get_string('competencies', 'local_flwcupkp') .
        html_writer::select(local_flwcupkp_a1_option_map($options['competencies']), 'competencyids[]',
            $destination['competencyids'] ?? [], false, ['multiple' => 'multiple', 'size' => 6]),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('usepoints', 'local_flwcupkp') .
        html_writer::select(local_flwcupkp_a1_option_map($options['use_points']), 'upids[]',
            $destination['upids'] ?? [], false, ['multiple' => 'multiple', 'size' => 6]),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('knowledgepoints', 'local_flwcupkp') .
        html_writer::select(local_flwcupkp_a1_option_map($options['knowledge_points']), 'kpids[]',
            $destination['kpids'] ?? [], false, ['multiple' => 'multiple', 'size' => 6]),
        ['class' => 'local-flwcupkp-filter']);
    echo html_writer::tag('label', get_string('reason', 'local_flwcupkp') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'reason',
            'maxlength' => 160,
            'class' => 'form-control',
        ]), ['class' => 'local-flwcupkp-filter local-flwcupkp-e1-course']);

    echo html_writer::tag('button', get_string('savelearninggoal', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('section');
}

/**
 * Render class summary.
 *
 * @param array $classsummary
 */
function local_flwcupkp_a1_render_class_summary(array $classsummary): void {
    $summary = $classsummary['summary'];
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('classlearninggoalsummary', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('classlearninggoalsummaryintro', 'local_flwcupkp', (object)$summary));
    echo html_writer::end_tag('div');
    if (empty($classsummary['goals'])) {
        echo html_writer::tag('p', get_string('learninggoalempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }
    $table = new html_table();
    $table->head = [
        get_string('learner', 'local_flwcupkp'),
        get_string('title', 'local_flwcupkp'),
        get_string('source', 'local_flwcupkp'),
        get_string('status'),
        get_string('version', 'local_flwcupkp'),
    ];
    foreach ($classsummary['goals'] as $goal) {
        $table->data[] = [
            s((string)$goal['userid']),
            s($goal['title']),
            s($goal['source']),
            s($goal['status']),
            s((string)$goal['currentversion']),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render recent audit history.
 *
 * @param array $history
 */
function local_flwcupkp_a1_render_history(array $history): void {
    echo html_writer::start_tag('section', ['class' => 'local-flwcupkp-foundation-panel local-flwcupkp-cm4-panel']);
    echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-foundation-panel-head']);
    echo html_writer::tag('h3', get_string('learninggoalaudithistory', 'local_flwcupkp'));
    echo html_writer::tag('p', get_string('learninggoalaudithistoryintro', 'local_flwcupkp'));
    echo html_writer::end_tag('div');
    if (!$history) {
        echo html_writer::tag('p', get_string('retentionrebuildhistoryempty', 'local_flwcupkp'),
            ['class' => 'local-flwcupkp-muted']);
        echo html_writer::end_tag('section');
        return;
    }
    $table = new html_table();
    $table->head = [
        get_string('time'),
        get_string('action', 'local_flwcupkp'),
        get_string('target', 'local_flwcupkp'),
        get_string('details', 'local_flwcupkp'),
    ];
    foreach ($history as $row) {
        $table->data[] = [
            userdate((int)$row->timecreated),
            s($row->action),
            s($row->targettype . ':' . $row->targetid),
            s(shorten_text((string)$row->detailsjson, 220)),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::end_tag('section');
}

/**
 * Render a dt/dd pair.
 *
 * @param string $term
 * @param string $value
 */
function local_flwcupkp_a1_dtdd(string $term, string $value): void {
    echo html_writer::tag('dt', s($term));
    echo html_writer::tag('dd', $value !== '' ? s($value) : html_writer::tag('span', get_string('none'),
        ['class' => 'local-flwcupkp-muted']));
}

/**
 * Target list.
 *
 * @param string $heading
 * @param array $rows
 * @return string
 */
function local_flwcupkp_a1_target_list(string $heading, array $rows): string {
    if (!$rows) {
        return '';
    }
    $items = array_map(static function(array $row): string {
        return html_writer::tag('li', s($row['externalid'] . ' - ' . $row['title']));
    }, $rows);
    return html_writer::tag('h4', s($heading)) . html_writer::tag('ul', implode('', $items));
}

/**
 * Badge HTML.
 *
 * @param string $value
 * @return string
 */
function local_flwcupkp_a1_badge(string $value): string {
    $class = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value));
    return html_writer::span(s($value), 'local-flwcupkp-badge local-flwcupkp-badge-' . $class);
}

/**
 * Course options.
 *
 * @return array
 */
function local_flwcupkp_a1_course_options(): array {
    global $DB;

    $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC',
        'id, fullname, shortname', 0, 500);
    $options = [];
    foreach ($courses as $course) {
        $options[(int)$course->id] = format_string($course->fullname) . ' (' . format_string($course->shortname) . ')';
    }
    return $options;
}

/**
 * Learner options.
 *
 * @param int $courseid
 * @param int $selected
 * @param context $context
 * @return array
 */
function local_flwcupkp_a1_learner_options(int $courseid, int $selected, context $context): array {
    global $USER, $DB;

    $options = [(int)$USER->id => fullname($USER)];
    if ($courseid > 0 && has_capability('local/flwcupkp:viewreports', $context)) {
        $users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email',
            'u.lastname ASC, u.firstname ASC', 0, 300);
        foreach ($users as $user) {
            $options[(int)$user->id] = fullname($user);
        }
    }
    if ($selected > 0 && !isset($options[$selected])) {
        $user = $DB->get_record('user', ['id' => $selected], 'id, firstname, lastname, email', IGNORE_MISSING);
        if ($user) {
            $options[$selected] = fullname($user);
        }
    }
    return $options;
}

/**
 * Option map for select controls.
 *
 * @param array $rows
 * @return array
 */
function local_flwcupkp_a1_option_map(array $rows): array {
    $options = [];
    foreach ($rows as $row) {
        $options[(int)$row['id']] = $row['externalid'] . ' - ' . $row['title'];
    }
    return $options;
}

/**
 * Text for desired profile.
 *
 * @param array $profile
 * @return string
 */
function local_flwcupkp_a1_profile_text(array $profile): string {
    if (!$profile) {
        return '';
    }
    if (isset($profile['description']) && count($profile) === 1) {
        return (string)$profile['description'];
    }
    return json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
