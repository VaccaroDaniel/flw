<?php
// Generic teacher-facing C-UP-KP unit overview page.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', 'U038', PARAM_ALPHANUMEXT);
$userid = optional_param('userid', 0, PARAM_INT);
$domain = optional_param('domain', '', PARAM_ALPHANUMEXT);
$lesson = optional_param('lesson', '', PARAM_ALPHANUMEXT);
$state = optional_param('state', '', PARAM_ALPHANUMEXT);
$evidencefilter = optional_param('evidence', '', PARAM_ALPHANUMEXT);
$targettype = optional_param('targettype', '', PARAM_ALPHANUMEXT);
$parentstate = optional_param('parentstate', '', PARAM_ALPHANUMEXT);
$parentreview = optional_param('parentreview', '', PARAM_ALPHANUMEXT);
$focus = optional_param('focus', '', PARAM_ALPHANUMEXT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

$course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST) : null;
require_login($course);
$context = $course ? context_course::instance($courseid) : context_system::instance();
require_capability('local/flwcupkp:viewreports', $context);
$canverify = has_capability('local/flwcupkp:override', $context);

$url = new moodle_url('/local/flwcupkp/teacher.php', ['courseid' => $courseid, 'unitcode' => $unitcode]);
if ($userid > 0) {
    $url->param('userid', $userid);
}
if ($domain !== '') {
    $url->param('domain', $domain);
}
if ($lesson !== '') {
    $url->param('lesson', $lesson);
}
if ($state !== '') {
    $url->param('state', $state);
}
if ($evidencefilter !== '') {
    $url->param('evidence', $evidencefilter);
}
if ($targettype !== '') {
    $url->param('targettype', $targettype);
}
if ($parentstate !== '') {
    $url->param('parentstate', $parentstate);
}
if ($parentreview !== '') {
    $url->param('parentreview', $parentreview);
}
if ($focus !== '') {
    $url->param('focus', $focus);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:override', $context);

    $action = required_param('action', PARAM_ALPHANUMEXT);
    $parenttargettype = optional_param('parenttargettype', '', PARAM_ALPHANUMEXT);
    $actionuserid = optional_param('targetuserid', 0, PARAM_INT);
    $actiontargetid = optional_param('targetid', 0, PARAM_INT);
    if (in_array($action, ['approve', 'override', 'clearoverride'], true)) {
        $result = \local_flwcupkp\local\unit_report::record_kp_action($courseid, $unitcode, $action, [
            'evidenceid' => optional_param('evidenceid', 0, PARAM_INT),
            'userid' => $actionuserid,
            'targetid' => $actiontargetid,
            'state' => optional_param('overridestate', '', PARAM_ALPHANUMEXT),
            'score' => optional_param('score', 0, PARAM_FLOAT),
            'reason' => optional_param('reason', '', PARAM_TEXT),
        ]);
        $redirect = local_flwcupkp_generic_next_kp_review_url($courseid, $unitcode, [
            'userid' => $userid,
            'domain' => $domain,
            'lesson' => $lesson,
            'state' => $state,
            'evidence' => $evidencefilter,
        ], $result);
    } else {
        $result = \local_flwcupkp\local\unit_report::record_parent_action($courseid, $unitcode, $action, [
            'userid' => $actionuserid,
            'targetid' => $actiontargetid,
            'parenttargettype' => $parenttargettype,
            'state' => optional_param('parentoverridestate', '', PARAM_ALPHANUMEXT),
            'score' => optional_param('score', 0, PARAM_FLOAT),
            'reason' => optional_param('reason', '', PARAM_TEXT),
        ]);
        $redirect = local_flwcupkp_generic_next_parent_review_url($courseid, $unitcode, [
            'userid' => $userid,
            'targettype' => $targettype,
            'parentstate' => $parentstate,
            'parentreview' => $parentreview,
        ], $parenttargettype, $result);
    }
    redirect($redirect);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('unitteacheroverview', 'local_flwcupkp'));
$PAGE->set_heading(get_string('unitteacheroverview', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$targets = \local_flwcupkp\local\unit_report::unit_targets($courseid, $unitcode);
$learners = \local_flwcupkp\local\unit_report::learners($courseid, $unitcode);
$kpreport = \local_flwcupkp\local\unit_report::kp_report($courseid, $unitcode, [
    'userid' => $userid,
    'domain' => $domain,
    'lesson' => $lesson,
    'state' => $state,
    'evidence' => $evidencefilter,
]);
$overview = \local_flwcupkp\local\unit_report::mastery_overview($courseid, $unitcode, [
    'userid' => $userid,
    'targettype' => $targettype,
    'stategroup' => $parentstate,
    'parentreview' => $parentreview,
]);
$parentqueues = \local_flwcupkp\local\unit_report::parent_queue_summary($courseid, $unitcode, $userid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unitteacheroverview', 'local_flwcupkp') . ': ' . s($unitcode));
echo \local_flwcupkp\local\visuals::unit_nav($courseid, $unitcode, $userid, true,
    $canverify && \local_flwcupkp\local\performance_service::has_tasks($courseid, $unitcode));

if ($status !== '') {
    echo $OUTPUT->notification(get_string('verification' . $status, 'local_flwcupkp'), 'success');
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
if ($unitcode === 'U038' && $courseid > 0) {
    echo \local_flwcupkp\local\visuals::nav_link(
        new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]),
        get_string('openrichu038verification', 'local_flwcupkp'), ['class' => 'btn btn-primary']);
}
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/manual_evidence.php',
        ['courseid' => $courseid, 'unitcode' => $unitcode]),
    get_string('manualevidence', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
if ($courseid > 0 && \local_flwcupkp\local\performance_service::has_tasks($courseid, $unitcode)) {
    $performanceurl = $unitcode === 'U038' ?
        new moodle_url('/local/flwcupkp/performance_u038.php', ['courseid' => $courseid]) :
        new moodle_url('/local/flwcupkp/performance.php', [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ]);
    echo \local_flwcupkp\local\visuals::nav_link($performanceurl,
        get_string('unitperformancenav', 'local_flwcupkp', $unitcode), ['class' => 'btn btn-secondary']);
}
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/curriculum.php', ['unitcode' => $unitcode]),
    get_string('curriculumgraph', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

echo local_flwcupkp_generic_parent_queue_dashboard($parentqueues, $courseid, $unitcode, $userid);

ob_start();
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/teacher.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);

$learneroptions = [0 => get_string('alllearners', 'local_flwcupkp')];
foreach ($learners as $learner) {
    $learneroptions[$learner->id] = fullname($learner);
}

echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
    html_writer::select($learneroptions, 'userid', $userid, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('kpdomain', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $kpreport['filters']['domains'], 'domain',
        $domain, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('lesson', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $kpreport['filters']['lessons'], 'lesson',
        $lesson, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('state', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $kpreport['filters']['states'], 'state',
        $state, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('evidencefilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_generic_evidence_filter_options(), 'evidence', $evidencefilter, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parenttargetfilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_generic_parent_targettype_options(), 'targettype', $targettype, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parentstatefilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_generic_parent_state_options(), 'parentstate', $parentstate, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parentreviewfilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_generic_parent_review_options(), 'parentreview', $parentreview, false),
    ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/teacher.php', ['courseid' => $courseid, 'unitcode' => $unitcode]),
    get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
$filterhtml = ob_get_clean();
$filtersopen = $userid || $domain !== '' || $lesson !== '' || $state !== '' || $evidencefilter !== '' ||
    $targettype !== '' || $parentstate !== '' || $parentreview !== '';
echo \local_flwcupkp\local\visuals::details_panel(get_string('filters', 'local_flwcupkp'), $filterhtml,
    $filtersopen, 'local-flwcupkp-filter-panel');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('learners', 'local_flwcupkp') . ': ' . count($learners));
echo html_writer::tag('span', get_string('targets', 'local_flwcupkp') . ': ' . count($targets));
echo html_writer::tag('span', get_string('unit', 'local_flwcupkp') . ': ' . s($unitcode));
echo html_writer::tag('span', get_string('rows', 'local_flwcupkp') . ': ' . count($kpreport['rows']));
echo html_writer::tag('span', get_string('competenciesachieved', 'local_flwcupkp') . ': ' .
    (int)$overview['summary']['competency_achieved'] . '/' . (int)$overview['summary']['competency_total']);
echo html_writer::tag('span', get_string('upsdemonstrated', 'local_flwcupkp') . ': ' .
    (int)$overview['summary']['up_demonstrated'] . '/' . (int)$overview['summary']['up_total']);
echo html_writer::end_tag('div');

echo html_writer::start_tag('section', [
    'class' => 'local-flwcupkp-overview',
    'id' => 'flwcupkp-unit-parent-overview',
]);
echo html_writer::tag('h3', get_string('parentoverview', 'local_flwcupkp'));
$overviewtable = new html_table();
$overviewtable->attributes['class'] = 'generaltable local-flwcupkp-table local-flwcupkp-parent-table';
$overviewtable->head = [
    get_string('learner', 'local_flwcupkp'),
    get_string('targettype', 'local_flwcupkp'),
    get_string('target', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidence', 'local_flwcupkp'),
    get_string('explanation', 'local_flwcupkp'),
];
if ($canverify) {
    $overviewtable->head[] = get_string('teacheractions', 'local_flwcupkp');
}
foreach ($overview['rows'] as $row) {
    $overviewtable->data[] = local_flwcupkp_generic_parent_table_row($row, $focus, $canverify, $courseid, $unitcode,
        $url);
}
if (!$overviewtable->data) {
    $emptystring = $parentreview === 'review' ? 'allreviewedparentrows' : 'noparentrows';
    echo $OUTPUT->notification(get_string($emptystring, 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('parenttargets', 'local_flwcupkp') . ' (' . count($overviewtable->data) . ')',
        html_writer::table($overviewtable),
        $parentreview !== '' || $parentstate !== '' || $targettype !== '' || str_contains($focus, '-comp') ||
            str_contains($focus, '-up')
    );
}
echo html_writer::end_tag('section');

echo html_writer::start_tag('section', [
    'class' => 'local-flwcupkp-overview',
    'id' => 'flwcupkp-unit-kp-evidence',
]);
echo html_writer::tag('h3', get_string('learningpointevidence', 'local_flwcupkp'));
$kptable = new html_table();
$kptable->attributes['class'] = 'generaltable local-flwcupkp-table';
$kptable->head = [
    get_string('learner', 'local_flwcupkp'),
    get_string('lesson', 'local_flwcupkp'),
    get_string('learningpoint', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidencesource', 'local_flwcupkp'),
    get_string('explanation', 'local_flwcupkp'),
];
if ($canverify) {
    $kptable->head[] = get_string('teacheractions', 'local_flwcupkp');
}
foreach ($kpreport['rows'] as $row) {
    $kptable->data[] = local_flwcupkp_generic_kp_table_row($row, $focus, $canverify, $courseid, $unitcode, $url);
}
if (!$kptable->data) {
    $emptystring = $evidencefilter === 'review' ? 'allreviewedrows' : 'noreportrows';
    echo $OUTPUT->notification(get_string($emptystring, 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('learningpointevidence', 'local_flwcupkp') . ' (' . count($kptable->data) . ')',
        html_writer::table($kptable),
        $evidencefilter !== '' || $domain !== '' || $lesson !== '' || $state !== '' || str_contains($focus, '-kp')
    );
}
echo html_writer::end_tag('section');

$table = new html_table();
$table->attributes['class'] = 'generaltable local-flwcupkp-table';
$table->head = [
    get_string('type', 'local_flwcupkp'),
    get_string('externalid', 'local_flwcupkp'),
    get_string('title', 'local_flwcupkp'),
    get_string('mastered', 'local_flwcupkp'),
    get_string('needpractice', 'local_flwcupkp'),
    get_string('evidence', 'local_flwcupkp'),
];
foreach ($targets as $target) {
    $stats = \local_flwcupkp\local\unit_report::target_stats($target, $learners);
    $table->data[] = [
        s($target->targettype),
        s($target->externalid),
        s($target->title),
        $stats['strong'],
        $stats['weak'],
        $stats['evidence'],
    ];
}

if (!$table->data) {
    echo $OUTPUT->notification(get_string('nogenericunitrows', 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('targetsummary', 'local_flwcupkp') . ' (' . count($table->data) . ')',
        html_writer::table($table)
    );
}

if ($focus !== '') {
    $focusids = json_encode([
        'flwcupkp-row-' . $focus,
        'flwcupkp-parent-row-' . $focus,
    ]);
    echo html_writer::script(
        "(function(){var ids={$focusids};for(var i=0;i<ids.length;i++){" .
        "var row=document.getElementById(ids[i]);" .
        "if(row){row.scrollIntoView({block:'center'});row.focus({preventScroll:true});break;}}}());"
    );
}

echo $OUTPUT->footer();

/**
 * Parent target type filter options.
 *
 * @return array
 */
function local_flwcupkp_generic_parent_targettype_options(): array {
    return [
        '' => get_string('all', 'local_flwcupkp'),
        'competency' => get_string('competency', 'local_flwcupkp'),
        'up' => get_string('usepoint', 'local_flwcupkp'),
    ];
}

/**
 * Parent state group filter options.
 *
 * @return array
 */
function local_flwcupkp_generic_parent_state_options(): array {
    return [
        '' => get_string('parentstateall', 'local_flwcupkp'),
        'attention' => get_string('parentstateattention', 'local_flwcupkp'),
        'achieved' => get_string('parentstateachieved', 'local_flwcupkp'),
        'notachieved' => get_string('parentstatenotachieved', 'local_flwcupkp'),
        'demonstrated' => get_string('parentstatedemonstrated', 'local_flwcupkp'),
        'notdemonstrated' => get_string('parentstatenotdemonstrated', 'local_flwcupkp'),
    ];
}

/**
 * Parent review filter options.
 *
 * @return array
 */
function local_flwcupkp_generic_parent_review_options(): array {
    return [
        '' => get_string('parentreviewall', 'local_flwcupkp'),
        'review' => get_string('parentreviewneedsdecision', 'local_flwcupkp'),
        'decided' => get_string('parentreviewdecided', 'local_flwcupkp'),
    ];
}

/**
 * Render the parent queue summary dashboard.
 *
 * @param array $queues
 * @param int $courseid
 * @param string $unitcode
 * @param int $userid
 * @return string
 */
function local_flwcupkp_generic_parent_queue_dashboard(array $queues, int $courseid, string $unitcode,
        int $userid): string {
    $html = html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-queue-dashboard',
        'id' => 'flwcupkp-unit-parent-queue-dashboard',
    ]);
    $html .= html_writer::tag('h3', get_string('parentqueuesummaryunit', 'local_flwcupkp', $unitcode));
    if ((int)$queues['total'] === 0) {
        $html .= html_writer::tag('p', get_string('parentqueuecompleteunit', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-queue-complete',
        ]);
    }

    $competencyurl = local_flwcupkp_generic_parent_queue_url($courseid, $unitcode, $userid, 'competency',
        'notachieved', $queues['competency']['first']);
    $upurl = local_flwcupkp_generic_parent_queue_url($courseid, $unitcode, $userid, 'up',
        'notdemonstrated', $queues['up']['first']);

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-queue-grid']);
    $html .= local_flwcupkp_generic_parent_queue_card($queues['competency'], get_string('competencyqueue',
        'local_flwcupkp'), $competencyurl);
    $html .= local_flwcupkp_generic_parent_queue_card($queues['up'], get_string('upqueue', 'local_flwcupkp'),
        $upurl);
    $html .= html_writer::end_tag('div');

    $decidedurl = new moodle_url('/local/flwcupkp/teacher.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'parentreview' => 'decided',
    ]);
    if ($userid > 0) {
        $decidedurl->param('userid', $userid);
    }
    $decidedurl->set_anchor('flwcupkp-unit-parent-overview');

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    if ((int)$queues['competency']['count'] > 0) {
        $html .= html_writer::link($competencyurl, get_string('opencompetencyqueue', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary btn-sm',
        ]);
    }
    if ((int)$queues['up']['count'] > 0) {
        $html .= html_writer::link($upurl, get_string('openupqueue', 'local_flwcupkp'), [
            'class' => 'btn btn-secondary btn-sm',
        ]);
    }
    $html .= html_writer::link($decidedurl, get_string('parentdecisionsrecorded', 'local_flwcupkp'), [
        'class' => 'btn btn-link btn-sm',
    ]);
    $html .= html_writer::end_tag('div');
    $html .= html_writer::end_tag('section');

    return $html;
}

/**
 * Build a filtered generic parent queue URL.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param int $userid
 * @param string $targettype
 * @param string $parentstate
 * @param array|null $first
 * @return moodle_url
 */
function local_flwcupkp_generic_parent_queue_url(int $courseid, string $unitcode, int $userid, string $targettype,
        string $parentstate, ?array $first): moodle_url {
    $url = new moodle_url('/local/flwcupkp/teacher.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'targettype' => $targettype,
        'parentstate' => $parentstate,
        'parentreview' => 'review',
    ]);
    if ($userid > 0) {
        $url->param('userid', $userid);
    }
    if ($first) {
        $anchor = \local_flwcupkp\local\unit_report::parent_row_anchor($first);
        $url->param('focus', $anchor);
        $url->set_anchor('flwcupkp-parent-row-' . $anchor);
    } else {
        $url->set_anchor('flwcupkp-unit-parent-overview');
    }

    return $url;
}

/**
 * Render one parent queue card.
 *
 * @param array $queue
 * @param string $label
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_generic_parent_queue_card(array $queue, string $label, moodle_url $url): string {
    $content = html_writer::tag('strong', (string)(int)$queue['count']) .
        html_writer::tag('em', s($label));

    if (!empty($queue['first'])) {
        $first = $queue['first'];
        $content .= html_writer::tag('span', get_string('parentqueuenext', 'local_flwcupkp') . ': ' .
            s($first['learner']) . ' - ' . s($first['externalid']), ['class' => 'local-flwcupkp-queue-next']);
        $content .= html_writer::tag('span', s($first['title']), ['class' => 'local-flwcupkp-muted']);
        return html_writer::link($url, $content, ['class' => 'local-flwcupkp-queue-card']);
    }

    $content .= html_writer::tag('span', get_string('parentqueueempty', 'local_flwcupkp'), [
        'class' => 'local-flwcupkp-queue-next',
    ]);
    return html_writer::tag('span', $content, ['class' => 'local-flwcupkp-queue-card']);
}

/**
 * Evidence filter options for the generic teacher page.
 *
 * @return array
 */
function local_flwcupkp_generic_evidence_filter_options(): array {
    return [
        '' => get_string('evidencefilterall', 'local_flwcupkp'),
        'with' => get_string('evidencefilterwith', 'local_flwcupkp'),
        'verified' => get_string('evidencefilterverified', 'local_flwcupkp'),
        'review' => get_string('evidencefilterreview', 'local_flwcupkp'),
    ];
}

/**
 * Build the post-action redirect for the generic KP evidence review queue.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param array $filters
 * @param string $status
 * @return moodle_url
 */
function local_flwcupkp_generic_next_kp_review_url(int $courseid, string $unitcode, array $filters,
        string $status): moodle_url {
    $redirect = new moodle_url('/local/flwcupkp/teacher.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
    ]);
    foreach (['userid', 'domain', 'lesson', 'state'] as $key) {
        if (!empty($filters[$key])) {
            $redirect->param($key, $filters[$key]);
        }
    }

    $redirect->param('evidence', 'review');
    $reviewfilters = $filters;
    $reviewfilters['evidence'] = 'review';
    $review = \local_flwcupkp\local\unit_report::kp_report($courseid, $unitcode, $reviewfilters);
    $nextrow = reset($review['rows']);
    if ($nextrow) {
        $nextanchor = \local_flwcupkp\local\unit_report::kp_row_anchor($nextrow);
        $redirect->param('focus', $nextanchor);
        $redirect->set_anchor('flwcupkp-row-' . $nextanchor);
    } else {
        $redirect->set_anchor('flwcupkp-unit-kp-evidence');
    }

    $redirect->param('status', $status);
    return $redirect;
}

/**
 * Build the post-action redirect for the generic parent review queue.
 *
 * @param int $courseid
 * @param string $unitcode
 * @param array $filters
 * @param string $actiontargettype
 * @param string $status
 * @return moodle_url
 */
function local_flwcupkp_generic_next_parent_review_url(int $courseid, string $unitcode, array $filters,
        string $actiontargettype, string $status): moodle_url {
    $redirect = new moodle_url('/local/flwcupkp/teacher.php', [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
    ]);
    $currentuserid = (int)($filters['userid'] ?? 0);
    if ($currentuserid > 0) {
        $redirect->param('userid', $currentuserid);
    }

    $targettype = (string)($filters['targettype'] ?? '');
    if ($targettype === '' && in_array($actiontargettype, ['up', 'competency'], true)) {
        $targettype = $actiontargettype;
    }
    if (in_array($targettype, ['up', 'competency'], true)) {
        $redirect->param('targettype', $targettype);
    }

    $parentstate = (string)($filters['parentstate'] ?? '');
    if ($parentstate === '') {
        $parentstate = $targettype === 'competency' ? 'notachieved' :
            ($targettype === 'up' ? 'notdemonstrated' : 'attention');
    }
    if ($parentstate !== '') {
        $redirect->param('parentstate', $parentstate);
    }

    $parentreview = (string)($filters['parentreview'] ?? '');
    if ($parentreview === '') {
        $parentreview = 'review';
    }
    $redirect->param('parentreview', $parentreview);

    $review = \local_flwcupkp\local\unit_report::mastery_overview($courseid, $unitcode, [
        'userid' => $currentuserid,
        'targettype' => $targettype,
        'stategroup' => $parentstate,
        'parentreview' => $parentreview,
    ]);
    $nextrow = reset($review['rows']);
    if ($nextrow) {
        $nextanchor = \local_flwcupkp\local\unit_report::parent_row_anchor($nextrow);
        $redirect->param('focus', $nextanchor);
        $redirect->set_anchor('flwcupkp-parent-row-' . $nextanchor);
    } else {
        $redirect->set_anchor('flwcupkp-unit-parent-overview');
    }

    $redirect->param('status', $status);
    return $redirect;
}

/**
 * Render a generic KP evidence table row.
 *
 * @param array $row
 * @param string $focus
 * @param bool $canverify
 * @param int $courseid
 * @param string $unitcode
 * @param moodle_url $url
 * @return html_table_row
 */
function local_flwcupkp_generic_kp_table_row(array $row, string $focus, bool $canverify, int $courseid,
        string $unitcode, moodle_url $url): html_table_row {
    $activityurl = ($row['cmid'] && $row['modname']) ?
        new moodle_url('/mod/' . $row['modname'] . '/view.php', ['id' => $row['cmid']]) : null;
    $sourceparts = [];
    if ($activityurl) {
        $sourceparts[] = html_writer::link($activityurl, s($row['object_title']));
    } else {
        $sourceparts[] = s($row['object_title']);
    }
    if ($row['cmid']) {
        $sourceparts[] = get_string('activityid', 'local_flwcupkp') . ' ' . (int)$row['cmid'];
    }
    if ($row['attempt_id']) {
        $sourceparts[] = get_string('attempt', 'local_flwcupkp') . ' ' . (int)$row['attempt_id'];
    }
    if ($row['evidence_score'] !== null) {
        $sourceparts[] = get_string('score', 'local_flwcupkp') . ' ' .
            format_float((float)$row['evidence_score'], 2);
    }
    if ($row['evidence_time']) {
        $sourceparts[] = userdate($row['evidence_time']);
    }
    if (!empty($row['verification'])) {
        $sourceparts[] = html_writer::tag('strong', get_string('verificationstatus', 'local_flwcupkp')) . ': ' .
            s(get_string($row['verification']['action'], 'local_flwcupkp')) . html_writer::tag('div',
                s($row['verification']['teacher']) . ' - ' . userdate($row['verification']['timecreated']),
                ['class' => 'local-flwcupkp-muted']);
    }

    $statehtml = \local_flwcupkp\local\visuals::state_badge((string)$row['state']);
    if ($row['mastery_score'] !== null) {
        $statehtml .= html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
            format_float((float)$row['mastery_score'], 2), ['class' => 'local-flwcupkp-muted']);
    }
    if ($row['manual_override']) {
        $statehtml .= html_writer::tag('div', get_string('manualoverride', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-override',
        ]);
    }
    if ($row['override_reason'] !== '') {
        $statehtml .= html_writer::tag('div', s($row['override_reason']), ['class' => 'local-flwcupkp-muted']);
    }

    $cells = [
        s($row['learner']),
        s($row['lesson']) . html_writer::tag('div', s($row['domain']), ['class' => 'local-flwcupkp-muted']),
        html_writer::tag('strong', s($row['kp_externalid'])) . html_writer::tag('div', s($row['kp_title'])),
        $statehtml,
        implode(html_writer::empty_tag('br'), $sourceparts),
        s($row['explanation']),
    ];
    if ($canverify) {
        $cells[] = local_flwcupkp_generic_kp_teacher_actions($row, $courseid, $unitcode, $url);
    }

    $tablerow = new html_table_row($cells);
    $rowanchor = \local_flwcupkp\local\unit_report::kp_row_anchor($row);
    $tablerow->id = 'flwcupkp-row-' . $rowanchor;
    $tablerow->attributes['data-flwcupkp-row-anchor'] = $rowanchor;
    if ($focus === $rowanchor) {
        $tablerow->attributes['class'] = 'local-flwcupkp-row-focus';
        $tablerow->attributes['tabindex'] = '-1';
    }

    return $tablerow;
}

/**
 * Render teacher verification controls for a generic KP row.
 *
 * @param array $row
 * @param int $courseid
 * @param string $unitcode
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_generic_kp_teacher_actions(array $row, int $courseid, string $unitcode,
        moodle_url $url): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-actions']);

    if ($row['evidence_id']) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'local-flwcupkp-actionform',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approve']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'evidenceid',
            'value' => $row['evidence_id']]);
        $html .= html_writer::tag('button', get_string('approveevidence', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-secondary btn-sm',
        ]);
        $html .= html_writer::end_tag('form');
    }

    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url,
        'class' => 'local-flwcupkp-actionform local-flwcupkp-overrideform',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'override']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['kp_id']]);
    $stateid = 'generic-override-state-' . $row['userid'] . '-' .
        clean_param($row['kp_externalid'], PARAM_ALPHANUMEXT);
    $html .= html_writer::label(get_string('state', 'local_flwcupkp'), $stateid, false, ['class' => 'accesshide']);
    $html .= html_writer::select(\local_flwcupkp\local\unit_report::KP_STATES, 'overridestate', $row['state'], false, [
        'id' => $stateid,
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'score',
        'value' => $row['mastery_score'] !== null ? format_float((float)$row['mastery_score'], 2) : '0.00',
        'min' => '0',
        'max' => '1',
        'step' => '0.01',
        'aria-label' => get_string('score', 'local_flwcupkp'),
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'reason',
        'value' => '',
        'maxlength' => '255',
        'placeholder' => get_string('reason', 'local_flwcupkp'),
        'required' => 'required',
    ]);
    $html .= html_writer::tag('button', get_string('overridestate', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary btn-sm',
    ]);
    $html .= html_writer::end_tag('form');

    if ($row['manual_override']) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'local-flwcupkp-actionform',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'clearoverride']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid',
            'value' => $row['userid']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid',
            'value' => $row['kp_id']]);
        $html .= html_writer::tag('button', get_string('clearoverride', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-link btn-sm',
        ]);
        $html .= html_writer::end_tag('form');
    }

    return $html . html_writer::end_tag('div');
}

/**
 * Render a generic parent overview table row.
 *
 * @param array $row
 * @param string $focus
 * @param bool $canverify
 * @param int $courseid
 * @param string $unitcode
 * @param moodle_url $url
 * @return html_table_row
 */
function local_flwcupkp_generic_parent_table_row(array $row, string $focus, bool $canverify, int $courseid,
        string $unitcode, moodle_url $url): html_table_row {
    $statehtml = \local_flwcupkp\local\visuals::state_badge((string)$row['state']);
    if ($row['mastery_score'] !== null) {
        $statehtml .= html_writer::tag('div', get_string('mastery', 'local_flwcupkp') . ' ' .
            format_float((float)$row['mastery_score'], 2), ['class' => 'local-flwcupkp-muted']);
    }
    if ($row['manual_override']) {
        $statehtml .= html_writer::tag('div', get_string('manualoverride', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-override',
        ]);
    }
    if ($row['override_reason'] !== '') {
        $statehtml .= html_writer::tag('div', s($row['override_reason']), ['class' => 'local-flwcupkp-muted']);
    }
    if (!empty($row['verification'])) {
        $statehtml .= html_writer::tag('div',
            s(get_string($row['verification']['action'], 'local_flwcupkp')) . ' - ' .
            s($row['verification']['teacher']) . ' - ' . userdate($row['verification']['timecreated']),
            ['class' => 'local-flwcupkp-verified']
        );
    }

    $evidenceparts = [];
    if ($row['evidence_count'] > 0) {
        $evidenceparts[] = get_string('evidence', 'local_flwcupkp') . ': ' . (int)$row['evidence_count'];
        if ($row['evidence_score'] !== null) {
            $evidenceparts[] = get_string('score', 'local_flwcupkp') . ' ' .
                format_float((float)$row['evidence_score'], 2);
        }
        if (!empty($row['sourceref'])) {
            $evidenceparts[] = s($row['sourceref']);
        }
        if ($row['evidence_time']) {
            $evidenceparts[] = userdate($row['evidence_time']);
        }
    } else {
        $evidenceparts[] = get_string('noevidenceyet', 'local_flwcupkp');
    }

    $typelabel = $row['targettype'] === 'up' ?
        get_string('usepoint', 'local_flwcupkp') : get_string('competency', 'local_flwcupkp');
    $cells = [
        s($row['learner']),
        s($typelabel),
        html_writer::tag('strong', s($row['externalid'])) . html_writer::tag('div', s($row['title'])),
        $statehtml,
        implode(html_writer::empty_tag('br'), $evidenceparts),
        s($row['explanation']),
    ];
    if ($canverify) {
        $cells[] = local_flwcupkp_generic_parent_teacher_actions($row, $courseid, $unitcode, $url);
    }

    $tablerow = new html_table_row($cells);

    $rowanchor = \local_flwcupkp\local\unit_report::parent_row_anchor($row);
    $tablerow->id = 'flwcupkp-parent-row-' . $rowanchor;
    $tablerow->attributes['data-flwcupkp-parent-row-anchor'] = $rowanchor;
    if ($focus === $rowanchor) {
        $tablerow->attributes['class'] = 'local-flwcupkp-row-focus';
        $tablerow->attributes['tabindex'] = '-1';
    }

    return $tablerow;
}

/**
 * Render teacher decision controls for a generic parent UP/competency row.
 *
 * @param array $row
 * @param int $courseid
 * @param string $unitcode
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_generic_parent_teacher_actions(array $row, int $courseid, string $unitcode,
        moodle_url $url): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-actions']);

    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url,
        'class' => 'local-flwcupkp-actionform',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approveparent']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype',
        'value' => $row['targettype']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['targetid']]);
    $html .= html_writer::tag('button', get_string('confirmstate', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary btn-sm',
    ]);
    $html .= html_writer::end_tag('form');

    $html .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url,
        'class' => 'local-flwcupkp-actionform local-flwcupkp-overrideform',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'overrideparent']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype',
        'value' => $row['targettype']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['targetid']]);
    $stateid = 'generic-parent-override-state-' . $row['userid'] . '-' . clean_param($row['targettype'] . '-' .
        $row['externalid'], PARAM_ALPHANUMEXT);
    $html .= html_writer::label(get_string('state', 'local_flwcupkp'), $stateid, false, ['class' => 'accesshide']);
    $html .= html_writer::select(local_flwcupkp_generic_parent_override_state_options($row['targettype']),
        'parentoverridestate', $row['state'], false, ['id' => $stateid]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'score',
        'value' => $row['mastery_score'] !== null ? format_float((float)$row['mastery_score'], 2) : '0.00',
        'min' => '0',
        'max' => '1',
        'step' => '0.01',
        'aria-label' => get_string('score', 'local_flwcupkp'),
    ]);
    $html .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'reason',
        'value' => '',
        'maxlength' => '255',
        'placeholder' => get_string('reason', 'local_flwcupkp'),
        'required' => 'required',
    ]);
    $html .= html_writer::tag('button', get_string('overridestate', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary btn-sm',
    ]);
    $html .= html_writer::end_tag('form');

    if ($row['manual_override']) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'local-flwcupkp-actionform',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'unitcode', 'value' => $unitcode]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
            'value' => 'clearparentoverride']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid',
            'value' => $row['userid']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype',
            'value' => $row['targettype']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid',
            'value' => $row['targetid']]);
        $html .= html_writer::tag('button', get_string('clearoverride', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-link btn-sm',
        ]);
        $html .= html_writer::end_tag('form');
    }

    return $html . html_writer::end_tag('div');
}

/**
 * Parent state override options for a generic row type.
 *
 * @param string $targettype
 * @return array
 */
function local_flwcupkp_generic_parent_override_state_options(string $targettype): array {
    if ($targettype === 'up') {
        return \local_flwcupkp\local\unit_report::UP_STATES;
    }
    return \local_flwcupkp\local\unit_report::COMPETENCY_STATES;
}
