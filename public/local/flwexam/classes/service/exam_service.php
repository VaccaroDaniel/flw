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

    /**
     * Return a named status in a readable form.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        return ucfirst(str_replace('_', ' ', $status));
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

        $sql = "SELECT r.*, e.name AS examname, e.code AS examcode, c.certificatecode,
                       t.verifycode
                  FROM {local_flwexam_results} r
                  JOIN {local_flwexam_exams} e ON e.id = r.examid
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
            'examname' => format_string($exam->name),
            'examcode' => $exam->code,
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

        $sql = "SELECT e.*, COUNT(q.id) AS questioncount
                  FROM {local_flwexam_exams} e
             LEFT JOIN {local_flwexam_questions} q ON q.examid = e.id AND q.visible = 1
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY e.id, e.code, e.name, e.language, e.learningcoursecategory, e.cefrlevel,
                       e.requiredthreshold, e.requiredskillfloor, e.moderationrequired,
                       e.criticalkpjson, e.profilejson, e.visible, e.timecreated, e.timemodified
              ORDER BY e.language ASC, e.learningcoursecategory ASC, e.cefrlevel ASC, e.name ASC";
        $records = $DB->get_records_sql($sql, $params);
        $exams = [];
        foreach ($records as $record) {
            $exams[] = [
                'id' => (int)$record->id,
                'code' => $record->code,
                'name' => format_string($record->name),
                'language' => $record->language,
                'learning_course_category' => $record->learningcoursecategory,
                'cefr_level' => $record->cefrlevel,
                'required_threshold' => (float)$record->requiredthreshold,
                'required_skill_floor' => (float)$record->requiredskillfloor,
                'question_count' => (int)$record->questioncount,
            ];
        }
        return $exams;
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
            'en' => 'English',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
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
     * Get FLW track selector options for a language.
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
                'adventure_world' => 'English Adventure World',
                'real_world' => 'English Real World',
            ];
        }

        if ($language === '') {
            return [];
        }

        return [
            $language . '_world' => $label . ' World',
        ];
    }

    /**
     * Human label for language code.
     *
     * @param string $language
     * @return string
     */
    public static function language_label(string $language): string {
        $labels = [
            'en' => 'English',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
        ];
        return $labels[$language] ?? strtoupper($language);
    }

    /**
     * Human label for FLW track key.
     *
     * @param string $track
     * @return string
     */
    public static function track_label(string $track): string {
        $labels = [
            'adventure_world' => 'English Adventure World',
            'real_world' => 'English Real World',
        ];
        if (isset($labels[$track])) {
            return $labels[$track];
        }
        if (preg_match('/^([a-z0-9_-]+)_world$/', $track, $matches)) {
            return self::language_label($matches[1]) . ' World';
        }
        return ucwords(str_replace('_', ' ', $track));
    }

    /**
     * Export visible questions without correct answers.
     *
     * @param int $examid
     * @return array
     */
    public static function get_attempt_questions(int $examid): array {
        global $DB;

        $questions = $DB->get_records('local_flwexam_questions', [
            'examid' => $examid,
            'visible' => 1,
        ], 'sortorder ASC, id ASC');
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
     * @return array
     */
    public static function submit_learner_attempt(int $examid, int $userid, array $answers): array {
        global $DB;

        $context = context_system::instance();
        require_capability('local/flwexam:viewown', $context);

        $exam = $DB->get_record('local_flwexam_exams', [
            'id' => $examid,
            'visible' => 1,
        ], '*', MUST_EXIST);
        $questions = $DB->get_records('local_flwexam_questions', [
            'examid' => $examid,
            'visible' => 1,
        ], 'sortorder ASC, id ASC');
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

        $criticalkp = array_flip(json_decode($exam->criticalkpjson ?? '[]', true) ?: []);
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
                'question_count' => count($questions),
                'answer_log' => $answerlog,
            ],
        ], $userid, false);

        self::audit('learner_attempt_submitted', $userid, $userid, (int)$result['id'], (int)$result['certificate_id'], 'success', [
            'examid' => (int)$exam->id,
            'overall_score' => $overall,
        ]);

        return $result;
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
     * @return array
     */
    public static function record_result(array $data, int $actorid, bool $requiresubmitcapability = true): array {
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
        $now = time();

        $decision = self::decide_certificate($exam, (float)$data['overall_score'], $skills, $kpresults, $integrity, $moderation);

        $transaction = $DB->start_delegated_transaction();
        try {
            $attemptnumber = (int)$DB->count_records('local_flwexam_attempts', [
                'userid' => $userid,
                'examid' => (int)$exam->id,
            ]) + 1;
            $attempt = (object)[
                'userid' => $userid,
                'examid' => (int)$exam->id,
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
        $criticalkp = json_decode($exam->criticalkpjson ?? '[]', true) ?: [];

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
            throw new invalid_parameter_exception('Missing verification code.');
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
        return [
            'id' => (int)$record->id,
            'examid' => (int)$record->examid,
            'examname' => format_string($record->examname),
            'examcode' => $record->examcode,
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
            throw new invalid_parameter_exception('At least one skill score is required.');
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
        $criticalkp = array_flip(json_decode($exam->criticalkpjson ?? '[]', true) ?: []);
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
            throw new invalid_parameter_exception('Invalid ' . $label . '.');
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
            throw new invalid_parameter_exception('Invalid ' . $label . '.');
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
