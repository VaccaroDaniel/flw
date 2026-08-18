<?php
// Admin Evidence Sync Health page for C-UP-KP.

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$historystatus = optional_param('historystatus', '', PARAM_ALPHAEXT);
$historylimit = local_flwcupkp_evidence_sync_history_limit(optional_param('historylimit', 100, PARAM_INT));

require_login();
$context = context_system::instance();
require_capability('local/flwcupkp:synccompetencies', $context);

$courseoptions = local_flwcupkp_evidence_sync_course_options();
if ($courseid <= 0 && $courseoptions) {
    $courseid = (int)array_key_first($courseoptions);
}

$unitoptions = $courseid > 0 ? local_flwcupkp_evidence_sync_unit_options($courseid) : [];
if ($unitcode !== '' && !array_key_exists($unitcode, $unitoptions)) {
    $unitcode = '';
}

$url = local_flwcupkp_evidence_sync_url($courseid, $unitcode, $historystatus, $historylimit);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHANUMEXT);
    try {
        if ($courseid <= 0) {
            throw new invalid_parameter_exception('A mapped Moodle course is required.');
        }

        if ($action === 'repair_pending_quiz_attempts') {
            $result = \local_flwcupkp\local\evidence_sync_repair::repair_pending_quiz_attempts($courseid, $unitcode);
            if (($result['status'] ?? '') === 'none') {
                redirect($url, get_string('repairquizsyncallnone', 'local_flwcupkp'), null,
                    \core\output\notification::NOTIFY_INFO);
            }

            $message = get_string('repairquizsyncallsuccess', 'local_flwcupkp', (object)[
                'found' => (int)($result['found'] ?? 0),
                'processed' => (int)($result['processed'] ?? 0),
                'created' => (int)($result['created'] ?? 0),
                'failed' => (int)($result['failed'] ?? 0),
            ]);
            $type = empty($result['failed']) ? \core\output\notification::NOTIFY_SUCCESS :
                \core\output\notification::NOTIFY_WARNING;
            redirect($url, $message, null, $type);
        }

        if ($action === 'repair_quiz_attempt') {
            $attemptid = required_param('attemptid', PARAM_INT);
            $result = \local_flwcupkp\local\evidence_sync_repair::repair_quiz_attempt($attemptid, $courseid, $unitcode);
            $created = count($result['evidenceids'] ?? []);
            if (($result['status'] ?? '') === 'processed' && $created > 0) {
                redirect($url, get_string('repairquizsyncsuccess', 'local_flwcupkp', (object)[
                    'attemptid' => $attemptid,
                    'count' => $created,
                ]), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            if (($result['status'] ?? '') === 'processed') {
                redirect($url, get_string('repairquizsyncnone', 'local_flwcupkp', $attemptid), null,
                    \core\output\notification::NOTIFY_INFO);
            }
            redirect($url, get_string('repairquizsyncignored', 'local_flwcupkp', (object)[
                'attemptid' => $attemptid,
                'reason' => (string)($result['reason'] ?? 'unknown'),
            ]), null, \core\output\notification::NOTIFY_WARNING);
        }

        redirect($url, get_string('invalidrequest', 'error'), null, \core\output\notification::NOTIFY_ERROR);
    } catch (Throwable $e) {
        \local_flwcupkp\local\repository::audit('quiz_evidence_repair_failed', 'quiz_attempt',
            optional_param('attemptid', 0, PARAM_INT), [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'action' => $action,
                'message' => $e->getMessage(),
                'source' => 'evidence_sync_health_page',
            ]);
        $message = $action === 'repair_pending_quiz_attempts' ?
            get_string('repairquizsyncallfailed', 'local_flwcupkp', $e->getMessage()) :
            get_string('repairquizsyncfailed', 'local_flwcupkp', (object)[
                'attemptid' => optional_param('attemptid', 0, PARAM_INT),
                'message' => $e->getMessage(),
            ]);
        redirect($url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('evidencesynchealth', 'local_flwcupkp'));
$PAGE->set_heading(get_string('evidencesynchealth', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$scopes = $courseid > 0 ? [[
    'courseid' => $courseid,
    'unitcode' => $unitcode,
]] : [];
$pendingcount = $scopes ?
    \local_flwcupkp\local\evidence_sync_repair::pending_quiz_attempt_count_for_scopes($scopes) : 0;
$pendingrows = $courseid > 0 ?
    \local_flwcupkp\local\evidence_sync_repair::pending_quiz_attempts($courseid, $unitcode, 100) : [];
$history = $scopes ?
    \local_flwcupkp\local\evidence_sync_repair::recent_repair_history_for_scopes($scopes, $historylimit, true) : [];
$history = local_flwcupkp_evidence_sync_filter_history($history, $historystatus);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evidencesynchealth', 'local_flwcupkp'));

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-sync-health-shell']);
echo html_writer::tag('p', get_string('evidencesynchealthintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-sync-health-intro',
]);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-toolbar']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/index.php'),
    get_string('cupkphome', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo \local_flwcupkp\local\visuals::nav_link(new moodle_url('/local/flwcupkp/sync.php'),
    get_string('competencysync', 'local_flwcupkp'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('div');

if (!$courseoptions) {
    echo $OUTPUT->notification(get_string('evidencesynchealthnocourses', 'local_flwcupkp'), 'warning');
    echo html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

echo local_flwcupkp_evidence_sync_filter_form($url, $courseoptions, $courseid, $unitoptions, $unitcode,
    $historystatus, $historylimit);

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-summary local-flwcupkp-sync-health-summary']);
echo html_writer::tag('span', get_string('evidencesynchealthpendingmetric', 'local_flwcupkp', $pendingcount));
echo html_writer::tag('span', get_string('evidencesynchealthhistorymetric', 'local_flwcupkp', count($history)));
echo html_writer::tag('span', get_string('evidencesynchealthscopemetric', 'local_flwcupkp',
    local_flwcupkp_evidence_sync_scope_label($courseoptions, $courseid, $unitcode)));
echo html_writer::end_tag('div');

echo local_flwcupkp_evidence_sync_pending_panel($url, $pendingrows, $pendingcount, $courseid, $unitcode);
echo local_flwcupkp_evidence_sync_history_panel($history, $courseoptions);

echo html_writer::end_tag('div');
echo $OUTPUT->footer();

/**
 * Stable page URL for the selected filters.
 */
function local_flwcupkp_evidence_sync_url(int $courseid, string $unitcode, string $historystatus,
        int $historylimit): moodle_url {
    $params = [
        'historylimit' => $historylimit,
    ];
    if ($courseid > 0) {
        $params['courseid'] = $courseid;
    }
    if ($unitcode !== '') {
        $params['unitcode'] = $unitcode;
    }
    if ($historystatus !== '') {
        $params['historystatus'] = $historystatus;
    }
    return new moodle_url('/local/flwcupkp/evidence_sync.php', $params);
}

/**
 * Normalize the history limit to one of the UI choices.
 */
function local_flwcupkp_evidence_sync_history_limit(int $historylimit): int {
    $allowed = [25, 50, 100, 200];
    return in_array($historylimit, $allowed, true) ? $historylimit : 100;
}

/**
 * Course options that have imported C-UP-KP objects.
 */
function local_flwcupkp_evidence_sync_course_options(): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname
           FROM {flwcupkp_object} o
           JOIN {course} c ON c.id = o.courseid
          WHERE o.courseid IS NOT NULL
            AND o.courseid > 0
       ORDER BY c.fullname ASC, c.shortname ASC"
    );

    $options = [];
    foreach ($records as $record) {
        $label = format_string($record->fullname);
        if ((string)$record->shortname !== '') {
            $label .= ' (' . format_string($record->shortname) . ')';
        }
        $options[(int)$record->id] = $label;
    }
    return $options;
}

/**
 * Unit options for one mapped course.
 */
function local_flwcupkp_evidence_sync_unit_options(int $courseid): array {
    global $DB;

    $records = $DB->get_records_sql(
        "SELECT DISTINCT unitcode
           FROM {flwcupkp_object}
          WHERE courseid = :courseid
            AND unitcode IS NOT NULL
            AND unitcode <> ''
       ORDER BY unitcode ASC",
        ['courseid' => $courseid]
    );

    $options = ['' => get_string('allunits', 'local_flwcupkp')];
    foreach ($records as $record) {
        $options[(string)$record->unitcode] = (string)$record->unitcode;
    }
    return $options;
}

/**
 * Render filter controls.
 */
function local_flwcupkp_evidence_sync_filter_form(moodle_url $url, array $courseoptions, int $courseid,
        array $unitoptions, string $unitcode, string $historystatus, int $historylimit): string {
    $statusoptions = [
        '' => get_string('evidencesynchealthhistoryall', 'local_flwcupkp'),
        'completed' => get_string('repairhistorybadgecompleted', 'local_flwcupkp'),
        'queued' => get_string('repairhistorybadgequeued', 'local_flwcupkp'),
        'requested' => get_string('repairhistorybadgerequested', 'local_flwcupkp'),
        'warning' => get_string('repairhistorybadgewarning', 'local_flwcupkp'),
        'failed' => get_string('repairhistorybadgefailed', 'local_flwcupkp'),
    ];
    $limitoptions = [
        25 => '25',
        50 => '50',
        100 => '100',
        200 => '200',
    ];

    $html = html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $url->out_omit_querystring(),
        'class' => 'local-flwcupkp-sync-health-filters',
    ]);
    $html .= local_flwcupkp_evidence_sync_filter_select('courseid', get_string('course'), $courseoptions,
        $courseid, 'local-flwcupkp-sync-filter-course');
    $html .= local_flwcupkp_evidence_sync_filter_select('unitcode', get_string('field_unitcode', 'local_flwcupkp'),
        $unitoptions, $unitcode, '');
    $html .= local_flwcupkp_evidence_sync_filter_select('historystatus',
        get_string('evidencesynchealthhistorystatus', 'local_flwcupkp'), $statusoptions, $historystatus, '');
    $html .= local_flwcupkp_evidence_sync_filter_select('historylimit',
        get_string('evidencesynchealthhistorylimit', 'local_flwcupkp'), $limitoptions, $historylimit, '');
    $html .= html_writer::tag('button', get_string('applyfilters', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    $html .= html_writer::end_tag('form');

    return $html;
}

/**
 * Render one labeled select filter.
 */
function local_flwcupkp_evidence_sync_filter_select(string $name, string $label, array $options, $selected,
        string $extra_class): string {
    $id = 'local-flwcupkp-sync-' . $name;
    $classes = trim('local-flwcupkp-filter ' . $extra_class);
    $html = html_writer::start_tag('label', ['for' => $id, 'class' => $classes]);
    $html .= html_writer::tag('span', s($label));
    $html .= html_writer::select($options, $name, $selected, false, [
        'id' => $id,
        'class' => 'custom-select',
    ]);
    $html .= html_writer::end_tag('label');
    return $html;
}

/**
 * Render the pending attempt queue.
 */
function local_flwcupkp_evidence_sync_pending_panel(moodle_url $url, array $pendingrows, int $pendingcount,
        int $courseid, string $unitcode): string {
    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-sync-health-panel']);
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-sync-health-panel-head']);
    $html .= html_writer::tag('h3', get_string('evidencesynchealthpendingtitle', 'local_flwcupkp'));
    $html .= html_writer::tag('p', get_string('evidencesynchealthpendingintro', 'local_flwcupkp'));
    $html .= html_writer::end_tag('div');

    if ($pendingcount > 0) {
        $html .= html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $url->out(false),
            'class' => 'local-flwcupkp-actionform local-flwcupkp-sync-health-repair-all',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'repair_pending_quiz_attempts',
        ]);
        $html .= html_writer::tag('button', get_string('healthrepairallquizsync', 'local_flwcupkp'), [
            'type' => 'submit',
            'class' => 'btn btn-primary',
        ]);
        $html .= html_writer::end_tag('form');
    }

    if (!$pendingrows) {
        $html .= html_writer::tag('p', get_string('evidencesynchealthpendingempty', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted',
        ]);
        $html .= html_writer::end_tag('section');
        return $html;
    }

    if ($pendingcount > count($pendingrows)) {
        $html .= html_writer::tag('p', get_string('evidencesynchealthpendinglimited', 'local_flwcupkp',
            (object)['shown' => count($pendingrows), 'total' => $pendingcount]), ['class' => 'local-flwcupkp-muted']);
    }

    $users = local_flwcupkp_evidence_sync_users_for_rows($pendingrows);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-sync-health-table';
    $table->head = [
        get_string('attempt', 'local_flwcupkp'),
        get_string('learner', 'local_flwcupkp'),
        get_string('evidencesynchealthquizactivity', 'local_flwcupkp'),
        get_string('evidencesynchealthobject', 'local_flwcupkp'),
        get_string('evidencesynchealthfinished', 'local_flwcupkp'),
        get_string('action', 'local_flwcupkp'),
    ];
    foreach ($pendingrows as $row) {
        $userid = (int)$row->userid;
        $user = $users[$userid] ?? null;
        $table->data[] = [
            '#' . (int)$row->attemptid,
            $user ? fullname($user) : get_string('unknownuser'),
            s((string)$row->quizname) . html_writer::tag('div', get_string('evidencesynchealthcmid',
                'local_flwcupkp', (int)$row->cmid), ['class' => 'local-flwcupkp-muted']),
            s((string)$row->externalid) . html_writer::tag('div', s((string)$row->title),
                ['class' => 'local-flwcupkp-muted']),
            userdate((int)$row->timefinish, get_string('strftimedatetimeshort', 'langconfig')),
            local_flwcupkp_evidence_sync_single_repair_form($url, (int)$row->attemptid),
        ];
    }
    $html .= html_writer::table($table);
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Render the per-attempt repair form for a queue row.
 */
function local_flwcupkp_evidence_sync_single_repair_form(moodle_url $url, int $attemptid): string {
    $html = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
        'class' => 'local-flwcupkp-sync-health-row-action',
    ]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'repair_quiz_attempt']);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attemptid', 'value' => $attemptid]);
    $html .= html_writer::tag('button', get_string('healthrepairquizsync', 'local_flwcupkp'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary btn-sm',
    ]);
    $html .= html_writer::end_tag('form');
    return $html;
}

/**
 * Users referenced by pending rows.
 */
function local_flwcupkp_evidence_sync_users_for_rows(array $pendingrows): array {
    global $DB;

    $userids = [];
    foreach ($pendingrows as $row) {
        $userids[(int)$row->userid] = (int)$row->userid;
    }
    if (!$userids) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'syncuser');
    return $DB->get_records_sql(
        "SELECT id, firstname, lastname, username, email
           FROM {user}
          WHERE id {$insql}",
        $params
    );
}

/**
 * Render repair audit history.
 */
function local_flwcupkp_evidence_sync_history_panel(array $history, array $courseoptions): string {
    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-sync-health-panel']);
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-sync-health-panel-head']);
    $html .= html_writer::tag('h3', get_string('evidencesynchealthhistorytitle', 'local_flwcupkp'));
    $html .= html_writer::tag('p', get_string('evidencesynchealthhistoryintro', 'local_flwcupkp'));
    $html .= html_writer::end_tag('div');

    if (!$history) {
        $html .= html_writer::tag('p', get_string('repairhistoryempty', 'local_flwcupkp'), [
            'class' => 'local-flwcupkp-muted',
        ]);
        $html .= html_writer::end_tag('section');
        return $html;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-flwcupkp-sync-health-table';
    $table->head = [
        get_string('evidencesynchealthtime', 'local_flwcupkp'),
        get_string('field_status', 'local_flwcupkp'),
        get_string('evidencesynchealthevent', 'local_flwcupkp'),
        get_string('evidencesynchealthscope', 'local_flwcupkp'),
        get_string('evidencesynchealthtarget', 'local_flwcupkp'),
        get_string('evidencesynchealthdetails', 'local_flwcupkp'),
        get_string('evidencesynchealthuser', 'local_flwcupkp'),
    ];
    foreach ($history as $row) {
        $status = local_flwcupkp_evidence_sync_history_status($row);
        $table->data[] = [
            userdate((int)$row['timecreated'], get_string('strftimedatetimeshort', 'langconfig')),
            html_writer::tag('span', s(local_flwcupkp_evidence_sync_history_badge($status)), [
                'class' => 'local-flwcupkp-sync-health-badge local-flwcupkp-sync-health-badge-' . $status,
            ]),
            s(local_flwcupkp_evidence_sync_history_title($row)),
            s(local_flwcupkp_evidence_sync_scope_label($courseoptions, (int)$row['courseid'],
                (string)$row['unitcode'])),
            s((string)$row['targettype'] . ':' . (int)$row['targetid']),
            s(local_flwcupkp_evidence_sync_history_detail($row)),
            s(local_flwcupkp_evidence_sync_history_user($row)),
        ];
    }
    $html .= html_writer::table($table);
    $html .= html_writer::end_tag('section');
    return $html;
}

/**
 * Filter history rows by visual status.
 */
function local_flwcupkp_evidence_sync_filter_history(array $history, string $historystatus): array {
    if ($historystatus === '') {
        return $history;
    }

    return array_values(array_filter($history, static function(array $row) use ($historystatus): bool {
        return local_flwcupkp_evidence_sync_history_status($row) === $historystatus;
    }));
}

/**
 * Visual status for one repair audit row.
 */
function local_flwcupkp_evidence_sync_history_status(array $row): string {
    $action = (string)($row['action'] ?? '');
    if ($action === 'quiz_evidence_repair_failed') {
        return 'failed';
    }
    if ($action === 'quiz_evidence_repair_requested') {
        return 'requested';
    }
    if ($action === 'quiz_evidence_repair_all_queued') {
        return 'queued';
    }
    $details = $row['details'] ?? [];
    if (is_array($details) && !empty($details['failed'])) {
        return 'warning';
    }
    return 'completed';
}

/**
 * Badge label for one repair status.
 */
function local_flwcupkp_evidence_sync_history_badge(string $status): string {
    if ($status === 'failed') {
        return get_string('repairhistorybadgefailed', 'local_flwcupkp');
    }
    if ($status === 'requested') {
        return get_string('repairhistorybadgerequested', 'local_flwcupkp');
    }
    if ($status === 'queued') {
        return get_string('repairhistorybadgequeued', 'local_flwcupkp');
    }
    if ($status === 'warning') {
        return get_string('repairhistorybadgewarning', 'local_flwcupkp');
    }
    return get_string('repairhistorybadgecompleted', 'local_flwcupkp');
}

/**
 * Title for one repair audit row.
 */
function local_flwcupkp_evidence_sync_history_title(array $row): string {
    $action = (string)($row['action'] ?? '');
    if ($action === 'quiz_evidence_repair_all_completed') {
        return get_string('repairhistorybulkcompleted', 'local_flwcupkp');
    }
    if ($action === 'quiz_evidence_repair_all_queued') {
        return get_string('repairhistorybulkqueued', 'local_flwcupkp');
    }
    if ($action === 'quiz_evidence_repair_requested') {
        return get_string('repairhistoryattemptrequested', 'local_flwcupkp', (int)($row['targetid'] ?? 0));
    }
    if ($action === 'quiz_evidence_repair_failed') {
        return get_string('repairhistoryfailed', 'local_flwcupkp');
    }
    return get_string('repairhistoryattemptcompleted', 'local_flwcupkp', (int)($row['targetid'] ?? 0));
}

/**
 * Detail text for one repair audit row.
 */
function local_flwcupkp_evidence_sync_history_detail(array $row): string {
    $action = (string)($row['action'] ?? '');
    $details = $row['details'] ?? [];
    if (!is_array($details)) {
        $details = [];
    }

    if ($action === 'quiz_evidence_repair_all_completed') {
        return get_string('repairhistorybulkdetail', 'local_flwcupkp', (object)[
            'created' => (int)($details['created'] ?? 0),
            'processed' => (int)($details['processed'] ?? 0),
            'found' => (int)($details['found'] ?? 0),
            'failed' => (int)($details['failed'] ?? 0),
        ]);
    }
    if ($action === 'quiz_evidence_repair_all_queued') {
        return get_string('repairhistoryqueuedetail', 'local_flwcupkp', (int)($details['pending'] ?? 0));
    }
    if ($action === 'quiz_evidence_repair_requested') {
        return get_string('repairhistoryrequesteddetail', 'local_flwcupkp', (string)($details['unitcode'] ?? ''));
    }
    if ($action === 'quiz_evidence_repair_failed') {
        return get_string('repairhistoryfaileddetail', 'local_flwcupkp', (object)[
            'attemptid' => (int)($row['targetid'] ?? 0),
            'message' => (string)($details['message'] ?? get_string('unknown', 'moodle')),
        ]);
    }

    $adapter = $details['adapter_result'] ?? [];
    if (!is_array($adapter)) {
        $adapter = [];
    }
    return get_string('repairhistoryattemptdetail', 'local_flwcupkp', (object)[
        'count' => count($adapter['evidenceids'] ?? []),
        'unitcode' => (string)($details['unitcode'] ?? $row['unitcode'] ?? ''),
    ]);
}

/**
 * Human user label for one audit row.
 */
function local_flwcupkp_evidence_sync_history_user(array $row): string {
    $name = trim((string)($row['firstname'] ?? '') . ' ' . (string)($row['lastname'] ?? ''));
    if ($name === '') {
        $name = (string)($row['username'] ?? '');
    }
    return $name !== '' ? $name : get_string('repairhistoryunknownuser', 'local_flwcupkp');
}

/**
 * Scope label for metrics and history rows.
 */
function local_flwcupkp_evidence_sync_scope_label(array $courseoptions, int $courseid, string $unitcode): string {
    $label = $courseoptions[$courseid] ?? get_string('courseid', 'local_flwcupkp') . ' ' . $courseid;
    if ($unitcode !== '') {
        $label .= ' / ' . $unitcode;
    } else {
        $label .= ' / ' . get_string('allunits', 'local_flwcupkp');
    }
    return $label;
}
