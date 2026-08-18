<?php
// Repair actions for C-UP-KP evidence and sync health.

require_once(__DIR__ . '/../../config.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);
$unitcode = optional_param('unitcode', '', PARAM_ALPHANUMEXT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$returnurlparam = optional_param('returnurl', '/my/', PARAM_LOCALURL);
$returnurl = new moodle_url($returnurlparam ?: '/my/');

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/flwcupkp:viewreports', $context);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect($returnurl, get_string('invalidrequest', 'error'), null, \core\output\notification::NOTIFY_ERROR);
}
require_sesskey();

try {
    if ($action === 'repair_pending_quiz_attempts') {
        require_capability('local/flwcupkp:synccompetencies', context_system::instance());
        $result = \local_flwcupkp\local\evidence_sync_repair::repair_pending_quiz_attempts($courseid, $unitcode);
        if (($result['status'] ?? '') === 'none') {
            redirect($returnurl, get_string('repairquizsyncallnone', 'local_flwcupkp'), null,
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
        redirect($returnurl, $message, null, $type);
    }

    if ($action !== 'repair_quiz_attempt' || $attemptid <= 0) {
        redirect($returnurl, get_string('invalidrequest', 'error'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $result = \local_flwcupkp\local\evidence_sync_repair::repair_quiz_attempt($attemptid, $courseid, $unitcode);

    $created = count($result['evidenceids'] ?? []);
    if (($result['status'] ?? '') === 'processed' && $created > 0) {
        redirect($returnurl, get_string('repairquizsyncsuccess', 'local_flwcupkp', (object)[
            'attemptid' => $attemptid,
            'count' => $created,
        ]), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if (($result['status'] ?? '') === 'processed') {
        redirect($returnurl, get_string('repairquizsyncnone', 'local_flwcupkp', $attemptid), null,
            \core\output\notification::NOTIFY_INFO);
    }

    redirect($returnurl, get_string('repairquizsyncignored', 'local_flwcupkp', (object)[
        'attemptid' => $attemptid,
        'reason' => (string)($result['reason'] ?? 'unknown'),
    ]), null, \core\output\notification::NOTIFY_WARNING);
} catch (Throwable $e) {
    \local_flwcupkp\local\repository::audit('quiz_evidence_repair_failed', 'quiz_attempt', $attemptid, [
        'courseid' => $courseid,
        'unitcode' => $unitcode,
        'action' => $action,
        'message' => $e->getMessage(),
    ]);
    $message = $action === 'repair_pending_quiz_attempts' ?
        get_string('repairquizsyncallfailed', 'local_flwcupkp', $e->getMessage()) :
        get_string('repairquizsyncfailed', 'local_flwcupkp', (object)[
            'attemptid' => $attemptid,
            'message' => $e->getMessage(),
        ]);
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_ERROR);
}
