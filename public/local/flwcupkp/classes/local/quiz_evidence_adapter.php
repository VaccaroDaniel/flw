<?php
// Quiz evidence adapter for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts mapped Moodle quiz submissions into C-UP-KP evidence.
 */
class quiz_evidence_adapter {
    /**
     * Process a quiz attempt_graded event.
     *
     * @param \mod_quiz\event\attempt_graded $event
     * @return array
     */
    public static function process_attempt_graded(\mod_quiz\event\attempt_graded $event): array {
        return self::process_attempt((int)$event->objectid, (int)$event->courseid, (int)$event->contextinstanceid);
    }

    /**
     * Replay evidence conversion for an existing Moodle quiz attempt.
     *
     * @param int $attemptid
     * @param int $courseid
     * @param int $cmid
     * @return array
     */
    public static function replay_attempt(int $attemptid, int $courseid = 0, int $cmid = 0): array {
        return self::process_attempt($attemptid, $courseid, $cmid);
    }

    /**
     * Convert a Moodle quiz attempt into C-UP-KP evidence.
     *
     * @param int $attemptid
     * @param int $courseid
     * @param int $cmid
     * @return array
     */
    private static function process_attempt(int $attemptid, int $courseid = 0, int $cmid = 0): array {
        global $DB, $USER;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', IGNORE_MISSING);
        if (!$attempt || (int)$attempt->preview === 1) {
            return ['status' => 'ignored', 'reason' => 'missing_or_preview_attempt'];
        }
        if ($attempt->sumgrades === null) {
            return ['status' => 'ignored', 'reason' => 'attempt_not_graded_yet', 'attemptid' => $attemptid];
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        if ($courseid <= 0) {
            $courseid = (int)$quiz->course;
        }
        if ($cmid <= 0) {
            $module = $DB->get_record('modules', ['name' => 'quiz'], '*', MUST_EXIST);
            $cm = $DB->get_record('course_modules', [
                'course' => (int)$quiz->course,
                'module' => (int)$module->id,
                'instance' => (int)$quiz->id,
            ], '*', MUST_EXIST);
            $cmid = (int)$cm->id;
        }

        $object = $DB->get_record('flwcupkp_object', ['cmid' => $cmid], '*', IGNORE_MISSING);
        if (!$object) {
            return ['status' => 'ignored', 'reason' => 'unmapped_cmid', 'cmid' => $cmid];
        }
        try {
            evidence_guard::assert_object_scope($object, $courseid);
            evidence_guard::assert_user_enrolled_for_course((int)$attempt->userid, $courseid);
        } catch (\invalid_parameter_exception $e) {
            return ['status' => 'ignored', 'reason' => 'evidence_scope_rejected', 'message' => $e->getMessage()];
        }

        $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => $object->id]);
        if (!$maps) {
            return ['status' => 'ignored', 'reason' => 'object_has_no_targets', 'objectid' => (int)$object->id];
        }

        $normalized = self::normalized_attempt_score($attempt, $quiz);
        $evidenceids = [];
        $rejectedmaps = [];

        foreach ($maps as $map) {
            try {
                evidence_guard::assert_object_map($object, $map);
            } catch (\invalid_parameter_exception $e) {
                $rejectedmaps[] = ['mapid' => (int)$map->id, 'reason' => $e->getMessage()];
                continue;
            }

            $sourceattempt = 'quiz_attempt:' . $attemptid . ':target:' . $map->targettype . ':' . $map->targetid;
            if ($DB->record_exists('flwcupkp_evidence', [
                'objectid' => $object->id,
                'sourceattempt' => $sourceattempt,
                'targettype' => $map->targettype,
                'targetid' => $map->targetid,
            ])) {
                continue;
            }

            $result = mastery_engine::record_evidence((object)[
                'userid' => (int)$attempt->userid,
                'courseid' => $courseid,
                'unitcode' => $object->unitcode,
                'objectid' => (int)$object->id,
                'sourceattempt' => $sourceattempt,
                'evidencetype' => 'quiz_attempt_submitted',
                'targettype' => $map->targettype,
                'targetid' => (int)$map->targetid,
                'rawscore' => $attempt->sumgrades === null ? 0 : (float)$attempt->sumgrades,
                'normalizedscore' => $normalized,
                'rubricjson' => json_encode([
                    'quizid' => (int)$quiz->id,
                    'attemptid' => $attemptid,
                    'cmid' => $cmid,
                    'question_ids' => self::question_ids_for_quiz((int)$quiz->id),
                ]),
                'assessortype' => 'moodle_quiz',
                'confidence' => 0.80,
                'evidencestrength' => $map->evidencestrength ?: 'recognition',
                'provenance' => 'mod_quiz_attempt_submitted',
                'sourceref' => 'quiz_attempt:' . $attemptid,
                'timecreated' => (int)$attempt->timefinish ?: time(),
                'usermodified' => $USER->id ?? 0,
            ]);
            $evidenceids[] = $result['evidenceid'];
        }

        repository::audit('quiz_evidence_ingested', 'quiz_attempt', $attemptid, [
            'cmid' => $cmid,
            'objectid' => (int)$object->id,
            'evidenceids' => $evidenceids,
            'rejectedmaps' => $rejectedmaps,
            'normalizedscore' => $normalized,
        ]);

        return [
            'status' => 'processed',
            'attemptid' => $attemptid,
            'cmid' => $cmid,
            'objectid' => (int)$object->id,
            'evidenceids' => $evidenceids,
            'rejectedmaps' => $rejectedmaps,
            'normalizedscore' => $normalized,
        ];
    }

    /**
     * Normalize a quiz attempt score.
     *
     * @param \stdClass $attempt
     * @param \stdClass $quiz
     * @return float
     */
    private static function normalized_attempt_score(\stdClass $attempt, \stdClass $quiz): float {
        $sumgrades = $attempt->sumgrades === null ? 0.0 : (float)$attempt->sumgrades;
        $max = (float)($quiz->sumgrades ?: 0);
        if ($max <= 0) {
            return $sumgrades > 0 ? 1.0 : 0.0;
        }
        return round(max(0.0, min(1.0, $sumgrades / $max)), 5);
    }

    /**
     * Return question IDs used by a quiz.
     *
     * @param int $quizid
     * @return array
     */
    private static function question_ids_for_quiz(int $quizid): array {
        global $DB;

        $sql = "SELECT q.id
                  FROM {quiz_slots} qs
                  JOIN {question_references} qr ON qr.itemid = qs.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qs.quizid = :quizid
                   AND qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'
              ORDER BY qs.slot";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['quizid' => $quizid]));
    }
}
