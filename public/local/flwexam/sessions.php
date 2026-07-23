<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

use local_flwexam\service\exam_service;

require_login();

$context = context_system::instance();
$canmanageteacher = has_capability('local/flwexam:manageteacherexams', $context) ||
    has_capability('local/flwexam:manageselfexams', $context);
$canmanageofficial = has_capability('local/flwexam:manageofficialexams', $context);
if (!$canmanageteacher && !$canmanageofficial) {
    require_capability('local/flwexam:manageofficialexams', $context);
}

$editid = optional_param('edit', 0, PARAM_INT);
$url = new moodle_url('/local/flwexam/sessions.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('manageexamsessions', 'local_flwexam'));
$PAGE->set_heading(get_string('manageexamsessions', 'local_flwexam'));
local_flwexam_require_styles();

$output = $PAGE->get_renderer('core');

if (!$DB->get_manager()->table_exists('local_flwexam_sessions')) {
    echo $output->header();
    echo html_writer::start_div('flwexam-page');
    echo $output->notification(get_string('pluginnotinstalled', 'local_flwexam'), 'warning');
    echo html_writer::end_div();
    echo $output->footer();
    exit;
}

$deleteid = optional_param('delete', 0, PARAM_INT);
if ($deleteid > 0) {
    require_sesskey();
    $deletesession = $DB->get_record('local_flwexam_sessions', ['id' => $deleteid], '*', MUST_EXIST);
    local_flwexam_require_session_manage_capability($deletesession->sessiontype, $canmanageteacher, $canmanageofficial, $context);
    $DB->delete_records('local_flwexam_sessions', ['id' => $deleteid]);
    redirect($url, get_string('examsessiondeleted', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$editing = null;
if ($editid > 0) {
    $editing = $DB->get_record('local_flwexam_sessions', ['id' => $editid], '*', MUST_EXIST);
}

$formerrors = [];
if (data_submitted() && confirm_sesskey() && optional_param('action', '', PARAM_ALPHA) === 'save') {
    $submittedid = optional_param('id', 0, PARAM_INT);
    $sessiontype = optional_param('sessiontype', '', PARAM_ALPHA);
    if (!in_array($sessiontype, [exam_service::SESSION_TYPE_TEACHER, exam_service::SESSION_TYPE_OFFICIAL], true)) {
        $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
        $sessiontype = exam_service::SESSION_TYPE_TEACHER;
    }
    local_flwexam_require_session_manage_capability($sessiontype, $canmanageteacher, $canmanageofficial, $context);

    $name = trim(optional_param('name', '', PARAM_TEXT));
    if ($name === '') {
        $formerrors[] = get_string('sessionnamerequired', 'local_flwexam');
    }

    $examid = optional_param('examid', 0, PARAM_INT);
    if (!$DB->record_exists('local_flwexam_exams', ['id' => $examid, 'visible' => 1])) {
        $formerrors[] = get_string('invalidexamprofile', 'local_flwexam');
    }
    $status = optional_param('status', '', PARAM_ALPHA);
    if (!isset(exam_service::get_session_status_options()[$status])) {
        $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
        $status = 'open';
    }

    $courseid = optional_param('courseid', 0, PARAM_INT);
    if ($courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
        $formerrors[] = get_string('invalidcourse', 'error');
    }
    $allowedcourseids = [];
    if (!$canmanageofficial) {
        require_once($CFG->libdir . '/enrollib.php');
        $allowedcourseids = array_map('intval', array_keys(enrol_get_users_courses((int)$USER->id, true, 'id')));
        if ($courseid > 0 && !in_array($courseid, $allowedcourseids, true)) {
            $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
        }
    }

    $groupid = optional_param('groupid', 0, PARAM_INT);
    if ($groupid < 0) {
        $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
    } else if ($groupid > 0) {
        $group = $DB->get_record('groups', ['id' => $groupid], 'id,courseid', IGNORE_MISSING);
        if (!$group || ($courseid > 0 && (int)$group->courseid !== $courseid)) {
            $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
        } else if (!$canmanageofficial && !in_array((int)$group->courseid, $allowedcourseids, true)) {
            $formerrors[] = get_string('invalidsessionsettings', 'local_flwexam');
        }
    }

    $questioncount = optional_param('questioncount', 20, PARAM_INT);
    if ($questioncount < 1 || $questioncount > 30) {
        $formerrors[] = get_string('invalidquestioncount', 'local_flwexam');
    }
    $questioncount = max(1, min(30, $questioncount));

    $maxattempts = optional_param('maxattempts', 1, PARAM_INT);
    if ($maxattempts < 1 || $maxattempts > 10) {
        $formerrors[] = get_string('invalidmaxattempts', 'local_flwexam');
    }
    $maxattempts = max(1, min(10, $maxattempts));

    $timestarttext = optional_param('timestart', '', PARAM_TEXT);
    $timeendtext = optional_param('timeend', '', PARAM_TEXT);
    $timestart = local_flwexam_parse_datetime($timestarttext);
    $timeend = local_flwexam_parse_datetime($timeendtext);
    if (trim($timestarttext) === '' || $timestart <= 0) {
        $formerrors[] = get_string('sessionstartrequired', 'local_flwexam');
    }
    if (trim($timeendtext) === '' || $timeend <= 0) {
        $formerrors[] = get_string('sessionendrequired', 'local_flwexam');
    }
    if ($timeend > 0 && $timestart > 0 && $timeend < $timestart) {
        $formerrors[] = get_string('invalidsessionwindow', 'local_flwexam');
    }

    $accesscode = strtoupper(clean_param(optional_param('accesscode', '', PARAM_TEXT), PARAM_ALPHANUMEXT));
    if ($accesscode !== '' && core_text::strlen($accesscode) < 6) {
        $formerrors[] = get_string('invalidaccesscode', 'local_flwexam');
    }

    $proctoruserid = optional_param('proctoruserid', 0, PARAM_INT);
    if ($proctoruserid < 0 || ($proctoruserid > 0 && !local_flwexam_can_select_proctor(
        $proctoruserid,
        (int)$USER->id,
        $canmanageofficial,
        $allowedcourseids
    ))) {
        $formerrors[] = get_string('invalidproctoruserid', 'local_flwexam');
    }

    $now = time();
    $record = (object)[
        'name' => $name,
        'sessiontype' => $sessiontype,
        'examid' => $examid,
        'courseid' => $courseid,
        'groupid' => $groupid,
        'questioncount' => $questioncount,
        'maxattempts' => $maxattempts,
        'timestart' => $timestart,
        'timeend' => $timeend,
        'accesscode' => $accesscode,
        'branchname' => optional_param('branchname', '', PARAM_TEXT),
        'proctoruserid' => $proctoruserid,
        'requireproctor' => optional_param('requireproctor', 0, PARAM_BOOL) ? 1 : 0,
        'status' => $status,
        'visible' => optional_param('visible', 0, PARAM_BOOL) ? 1 : 0,
        'timemodified' => $now,
    ];

    if ($submittedid > 0 && !$formerrors) {
        $existing = $DB->get_record('local_flwexam_sessions', ['id' => $submittedid], 'id,timecreated,createdby,sessiontype', MUST_EXIST);
        local_flwexam_require_session_manage_capability($existing->sessiontype, $canmanageteacher, $canmanageofficial, $context);
        $record->id = (int)$existing->id;
        $record->timecreated = (int)$existing->timecreated;
        $record->createdby = (int)$existing->createdby;
        $DB->update_record('local_flwexam_sessions', $record);
        redirect($url, get_string('examsessionupdated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if (!$formerrors) {
        $record->createdby = (int)$USER->id;
        $record->timecreated = $now;
        $DB->insert_record('local_flwexam_sessions', $record);
        redirect($url, get_string('examsessioncreated', 'local_flwexam'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $editing = $record;
    if ($submittedid > 0) {
        $editing->id = $submittedid;
    }
}

$output = $PAGE->get_renderer('core');
echo $output->header();
echo html_writer::start_div('flwexam-page');

echo local_flwexam_render_hero(
    get_string('exam', 'local_flwexam'),
    get_string('manageexamsessions', 'local_flwexam'),
    get_string('manageexamsessionsintro', 'local_flwexam'),
    [
        html_writer::link(
            new moodle_url('/local/flwexam/index.php', ['view' => 'available']),
            get_string('examcenter', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
        html_writer::link(
            new moodle_url('/local/flwexam/manage.php'),
            get_string('manageexams', 'local_flwexam'),
            ['class' => 'btn btn-secondary flwexam-main-action']
        ),
    ],
    [
        get_string('teacherexam', 'local_flwexam') => $canmanageteacher ? get_string('available', 'local_flwexam') : get_string('notavailable', 'local_flwexam'),
        get_string('officialexam', 'local_flwexam') => $canmanageofficial ? get_string('available', 'local_flwexam') : get_string('notavailable', 'local_flwexam'),
    ]
);

$sessiontype = $editing->sessiontype ?? ($canmanageteacher ? exam_service::SESSION_TYPE_TEACHER : exam_service::SESSION_TYPE_OFFICIAL);
if ($sessiontype === exam_service::SESSION_TYPE_SELF) {
    $sessiontype = exam_service::SESSION_TYPE_TEACHER;
}
$examoptions = local_flwexam_exam_options();
$courseoptions = local_flwexam_course_options((int)$USER->id, $canmanageofficial);
$groupoptions = local_flwexam_group_options((int)$USER->id, $canmanageofficial);
$proctoroptions = local_flwexam_proctor_options((int)$USER->id, $canmanageofficial, (int)($editing->proctoruserid ?? 0));
$defaulttime = time();
$startvalue = local_flwexam_datetime_value((int)($editing->timestart ?? $defaulttime));
$endvalue = local_flwexam_datetime_value((int)($editing->timeend ?? $defaulttime));

if ($formerrors) {
    echo $output->notification(
        html_writer::alist(array_map('s', array_unique($formerrors))),
        \core\output\notification::NOTIFY_ERROR
    );
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $url->out(false),
    'class' => 'flwexam-filter-form flwexam-manage-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
if ($editing) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int)$editing->id]);
}
echo html_writer::tag('h3', $editing ? get_string('editexamsession', 'local_flwexam') : get_string('addexamsession', 'local_flwexam'));
echo html_writer::start_div('flwexam-manage-body flwexam-session-manage-body');
echo html_writer::start_div('flwexam-filter-grid flwexam-session-grid');

echo local_flwexam_select_field('sessiontype', get_string('sessiontype', 'local_flwexam'), [
    exam_service::SESSION_TYPE_TEACHER => get_string('teacherexam', 'local_flwexam'),
    exam_service::SESSION_TYPE_OFFICIAL => get_string('officialexam', 'local_flwexam'),
], $sessiontype, 'flwexam-session-short', true);
echo local_flwexam_text_field('name', get_string('sessionname', 'local_flwexam'), $editing->name ?? '', 'text', 'flwexam-session-wide', true);
echo local_flwexam_select_field('examid', get_string('examname', 'local_flwexam'), $examoptions, (int)($editing->examid ?? 0), 'flwexam-session-wide', true);
echo local_flwexam_select_field('courseid', get_string('course'), $courseoptions, (int)($editing->courseid ?? 0), 'flwexam-session-wide', false, ['data-flwexam-course-select' => '1']);
echo local_flwexam_group_select_field($groupoptions, (int)($editing->groupid ?? 0));
echo local_flwexam_text_field('questioncount', get_string('questioncountpersession', 'local_flwexam'), (string)($editing->questioncount ?? 20), 'number', 'flwexam-session-short', true, ['min' => 1, 'max' => 30]);
echo local_flwexam_text_field('maxattempts', get_string('maxattempts', 'local_flwexam'), (string)($editing->maxattempts ?? 1), 'number', 'flwexam-session-short', true, ['min' => 1, 'max' => 10]);
echo local_flwexam_text_field('timestart', get_string('sessionstart', 'local_flwexam'), $startvalue, 'datetime-local', 'flwexam-session-medium', true);
echo local_flwexam_text_field('timeend', get_string('sessionend', 'local_flwexam'), $endvalue, 'datetime-local', 'flwexam-session-medium', true);
echo local_flwexam_access_code_field($editing->accesscode ?? local_flwexam_generate_access_code());
echo local_flwexam_text_field('branchname', get_string('branchname', 'local_flwexam'), $editing->branchname ?? '', 'text', 'flwexam-session-medium');
echo local_flwexam_select_field('proctoruserid', get_string('proctoruserid', 'local_flwexam'), $proctoroptions, (int)($editing->proctoruserid ?? 0), 'flwexam-session-medium flwexam-proctor-select-group');
echo local_flwexam_select_field('status', get_string('status', 'local_flwexam'), exam_service::get_session_status_options(), $editing->status ?? 'open', 'flwexam-session-short', true);

echo html_writer::start_div('form-group flwexam-session-check');
echo html_writer::label(
    html_writer::checkbox('requireproctor', 1, !empty($editing->requireproctor), get_string('requireproctor', 'local_flwexam')),
    '',
    false
);
echo html_writer::end_div();
echo html_writer::start_div('form-group flwexam-session-check');
echo html_writer::label(
    html_writer::checkbox('visible', 1, !isset($editing->visible) || !empty($editing->visible), get_string('sessionvisible', 'local_flwexam')),
    '',
    false
);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('flwexam-action-row');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary flwexam-main-action',
    'value' => $editing ? get_string('updateexamsession', 'local_flwexam') : get_string('createexamsession', 'local_flwexam'),
]);
if ($editing) {
    echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');

$sessions = exam_service::get_manage_sessions();
echo html_writer::tag('h3', get_string('existingexamsessions', 'local_flwexam'), ['class' => 'flwexam-section-title']);
if (!$sessions) {
    echo html_writer::div(get_string('noexamsessions', 'local_flwexam'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flwexam-table';
    $table->head = [
        get_string('actions', 'local_flwexam'),
        get_string('sessionname', 'local_flwexam'),
        get_string('sessiontype', 'local_flwexam'),
        get_string('examname', 'local_flwexam'),
        get_string('language', 'local_flwexam'),
        get_string('cefrlevel', 'local_flwexam'),
        get_string('questions', 'local_flwexam'),
        get_string('accesscode', 'local_flwexam'),
        get_string('branchname', 'local_flwexam'),
        get_string('sessionwindow', 'local_flwexam'),
        get_string('status', 'local_flwexam'),
    ];
    foreach ($sessions as $session) {
        $attemptcount = local_flwexam_count_session_attempts((int)$session['id']);
        $deleteurl = new moodle_url('/local/flwexam/sessions.php', [
            'delete' => $session['id'],
            'sesskey' => sesskey(),
        ]);
        $actions = html_writer::div(
            html_writer::link(new moodle_url('/local/flwexam/sessions.php', ['edit' => $session['id']]), get_string('edit'), ['class' => 'btn btn-secondary btn-sm']) .
            html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-secondary btn-sm flwexam-danger-link',
                'data-confirm' => get_string('deleteexamsessionconfirm', 'local_flwexam', $session['name']),
            ]),
            'flwexam-row-actions'
        );
        $table->data[] = [
            $actions,
            s($session['name']),
            s($session['session_type_label']),
            s($session['examname']),
            s(exam_service::language_label($session['language'])),
            s($session['cefr_level']),
            s((string)$session['question_count']),
            s($session['requires_access_code'] ? local_flwexam_session_access_code((int)$session['id']) : get_string('notrequired', 'local_flwexam')),
            s($session['branchname']),
            s(local_flwexam_session_window($session['timestart'], $session['timeend'])),
            s(exam_service::status_label($session['status'])) .
                ($attemptcount > 0 ? html_writer::div(get_string('sessionattemptcount', 'local_flwexam', $attemptcount), 'flwexam-muted') : ''),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'flwexam-table-wrap');
}

echo html_writer::script(
    '(function() {' .
    'var generateButton = document.querySelector("[data-flwexam-generate-code]");' .
    'var accessCode = document.getElementById("flwexam-accesscode");' .
    'if (generateButton && accessCode) {' .
    'generateButton.addEventListener("click", function() {' .
    'var chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";' .
    'var bytes = new Uint8Array(10);' .
    'if (window.crypto && window.crypto.getRandomValues) { window.crypto.getRandomValues(bytes); }' .
    'var code = "";' .
    'for (var i = 0; i < bytes.length; i++) {' .
    'var value = bytes[i] || Math.floor(Math.random() * 256);' .
    'code += chars.charAt(value % chars.length);' .
    '}' .
    'accessCode.value = code;' .
    'accessCode.focus();' .
    'accessCode.select();' .
    '});' .
    '}' .
    'var courseSelect = document.querySelector("[data-flwexam-course-select]");' .
    'var groupSelect = document.querySelector("[data-flwexam-group-select]");' .
    'function syncGroupOptions() {' .
    'if (!courseSelect || !groupSelect) { return; }' .
    'var courseid = courseSelect.value || "0";' .
    'var selectedHidden = false;' .
    'Array.prototype.slice.call(groupSelect.options).forEach(function(option) {' .
    'var groupcourseid = option.getAttribute("data-courseid") || "0";' .
    'var show = groupcourseid === "0" || courseid === "0" || groupcourseid === courseid;' .
    'option.hidden = !show;' .
    'option.disabled = !show;' .
    'if (option.selected && !show) { selectedHidden = true; }' .
    '});' .
    'if (selectedHidden) { groupSelect.value = "0"; }' .
    '}' .
    'if (courseSelect && groupSelect) {' .
    'courseSelect.addEventListener("change", syncGroupOptions);' .
    'syncGroupOptions();' .
    '}' .
    'document.addEventListener("click", function(event) {' .
    'var link = event.target.closest("[data-confirm]");' .
    'if (!link) { return; }' .
    'if (!window.confirm(link.getAttribute("data-confirm"))) { event.preventDefault(); }' .
    '});' .
    '})();'
);

echo html_writer::end_div();
echo $output->footer();

function local_flwexam_parse_datetime(string $value): int {
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $time = strtotime($value);
    return $time ? (int)$time : 0;
}

function local_flwexam_datetime_value(int $time): string {
    return $time > 0 ? date('Y-m-d\TH:i', $time) : '';
}

function local_flwexam_session_window(int $start, int $end): string {
    if ($start <= 0 && $end <= 0) {
        return get_string('anytime', 'local_flwexam');
    }
    $from = $start > 0 ? userdate($start) : get_string('anytime', 'local_flwexam');
    $to = $end > 0 ? userdate($end) : get_string('anytime', 'local_flwexam');
    return $from . ' - ' . $to;
}

function local_flwexam_session_access_code(int $sessionid): string {
    global $DB;

    return (string)$DB->get_field('local_flwexam_sessions', 'accesscode', ['id' => $sessionid]);
}

function local_flwexam_count_session_attempts(int $sessionid): int {
    global $DB;

    return $sessionid > 0 ? (int)$DB->count_records('local_flwexam_attempts', ['sessionid' => $sessionid]) : 0;
}

function local_flwexam_generate_access_code(): string {
    return strtoupper(random_string(10));
}

function local_flwexam_require_session_manage_capability(
    string $sessiontype,
    bool $canmanageself,
    bool $canmanageofficial,
    context_system $context
): void {
    if ($sessiontype === exam_service::SESSION_TYPE_OFFICIAL && !$canmanageofficial) {
        require_capability('local/flwexam:manageofficialexams', $context);
    }
    if ($sessiontype !== exam_service::SESSION_TYPE_OFFICIAL && !$canmanageself) {
        require_capability('local/flwexam:manageteacherexams', $context);
    }
}

function local_flwexam_exam_options(): array {
    global $DB;

    $records = $DB->get_records('local_flwexam_exams', ['visible' => 1], 'language ASC, cefrlevel ASC, name ASC');
    $options = [];
    foreach ($records as $record) {
        $options[(int)$record->id] = exam_service::language_label($record->language) . ' ' .
            $record->cefrlevel . ' / ' . exam_service::track_label($record->learningcoursecategory) .
            ' / ' . format_string($record->name);
    }
    return $options;
}

function local_flwexam_course_options(int $userid, bool $allcourses): array {
    global $CFG, $DB;

    $options = [0 => get_string('alllearners', 'local_flwexam')];
    if ($allcourses) {
        $courses = $DB->get_records_select('course', 'id <> :siteid', ['siteid' => SITEID], 'fullname ASC', 'id, fullname', 0, 200);
    } else {
        require_once($CFG->libdir . '/enrollib.php');
        $courses = enrol_get_users_courses($userid, true, 'id, fullname');
    }
    foreach ($courses as $course) {
        $options[(int)$course->id] = format_string($course->fullname);
    }
    return $options;
}

function local_flwexam_group_options(int $userid, bool $allcourses): array {
    global $CFG, $DB;

    $options = [
        (object)[
            'id' => 0,
            'courseid' => 0,
            'label' => get_string('nogrouprestriction', 'local_flwexam'),
        ],
    ];

    if ($allcourses) {
        $groups = $DB->get_records_sql(
            "SELECT g.id, g.name, g.courseid, c.fullname AS coursename
               FROM {groups} g
               JOIN {course} c ON c.id = g.courseid
              WHERE c.id <> :siteid
           ORDER BY c.fullname ASC, g.name ASC",
            ['siteid' => SITEID]
        );
    } else {
        require_once($CFG->libdir . '/enrollib.php');
        $courseids = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
        if (!$courseids) {
            return $options;
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $groups = $DB->get_records_sql(
            "SELECT g.id, g.name, g.courseid, c.fullname AS coursename
               FROM {groups} g
               JOIN {course} c ON c.id = g.courseid
              WHERE g.courseid {$insql}
           ORDER BY c.fullname ASC, g.name ASC",
            $params
        );
    }

    foreach ($groups as $group) {
        $options[] = (object)[
            'id' => (int)$group->id,
            'courseid' => (int)$group->courseid,
            'label' => format_string($group->coursename) . ' / ' . format_string($group->name),
        ];
    }

    return $options;
}

function local_flwexam_proctor_options(int $userid, bool $allusers, int $selectedid = 0): array {
    global $CFG, $DB;

    $options = [0 => get_string('noproctor', 'local_flwexam')];
    $guestid = (int)($CFG->siteguest ?? 0);
    $userfields = local_flwexam_user_fields_sql('u');

    if ($allusers) {
        $users = $DB->get_records_sql(
            "SELECT {$userfields}
               FROM {user} u
              WHERE u.deleted = 0
                AND u.suspended = 0
                AND u.id <> :guestid
           ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
            ['guestid' => $guestid],
            0,
            500
        );
    } else {
        require_once($CFG->libdir . '/enrollib.php');
        $courseids = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
        if (!$courseids) {
            $users = [];
        } else {
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
            $params['guestid'] = $guestid;
            $users = $DB->get_records_sql(
                "SELECT DISTINCT {$userfields}
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid {$insql}
                    AND u.deleted = 0
                    AND u.suspended = 0
                    AND u.id <> :guestid
               ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
                $params,
                0,
                500
            );
        }
    }

    if ($selectedid > 0 && !isset($users[$selectedid])) {
        $selected = $DB->get_record('user', ['id' => $selectedid, 'deleted' => 0, 'suspended' => 0], local_flwexam_user_fields_sql(), IGNORE_MISSING);
        if ($selected && ($allusers || local_flwexam_can_select_proctor($selectedid, $userid, false))) {
            $users[$selectedid] = $selected;
        }
    }

    foreach ($users as $user) {
        $options[(int)$user->id] = local_flwexam_user_option_label($user);
    }

    return $options;
}

function local_flwexam_can_select_proctor(
    int $proctoruserid,
    int $manageruserid,
    bool $allusers,
    array $managercourseids = []
): bool {
    global $CFG, $DB;

    if ($proctoruserid <= 0) {
        return true;
    }
    if (!$DB->record_exists('user', ['id' => $proctoruserid, 'deleted' => 0, 'suspended' => 0])) {
        return false;
    }
    if ($allusers) {
        return true;
    }

    require_once($CFG->libdir . '/enrollib.php');
    if (!$managercourseids) {
        $managercourseids = array_map('intval', array_keys(enrol_get_users_courses($manageruserid, true, 'id')));
    }
    if (!$managercourseids) {
        return false;
    }

    [$insql, $params] = $DB->get_in_or_equal($managercourseids, SQL_PARAMS_NAMED, 'courseid');
    $params['userid'] = $proctoruserid;
    return $DB->record_exists_sql(
        "SELECT 1
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE ue.userid = :userid
            AND e.courseid {$insql}",
        $params
    );
}

function local_flwexam_user_fields_sql(string $alias = ''): string {
    $prefix = $alias === '' ? '' : $alias . '.';
    $fields = array_unique(array_merge(['id', 'username', 'email'], \core_user\fields::get_name_fields()));
    return implode(', ', array_map(static function(string $field) use ($prefix): string {
        return $prefix . $field;
    }, $fields));
}

function local_flwexam_user_option_label(object $user): string {
    $name = trim(fullname($user));
    if ($name === '') {
        $name = $user->username ?? '';
    }
    $username = trim((string)($user->username ?? ''));
    if ($username !== '' && core_text::strtolower($username) !== core_text::strtolower($name)) {
        return $name . ' (' . $username . ')';
    }
    return $name;
}

function local_flwexam_text_field(
    string $name,
    string $label,
    string $value,
    string $type = 'text',
    string $groupclass = '',
    bool $required = false,
    array $attributes = []
): string {
    $id = 'flwexam-' . $name;
    $attributes = $attributes + [
        'type' => $type,
        'name' => $name,
        'id' => $id,
        'class' => 'form-control',
        'value' => $value,
    ];
    if ($required) {
        $attributes['required'] = 'required';
    }
    return html_writer::div(
        html_writer::label($label, $id) . local_flwexam_required_mark($required) .
        html_writer::empty_tag('input', $attributes),
        trim('form-group ' . $groupclass)
    );
}

function local_flwexam_select_field(
    string $name,
    string $label,
    array $options,
    $selected,
    string $groupclass = '',
    bool $required = false,
    array $attributes = []
): string {
    $id = 'flwexam-' . $name;
    $attributes = $attributes + ['id' => $id, 'class' => 'form-control'];
    if ($required) {
        $attributes['required'] = 'required';
    }
    return html_writer::div(
        html_writer::label($label, $id) . local_flwexam_required_mark($required) .
        html_writer::select($options, $name, $selected, false, $attributes),
        trim('form-group ' . $groupclass)
    );
}

function local_flwexam_group_select_field(array $groups, int $selected): string {
    $id = 'flwexam-groupid';
    $select = html_writer::start_tag('select', [
        'name' => 'groupid',
        'id' => $id,
        'class' => 'form-control',
        'data-flwexam-group-select' => '1',
    ]);
    foreach ($groups as $group) {
        $attributes = [
            'value' => (int)$group->id,
            'data-courseid' => (int)$group->courseid,
        ];
        if ((int)$group->id === $selected) {
            $attributes['selected'] = 'selected';
        }
        $select .= html_writer::tag('option', s($group->label), $attributes);
    }
    $select .= html_writer::end_tag('select');

    return html_writer::div(
        html_writer::label(get_string('groupid', 'local_flwexam'), $id) . $select,
        'form-group flwexam-session-medium flwexam-group-select-group'
    );
}

function local_flwexam_access_code_field(string $value): string {
    $id = 'flwexam-accesscode';
    return html_writer::div(
        html_writer::label(get_string('accesscode', 'local_flwexam'), $id) .
        html_writer::div(
            html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'accesscode',
                'id' => $id,
                'class' => 'form-control',
                'value' => $value,
                'maxlength' => 80,
                'pattern' => '[A-Za-z0-9_-]{6,80}',
            ]) .
            html_writer::tag('button', get_string('generateaccesscode', 'local_flwexam'), [
                'type' => 'button',
                'class' => 'btn btn-secondary flwexam-code-generate',
                'data-flwexam-generate-code' => '1',
            ]),
            'flwexam-access-code-row'
        ),
        'form-group flwexam-session-medium flwexam-access-code-group'
    );
}

function local_flwexam_required_mark(bool $required): string {
    return $required ? html_writer::span('*', 'flwexam-required-mark', ['aria-hidden' => 'true']) : '';
}
