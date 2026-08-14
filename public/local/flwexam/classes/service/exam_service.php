<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwexam\service;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_text;
use core_user;
use dml_exception;
use invalid_parameter_exception;
use moodle_exception;
use moodle_url;
use required_capability_exception;

/**
 * Service layer for FLW Exam result and certificate operations.
 */
class exam_service {
    /** @var string Passed result status. */
    public const PASS_STATUS_PASSED = 'passed';

    /** @var string Failed result status. */
    public const PASS_STATUS_FAILED = 'failed';

    /** @var string Valid certificate status. */
    public const CERT_STATUS_VALID = 'valid';

    /** @var string Learner-started direct exam path. */
    public const SESSION_TYPE_SELF = 'self';

    /** @var string Teacher/class organised exam session. */
    public const SESSION_TYPE_TEACHER = 'teacher';

    /** @var string Branch/government official exam session. */
    public const SESSION_TYPE_OFFICIAL = 'official';

    /** @var int Moodle Quiz-backed exam attempts should sample 20 questions. */
    public const QUIZ_EXAM_ATTEMPT_QUESTION_COUNT = 20;

    /**
     * Return a named status in a readable form.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        $status = clean_param($status, PARAM_ALPHANUMEXT);
        $labels = [
            self::PASS_STATUS_PASSED => get_string('statuspassed', 'local_flwexam'),
            self::PASS_STATUS_FAILED => get_string('statusfailed', 'local_flwexam'),
            self::CERT_STATUS_VALID => get_string('statusvalid', 'local_flwexam'),
            'active' => get_string('statusactive', 'local_flwexam'),
            'already_issued' => get_string('statusalreadyissued', 'local_flwexam'),
            'approved' => get_string('statusapproved', 'local_flwexam'),
            'clear' => get_string('statusclear', 'local_flwexam'),
            'eligible' => get_string('statuseligible', 'local_flwexam'),
            'expired' => get_string('statusexpired', 'local_flwexam'),
            'issued' => get_string('statusissued', 'local_flwexam'),
            'not_issued' => get_string('statusnotissued', 'local_flwexam'),
            'open' => get_string('sessionstatusopen', 'local_flwexam'),
            'draft' => get_string('sessionstatusdraft', 'local_flwexam'),
            'closed' => get_string('sessionstatusclosed', 'local_flwexam'),
            'pending' => get_string('statuspending', 'local_flwexam'),
            'rejected' => get_string('statusrejected', 'local_flwexam'),
            'revoked' => get_string('statusrevoked', 'local_flwexam'),
            'submitted' => get_string('statussubmitted', 'local_flwexam'),
        ];

        return $labels[$status] ?? get_string('statusunknown', 'local_flwexam', $status);
    }

    /**
     * Check whether the current actor can view a result.
     *
     * @param object $result
     * @param int $viewerid
     * @return bool
     */
    public static function can_view_result(object $result, int $viewerid): bool {
        $context = context_system::instance();
        if ((int)$result->userid === $viewerid) {
            return has_capability('local/flwexam:viewown', $context);
        }
        return has_capability('local/flwexam:viewall', $context);
    }

    /**
     * Require permission to view a result.
     *
     * @param object $result
     * @param int $viewerid
     */
    public static function require_can_view_result(object $result, int $viewerid): void {
        if (!self::can_view_result($result, $viewerid)) {
            throw new required_capability_exception(
                context_system::instance(),
                'local/flwexam:viewall',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Fetch learner history.
     *
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function get_history(int $userid, int $limit = 50): array {
        global $DB;

        $sql = "SELECT r.*, e.name AS examname, e.code AS examcode,
                       a.sessionid, a.metadatajson AS attemptmetadatajson,
                       s.name AS sessionname, s.sessiontype, s.branchname,
                       s.timestart AS sessiontimestart, s.timeend AS sessiontimeend,
                       s.questioncount AS sessionquestioncount, s.maxattempts AS sessionmaxattempts,
                       c.certificatecode, t.verifycode
                  FROM {local_flwexam_results} r
                  JOIN {local_flwexam_exams} e ON e.id = r.examid
                  JOIN {local_flwexam_attempts} a ON a.id = r.attemptid
             LEFT JOIN {local_flwexam_sessions} s ON s.id = a.sessionid
             LEFT JOIN {local_flwexam_certificates} c ON c.id = r.certificateid
             LEFT JOIN {local_flwexam_verify_tokens} t
                    ON t.certificateid = c.id AND t.status = :tokenstatus
                 WHERE r.userid = :userid
              ORDER BY r.timecreated DESC, r.id DESC";
        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'tokenstatus' => 'active',
        ], 0, $limit);

        $history = [];
        foreach ($records as $record) {
            $history[] = self::export_history_record($record);
        }
        return $history;
    }

    /**
     * Fetch the learner's latest result for one exam.
     *
     * @param int $examid
     * @param int $userid
     * @return array|null
     */
    public static function get_latest_result_for_exam(int $examid, int $userid): ?array {
        global $DB;

        if ($examid <= 0 || $userid <= 0 || !$DB->get_manager()->table_exists('local_flwexam_results')) {
            return null;
        }

        $resultid = $DB->get_field_sql(
            "SELECT id
               FROM {local_flwexam_results}
              WHERE examid = :examid
                AND userid = :userid
           ORDER BY timecreated DESC, id DESC",
            [
                'examid' => $examid,
                'userid' => $userid,
            ],
            IGNORE_MULTIPLE
        );
        if (!$resultid) {
            return null;
        }

        return self::get_result_package((int)$resultid, $userid);
    }

    /**
     * Return the FLW Exam result URL for a finished Moodle Quiz review.
     *
     * If the quiz attempt has not been synced yet, this tries to sync visible
     * FLW Exam definitions linked to the Moodle Quiz before returning the URL.
     *
     * @param int $quizid Moodle quiz instance id.
     * @param int $userid Learner id.
     * @param int $quizattemptid Moodle quiz attempt id.
     * @return moodle_url|null Result URL, or null for normal Moodle flow.
     */
    public static function get_quiz_review_result_url(int $quizid, int $userid, int $quizattemptid): ?moodle_url {
        global $DB;

        if ($quizid <= 0 || $userid <= 0 || $quizattemptid <= 0 ||
                !$DB->get_manager()->table_exists('local_flwexam_results') ||
                !$DB->get_manager()->table_exists('local_flwexam_attempts') ||
                !$DB->get_manager()->table_exists('local_flwexam_exams')) {
            return null;
        }

        $resultid = self::find_quiz_attempt_result_id($quizid, $userid, $quizattemptid);
        if ($resultid <= 0) {
            $exams = $DB->get_records('local_flwexam_exams', [
                'quizid' => $quizid,
                'visible' => 1,
            ], 'id ASC', 'id');

            foreach ($exams as $exam) {
                try {
                    self::record_quiz_attempt_result_from_event((int)$exam->id, $userid, $quizattemptid);
                } catch (\Throwable $e) {
                    debugging(
                        'local_flwexam could not prepare exam result URL for quiz attempt ' .
                            $quizattemptid . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            }

            $resultid = self::find_quiz_attempt_result_id($quizid, $userid, $quizattemptid);
        }

        return $resultid > 0 ? new moodle_url('/local/flwexam/result.php', ['id' => $resultid]) : null;
    }

    /**
     * Fetch a result package.
     *
     * @param int $resultid
     * @param int $viewerid
     * @param bool $includeprivate
     * @return array
     */
    public static function get_result_package(int $resultid, int $viewerid, bool $includeprivate = false): array {
        global $DB;

        $result = $DB->get_record('local_flwexam_results', ['id' => $resultid], '*', MUST_EXIST);
        self::require_can_view_result($result, $viewerid);

        $exam = $DB->get_record('local_flwexam_exams', ['id' => $result->examid], '*', MUST_EXIST);
        $user = core_user::get_user($result->userid, '*', MUST_EXIST);
        $attempt = $DB->get_record('local_flwexam_attempts', ['id' => $result->attemptid], '*', IGNORE_MISSING);
        $attemptmetadata = $attempt ? (json_decode($attempt->metadatajson ?? '[]', true) ?: []) : [];
        $session = null;
        if ($attempt && !empty($attempt->sessionid)) {
            $session = $DB->get_record('local_flwexam_sessions', ['id' => (int)$attempt->sessionid], '*', IGNORE_MISSING);
        }
        if (!$session && $attempt && ($attempt->source ?? '') === 'modquiz' &&
                !empty($attemptmetadata['quiz_attempt_id'])) {
            $quizattempt = $DB->get_record('quiz_attempts', ['id' => (int)$attemptmetadata['quiz_attempt_id']], '*', IGNORE_MISSING);
            if ($quizattempt) {
                $session = self::resolve_quiz_session_record($exam, (int)$result->userid, 0, $quizattempt);
            }
        }
        $sessiontype = $session->sessiontype ?? ($attemptmetadata['session_type'] ?? self::SESSION_TYPE_SELF);
        $sessionname = $session->name ?? ($attemptmetadata['session_name'] ?? get_string('selfexamsession', 'local_flwexam'));
        $skills = $DB->get_records('local_flwexam_skill_scores', ['resultid' => $resultid], 'skill ASC');
        $kpresults = $DB->get_records('local_flwexam_kp_results', ['resultid' => $resultid], 'critical DESC, kpcode ASC');
        $certificate = null;
        $token = null;
        if (!empty($result->certificateid)) {
            $certificate = $DB->get_record('local_flwexam_certificates', ['id' => $result->certificateid], '*', IGNORE_MISSING);
            if ($certificate) {
                $token = $DB->get_record('local_flwexam_verify_tokens', [
                    'certificateid' => $certificate->id,
                    'status' => 'active',
                ], '*', IGNORE_MULTIPLE);
            }
        }

        $canviewprivate = $includeprivate && has_capability('local/flwexam:viewall', context_system::instance());
        return [
            'id' => (int)$result->id,
            'userid' => (int)$result->userid,
            'learnername' => fullname($user),
            'examid' => (int)$result->examid,
            'examname' => self::format_display_name($exam->name),
            'examcode' => $exam->code,
            'session_id' => $session ? (int)$session->id : (int)($attempt->sessionid ?? 0),
            'session_name' => self::format_display_name((string)$sessionname),
            'session_type' => (string)$sessiontype,
            'session_type_label' => self::session_type_label((string)$sessiontype),
            'branchname' => (string)($session->branchname ?? ''),
            'session_time_start' => (int)($session->timestart ?? 0),
            'session_time_end' => (int)($session->timeend ?? 0),
            'session_question_count' => (int)($session->questioncount ?? ($attemptmetadata['question_count'] ?? 0)),
            'session_max_attempts' => (int)($session->maxattempts ?? 0),
            'language' => $result->language,
            'learning_course_category' => $result->learningcoursecategory,
            'cefr_level' => $result->cefrlevel,
            'overall_score' => (float)$result->overallscore,
            'pass_status' => $result->passstatus,
            'certificate_status' => $result->certificatestatus,
            'certificate_id' => (int)$result->certificateid,
            'certificate_code' => $certificate ? $certificate->certificatecode : '',
            'verify_code' => $token ? $token->verifycode : '',
            'timecreated' => (int)$result->timecreated,
            'skills' => self::export_skill_rows($skills),
            'kp_results' => self::export_kp_rows($kpresults),
            'decision' => json_decode($result->decisionjson ?? '[]', true) ?: [],
            'private' => $canviewprivate ? [
                'integrity_status' => $result->integritystatus,
                'moderation_status' => $result->moderationstatus,
                'summary' => json_decode($result->summaryjson ?? '[]', true) ?: [],
            ] : [],
        ];
    }

    /**
     * Return the minimal result data needed by trusted server-side sync paths.
     *
     * @param int $resultid
     * @return array
     */
    protected static function get_trusted_result_summary(int $resultid): array {
        global $DB;

        $sql = "SELECT r.id, r.examid, r.certificateid, r.overallscore, e.code AS examcode
                  FROM {local_flwexam_results} r
                  JOIN {local_flwexam_exams} e ON e.id = r.examid
                 WHERE r.id = :resultid";
        $record = $DB->get_record_sql($sql, ['resultid' => $resultid], MUST_EXIST);

        return [
            'id' => (int)$record->id,
            'examid' => (int)$record->examid,
            'examcode' => $record->examcode,
            'certificate_id' => (int)$record->certificateid,
            'overall_score' => (float)$record->overallscore,
        ];
    }

    /**
     * Find an FLW Exam result created from a specific Moodle Quiz attempt.
     *
     * @param int $quizid Moodle quiz instance id.
     * @param int $userid Learner id.
     * @param int $quizattemptid Moodle quiz attempt id.
     * @return int Result id or 0.
     */
    protected static function find_quiz_attempt_result_id(int $quizid, int $userid, int $quizattemptid): int {
        global $DB;

        if ($quizid <= 0 || $userid <= 0 || $quizattemptid <= 0) {
            return 0;
        }

        $resultid = $DB->get_field_sql(
            "SELECT r.id
               FROM {local_flwexam_results} r
               JOIN {local_flwexam_attempts} a ON a.id = r.attemptid
               JOIN {local_flwexam_exams} e ON e.id = r.examid
              WHERE r.userid = :userid
                AND e.quizid = :quizid
                AND e.visible = 1
                AND a.source = :source
                AND a.externalattemptid = :externalid
           ORDER BY r.timecreated DESC, r.id DESC",
            [
                'userid' => $userid,
                'quizid' => $quizid,
                'source' => 'modquiz',
                'externalid' => 'quizattempt' . $quizattemptid,
            ],
            IGNORE_MULTIPLE
        );

        return $resultid ? (int)$resultid : 0;
    }

    /**
     * List visible exams that a learner can start.
     *
     * @return array
     */
    public static function get_available_exams(array $filters = []): array {
        global $DB;

        $where = ['e.visible = 1'];
        $params = [];
        foreach ([
            'language' => 'language',
            'learning_course_category' => 'learningcoursecategory',
            'cefr_level' => 'cefrlevel',
        ] as $filterkey => $field) {
            $value = clean_param($filters[$filterkey] ?? '', PARAM_ALPHANUMEXT);
            if ($value !== '') {
                $where[] = 'e.' . $field . ' = :' . $filterkey;
                $params[$filterkey] = $value;
            }
        }

        $sql = "SELECT e.*, COUNT(q.id) AS localquestioncount
                  FROM {local_flwexam_exams} e
             LEFT JOIN {local_flwexam_questions} q ON q.examid = e.id AND q.visible = 1
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY e.id, e.code, e.name, e.language, e.learningcoursecategory, e.cefrlevel,
                       e.requiredthreshold, e.requiredskillfloor, e.moderationrequired,
                       e.criticalkpjson, e.profilejson, e.quizid, e.visible, e.timecreated, e.timemodified
              ORDER BY e.language ASC, e.learningcoursecategory ASC, e.cefrlevel ASC, e.name ASC";
        $records = $DB->get_records_sql($sql, $params);
        $exams = [];
        foreach ($records as $record) {
            $questioncount = self::get_exam_question_count($record);
            $exams[] = [
                'id' => (int)$record->id,
                'code' => $record->code,
                'name' => self::format_display_name($record->name),
                'language' => $record->language,
                'learning_course_category' => $record->learningcoursecategory,
                'cefr_level' => $record->cefrlevel,
                'required_threshold' => (float)$record->requiredthreshold,
                'required_skill_floor' => (float)$record->requiredskillfloor,
                'question_count' => $questioncount,
                'question_source' => !empty($record->quizid) ? 'moodle_quiz' : 'flw_internal',
                'quizid' => (int)($record->quizid ?? 0),
            ];
        }
        return $exams;
    }

    /**
     * Return Moodle Quiz activities that can be linked as an FLW question source.
     *
     * @return array
     */
    public static function get_quiz_options(): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('quiz')) {
            return [];
        }

        $sql = "SELECT q.id, q.name, q.course, c.fullname AS coursename, cm.id AS cmid
                  FROM {quiz} q
                  JOIN {course} c ON c.id = q.course
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm
                    ON cm.instance = q.id
                   AND cm.module = m.id
                   AND cm.course = q.course
                 WHERE cm.deletioninprogress = 0
              ORDER BY c.fullname ASC, q.name ASC";
        $records = $DB->get_records_sql($sql, ['modname' => 'quiz']);
        $options = [];
        foreach ($records as $record) {
            $options[(int)$record->id] =
                self::format_display_name($record->coursename) . ' / ' .
                self::format_display_name($record->name) . ' (#' . (int)$record->cmid . ')';
        }
        return $options;
    }

    /**
     * Check whether a Moodle Quiz exists.
     *
     * @param int $quizid
     * @return bool
     */
    public static function quiz_exists(int $quizid): bool {
        global $DB;

        return $quizid > 0 && $DB->record_exists('quiz', ['id' => $quizid]);
    }

    /**
     * Count the questions shown in one Moodle Quiz attempt.
     *
     * @param int $quizid
     * @return int
     */
    public static function get_quiz_attempt_question_count(int $quizid): int {
        global $DB;

        if ($quizid <= 0 || !$DB->get_manager()->table_exists('quiz_slots')) {
            return 0;
        }

        return (int)$DB->count_records('quiz_slots', ['quizid' => $quizid]);
    }

    /**
     * Count the source bank questions available to a Moodle Quiz.
     *
     * @param int $quizid
     * @return int
     */
    public static function get_quiz_source_question_count(int $quizid): int {
        return self::get_quiz_question_count($quizid);
    }

    /**
     * Remove unfinished Moodle Quiz attempts whose saved layout no longer matches the quiz slots.
     *
     * @param int $quizid
     * @param int $userid
     * @return int Number of stale attempts removed.
     */
    public static function cleanup_stale_quiz_attempts(int $quizid, int $userid): int {
        global $CFG, $DB;

        if ($quizid <= 0 || $userid <= 0 ||
                !$DB->get_manager()->table_exists('quiz_attempts') ||
                !$DB->get_manager()->table_exists('quiz_slots')) {
            return 0;
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING);
        if (!$quiz) {
            return 0;
        }

        $slotrecords = $DB->get_records('quiz_slots', ['quizid' => $quizid], '', 'id, slot');
        if (!$slotrecords) {
            return 0;
        }

        $validslots = [];
        foreach ($slotrecords as $slotrecord) {
            $validslots[(int)$slotrecord->slot] = true;
        }

        [$statesql, $stateparams] = $DB->get_in_or_equal(['inprogress', 'overdue'], SQL_PARAMS_NAMED, 'state');
        $params = [
            'quizid' => $quizid,
            'userid' => $userid,
        ] + $stateparams;

        $attempts = $DB->get_records_sql(
            "SELECT *
               FROM {quiz_attempts}
              WHERE quiz = :quizid
                AND userid = :userid
                AND state {$statesql}
           ORDER BY id",
            $params
        );
        if (!$attempts) {
            return 0;
        }

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $removed = 0;
        foreach ($attempts as $attempt) {
            if (!self::quiz_attempt_layout_is_stale((string)$attempt->layout, $validslots)) {
                continue;
            }

            quiz_delete_attempt($attempt, $quiz);
            $removed++;
        }

        return $removed;
    }

    /**
     * Check whether an attempt layout references slots that the quiz no longer has.
     *
     * @param string $layout
     * @param array $validslots
     * @return bool
     */
    protected static function quiz_attempt_layout_is_stale(string $layout, array $validslots): bool {
        foreach (preg_split('/,/', $layout, -1, PREG_SPLIT_NO_EMPTY) as $item) {
            $slot = (int)trim($item);
            if ($slot > 0 && !isset($validslots[$slot])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return linked Moodle Quiz display metadata.
     *
     * @param int $quizid
     * @return array|null
     */
    public static function get_linked_quiz_info(int $quizid): ?array {
        global $CFG, $DB;

        if ($quizid <= 0 || !$DB->get_manager()->table_exists('quiz')) {
            return null;
        }

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING);
        if (!$quiz) {
            return null;
        }

        require_once($CFG->dirroot . '/course/lib.php');
        $cm = get_coursemodule_from_instance('quiz', $quizid, (int)$quiz->course, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        $attemptquestioncount = self::get_quiz_attempt_question_count((int)$quiz->id);
        $sourcequestioncount = self::get_quiz_source_question_count((int)$quiz->id);

        return [
            'id' => (int)$quiz->id,
            'name' => self::format_display_name($quiz->name),
            'courseid' => (int)$quiz->course,
            'cmid' => (int)$cm->id,
            'url' => new moodle_url('/mod/quiz/view.php', ['id' => (int)$cm->id]),
            'questioncount' => $attemptquestioncount,
            'attemptquestioncount' => $attemptquestioncount,
            'sourcequestioncount' => $sourcequestioncount,
            'requiredquestioncount' => self::QUIZ_EXAM_ATTEMPT_QUESTION_COUNT,
            'isready' => $attemptquestioncount > 0,
            'issamplecountok' => $attemptquestioncount === self::QUIZ_EXAM_ATTEMPT_QUESTION_COUNT,
            'grade' => (float)$quiz->grade,
            'sumgrades' => (float)$quiz->sumgrades,
        ];
    }

    /**
     * Return the effective question count for an FLW Exam definition.
     *
     * @param object $exam
     * @return int
     */
    public static function get_exam_question_count(object $exam): int {
        global $DB;

        if (!empty($exam->quizid)) {
            return self::get_quiz_attempt_question_count((int)$exam->quizid);
        }

        return (int)$DB->count_records('local_flwexam_questions', [
            'examid' => (int)$exam->id,
            'visible' => 1,
        ]);
    }

    /**
     * Import the learner's latest completed Moodle Quiz attempt as an official FLW Exam result.
     *
     * @param int $examid
     * @param int $userid
     * @return array
     */
    public static function record_quiz_attempt_result(int $examid, int $userid, int $sessionid = 0): array {
        require_capability('local/flwexam:viewown', context_system::instance());

        return self::record_quiz_attempt_result_internal($examid, $userid, 0, false, $sessionid);
    }

    /**
     * Import a completed Moodle Quiz attempt after Moodle fires a quiz completion event.
     *
     * @param int $examid
     * @param int $userid
     * @param int $quizattemptid
     * @return array
     */
    public static function record_quiz_attempt_result_from_event(int $examid, int $userid, int $quizattemptid): array {
        return self::record_quiz_attempt_result_internal($examid, $userid, $quizattemptid, true);
    }

    /**
     * Import a completed Moodle Quiz attempt as an official FLW Exam result.
     *
     * @param int $examid
     * @param int $userid
     * @param int $quizattemptid Exact Moodle quiz attempt id, or 0 for latest attempt.
     * @param bool $trustedreturn Return a minimal package without current-user capability checks.
     * @return array
     */
    protected static function record_quiz_attempt_result_internal(
        int $examid,
        int $userid,
        int $quizattemptid = 0,
        bool $trustedreturn = false,
        int $sessionid = 0
    ): array {
        global $CFG, $DB;

        $exam = $DB->get_record('local_flwexam_exams', [
            'id' => $examid,
            'visible' => 1,
        ], '*', MUST_EXIST);
        if (empty($exam->quizid)) {
            throw new moodle_exception('linkedquiznotavailable', 'local_flwexam');
        }

        if ($quizattemptid > 0) {
            $quizattempt = $DB->get_record('quiz_attempts', [
                'id' => $quizattemptid,
                'quiz' => (int)$exam->quizid,
                'userid' => $userid,
                'state' => 'finished',
                'preview' => 0,
            ], '*', IGNORE_MISSING);
        } else {
            $attempts = $DB->get_records('quiz_attempts', [
                'quiz' => (int)$exam->quizid,
                'userid' => $userid,
                'state' => 'finished',
                'preview' => 0,
            ], 'timefinish DESC, id DESC', '*', 0, 1);
            $quizattempt = $attempts ? reset($attempts) : false;
        }
        if (!$quizattempt || $quizattempt->sumgrades === null) {
            throw new moodle_exception('noquizattempttosync', 'local_flwexam');
        }
        $externalid = 'quizattempt' . (int)$quizattempt->id;
        $session = self::resolve_quiz_session_record($exam, $userid, $sessionid, $quizattempt);

        $existingresultid = $DB->get_field_sql(
            "SELECT r.id
               FROM {local_flwexam_results} r
               JOIN {local_flwexam_attempts} a ON a.id = r.attemptid
              WHERE r.examid = :examid
                AND r.userid = :userid
                AND a.source = :source
                AND a.externalattemptid = :externalid
           ORDER BY r.id DESC",
            [
                'examid' => (int)$exam->id,
                'userid' => $userid,
                'source' => 'modquiz',
                'externalid' => $externalid,
            ],
            IGNORE_MULTIPLE
        );
        if ($existingresultid) {
            if ($session) {
                self::attach_session_to_existing_quiz_result((int)$existingresultid, $session);
            }
            if ($trustedreturn) {
                return self::get_trusted_result_summary((int)$existingresultid);
            }
            return self::get_result_package((int)$existingresultid, $userid, true);
        }

        $quiz = $DB->get_record('quiz', ['id' => (int)$exam->quizid], '*', MUST_EXIST);
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $overall = self::get_quiz_attempt_percent($quiz, $quizattempt);
        $skills = self::build_quiz_skill_scores($exam, $overall);
        $kpresults = self::build_quiz_kp_results($exam, $overall);
        $sessionmetadata = $session ? [
            'session_id' => (int)$session->id,
            'session_type' => $session->sessiontype,
            'session_name' => $session->name,
        ] : [
            'session_id' => 0,
            'session_type' => self::SESSION_TYPE_SELF,
            'session_name' => get_string('selfexamsession', 'local_flwexam'),
        ];

        $result = self::record_result([
            'userid' => $userid,
            'examid' => (int)$exam->id,
            'language' => $exam->language,
            'learning_course_category' => $exam->learningcoursecategory,
            'cefr_level' => $exam->cefrlevel,
            'overall_score' => $overall,
            'skill_scores' => $skills,
            'kp_results' => $kpresults,
            'integrity_status' => 'clear',
            'moderation_status' => 'approved',
            'attempt_metadata' => $sessionmetadata + [
                'source' => 'modquiz',
                'external_attempt_id' => $externalid,
                'quizid' => (int)$quiz->id,
                'quiz_attempt_id' => (int)$quizattempt->id,
                'quiz_courseid' => (int)$quiz->course,
                'quiz_sumgrades' => (float)$quiz->sumgrades,
                'quiz_grade' => (float)$quiz->grade,
                'attempt_sumgrades' => (float)$quizattempt->sumgrades,
                'question_count' => self::get_quiz_attempt_question_count((int)$quiz->id),
                'source_question_count' => self::get_quiz_source_question_count((int)$quiz->id),
                'time_started' => (int)$quizattempt->timestart,
                'time_finished' => (int)$quizattempt->timefinish,
                'score_source' => 'moodle_quiz_attempt',
            ],
        ], $userid, false, $trustedreturn);

        self::audit('quiz_attempt_synced', $userid, $userid, (int)$result['id'], (int)$result['certificate_id'], 'success', [
            'examid' => (int)$exam->id,
            'quizid' => (int)$quiz->id,
            'quiz_attempt_id' => (int)$quizattempt->id,
            'overall_score' => $overall,
        ]);

        return $result;
    }

    /**
     * Resolve the FLW session context for a Moodle Quiz-backed attempt.
     *
     * @param object $exam
     * @param int $userid
     * @param int $sessionid
     * @param object $quizattempt
     * @return object|null
     */
    protected static function resolve_quiz_session_record(
        object $exam,
        int $userid,
        int $sessionid,
        object $quizattempt
    ): ?object {
        global $DB, $SESSION;

        if (!$DB->get_manager()->table_exists('local_flwexam_sessions')) {
            return null;
        }

        if ($sessionid <= 0 && !empty($SESSION->local_flwexam_pending_quiz_sessions) &&
                is_array($SESSION->local_flwexam_pending_quiz_sessions)) {
            $pendingkey = (int)$exam->quizid . ':' . (int)$exam->id;
            $pending = $SESSION->local_flwexam_pending_quiz_sessions[$pendingkey] ?? null;
            if (is_array($pending) && (int)($pending['sessionid'] ?? 0) > 0) {
                $created = (int)($pending['timecreated'] ?? 0);
                $attemptstarted = (int)($quizattempt->timestart ?? 0);
                if ($created > 0 && $attemptstarted >= $created - 300 && $attemptstarted <= $created + DAYSECS) {
                    $sessionid = (int)$pending['sessionid'];
                } else {
                    unset($SESSION->local_flwexam_pending_quiz_sessions[$pendingkey]);
                }
            }
        }

        if ($sessionid > 0) {
            $session = $DB->get_record('local_flwexam_sessions', [
                'id' => $sessionid,
                'examid' => (int)$exam->id,
            ], '*', IGNORE_MISSING);
            if ($session && self::is_session_available_to_user($session, $userid)) {
                return $session;
            }
        }

        $attempttime = (int)($quizattempt->timestart ?: $quizattempt->timefinish);
        if ($attempttime <= 0) {
            return null;
        }

        $candidates = $DB->get_records_sql(
            "SELECT *
               FROM {local_flwexam_sessions}
              WHERE examid = :examid
                AND visible = 1
                AND status = :status
                AND (timestart = 0 OR timestart <= :attemptstart)
                AND (timeend = 0 OR timeend >= :attemptend)
           ORDER BY timestart DESC, id DESC",
            [
                'examid' => (int)$exam->id,
                'status' => 'open',
                'attemptstart' => $attempttime,
                'attemptend' => $attempttime,
            ]
        );

        $available = [];
        foreach ($candidates as $candidate) {
            if (self::is_session_available_to_user($candidate, $userid)) {
                $available[] = $candidate;
            }
        }

        return count($available) === 1 ? reset($available) : null;
    }

    /**
     * Attach a resolved session to an already-created Moodle Quiz result.
     *
     * @param int $resultid
     * @param object $session
     */
    protected static function attach_session_to_existing_quiz_result(int $resultid, object $session): void {
        global $DB;

        $attempt = $DB->get_record_sql(
            "SELECT a.*
               FROM {local_flwexam_attempts} a
               JOIN {local_flwexam_results} r ON r.attemptid = a.id
              WHERE r.id = :resultid",
            ['resultid' => $resultid],
            IGNORE_MISSING
        );
        if (!$attempt || (int)$attempt->sessionid > 0 || $attempt->source !== 'modquiz') {
            return;
        }

        $metadata = json_decode($attempt->metadatajson ?? '[]', true) ?: [];
        $metadata['session_id'] = (int)$session->id;
        $metadata['session_type'] = $session->sessiontype;
        $metadata['session_name'] = $session->name;

        $DB->set_field('local_flwexam_attempts', 'sessionid', (int)$session->id, ['id' => (int)$attempt->id]);
        $DB->set_field('local_flwexam_attempts', 'metadatajson', json_encode($metadata), ['id' => (int)$attempt->id]);
    }

    /**
     * Check whether a session is visible to the learner.
     *
     * @param object $session
     * @param int $userid
     * @return bool
     */
    protected static function is_session_available_to_user(object $session, int $userid): bool {
        global $CFG, $DB;

        if (empty($session->visible) || $session->status !== 'open') {
            return false;
        }
        if ((int)$session->courseid > 0) {
            require_once($CFG->libdir . '/enrollib.php');
            $courseids = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
            if (!in_array((int)$session->courseid, $courseids, true)) {
                return false;
            }
        }
        if ((int)$session->groupid > 0 && !$DB->record_exists('groups_members', [
            'groupid' => (int)$session->groupid,
            'userid' => $userid,
        ])) {
            return false;
        }
        return true;
    }

    /**
     * Get selector options for the exam start page.
     *
     * @return array
     */
    public static function get_exam_filter_options(): array {
        return [
            'languages' => self::get_learning_language_options(),
            'levels' => self::get_cefr_level_options(),
        ];
    }

    /**
     * Get FLW learning language selector options.
     *
     * @return array
     */
    public static function get_learning_language_options(): array {
        global $CFG;

        $languages = [];
        $themelib = $CFG->dirroot . '/theme/flwacademy/lib.php';
        if (!function_exists('theme_flwacademy_export_learning_languages') && is_readable($themelib)) {
            require_once($themelib);
        }
        if (function_exists('theme_flwacademy_export_learning_languages')) {
            foreach (theme_flwacademy_export_learning_languages() as $language) {
                if (!empty($language['code']) && !empty($language['label'])) {
                    $languages[$language['code']] = $language['label'];
                }
            }
        }

        if ($languages) {
            return $languages;
        }

        return [
            'en' => get_string('languageen', 'local_flwexam'),
            'ru' => get_string('languageru', 'local_flwexam'),
            'zh' => get_string('languagezh', 'local_flwexam'),
            'ja' => get_string('languageja', 'local_flwexam'),
            'de' => get_string('languagede', 'local_flwexam'),
            'fr' => get_string('languagefr', 'local_flwexam'),
            'es' => get_string('languagees', 'local_flwexam'),
        ];
    }

    /**
     * Get CEFR selector options.
     *
     * @return array
     */
    public static function get_cefr_level_options(): array {
        return [
            'A1' => 'A1',
            'A2' => 'A2',
            'B1' => 'B1',
            'B2' => 'B2',
            'C1' => 'C1',
            'C2' => 'C2',
        ];
    }

    /**
     * Get legacy internal category options for a language.
     *
     * @param string $language
     * @return array
     */
    public static function get_track_options_for_language(string $language): array {
        $language = clean_param($language, PARAM_ALPHANUMEXT);
        $languages = self::get_learning_language_options();
        $label = $languages[$language] ?? self::language_label($language);

        if ($language === 'en') {
            return [
                'adventure_world' => get_string('trackenglishadventureworld', 'local_flwexam'),
                'real_world' => get_string('trackenglishrealworld', 'local_flwexam'),
            ];
        }

        if ($language === '') {
            return [];
        }

        return [
            $language . '_world' => get_string('tracklanguageworld', 'local_flwexam', $label),
        ];
    }

    /**
     * Human label for language code.
     *
     * @param string $language
     * @return string
     */
    public static function language_label(string $language): string {
        $language = clean_param($language, PARAM_ALPHANUMEXT);
        $labels = [
            'en' => get_string('languageen', 'local_flwexam'),
            'ru' => get_string('languageru', 'local_flwexam'),
            'zh' => get_string('languagezh', 'local_flwexam'),
            'ja' => get_string('languageja', 'local_flwexam'),
            'de' => get_string('languagede', 'local_flwexam'),
            'fr' => get_string('languagefr', 'local_flwexam'),
            'es' => get_string('languagees', 'local_flwexam'),
        ];
        return $labels[$language] ?? strtoupper($language);
    }

    /**
     * Human label for an exam skill key.
     *
     * @param string $skill
     * @return string
     */
    public static function skill_label(string $skill): string {
        $skill = clean_param($skill, PARAM_ALPHANUMEXT);
        $labels = [
            'listening' => get_string('listening', 'local_flwexam'),
            'speaking' => get_string('speaking', 'local_flwexam'),
            'reading' => get_string('reading', 'local_flwexam'),
            'writing' => get_string('writing', 'local_flwexam'),
        ];
        return $labels[$skill] ?? get_string('skillunknown', 'local_flwexam', $skill);
    }

    /**
     * Human label for a legacy internal category key.
     *
     * @param string $track
     * @return string
     */
    public static function track_label(string $track): string {
        $track = clean_param($track, PARAM_ALPHANUMEXT);
        $labels = [
            'adventure_world' => get_string('trackenglishadventureworld', 'local_flwexam'),
            'real_world' => get_string('trackenglishrealworld', 'local_flwexam'),
        ];
        if (isset($labels[$track])) {
            return $labels[$track];
        }
        if (preg_match('/^([a-z0-9_-]+)_world$/', $track, $matches)) {
            return get_string('tracklanguageworld', 'local_flwexam', self::language_label($matches[1]));
        }
        return get_string('trackunknown', 'local_flwexam', $track);
    }

    /**
     * Human label for an exam session type.
     *
     * @param string $type
     * @return string
     */
    public static function session_type_label(string $type): string {
        if ($type === self::SESSION_TYPE_OFFICIAL) {
            return get_string('officialexam', 'local_flwexam');
        }
        if ($type === self::SESSION_TYPE_TEACHER) {
            return get_string('teacherexam', 'local_flwexam');
        }
        return get_string('selfexamsession', 'local_flwexam');
    }

    /**
     * Return valid session status options.
     *
     * @return array
     */
    public static function get_session_status_options(): array {
        return [
            'draft' => get_string('sessionstatusdraft', 'local_flwexam'),
            'open' => get_string('sessionstatusopen', 'local_flwexam'),
            'closed' => get_string('sessionstatusclosed', 'local_flwexam'),
        ];
    }

    /**
     * Return sessions a learner can see in Exam Center.
     *
     * @param int $userid
     * @param array $filters
     * @return array
     */
    public static function get_available_sessions(int $userid, array $filters = []): array {
        global $CFG, $DB;

        if (!$DB->get_manager()->table_exists('local_flwexam_sessions')) {
            return [];
        }

        $where = ['s.visible = 1', 's.status = :status', 'e.visible = 1'];
        $params = ['status' => 'open'];
        foreach ([
            'language' => 'language',
            'learning_course_category' => 'learningcoursecategory',
            'cefr_level' => 'cefrlevel',
        ] as $filterkey => $field) {
            $value = clean_param($filters[$filterkey] ?? '', PARAM_ALPHANUMEXT);
            if ($value !== '') {
                $where[] = 'e.' . $field . ' = :' . $filterkey;
                $params[$filterkey] = $value;
            }
        }

        $now = time();
        $where[] = '(s.timeend = 0 OR s.timeend >= :nowend)';
        $params['nowend'] = $now;

        $sql = "SELECT s.*, e.code AS examcode, e.name AS examname, e.language,
                       e.learningcoursecategory, e.cefrlevel, e.quizid
                  FROM {local_flwexam_sessions} s
                  JOIN {local_flwexam_exams} e ON e.id = s.examid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY s.sessiontype ASC, s.timestart ASC, s.timeend ASC, s.name ASC";
        $records = $DB->get_records_sql($sql, $params);

        require_once($CFG->libdir . '/enrollib.php');
        $courseids = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
        $sessions = [];
        foreach ($records as $record) {
            if ((int)$record->courseid > 0 && !in_array((int)$record->courseid, $courseids, true)) {
                continue;
            }
            if ((int)$record->groupid > 0 && !$DB->record_exists('groups_members', [
                'groupid' => (int)$record->groupid,
                'userid' => $userid,
            ])) {
                continue;
            }
            $attemptcount = self::count_session_attempts((int)$record->id, $userid);
            $maxattempts = max(1, (int)$record->maxattempts);
            if ($attemptcount >= $maxattempts) {
                continue;
            }
            $sessions[] = self::export_session_record($record, $attemptcount);
        }

        return $sessions;
    }

    /**
     * Return all sessions for the organiser page.
     *
     * @return array
     */
    public static function get_manage_sessions(): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_flwexam_sessions')) {
            return [];
        }

        $sql = "SELECT s.*, e.code AS examcode, e.name AS examname, e.language,
                       e.learningcoursecategory, e.cefrlevel, e.quizid
                  FROM {local_flwexam_sessions} s
                  JOIN {local_flwexam_exams} e ON e.id = s.examid
              ORDER BY s.timemodified DESC, s.id DESC";
        $records = $DB->get_records_sql($sql);
        $sessions = [];
        foreach ($records as $record) {
            $sessions[] = self::export_session_record($record, 0);
        }
        return $sessions;
    }

    /**
     * Return a session joined to its exam.
     *
     * @param int $sessionid
     * @return object
     */
    public static function get_session(int $sessionid): object {
        global $DB;

        $sql = "SELECT s.*, e.code AS examcode, e.name AS examname, e.language,
                       e.learningcoursecategory, e.cefrlevel, e.quizid
                  FROM {local_flwexam_sessions} s
                  JOIN {local_flwexam_exams} e ON e.id = s.examid
                 WHERE s.id = :sessionid";
        return $DB->get_record_sql($sql, ['sessionid' => $sessionid], MUST_EXIST);
    }

    /**
     * Validate that a learner may attempt a session right now.
     *
     * @param object $session
     * @param int $userid
     * @param string $accesscode
     */
    public static function require_can_attempt_session(object $session, int $userid, string $accesscode = ''): void {
        global $CFG, $DB;

        if (empty($session->visible) || $session->status !== 'open') {
            throw new moodle_exception('sessionnotopen', 'local_flwexam');
        }

        $now = time();
        if (!empty($session->timestart) && (int)$session->timestart > $now) {
            throw new moodle_exception('sessionnotopen', 'local_flwexam');
        }
        if (!empty($session->timeend) && (int)$session->timeend < $now) {
            throw new moodle_exception('sessionclosed', 'local_flwexam');
        }

        if ((int)$session->courseid > 0) {
            require_once($CFG->libdir . '/enrollib.php');
            $courseids = array_map('intval', array_keys(enrol_get_users_courses($userid, true, 'id')));
            if (!in_array((int)$session->courseid, $courseids, true)) {
                throw new moodle_exception('sessionnotavailable', 'local_flwexam');
            }
        }
        if ((int)$session->groupid > 0 && !$DB->record_exists('groups_members', [
            'groupid' => (int)$session->groupid,
            'userid' => $userid,
        ])) {
            throw new moodle_exception('sessionnotavailable', 'local_flwexam');
        }

        $expectedcode = trim((string)($session->accesscode ?? ''));
        if ($expectedcode !== '' && !hash_equals($expectedcode, trim($accesscode))) {
            throw new moodle_exception('invalidsessionaccesscode', 'local_flwexam');
        }

        $attemptcount = self::count_session_attempts((int)$session->id, $userid);
        if ($attemptcount >= max(1, (int)$session->maxattempts)) {
            throw new moodle_exception('sessionattemptlimitreached', 'local_flwexam');
        }
    }

    /**
     * Count a learner's attempts in one organised session.
     *
     * @param int $sessionid
     * @param int $userid
     * @return int
     */
    public static function count_session_attempts(int $sessionid, int $userid): int {
        global $DB;

        if ($sessionid <= 0 || !$DB->get_manager()->field_exists('local_flwexam_attempts', 'sessionid')) {
            return 0;
        }

        return (int)$DB->count_records('local_flwexam_attempts', [
            'sessionid' => $sessionid,
            'userid' => $userid,
        ]);
    }

    /**
     * Export visible questions without correct answers.
     *
     * @param int $examid
     * @param int $limit
     * @param array $questionids
     * @return array
     */
    public static function get_attempt_questions(int $examid, int $limit = 20, array $questionids = []): array {
        global $DB;

        if ($questionids) {
            [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $questionids), SQL_PARAMS_NAMED, 'qid');
            $params['examid'] = $examid;
            $questions = $DB->get_records_sql(
                "SELECT *
                   FROM {local_flwexam_questions}
                  WHERE examid = :examid
                    AND visible = 1
                    AND id $insql
               ORDER BY sortorder ASC, id ASC",
                $params
            );
        } else {
            $questions = $DB->get_records('local_flwexam_questions', [
                'examid' => $examid,
                'visible' => 1,
            ], 'sortorder ASC, id ASC');
            $limit = max(1, min(30, $limit));
            if (count($questions) > $limit) {
                $questions = array_values($questions);
                shuffle($questions);
                $questions = array_slice($questions, 0, $limit);
            }
        }
        $out = [];
        foreach ($questions as $question) {
            $out[] = [
                'id' => (int)$question->id,
                'qtype' => $question->qtype,
                'questiontext' => $question->questiontext,
                'options' => json_decode($question->optionsjson ?? '[]', true) ?: [],
                'skill' => $question->skill,
                'kpcode' => $question->kpcode,
            ];
        }
        return $out;
    }

    /**
     * Grade a learner attempt and record the official result.
     *
     * @param int $examid
     * @param int $userid
     * @param array $answers
     * @param int $sessionid
     * @param array $questionids
     * @param string $accesscode
     * @return array
     */
    public static function submit_learner_attempt(
        int $examid,
        int $userid,
        array $answers,
        int $sessionid = 0,
        array $questionids = [],
        string $accesscode = ''
    ): array {
        global $DB;

        $context = context_system::instance();
        require_capability('local/flwexam:viewown', $context);

        $exam = $DB->get_record('local_flwexam_exams', [
            'id' => $examid,
            'visible' => 1,
        ], '*', MUST_EXIST);
        $session = null;
        if ($sessionid > 0) {
            $session = self::get_session($sessionid);
            if ((int)$session->examid !== (int)$exam->id) {
                throw new moodle_exception('sessionnotavailable', 'local_flwexam');
            }
            self::require_can_attempt_session($session, $userid, $accesscode);
        }

        $questions = self::get_grading_questions($examid, $questionids);
        if (!$questions) {
            throw new moodle_exception('noquestions', 'local_flwexam');
        }

        $totalweight = 0.0;
        $correctweight = 0.0;
        $skilltotals = [];
        $skillcorrect = [];
        $kptotals = [];
        $kpcorrect = [];
        $answerlog = [];
        foreach ($questions as $question) {
            $weight = max(0.01, (float)$question->weight);
            $submitted = self::normalise_question_answer($answers[(int)$question->id] ?? '', $question->qtype);
            $correctanswer = self::normalise_question_answer($question->correctanswer, $question->qtype);
            $iscorrect = $submitted !== '' && $submitted === $correctanswer;

            $totalweight += $weight;
            $correctweight += $iscorrect ? $weight : 0;
            $skilltotals[$question->skill] = ($skilltotals[$question->skill] ?? 0) + $weight;
            $skillcorrect[$question->skill] = ($skillcorrect[$question->skill] ?? 0) + ($iscorrect ? $weight : 0);
            $kptotals[$question->kpcode] = ($kptotals[$question->kpcode] ?? 0) + $weight;
            $kpcorrect[$question->kpcode] = ($kpcorrect[$question->kpcode] ?? 0) + ($iscorrect ? $weight : 0);
            $answerlog[] = [
                'questionid' => (int)$question->id,
                'qtype' => $question->qtype,
                'answer' => $submitted,
                'correct' => $iscorrect,
                'skill' => $question->skill,
                'kpcode' => $question->kpcode,
            ];
        }

        $skills = [];
        foreach ($skilltotals as $skill => $total) {
            $skills[] = [
                'skill' => $skill,
                'score' => $total > 0 ? round(($skillcorrect[$skill] / $total) * 100, 2) : 0,
            ];
        }

        $criticalkp = array_flip(self::normalise_critical_kp_codes($exam));
        $kpresults = [];
        foreach ($kptotals as $kpcode => $total) {
            $score = $total > 0 ? round(($kpcorrect[$kpcode] / $total) * 100, 2) : 0;
            $kpresults[] = [
                'kpcode' => $kpcode,
                'score' => $score,
                'passed' => $score >= 60,
                'critical' => isset($criticalkp[$kpcode]),
            ];
        }

        $overall = $totalweight > 0 ? round(($correctweight / $totalweight) * 100, 2) : 0;
        $result = self::record_result([
            'userid' => $userid,
            'examid' => (int)$exam->id,
            'language' => $exam->language,
            'learning_course_category' => $exam->learningcoursecategory,
            'cefr_level' => $exam->cefrlevel,
            'overall_score' => $overall,
            'skill_scores' => $skills,
            'kp_results' => $kpresults,
            'integrity_status' => 'clear',
            'moderation_status' => 'approved',
            'attempt_metadata' => [
                'source' => 'local_flwexam_take',
                'session_id' => $session ? (int)$session->id : 0,
                'session_type' => $session ? $session->sessiontype : self::SESSION_TYPE_SELF,
                'session_name' => $session ? $session->name : get_string('selfexamsession', 'local_flwexam'),
                'question_count' => count($questions),
                'answer_log' => $answerlog,
            ],
        ], $userid, false);

        self::audit('learner_attempt_submitted', $userid, $userid, (int)$result['id'], (int)$result['certificate_id'], 'success', [
            'examid' => (int)$exam->id,
            'sessionid' => $session ? (int)$session->id : 0,
            'overall_score' => $overall,
        ]);

        return $result;
    }

    /**
     * Fetch visible question records for grading.
     *
     * @param int $examid
     * @param array $questionids
     * @return array
     */
    private static function get_grading_questions(int $examid, array $questionids = []): array {
        global $DB;

        if (!$questionids) {
            return $DB->get_records('local_flwexam_questions', [
                'examid' => $examid,
                'visible' => 1,
            ], 'sortorder ASC, id ASC');
        }

        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $questionids), SQL_PARAMS_NAMED, 'qid');
        $params['examid'] = $examid;
        return $DB->get_records_sql(
            "SELECT *
               FROM {local_flwexam_questions}
              WHERE examid = :examid
                AND visible = 1
                AND id $insql
           ORDER BY sortorder ASC, id ASC",
            $params
        );
    }

    /**
     * Export one organised session row.
     *
     * @param object $record
     * @param int $attemptcount
     * @return array
     */
    private static function export_session_record(object $record, int $attemptcount): array {
        $now = time();
        $canstart = $record->status === 'open' &&
            !empty($record->visible) &&
            ((int)$record->timestart <= 0 || (int)$record->timestart <= $now) &&
            ((int)$record->timeend <= 0 || (int)$record->timeend >= $now) &&
            $attemptcount < max(1, (int)$record->maxattempts);
        $availability = $canstart ? 'available' : 'comingsoon';
        if (!empty($record->timeend) && (int)$record->timeend < $now) {
            $availability = 'closed';
        } else if (!empty($record->timestart) && (int)$record->timestart > $now) {
            $availability = 'comingsoon';
        } else if ($attemptcount >= max(1, (int)$record->maxattempts)) {
            $availability = 'attemptlimitreached';
        }

        return [
            'id' => (int)$record->id,
            'name' => self::format_display_name($record->name),
            'session_type' => $record->sessiontype,
            'session_type_label' => self::session_type_label($record->sessiontype),
            'examid' => (int)$record->examid,
            'examcode' => $record->examcode,
            'examname' => self::format_display_name($record->examname),
            'language' => $record->language,
            'learning_course_category' => $record->learningcoursecategory,
            'cefr_level' => $record->cefrlevel,
            'courseid' => (int)$record->courseid,
            'groupid' => (int)$record->groupid,
            'question_count' => max(1, min(30, (int)$record->questioncount)),
            'max_attempts' => max(1, (int)$record->maxattempts),
            'attempt_count' => $attemptcount,
            'timestart' => (int)$record->timestart,
            'timeend' => (int)$record->timeend,
            'requires_access_code' => trim((string)$record->accesscode) !== '',
            'branchname' => $record->branchname,
            'requireproctor' => !empty($record->requireproctor),
            'status' => $record->status,
            'visible' => !empty($record->visible),
            'can_start' => $canstart,
            'availability_status' => $availability,
        ];
    }

    /**
     * Normalise a submitted learner answer for server-side grading.
     *
     * @param mixed $answer
     * @param string $qtype
     * @return string
     */
    private static function normalise_question_answer($answer, string $qtype): string {
        $answer = trim((string)$answer);
        if ($qtype === 'shortanswer') {
            $answer = core_text::strtolower($answer);
            $answer = preg_replace('/\s+/u', ' ', $answer);
            return clean_param($answer, PARAM_TEXT);
        }
        if ($qtype === 'truefalse') {
            return clean_param(core_text::strtolower($answer), PARAM_ALPHA);
        }
        return clean_param($answer, PARAM_ALPHANUMEXT);
    }

    /**
     * Submit an official exam result and run certificate logic.
     *
     * @param array $data
     * @param int $actorid
     * @return array
     */
    public static function submit_result(array $data, int $actorid): array {
        return self::record_result($data, $actorid, true);
    }

    /**
     * Record an official exam result from a trusted source or server-side attempt.
     *
     * @param array $data
     * @param int $actorid
     * @param bool $requiresubmitcapability
     * @param bool $trustedreturn Return a minimal package without current-user capability checks.
     * @return array
     */
    public static function record_result(
        array $data,
        int $actorid,
        bool $requiresubmitcapability = true,
        bool $trustedreturn = false
    ): array {
        global $DB;

        if ($requiresubmitcapability) {
            require_capability('local/flwexam:submitresult', context_system::instance());
        }

        $exam = $DB->get_record('local_flwexam_exams', ['id' => (int)$data['examid']], '*', MUST_EXIST);
        $userid = (int)$data['userid'];
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new invalid_parameter_exception('Unknown learner user id.');
        }

        $language = self::clean_key($data['language'] ?: $exam->language, 'language');
        $category = self::clean_key($data['learning_course_category'] ?: $exam->learningcoursecategory, 'learning course category');
        $cefr = self::clean_key($data['cefr_level'] ?: $exam->cefrlevel, 'CEFR level');
        $integrity = self::clean_status($data['integrity_status'] ?? 'clear', 'integrity status');
        $moderation = self::clean_status($data['moderation_status'] ?? 'approved', 'moderation status');
        $skills = self::normalise_skill_scores($data['skill_scores'] ?? []);
        $kpresults = self::normalise_kp_results($data['kp_results'] ?? [], $exam);
        $metadata = is_array($data['attempt_metadata'] ?? null) ? $data['attempt_metadata'] : [];
        $sessionid = max(0, (int)($metadata['session_id'] ?? $data['session_id'] ?? 0));
        $now = time();

        $decision = self::decide_certificate($exam, (float)$data['overall_score'], $skills, $kpresults, $integrity, $moderation);

        $transaction = $DB->start_delegated_transaction();
        try {
            $attemptconditions = [
                'userid' => $userid,
                'examid' => (int)$exam->id,
            ];
            if ($sessionid > 0) {
                $attemptconditions['sessionid'] = $sessionid;
            }
            $attemptnumber = (int)$DB->count_records('local_flwexam_attempts', $attemptconditions) + 1;
            $attempt = (object)[
                'userid' => $userid,
                'examid' => (int)$exam->id,
                'sessionid' => $sessionid,
                'attemptnumber' => $attemptnumber,
                'source' => self::clean_key($metadata['source'] ?? 'api', 'source'),
                'externalattemptid' => clean_param($metadata['external_attempt_id'] ?? '', PARAM_ALPHANUMEXT),
                'status' => 'submitted',
                'metadatajson' => json_encode($metadata),
                'timestarted' => (int)($metadata['time_started'] ?? 0),
                'timefinished' => (int)($metadata['time_finished'] ?? $now),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $attemptid = $DB->insert_record('local_flwexam_attempts', $attempt);

            $result = (object)[
                'userid' => $userid,
                'examid' => (int)$exam->id,
                'attemptid' => $attemptid,
                'language' => $language,
                'learningcoursecategory' => $category,
                'cefrlevel' => $cefr,
                'overallscore' => (float)$data['overall_score'],
                'passstatus' => $decision['passed'] ? self::PASS_STATUS_PASSED : self::PASS_STATUS_FAILED,
                'certificatestatus' => $decision['passed'] ? 'eligible' : 'not_issued',
                'certificateid' => 0,
                'integritystatus' => $integrity,
                'moderationstatus' => $moderation,
                'decisionjson' => json_encode($decision),
                'summaryjson' => json_encode([
                    'submitted_by' => $actorid,
                    'attempt_metadata' => $metadata,
                ]),
                'timemoderated' => $moderation === 'approved' ? $now : 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $resultid = $DB->insert_record('local_flwexam_results', $result);

            foreach ($skills as $skill => $score) {
                $DB->insert_record('local_flwexam_skill_scores', (object)[
                    'resultid' => $resultid,
                    'skill' => $skill,
                    'score' => $score,
                    'passed' => $score >= (float)$exam->requiredskillfloor ? 1 : 0,
                    'detailsjson' => null,
                    'timecreated' => $now,
                ]);
            }
            foreach ($kpresults as $kpcode => $kp) {
                $DB->insert_record('local_flwexam_kp_results', (object)[
                    'resultid' => $resultid,
                    'kpcode' => $kpcode,
                    'score' => (float)$kp['score'],
                    'passed' => !empty($kp['passed']) ? 1 : 0,
                    'critical' => !empty($kp['critical']) ? 1 : 0,
                    'detailsjson' => json_encode($kp['details'] ?? []),
                    'timecreated' => $now,
                ]);
            }

            $certificateid = 0;
            if ($decision['passed']) {
                $certificateid = self::issue_certificate_if_needed($resultid, $exam, $userid, $language, $category, $cefr, $now);
                $resultupdate = (object)[
                    'id' => $resultid,
                    'certificateid' => $certificateid,
                    'certificatestatus' => $DB->record_exists('local_flwexam_certificates', [
                        'id' => $certificateid,
                        'resultid' => $resultid,
                    ]) ? 'issued' : 'already_issued',
                    'timemodified' => $now,
                ];
                $DB->update_record('local_flwexam_results', $resultupdate);
            }

            self::audit('result_submitted', $actorid, $userid, $resultid, $certificateid, 'success', [
                'examid' => (int)$exam->id,
                'passed' => $decision['passed'],
            ]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        if ($trustedreturn) {
            return self::get_trusted_result_summary((int)$resultid);
        }

        return self::get_result_package($resultid, $actorid, true);
    }

    /**
     * Decide whether certificate gates pass.
     *
     * @param object $exam
     * @param float $overallscore
     * @param array $skills
     * @param array $kpresults
     * @param string $integrity
     * @param string $moderation
     * @return array
     */
    public static function decide_certificate(
        object $exam,
        float $overallscore,
        array $skills,
        array $kpresults,
        string $integrity,
        string $moderation
    ): array {
        $requiredthreshold = (float)$exam->requiredthreshold;
        $requiredskillfloor = (float)$exam->requiredskillfloor;
        $criticalkp = self::normalise_critical_kp_codes($exam);

        $failures = [];
        if ($overallscore < $requiredthreshold) {
            $failures[] = 'overall_threshold';
        }
        foreach ($skills as $skill => $score) {
            if ((float)$score < $requiredskillfloor) {
                $failures[] = 'skill_floor:' . $skill;
            }
        }
        foreach ($criticalkp as $kpcode) {
            if (empty($kpresults[$kpcode]) || empty($kpresults[$kpcode]['passed'])) {
                $failures[] = 'critical_kp:' . $kpcode;
            }
        }
        if (!empty($exam->moderationrequired) && $moderation !== 'approved') {
            $failures[] = 'moderation_required';
        }
        if ($integrity !== 'clear') {
            $failures[] = 'integrity_block';
        }

        return [
            'passed' => empty($failures),
            'failures' => $failures,
            'required_threshold' => $requiredthreshold,
            'required_skill_floor' => $requiredskillfloor,
            'critical_kp' => array_values($criticalkp),
            'moderation_required' => !empty($exam->moderationrequired),
        ];
    }

    /**
     * Verify a certificate code and return only safe public fields.
     *
     * @param string $verifycode
     * @param int $viewerid
     * @return array
     */
    public static function verify_certificate(string $verifycode, int $viewerid = 0): array {
        global $DB;

        $verifycode = clean_param($verifycode, PARAM_ALPHANUMEXT);
        if ($verifycode === '') {
            throw new invalid_parameter_exception(get_string('missingverificationcode', 'local_flwexam'));
        }

        $sql = "SELECT c.*, t.verifycode, t.id AS tokenid, t.status AS tokenstatus, t.timeexpires AS tokenexpires
                  FROM {local_flwexam_verify_tokens} t
                  JOIN {local_flwexam_certificates} c ON c.id = t.certificateid
                 WHERE t.verifycode = :verifycode";
        $certificate = $DB->get_record_sql($sql, ['verifycode' => $verifycode], MUST_EXIST);
        $now = time();
        $status = $certificate->status;
        if ($status === self::CERT_STATUS_VALID && !empty($certificate->timeexpires) && (int)$certificate->timeexpires < $now) {
            $status = 'expired';
        }
        if ($certificate->tokenstatus !== 'active') {
            $status = 'inactive';
        }

        $DB->set_field('local_flwexam_verify_tokens', 'lastverified', $now, ['id' => $certificate->tokenid]);

        $user = core_user::get_user($certificate->userid, '*', IGNORE_MISSING);
        $canviewprivate = $viewerid > 0 && has_capability('local/flwexam:viewall', context_system::instance());
        self::audit('certificate_verified', $viewerid, (int)$certificate->userid, 0, (int)$certificate->id, 'success', [
            'verify_code_prefix' => substr($verifycode, 0, 8),
        ]);

        return [
            'valid' => $status === self::CERT_STATUS_VALID,
            'certificate_id' => (int)$certificate->id,
            'certificate_code' => $certificate->certificatecode,
            'learner_name' => $user ? self::safe_display_name($user, $canviewprivate) : '',
            'language' => $certificate->language,
            'learning_course_category' => $certificate->learningcoursecategory,
            'cefr_level' => $certificate->cefrlevel,
            'status' => $status,
            'timeissued' => (int)$certificate->timeissued,
            'timeexpires' => (int)$certificate->timeexpires,
        ];
    }

    /**
     * Write an audit record.
     *
     * @param string $eventname
     * @param int $userid
     * @param int $targetuserid
     * @param int $resultid
     * @param int $certificateid
     * @param string $status
     * @param array $details
     */
    public static function audit(
        string $eventname,
        int $userid,
        int $targetuserid,
        int $resultid,
        int $certificateid,
        string $status,
        array $details = []
    ): void {
        global $DB;

        try {
            $DB->insert_record('local_flwexam_audit_log', (object)[
                'eventname' => clean_param($eventname, PARAM_ALPHANUMEXT),
                'userid' => $userid,
                'targetuserid' => $targetuserid,
                'resultid' => $resultid,
                'certificateid' => $certificateid,
                'ipaddress' => getremoteaddr() ?: '',
                'status' => clean_param($status, PARAM_ALPHANUMEXT),
                'detailsjson' => json_encode($details),
                'timecreated' => time(),
            ]);
        } catch (dml_exception $e) {
            debugging('Could not write FLW Exam audit log: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Export a compact history row.
     *
     * @param object $record
     * @return array
     */
    protected static function export_history_record(object $record): array {
        $metadata = json_decode($record->attemptmetadatajson ?? '[]', true) ?: [];
        $sessiontype = $record->sessiontype ?? ($metadata['session_type'] ?? self::SESSION_TYPE_SELF);
        $sessionname = $record->sessionname ?? ($metadata['session_name'] ?? get_string('selfexamsession', 'local_flwexam'));
        return [
            'id' => (int)$record->id,
            'examid' => (int)$record->examid,
            'examname' => self::format_display_name($record->examname),
            'examcode' => $record->examcode,
            'session_id' => (int)($record->sessionid ?? 0),
            'session_name' => self::format_display_name((string)$sessionname),
            'session_type' => (string)$sessiontype,
            'session_type_label' => self::session_type_label((string)$sessiontype),
            'branchname' => (string)($record->branchname ?? ''),
            'session_time_start' => (int)($record->sessiontimestart ?? 0),
            'session_time_end' => (int)($record->sessiontimeend ?? 0),
            'session_question_count' => (int)($record->sessionquestioncount ?? 0),
            'session_max_attempts' => (int)($record->sessionmaxattempts ?? 0),
            'language' => $record->language,
            'learning_course_category' => $record->learningcoursecategory,
            'cefr_level' => $record->cefrlevel,
            'overall_score' => (float)$record->overallscore,
            'pass_status' => $record->passstatus,
            'certificate_status' => $record->certificatestatus,
            'certificate_id' => (int)$record->certificateid,
            'verify_code' => $record->verifycode ?? '',
            'timecreated' => (int)$record->timecreated,
        ];
    }

    /**
     * Export skill rows.
     *
     * @param array $skills
     * @return array
     */
    protected static function export_skill_rows(array $skills): array {
        $out = [];
        foreach ($skills as $skill) {
            $out[] = [
                'skill' => $skill->skill,
                'score' => (float)$skill->score,
                'passed' => (int)$skill->passed,
            ];
        }
        return $out;
    }

    /**
     * Export KP rows.
     *
     * @param array $kpresults
     * @return array
     */
    protected static function export_kp_rows(array $kpresults): array {
        $out = [];
        foreach ($kpresults as $kp) {
            $out[] = [
                'kpcode' => $kp->kpcode,
                'score' => (float)$kp->score,
                'passed' => (int)$kp->passed,
                'critical' => (int)$kp->critical,
            ];
        }
        return $out;
    }

    /**
     * Create a certificate unless a valid one already exists for the same profile.
     *
     * @param int $resultid
     * @param object $exam
     * @param int $userid
     * @param string $language
     * @param string $category
     * @param string $cefr
     * @param int $now
     * @return int
     */
    protected static function issue_certificate_if_needed(
        int $resultid,
        object $exam,
        int $userid,
        string $language,
        string $category,
        string $cefr,
        int $now
    ): int {
        global $DB;

        $existing = $DB->get_record('local_flwexam_certificates', [
            'userid' => $userid,
            'examid' => (int)$exam->id,
            'language' => $language,
            'learningcoursecategory' => $category,
            'cefrlevel' => $cefr,
            'status' => self::CERT_STATUS_VALID,
        ], '*', IGNORE_MULTIPLE);
        if ($existing) {
            return (int)$existing->id;
        }

        $certificatecode = self::unique_code('FLW-CERT-', 'local_flwexam_certificates', 'certificatecode', 12);
        $certificateid = $DB->insert_record('local_flwexam_certificates', (object)[
            'certificatecode' => $certificatecode,
            'userid' => $userid,
            'examid' => (int)$exam->id,
            'resultid' => $resultid,
            'language' => $language,
            'learningcoursecategory' => $category,
            'cefrlevel' => $cefr,
            'status' => self::CERT_STATUS_VALID,
            'timeissued' => $now,
            'timeexpires' => 0,
            'timerevoked' => 0,
            'revokedby' => 0,
            'revokereason' => null,
        ]);

        $DB->insert_record('local_flwexam_verify_tokens', (object)[
            'certificateid' => $certificateid,
            'verifycode' => self::unique_code('FLW-VERIFY-', 'local_flwexam_verify_tokens', 'verifycode', 20),
            'status' => 'active',
            'timecreated' => $now,
            'timeexpires' => 0,
            'lastverified' => 0,
        ]);

        return (int)$certificateid;
    }

    /**
     * Generate a unique code.
     *
     * @param string $prefix
     * @param string $table
     * @param string $field
     * @param int $bytes
     * @return string
     */
    protected static function unique_code(string $prefix, string $table, string $field, int $bytes): string {
        global $DB;

        do {
            $code = $prefix . strtoupper(bin2hex(random_bytes($bytes)));
        } while ($DB->record_exists($table, [$field => $code]));
        return $code;
    }

    /**
     * Count Moodle Quiz source questions.
     *
     * Random-slot quizzes should report the size of the source bank rather than
     * only the sampled attempt length.
     *
     * @param int $quizid
     * @return int
     */
    protected static function get_quiz_question_count(int $quizid): int {
        global $DB;

        if ($quizid <= 0) {
            return 0;
        }

        $entryids = [];
        $directentries = $DB->get_records_sql(
            "SELECT DISTINCT qr.questionbankentryid AS id
               FROM {quiz_slots} qs
               JOIN {question_references} qr
                 ON qr.itemid = qs.id
                AND qr.component = :component
                AND qr.questionarea = :questionarea
              WHERE qs.quizid = :quizid",
            [
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'quizid' => $quizid,
            ]
        );
        foreach ($directentries as $entry) {
            $entryids[(int)$entry->id] = true;
        }

        $categoryids = self::get_quiz_random_source_category_ids($quizid);
        if ($categoryids) {
            [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
            $params['status'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
            $randomentries = $DB->get_records_sql(
                "SELECT DISTINCT qbe.id
                   FROM {question_bank_entries} qbe
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                   JOIN {question} q ON q.id = qv.questionid
                  WHERE qbe.questioncategoryid {$insql}
                    AND qv.status = :status
                    AND q.parent = 0",
                $params
            );
            foreach ($randomentries as $entry) {
                $entryids[(int)$entry->id] = true;
            }
        }

        return $entryids ? count($entryids) : (int)$DB->count_records('quiz_slots', ['quizid' => $quizid]);
    }

    /**
     * Get question category ids used by random slots in a Moodle Quiz.
     *
     * @param int $quizid
     * @return array
     */
    protected static function get_quiz_random_source_category_ids(int $quizid): array {
        global $DB;

        $refs = $DB->get_records_sql(
            "SELECT qsr.id, qsr.filtercondition
               FROM {quiz_slots} qs
               JOIN {question_set_references} qsr
                 ON qsr.itemid = qs.id
                AND qsr.component = :component
                AND qsr.questionarea = :questionarea
              WHERE qs.quizid = :quizid",
            [
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'quizid' => $quizid,
            ]
        );

        $categoryids = [];
        foreach ($refs as $ref) {
            $filter = json_decode($ref->filtercondition ?? '[]', true) ?: [];
            $categoryfilter = $filter['filter']['category'] ?? [];
            $values = array_map('intval', $categoryfilter['values'] ?? []);
            if (!empty($categoryfilter['filteroptions']['includesubcategories'])) {
                $values = self::expand_question_category_ids($values);
            }
            foreach ($values as $value) {
                if ($value > 0) {
                    $categoryids[$value] = $value;
                }
            }
        }

        return array_values($categoryids);
    }

    /**
     * Include descendants for a list of question category ids.
     *
     * @param array $categoryids
     * @return array
     */
    protected static function expand_question_category_ids(array $categoryids): array {
        global $DB;

        $expanded = [];
        $queue = array_values(array_filter(array_map('intval', $categoryids)));
        while ($queue) {
            $id = array_shift($queue);
            if (isset($expanded[$id])) {
                continue;
            }
            $expanded[$id] = $id;
            $children = $DB->get_records('question_categories', ['parent' => $id], '', 'id');
            foreach ($children as $child) {
                $queue[] = (int)$child->id;
            }
        }

        return array_values($expanded);
    }

    /**
     * Convert a completed Moodle Quiz attempt into a 0-100 percentage.
     *
     * @param object $quiz
     * @param object $attempt
     * @return float
     */
    protected static function get_quiz_attempt_percent(object $quiz, object $attempt): float {
        $rawgrade = (float)($attempt->sumgrades ?? 0);
        $quizgrade = (float)($quiz->grade ?? 0);
        $sumgrades = (float)($quiz->sumgrades ?? 0);

        if ($quizgrade > 0 && function_exists('quiz_rescale_grade')) {
            $scaledgrade = (float)quiz_rescale_grade($rawgrade, $quiz, false);
            return round(max(0, min(100, ($scaledgrade / $quizgrade) * 100)), 2);
        }

        if ($sumgrades > 0) {
            return round(max(0, min(100, ($rawgrade / $sumgrades) * 100)), 2);
        }

        return 0.0;
    }

    /**
     * Build skill scores for a Quiz-backed exam.
     *
     * Moodle Quiz is the question engine. FLW keeps certificate gates stable by
     * applying the final Quiz percentage to the configured skill profile.
     *
     * @param object $exam
     * @param float $overall
     * @return array
     */
    protected static function build_quiz_skill_scores(object $exam, float $overall): array {
        $profile = json_decode($exam->profilejson ?? '[]', true) ?: [];
        $skills = $profile['skills'] ?? ['listening', 'speaking', 'reading', 'writing'];
        if (!is_array($skills) || !$skills) {
            $skills = ['listening', 'speaking', 'reading', 'writing'];
        }

        $scores = [];
        foreach (array_unique($skills) as $skill) {
            $skill = clean_param((string)$skill, PARAM_ALPHANUMEXT);
            if ($skill !== '') {
                $scores[] = [
                    'skill' => $skill,
                    'score' => $overall,
                ];
            }
        }

        return $scores ?: [
            ['skill' => 'reading', 'score' => $overall],
        ];
    }

    /**
     * Build knowledge-point gate scores for a Quiz-backed exam.
     *
     * @param object $exam
     * @param float $overall
     * @return array
     */
    protected static function build_quiz_kp_results(object $exam, float $overall): array {
        $criticalkp = self::normalise_critical_kp_codes($exam);

        $results = [];
        foreach ($criticalkp as $kpcode) {
            $kpcode = clean_param((string)$kpcode, PARAM_ALPHANUMEXT);
            if ($kpcode === '') {
                continue;
            }
            $results[] = [
                'kpcode' => $kpcode,
                'score' => $overall,
                'passed' => $overall >= 60,
                'critical' => true,
            ];
        }

        return $results;
    }

    /**
     * Return scalar critical KP codes from stored JSON.
     *
     * Older seed/admin data may store KP gates as strings, rows, or keyed maps.
     *
     * @param object $exam
     * @return array
     */
    protected static function normalise_critical_kp_codes(object $exam): array {
        $raw = json_decode($exam->criticalkpjson ?? '[]', true);
        if (!is_array($raw)) {
            return [];
        }

        $codes = [];
        self::collect_critical_kp_codes($raw, $codes, true);
        return array_values($codes);
    }

    /**
     * Collect KP code strings from mixed legacy shapes.
     *
     * @param array $values
     * @param array $codes
     * @param bool $allowlist
     */
    protected static function collect_critical_kp_codes(array $values, array &$codes, bool $allowlist): void {
        foreach (['kpcode', 'code', 'id', 'value'] as $field) {
            if (isset($values[$field]) && (is_string($values[$field]) || is_int($values[$field]))) {
                self::add_critical_kp_code($values[$field], $codes);
                return;
            }
        }

        $islist = array_keys($values) === range(0, count($values) - 1);
        if (!$islist) {
            foreach ($values as $key => $value) {
                if (is_string($key) && $value === true && !self::is_reserved_critical_kp_key($key)) {
                    self::add_critical_kp_code($key, $codes);
                }
            }
            return;
        }

        if (!$allowlist) {
            return;
        }

        foreach ($values as $key => $value) {
            if (is_string($key) && !ctype_digit($key) && $value === true) {
                self::add_critical_kp_code($key, $codes);
                continue;
            }

            if ((is_int($key) || ctype_digit((string)$key)) && (is_string($value) || is_int($value))) {
                self::add_critical_kp_code($value, $codes);
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            self::collect_critical_kp_codes($value, $codes, false);
        }
    }

    /**
     * Check whether a JSON key is known exam metadata, not a KP code.
     *
     * @param string $key
     * @return bool
     */
    protected static function is_reserved_critical_kp_key(string $key): bool {
        return isset([
            'language' => true,
            'level' => true,
            'skills' => true,
            'source' => true,
            'score' => true,
            'passed' => true,
            'critical' => true,
            'details' => true,
            'missing' => true,
        ][$key]);
    }

    /**
     * Add a cleaned KP code to a set.
     *
     * @param string|int $value
     * @param array $codes
     */
    protected static function add_critical_kp_code($value, array &$codes): void {
        $code = clean_param((string)$value, PARAM_ALPHANUMEXT);
        if ($code !== '') {
            $codes[$code] = $code;
        }
    }

    /**
     * Format a display name without depending on a prepared $PAGE context.
     *
     * @param string $name
     * @return string
     */
    protected static function format_display_name(string $name): string {
        return format_string($name, true, ['context' => context_system::instance()]);
    }

    /**
     * Normalise skill score input.
     *
     * @param array $skillrows
     * @return array
     */
    protected static function normalise_skill_scores(array $skillrows): array {
        $skills = [];
        foreach ($skillrows as $row) {
            $skill = self::clean_key($row['skill'] ?? '', 'skill');
            if ($skill === '') {
                continue;
            }
            $score = max(0, min(100, (float)($row['score'] ?? 0)));
            $skills[$skill] = $score;
        }
        if (!$skills) {
            throw new invalid_parameter_exception(get_string('skillscorerequired', 'local_flwexam'));
        }
        return $skills;
    }

    /**
     * Normalise KP result input.
     *
     * @param array $kprows
     * @param object $exam
     * @return array
     */
    protected static function normalise_kp_results(array $kprows, object $exam): array {
        $criticalkp = array_flip(self::normalise_critical_kp_codes($exam));
        $kpresults = [];
        foreach ($kprows as $row) {
            $kpcode = self::clean_key($row['kpcode'] ?? '', 'KP code');
            if ($kpcode === '') {
                continue;
            }
            $kpresults[$kpcode] = [
                'score' => max(0, min(100, (float)($row['score'] ?? 0))),
                'passed' => !empty($row['passed']),
                'critical' => !empty($row['critical']) || isset($criticalkp[$kpcode]),
                'details' => [],
            ];
        }
        foreach ($criticalkp as $kpcode => $unused) {
            if (!isset($kpresults[$kpcode])) {
                $kpresults[$kpcode] = [
                    'score' => 0,
                    'passed' => false,
                    'critical' => true,
                    'details' => ['missing' => true],
                ];
            }
        }
        return $kpresults;
    }

    /**
     * Clean a key-like value.
     *
     * @param string $value
     * @param string $label
     * @return string
     */
    protected static function clean_key(string $value, string $label): string {
        $clean = clean_param($value, PARAM_ALPHANUMEXT);
        if ($clean !== $value) {
            throw new invalid_parameter_exception(get_string('invalidfieldvalue', 'local_flwexam', $label));
        }
        return $clean;
    }

    /**
     * Clean a status value.
     *
     * @param string $value
     * @param string $label
     * @return string
     */
    protected static function clean_status(string $value, string $label): string {
        $clean = clean_param($value, PARAM_ALPHANUMEXT);
        if ($clean === '' || $clean !== $value) {
            throw new invalid_parameter_exception(get_string('invalidfieldvalue', 'local_flwexam', $label));
        }
        return $clean;
    }

    /**
     * Return a safe display name for verification.
     *
     * @param object $user
     * @param bool $full
     * @return string
     */
    protected static function safe_display_name(object $user, bool $full): string {
        if ($full) {
            return fullname($user);
        }
        $firstname = trim($user->firstname ?? '');
        $lastname = trim($user->lastname ?? '');
        $initial = $lastname !== '' ? core_text::substr($lastname, 0, 1) . '.' : '';
        return trim($firstname . ' ' . $initial);
    }
}
