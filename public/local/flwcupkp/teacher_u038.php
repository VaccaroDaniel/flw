<?php
// Teacher verification page for U038 C-UP-KP evidence.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 124, PARAM_INT);
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
$approvedcount = optional_param('approvedcount', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/flwcupkp:viewreports', $context);
$canverify = has_capability('local/flwcupkp:override', $context);

$url = new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]);
if ($userid) {
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

$reportfilters = [
    'userid' => $userid,
    'domain' => $domain,
    'lesson' => $lesson,
    'state' => $state,
    'evidence' => $evidencefilter,
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    require_capability('local/flwcupkp:override', $context);

    $action = required_param('action', PARAM_ALPHANUMEXT);
    if ($action === 'bulkapprove') {
        $count = local_flwcupkp_bulk_approve_review_evidence($courseid, $reportfilters);
        $redirect = clone $url;
        $redirect->remove_params('focus', 'status', 'approvedcount');
        $redirect->param('evidence', 'review');
        $redirect->param('status', 'bulkapproved');
        $redirect->param('approvedcount', $count);
        redirect($redirect);
    }

    $parenttargettype = optional_param('parenttargettype', '', PARAM_ALPHANUMEXT);
    $actionuserid = optional_param('targetuserid', 0, PARAM_INT);
    $actiontargetid = optional_param('targetid', 0, PARAM_INT);
    $result = \local_flwcupkp\local\teacher_report::record_teacher_action($courseid, $action, [
        'evidenceid' => optional_param('evidenceid', 0, PARAM_INT),
        'userid' => $actionuserid,
        'targetid' => $actiontargetid,
        'parenttargettype' => $parenttargettype,
        'state' => optional_param('parentoverridestate', optional_param('overridestate', '', PARAM_ALPHANUMEXT),
            PARAM_ALPHANUMEXT),
        'score' => optional_param('score', 0, PARAM_FLOAT),
        'reason' => optional_param('reason', '', PARAM_TEXT),
    ]);

    if (in_array($action, ['approve', 'override'], true)) {
        $redirect = local_flwcupkp_next_review_url($courseid, $url, $result, $reportfilters);
    } else if (in_array($action, ['approveparent', 'overrideparent', 'clearparentoverride'], true)) {
        $redirect = local_flwcupkp_next_parent_review_url($courseid, [
            'userid' => $userid,
            'targettype' => $targettype,
            'parentstate' => $parentstate,
            'parentreview' => $parentreview,
        ], $parenttargettype, $actionuserid, $actiontargetid, $result);
    } else {
        $redirect = clone $url;
        $redirect->param('status', $result);
    }
    redirect($redirect);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('teacherverification', 'local_flwcupkp'));
$PAGE->set_heading(get_string('teacherverification', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$report = \local_flwcupkp\local\teacher_report::u038_report($courseid, [
    'userid' => $reportfilters['userid'],
    'domain' => $reportfilters['domain'],
    'lesson' => $reportfilters['lesson'],
    'state' => $reportfilters['state'],
    'evidence' => $reportfilters['evidence'],
]);
$overview = \local_flwcupkp\local\teacher_report::u038_mastery_overview($courseid, [
    'userid' => $reportfilters['userid'],
    'targettype' => $targettype,
    'stategroup' => $parentstate,
    'parentreview' => $parentreview,
]);
$parentqueues = local_flwcupkp_parent_queue_summary($courseid, $userid);
$reviewfilters = $reportfilters;
$reviewfilters['evidence'] = 'review';
$reviewqueue = $evidencefilter === 'review' ? $report :
    \local_flwcupkp\local\teacher_report::u038_report($courseid, $reviewfilters);
$reviewevidenceids = local_flwcupkp_review_evidence_ids($reviewqueue);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacherverification', 'local_flwcupkp'));
echo html_writer::tag('p', s($course->fullname), ['class' => 'local-flwcupkp-muted']);
echo \local_flwcupkp\local\visuals::unit_nav($courseid, 'U038', $userid, true,
    $canverify && \local_flwcupkp\local\performance_service::has_tasks($courseid, 'U038'));

if ($status === 'bulkapproved') {
    echo $OUTPUT->notification(get_string('verificationbulkapproved', 'local_flwcupkp', $approvedcount), 'success');
} else if ($status !== '') {
    echo $OUTPUT->notification(get_string('verification' . $status, 'local_flwcupkp'), 'success');
}

echo local_flwcupkp_parent_queue_dashboard($parentqueues);
echo local_flwcupkp_evidence_review_dashboard(count($reviewevidenceids), $canverify, $courseid, $url);

ob_start();
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/flwcupkp/teacher_u038.php'),
    'class' => 'local-flwcupkp-filters',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

$learneroptions = [0 => get_string('alllearners', 'local_flwcupkp')];
foreach ($report['learners'] as $learner) {
    $learneroptions[$learner->id] = fullname($learner);
}

echo html_writer::tag('label', get_string('learner', 'local_flwcupkp') .
    html_writer::select($learneroptions, 'userid', $userid, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('kpdomain', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $report['filters']['domains'], 'domain', $domain, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('lesson', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $report['filters']['lessons'], 'lesson', $lesson, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('state', 'local_flwcupkp') .
    html_writer::select(['' => get_string('all', 'local_flwcupkp')] + $report['filters']['states'], 'state', $state, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('evidencefilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_evidence_filter_options(), 'evidence', $evidencefilter, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parenttargetfilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_parent_targettype_options(), 'targettype', $targettype, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parentstatefilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_parent_state_options(), 'parentstate', $parentstate, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('label', get_string('parentreviewfilter', 'local_flwcupkp') .
    html_writer::select(local_flwcupkp_parent_review_options(), 'parentreview', $parentreview, false), ['class' => 'local-flwcupkp-filter']);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link(new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]), get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
$filterhtml = ob_get_clean();
$filtersopen = $userid || $domain !== '' || $lesson !== '' || $state !== '' || $evidencefilter !== '' ||
    $targettype !== '' || $parentstate !== '' || $parentreview !== '';
echo \local_flwcupkp\local\visuals::details_panel(get_string('filters', 'local_flwcupkp'), $filterhtml,
    $filtersopen, 'local-flwcupkp-filter-panel');

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary']);
echo html_writer::tag('span', get_string('learners', 'local_flwcupkp') . ': ' . count($report['learners']));
echo html_writer::tag('span', get_string('learningpoints', 'local_flwcupkp') . ': ' . count($report['targets']));
echo html_writer::tag('span', get_string('rows', 'local_flwcupkp') . ': ' . count($report['rows']));
echo html_writer::tag('span', get_string('competenciesachieved', 'local_flwcupkp') . ': ' .
    (int)$overview['summary']['competency_achieved'] . '/' . (int)$overview['summary']['competency_total']);
echo html_writer::tag('span', get_string('upsdemonstrated', 'local_flwcupkp') . ': ' .
    (int)$overview['summary']['up_demonstrated'] . '/' . (int)$overview['summary']['up_total']);
echo html_writer::end_tag('div');

echo \local_flwcupkp\local\visuals::teacher_heatmap($report, $url);

echo html_writer::start_tag('section', [
    'class' => 'local-flwcupkp-overview',
    'id' => 'flwcupkp-u038-mastery-overview',
]);
echo html_writer::tag('h3', get_string('masteryoverviewu038', 'local_flwcupkp'));
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
    $rowanchor = local_flwcupkp_parent_row_anchor($row);
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
            $evidenceparts[] = get_string('score', 'local_flwcupkp') . ' ' . format_float((float)$row['evidence_score'], 2);
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
    $tablerow = new html_table_row([
        s($row['learner']),
        s($typelabel),
        html_writer::tag('strong', s($row['externalid'])) . html_writer::tag('div', s($row['title'])),
        $statehtml,
        implode(html_writer::empty_tag('br'), $evidenceparts),
        s($row['explanation']),
    ]);
    if ($canverify) {
        $tablerow->cells[] = new html_table_cell(local_flwcupkp_parent_teacher_actions($row, $courseid, $url));
    }
    $tablerow->id = 'flwcupkp-parent-row-' . $rowanchor;
    $tablerow->attributes['data-flwcupkp-parent-row-anchor'] = $rowanchor;
    if ($focus === $rowanchor) {
        $tablerow->attributes['class'] = 'local-flwcupkp-row-focus';
        $tablerow->attributes['tabindex'] = '-1';
    }
    $overviewtable->data[] = $tablerow;
}

if (empty($overviewtable->data)) {
    $emptystring = $parentreview === 'review' ? 'allreviewedparentrowsu038' : 'noparentrowsu038';
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

$table = new html_table();
$table->attributes['class'] = 'generaltable local-flwcupkp-table';
$table->head = [
    get_string('learner', 'local_flwcupkp'),
    get_string('lesson', 'local_flwcupkp'),
    get_string('learningpoint', 'local_flwcupkp'),
    get_string('state', 'local_flwcupkp'),
    get_string('evidencesource', 'local_flwcupkp'),
    get_string('explanation', 'local_flwcupkp'),
];
if ($canverify) {
    $table->head[] = get_string('teacheractions', 'local_flwcupkp');
}

foreach ($report['rows'] as $row) {
    $rowanchor = local_flwcupkp_row_anchor($row);
    $activityurl = ($row['cmid'] && $row['modname']) ?
        new moodle_url('/mod/' . $row['modname'] . '/view.php', ['id' => $row['cmid']]) : null;
    $sourceparts = [];
    if ($activityurl) {
        $sourceparts[] = html_writer::link($activityurl, s($row['object_title']));
    } else {
        $sourceparts[] = s($row['object_title']);
    }
    if (!empty($row['cmid'])) {
        $sourceparts[] = get_string('activityid', 'local_flwcupkp') . ' ' . (int)$row['cmid'];
    }
    if ($row['attempt_id']) {
        $sourceparts[] = get_string('attempt', 'local_flwcupkp') . ' ' . (int)$row['attempt_id'];
    }
    if ($row['evidence_score'] !== null) {
        $sourceparts[] = get_string('score', 'local_flwcupkp') . ' ' . format_float((float)$row['evidence_score'], 2);
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
        $statehtml .= html_writer::tag('div', get_string('manualoverride', 'local_flwcupkp'), ['class' => 'local-flwcupkp-override']);
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
        $cells[] = local_flwcupkp_teacher_actions($row, $courseid, $url);
    }

    $tablerow = new html_table_row($cells);
    $tablerow->id = 'flwcupkp-row-' . $rowanchor;
    $tablerow->attributes['data-flwcupkp-row-anchor'] = $rowanchor;
    if ($focus === $rowanchor) {
        $tablerow->attributes['class'] = 'local-flwcupkp-row-focus';
        $tablerow->attributes['tabindex'] = '-1';
    }
    $table->data[] = $tablerow;
}

if (empty($table->data)) {
    $emptystring = $evidencefilter === 'review' ? 'allreviewedrowsu038' : 'noreportrows';
    echo $OUTPUT->notification(get_string($emptystring, 'local_flwcupkp'), 'info');
} else {
    echo \local_flwcupkp\local\visuals::details_panel(
        get_string('learningpointevidence', 'local_flwcupkp') . ' (' . count($table->data) . ')',
        html_writer::table($table),
        $evidencefilter !== '' || $domain !== '' || $lesson !== '' || $state !== '' || str_contains($focus, '-kp')
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
 * Approve visible U038 evidence rows that are still in the review queue.
 *
 * @param int $courseid
 * @param array $filters
 * @return int
 */
function local_flwcupkp_bulk_approve_review_evidence(int $courseid, array $filters): int {
    $filters['evidence'] = 'review';
    $report = \local_flwcupkp\local\teacher_report::u038_report($courseid, $filters);
    $evidenceids = local_flwcupkp_review_evidence_ids($report);
    foreach ($evidenceids as $evidenceid) {
        \local_flwcupkp\local\teacher_report::record_teacher_action($courseid, 'approve', [
            'evidenceid' => $evidenceid,
        ]);
    }
    return count($evidenceids);
}

/**
 * Unique evidence IDs from a report result.
 *
 * @param array $report
 * @return array
 */
function local_flwcupkp_review_evidence_ids(array $report): array {
    $ids = [];
    foreach ($report['rows'] ?? [] as $row) {
        $evidenceid = (int)($row['evidence_id'] ?? 0);
        if ($evidenceid > 0) {
            $ids[$evidenceid] = $evidenceid;
        }
    }
    return array_values($ids);
}

/**
 * Render the evidence review queue card.
 *
 * @param int $reviewcount
 * @param bool $canverify
 * @param int $courseid
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_evidence_review_dashboard(int $reviewcount, bool $canverify, int $courseid,
        moodle_url $url): string {
    $reviewurl = clone $url;
    $reviewurl->remove_params('focus', 'status', 'approvedcount');
    $reviewurl->param('evidence', 'review');

    $html = html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-queue-dashboard local-flwcupkp-evidence-review',
    ]);
    $html .= html_writer::tag('h3', get_string('evidencereviewqueueu038', 'local_flwcupkp'));
    if ($reviewcount <= 0) {
        $html .= html_writer::tag('p', get_string('evidencereviewqueueempty', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-queue-complete',
        ]);
        $html .= html_writer::link($reviewurl, get_string('openreviewqueue', 'local_flwcupkp'), [
            'class' => 'btn btn-link btn-sm',
        ]);
        return $html . html_writer::end_tag('section');
    }

    $html .= html_writer::tag('p', get_string('evidencereviewqueuedetail', 'local_flwcupkp', $reviewcount));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    $html .= html_writer::link($reviewurl, get_string('openreviewqueue', 'local_flwcupkp'), [
        'class' => 'btn btn-secondary btn-sm',
    ]);
    if ($canverify) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'local-flwcupkp-actionform',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulkapprove']);
        $html .= html_writer::tag('button', get_string('approvevisiblereviewevidence', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-primary btn-sm',
        ]);
        $html .= html_writer::end_tag('form');
    }
    $html .= html_writer::end_tag('div');
    $html .= html_writer::tag('p', get_string('bulkapprovehint', 'local_flwcupkp'), [
        'class' => 'local-flwcupkp-muted',
    ]);
    $html .= html_writer::end_tag('section');

    return $html;
}

/**
 * Build a stable teacher-report row anchor.
 *
 * @param array $row
 * @return string
 */
function local_flwcupkp_row_anchor(array $row): string {
    return 'u' . (int)$row['userid'] . '-kp' . (int)$row['kp_id'];
}

/**
 * Build a stable parent mastery row anchor.
 *
 * @param array $row
 * @return string
 */
function local_flwcupkp_parent_row_anchor(array $row): string {
    $prefix = $row['targettype'] === 'competency' ? 'comp' : 'up';
    return 'u' . (int)$row['userid'] . '-' . $prefix . (int)$row['targetid'];
}

/**
 * Build parent queue summary data for the current class or selected learner.
 *
 * @param int $courseid
 * @param int $userid
 * @return array
 */
function local_flwcupkp_parent_queue_summary(int $courseid, int $userid): array {
    $basefilters = [];
    if ($userid > 0) {
        $basefilters['userid'] = $userid;
    }

    $competencyqueue = \local_flwcupkp\local\teacher_report::u038_mastery_overview($courseid, $basefilters + [
        'targettype' => 'competency',
        'stategroup' => 'notachieved',
        'parentreview' => 'review',
    ]);
    $upqueue = \local_flwcupkp\local\teacher_report::u038_mastery_overview($courseid, $basefilters + [
        'targettype' => 'up',
        'stategroup' => 'notdemonstrated',
        'parentreview' => 'review',
    ]);

    $decidedurl = new moodle_url('/local/flwcupkp/teacher_u038.php', [
        'courseid' => $courseid,
        'parentreview' => 'decided',
    ]);
    if ($userid > 0) {
        $decidedurl->param('userid', $userid);
    }
    $decidedurl->set_anchor('flwcupkp-u038-mastery-overview');

    return [
        'competency' => [
            'label' => get_string('competencyqueueu038', 'local_flwcupkp'),
            'openlabel' => get_string('opencompetencyqueueu038', 'local_flwcupkp'),
            'count' => count($competencyqueue['rows']),
            'first' => reset($competencyqueue['rows']) ?: null,
            'url' => local_flwcupkp_parent_queue_url($courseid, $userid, 'competency', 'notachieved',
                $competencyqueue['rows']),
        ],
        'up' => [
            'label' => get_string('upqueueu038', 'local_flwcupkp'),
            'openlabel' => get_string('openupqueueu038', 'local_flwcupkp'),
            'count' => count($upqueue['rows']),
            'first' => reset($upqueue['rows']) ?: null,
            'url' => local_flwcupkp_parent_queue_url($courseid, $userid, 'up', 'notdemonstrated',
                $upqueue['rows']),
        ],
        'decidedurl' => $decidedurl,
        'total' => count($competencyqueue['rows']) + count($upqueue['rows']),
    ];
}

/**
 * Build a filtered parent queue URL.
 *
 * @param int $courseid
 * @param int $userid
 * @param string $targettype
 * @param string $parentstate
 * @param array $rows
 * @return moodle_url
 */
function local_flwcupkp_parent_queue_url(int $courseid, int $userid, string $targettype, string $parentstate,
        array $rows): moodle_url {
    $url = new moodle_url('/local/flwcupkp/teacher_u038.php', [
        'courseid' => $courseid,
        'targettype' => $targettype,
        'parentstate' => $parentstate,
        'parentreview' => 'review',
    ]);
    if ($userid > 0) {
        $url->param('userid', $userid);
    }

    $first = reset($rows);
    if ($first) {
        $anchor = local_flwcupkp_parent_row_anchor($first);
        $url->param('focus', $anchor);
        $url->set_anchor('flwcupkp-parent-row-' . $anchor);
    } else {
        $url->set_anchor('flwcupkp-u038-mastery-overview');
    }

    return $url;
}

/**
 * Render the parent queue summary dashboard.
 *
 * @param array $queues
 * @return string
 */
function local_flwcupkp_parent_queue_dashboard(array $queues): string {
    $html = html_writer::start_tag('section', [
        'class' => 'local-flwcupkp-queue-dashboard',
        'id' => 'flwcupkp-u038-parent-queue-dashboard',
    ]);
    $html .= html_writer::tag('h3', get_string('parentqueuesummaryu038', 'local_flwcupkp'));
    if ((int)$queues['total'] === 0) {
        $html .= html_writer::tag('p', get_string('parentqueuecompleteu038', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-queue-complete',
        ]);
    }

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-queue-grid']);
    $html .= local_flwcupkp_parent_queue_card($queues['competency']);
    $html .= local_flwcupkp_parent_queue_card($queues['up']);
    $html .= html_writer::end_tag('div');

    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-formactions']);
    foreach (['competency', 'up'] as $key) {
        if ((int)$queues[$key]['count'] > 0) {
            $html .= html_writer::link($queues[$key]['url'], s($queues[$key]['openlabel']), [
                'class' => 'btn btn-secondary btn-sm',
            ]);
        }
    }
    $html .= html_writer::link($queues['decidedurl'], get_string('parentdecisionsrecorded', 'local_flwcupkp'), [
        'class' => 'btn btn-link btn-sm',
    ]);
    $html .= html_writer::end_tag('div');
    $html .= html_writer::end_tag('section');

    return $html;
}

/**
 * Render one parent queue card.
 *
 * @param array $queue
 * @return string
 */
function local_flwcupkp_parent_queue_card(array $queue): string {
    $content = html_writer::tag('strong', (string)(int)$queue['count']) .
        html_writer::tag('em', s($queue['label']));

    if (!empty($queue['first'])) {
        $first = $queue['first'];
        $content .= html_writer::tag('span', get_string('parentqueuenext', 'local_flwcupkp') . ': ' .
            s($first['learner']) . ' - ' . s($first['externalid']), ['class' => 'local-flwcupkp-queue-next']);
        $content .= html_writer::tag('span', s($first['title']), ['class' => 'local-flwcupkp-muted']);
        return html_writer::link($queue['url'], $content, ['class' => 'local-flwcupkp-queue-card']);
    }

    $content .= html_writer::tag('span', get_string('parentqueueemptyu038', 'local_flwcupkp'), [
        'class' => 'local-flwcupkp-queue-next',
    ]);
    return html_writer::tag('span', $content, ['class' => 'local-flwcupkp-queue-card']);
}

/**
 * Build the post-action redirect for the parent UP/competency review queue.
 *
 * @param int $courseid
 * @param array $filters
 * @param string $actiontargettype
 * @param int $userid
 * @param int $targetid
 * @param string $status
 * @return moodle_url
 */
function local_flwcupkp_next_parent_review_url(int $courseid, array $filters, string $actiontargettype, int $userid,
        int $targetid, string $status): moodle_url {
    $redirect = new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $courseid]);
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
        $parentstate = $targettype === 'competency' ? 'notachieved' : ($targettype === 'up' ? 'notdemonstrated' : 'attention');
    }
    if ($parentstate !== '') {
        $redirect->param('parentstate', $parentstate);
    }

    $parentreview = (string)($filters['parentreview'] ?? '');
    if ($parentreview === '') {
        $parentreview = 'review';
    }
    $redirect->param('parentreview', $parentreview);

    $review = \local_flwcupkp\local\teacher_report::u038_mastery_overview($courseid, [
        'userid' => $currentuserid,
        'targettype' => $targettype,
        'stategroup' => $parentstate,
        'parentreview' => $parentreview,
    ]);
    $nextrow = reset($review['rows']);
    if ($nextrow) {
        $nextanchor = local_flwcupkp_parent_row_anchor($nextrow);
        $redirect->param('focus', $nextanchor);
        $redirect->set_anchor('flwcupkp-parent-row-' . $nextanchor);
    } else {
        $redirect->set_anchor('flwcupkp-u038-mastery-overview');
    }

    $redirect->param('status', $status);
    return $redirect;
}

/**
 * Build the post-action redirect for the teacher review queue.
 *
 * @param int $courseid
 * @param moodle_url $url
 * @param string $status
 * @param array $filters
 * @return moodle_url
 */
function local_flwcupkp_next_review_url(int $courseid, moodle_url $url, string $status, array $filters): moodle_url {
    $redirect = clone $url;
    $redirect->remove_params('focus', 'status');
    $redirect->set_anchor(null);
    $redirect->param('evidence', 'review');

    $reviewfilters = $filters;
    $reviewfilters['evidence'] = 'review';
    $reviewreport = \local_flwcupkp\local\teacher_report::u038_report($courseid, $reviewfilters);
    $nextrow = reset($reviewreport['rows']);
    if ($nextrow) {
        $nextanchor = local_flwcupkp_row_anchor($nextrow);
        $redirect->param('focus', $nextanchor);
        $redirect->set_anchor('flwcupkp-row-' . $nextanchor);
    }

    $redirect->param('status', $status);
    return $redirect;
}

/**
 * Render teacher verification controls for a report row.
 *
 * @param array $row
 * @param int $courseid
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_teacher_actions(array $row, int $courseid, moodle_url $url): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-actions']);

    if ($row['evidence_id']) {
        $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approve']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'evidenceid', 'value' => $row['evidence_id']]);
        $html .= html_writer::tag('button', get_string('approveevidence', 'local_flwcupkp'), ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']);
        $html .= html_writer::end_tag('form');
    }

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform local-flwcupkp-overrideform']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'override']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $stateid = 'override-state-' . $row['userid'] . '-' . clean_param($row['kp_externalid'], PARAM_ALPHANUMEXT);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['kp_id']]);
    $html .= html_writer::label(get_string('state', 'local_flwcupkp'), $stateid, false, ['class' => 'accesshide']);
    $html .= html_writer::select(\local_flwcupkp\local\teacher_report::KP_STATES, 'overridestate', $row['state'], false, [
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
    $html .= html_writer::tag('button', get_string('overridestate', 'local_flwcupkp'), ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']);
    $html .= html_writer::end_tag('form');

    if ($row['manual_override']) {
        $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'clearoverride']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['kp_id']]);
        $html .= html_writer::tag('button', get_string('clearoverride', 'local_flwcupkp'), ['type' => 'submit', 'class' => 'btn btn-link btn-sm']);
        $html .= html_writer::end_tag('form');
    }

    return $html . html_writer::end_tag('div');
}

/**
 * Render teacher verification controls for a parent UP/competency row.
 *
 * @param array $row
 * @param int $courseid
 * @param moodle_url $url
 * @return string
 */
function local_flwcupkp_parent_teacher_actions(array $row, int $courseid, moodle_url $url): string {
    $html = html_writer::start_tag('div', ['class' => 'local-flwcupkp-actions']);

    $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'local-flwcupkp-actionform']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approveparent']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype', 'value' => $row['targettype']]);
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
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'overrideparent']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype', 'value' => $row['targettype']]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['targetid']]);
    $stateid = 'parent-override-state-' . $row['userid'] . '-' . clean_param($row['targettype'] . '-' .
        $row['externalid'], PARAM_ALPHANUMEXT);
    $html .= html_writer::label(get_string('state', 'local_flwcupkp'), $stateid, false, ['class' => 'accesshide']);
    $html .= html_writer::select(local_flwcupkp_parent_override_state_options($row['targettype']),
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
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'clearparentoverride']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $row['userid']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parenttargettype', 'value' => $row['targettype']]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetid', 'value' => $row['targetid']]);
        $html .= html_writer::tag('button', get_string('clearoverride', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-link btn-sm',
        ]);
        $html .= html_writer::end_tag('form');
    }

    return $html . html_writer::end_tag('div');
}

/**
 * Parent state override options for a row type.
 *
 * @param string $targettype
 * @return array
 */
function local_flwcupkp_parent_override_state_options(string $targettype): array {
    if ($targettype === 'up') {
        return \local_flwcupkp\local\teacher_report::UP_STATES;
    }
    return \local_flwcupkp\local\teacher_report::COMPETENCY_STATES;
}

/**
 * Evidence filter options for the teacher verification page.
 *
 * @return array
 */
function local_flwcupkp_evidence_filter_options(): array {
    return [
        '' => get_string('evidencefilterall', 'local_flwcupkp'),
        'with' => get_string('evidencefilterwith', 'local_flwcupkp'),
        'verified' => get_string('evidencefilterverified', 'local_flwcupkp'),
        'review' => get_string('evidencefilterreview', 'local_flwcupkp'),
    ];
}

/**
 * Parent target type filter options.
 *
 * @return array
 */
function local_flwcupkp_parent_targettype_options(): array {
    return [
        '' => get_string('all', 'local_flwcupkp'),
        'competency' => get_string('competency', 'local_flwcupkp'),
        'up' => get_string('usepoint', 'local_flwcupkp'),
    ];
}

/**
 * Parent mastery state-group filter options.
 *
 * @return array
 */
function local_flwcupkp_parent_state_options(): array {
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
 * Parent teacher-decision filter options.
 *
 * @return array
 */
function local_flwcupkp_parent_review_options(): array {
    return [
        '' => get_string('parentreviewall', 'local_flwcupkp'),
        'review' => get_string('parentreviewneedsdecision', 'local_flwcupkp'),
        'decided' => get_string('parentreviewdecided', 'local_flwcupkp'),
    ];
}
