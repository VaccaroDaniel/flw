<?php
// Specialized Moodle evidence adapters for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts mapped assignment, H5P, SCORM, and trusted STT signals into C-UP-KP evidence.
 */
final class specialized_evidence_adapter {
    /**
     * Assignment submission signal.
     *
     * @param \mod_assign\event\assessable_submitted $event
     * @return array
     */
    public static function process_assign_submission(\mod_assign\event\assessable_submitted $event): array {
        $userid = (int)($event->relateduserid ?: $event->userid);
        if ($userid <= 0) {
            return ['status' => 'ignored', 'reason' => 'missing_user'];
        }

        return self::record_mapped_cmid_evidence(
            (int)$event->contextinstanceid,
            (int)$event->courseid,
            $userid,
            'assign_submission:' . (int)$event->objectid,
            'assignment_submission',
            1.0,
            1.0,
            0.55,
            'guided_performance',
            'moodle_assignment',
            [
                'submissionid' => (int)$event->objectid,
                'submission_editable' => !empty($event->other['submission_editable']),
            ],
            'mod_assign_assessable_submitted',
            'assign_submission:' . (int)$event->objectid,
            (int)$event->timecreated
        );
    }

    /**
     * Assignment grade signal.
     *
     * @param \mod_assign\event\submission_graded $event
     * @return array
     */
    public static function process_assign_grade(\mod_assign\event\submission_graded $event): array {
        global $DB;

        $grade = $DB->get_record('assign_grades', ['id' => (int)$event->objectid], '*', IGNORE_MISSING);
        if (!$grade || (float)$grade->grade < 0) {
            return ['status' => 'ignored', 'reason' => 'missing_or_ungraded_assignment_grade'];
        }
        $cm = self::course_module((int)$event->contextinstanceid);
        if (!$cm) {
            return ['status' => 'ignored', 'reason' => 'missing_course_module'];
        }
        $assignment = $DB->get_record('assign', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
        $maxgrade = max(0.00001, (float)($assignment->grade ?? 100));
        $score = self::clamp((float)$grade->grade / $maxgrade);

        return self::record_mapped_cmid_evidence(
            (int)$event->contextinstanceid,
            (int)$event->courseid,
            (int)$event->relateduserid,
            'assign_grade:' . (int)$event->objectid,
            'assignment_grade',
            (float)$grade->grade,
            $score,
            0.80,
            'independent_performance',
            'moodle_assignment_grade',
            [
                'gradeid' => (int)$event->objectid,
                'rawgrade' => (float)$grade->grade,
                'maxgrade' => $maxgrade,
                'attemptnumber' => (int)($grade->attemptnumber ?? 0),
            ],
            'mod_assign_submission_graded',
            'assign_grade:' . (int)$event->objectid,
            (int)$event->timecreated
        );
    }

    /**
     * H5P xAPI statement signal.
     *
     * @param \mod_h5pactivity\event\statement_received $event
     * @return array
     */
    public static function process_h5p_statement(\mod_h5pactivity\event\statement_received $event): array {
        $parsed = self::h5p_statement_score($event->other ?? []);
        if ($parsed['score'] === null) {
            return ['status' => 'ignored', 'reason' => 'h5p_statement_without_score'];
        }

        return self::record_mapped_cmid_evidence(
            (int)$event->contextinstanceid,
            (int)$event->courseid,
            (int)$event->userid,
            'h5p_statement:' . (int)$event->contextinstanceid . ':' . sha1(json_encode($event->other)),
            'h5p_xapi_statement',
            $parsed['rawscore'],
            $parsed['score'],
            $parsed['confidence'],
            'controlled_production',
            'moodle_h5p',
            $parsed['rubric'],
            'mod_h5pactivity_statement_received',
            'h5pactivity:' . (int)$event->objectid,
            (int)$event->timecreated
        );
    }

    /**
     * SCORM status signal.
     *
     * @param \mod_scorm\event\status_submitted $event
     * @return array
     */
    public static function process_scorm_status(\mod_scorm\event\status_submitted $event): array {
        $value = strtolower((string)($event->other['cmivalue'] ?? ''));
        $score = self::scorm_status_score($value);
        if ($score === null) {
            return ['status' => 'ignored', 'reason' => 'scorm_status_not_evidence', 'value' => $value];
        }

        return self::record_mapped_cmid_evidence(
            (int)$event->contextinstanceid,
            (int)$event->courseid,
            (int)$event->userid,
            'scorm_status:' . (int)($event->other['attemptid'] ?? 0) . ':' . $value,
            'scorm_status',
            $score,
            $score,
            0.60,
            'recognition',
            'moodle_scorm',
            [
                'attemptid' => (int)($event->other['attemptid'] ?? 0),
                'cmielement' => (string)($event->other['cmielement'] ?? ''),
                'cmivalue' => $value,
            ],
            'mod_scorm_status_submitted',
            'scorm_attempt:' . (int)($event->other['attemptid'] ?? 0),
            (int)$event->timecreated
        );
    }

    /**
     * SCORM raw score signal.
     *
     * @param \mod_scorm\event\scoreraw_submitted $event
     * @return array
     */
    public static function process_scorm_score(\mod_scorm\event\scoreraw_submitted $event): array {
        $raw = (float)($event->other['cmivalue'] ?? 0);
        $score = self::clamp($raw > 1 ? $raw / 100 : $raw);

        return self::record_mapped_cmid_evidence(
            (int)$event->contextinstanceid,
            (int)$event->courseid,
            (int)$event->userid,
            'scorm_score:' . (int)($event->other['attemptid'] ?? 0) . ':' . (string)($event->other['cmielement'] ?? ''),
            'scorm_score',
            $raw,
            $score,
            0.65,
            'controlled_production',
            'moodle_scorm',
            [
                'attemptid' => (int)($event->other['attemptid'] ?? 0),
                'cmielement' => (string)($event->other['cmielement'] ?? ''),
                'rawscore' => $raw,
            ],
            'mod_scorm_scoreraw_submitted',
            'scorm_attempt:' . (int)($event->other['attemptid'] ?? 0),
            (int)$event->timecreated
        );
    }

    /**
     * Trusted server-side STT evidence input. This never stores raw audio.
     *
     * @param \stdClass $payload
     * @return array
     */
    public static function record_stt_result(\stdClass $payload): array {
        $similarity = self::clamp((float)($payload->similarity ?? 0));
        $completion = self::clamp((float)($payload->taskcompletion ?? $similarity));
        $intelligibility = self::clamp((float)($payload->intelligibility ?? $similarity));
        $score = round(($similarity * 0.50) + ($completion * 0.30) + ($intelligibility * 0.20), 5);

        return self::record_mapped_object_evidence(
            (int)$payload->objectid,
            (int)($payload->courseid ?? 0),
            (int)$payload->userid,
            'stt:' . sha1(((string)($payload->sourceref ?? '')) . ':' . (string)($payload->recognizedresponse ?? '')),
            'speaking_stt',
            $score,
            $score,
            self::clamp((float)($payload->confidence ?? 0.65)),
            'guided_performance',
            'trusted_stt',
            [
                'expected_response' => (string)($payload->expectedresponse ?? ''),
                'recognized_response' => (string)($payload->recognizedresponse ?? ''),
                'similarity' => $similarity,
                'task_completion' => $completion,
                'intelligibility' => $intelligibility,
            ],
            'server_side_stt',
            (string)($payload->sourceref ?? 'stt_result'),
            (int)($payload->timecreated ?? time())
        );
    }

    /**
     * Record mapped evidence from a course module.
     *
     * @param int $cmid
     * @param int $courseid
     * @param int $userid
     * @param string $sourceattemptprefix
     * @param string $evidencetype
     * @param float $rawscore
     * @param float $normalizedscore
     * @param float $confidence
     * @param string $fallbackstrength
     * @param string $assessortype
     * @param array $rubric
     * @param string $provenance
     * @param string $sourceref
     * @param int $timecreated
     * @return array
     */
    private static function record_mapped_cmid_evidence(int $cmid, int $courseid, int $userid,
            string $sourceattemptprefix, string $evidencetype, float $rawscore, float $normalizedscore,
            float $confidence, string $fallbackstrength, string $assessortype, array $rubric, string $provenance,
            string $sourceref, int $timecreated): array {
        global $DB;

        $object = $DB->get_record('flwcupkp_object', ['cmid' => $cmid], '*', IGNORE_MISSING);
        if (!$object) {
            return ['status' => 'ignored', 'reason' => 'unmapped_cmid', 'cmid' => $cmid];
        }

        return self::record_mapped_object_evidence((int)$object->id, $courseid, $userid, $sourceattemptprefix,
            $evidencetype, $rawscore, $normalizedscore, $confidence, $fallbackstrength, $assessortype, $rubric,
            $provenance, $sourceref, $timecreated);
    }

    /**
     * Record mapped evidence from an object ID.
     *
     * @param int $objectid
     * @param int $courseid
     * @param int $userid
     * @param string $sourceattemptprefix
     * @param string $evidencetype
     * @param float $rawscore
     * @param float $normalizedscore
     * @param float $confidence
     * @param string $fallbackstrength
     * @param string $assessortype
     * @param array $rubric
     * @param string $provenance
     * @param string $sourceref
     * @param int $timecreated
     * @return array
     */
    private static function record_mapped_object_evidence(int $objectid, int $courseid, int $userid,
            string $sourceattemptprefix, string $evidencetype, float $rawscore, float $normalizedscore,
            float $confidence, string $fallbackstrength, string $assessortype, array $rubric, string $provenance,
            string $sourceref, int $timecreated): array {
        global $DB, $USER;

        if ($objectid <= 0 || $userid <= 0) {
            return ['status' => 'ignored', 'reason' => 'missing_object_or_user'];
        }
        $object = $DB->get_record('flwcupkp_object', ['id' => $objectid], '*', IGNORE_MISSING);
        if (!$object) {
            return ['status' => 'ignored', 'reason' => 'missing_object', 'objectid' => $objectid];
        }
        $effectivecourseid = $courseid > 0 ? $courseid : (int)($object->courseid ?? 0);
        try {
            evidence_guard::assert_object_scope($object, $effectivecourseid);
            evidence_guard::assert_user_enrolled_for_course($userid, $effectivecourseid);
        } catch (\invalid_parameter_exception $e) {
            return ['status' => 'ignored', 'reason' => 'evidence_scope_rejected', 'message' => $e->getMessage()];
        }

        $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => $objectid]);
        if (!$maps) {
            return ['status' => 'ignored', 'reason' => 'object_has_no_targets', 'objectid' => $objectid];
        }

        $evidenceids = [];
        $rejectedmaps = [];
        foreach ($maps as $map) {
            try {
                evidence_guard::assert_object_map($object, $map);
            } catch (\invalid_parameter_exception $e) {
                $rejectedmaps[] = ['mapid' => (int)$map->id, 'reason' => $e->getMessage()];
                continue;
            }

            $sourceattempt = $sourceattemptprefix . ':target:' . $map->targettype . ':' . (int)$map->targetid;
            if ($DB->record_exists('flwcupkp_evidence', [
                'objectid' => $objectid,
                'sourceattempt' => $sourceattempt,
                'targettype' => $map->targettype,
                'targetid' => (int)$map->targetid,
            ])) {
                continue;
            }

            $result = mastery_engine::record_evidence((object)[
                'userid' => $userid,
                'courseid' => $effectivecourseid,
                'unitcode' => (string)($object->unitcode ?? ''),
                'objectid' => $objectid,
                'sourceattempt' => $sourceattempt,
                'evidencetype' => $evidencetype,
                'targettype' => (string)$map->targettype,
                'targetid' => (int)$map->targetid,
                'rawscore' => $rawscore,
                'normalizedscore' => self::clamp($normalizedscore),
                'rubricjson' => json_encode($rubric, JSON_UNESCAPED_SLASHES),
                'assessortype' => $assessortype,
                'confidence' => self::clamp($confidence),
                'evidencestrength' => $map->evidencestrength ?: ($object->evidencestrength ?: $fallbackstrength),
                'provenance' => $provenance,
                'sourceref' => $sourceref,
                'timecreated' => $timecreated ?: time(),
                'usermodified' => $USER->id ?? 0,
            ]);
            $evidenceids[] = $result['evidenceid'];
        }

        return [
            'status' => 'processed',
            'objectid' => $objectid,
            'userid' => $userid,
            'evidenceids' => $evidenceids,
            'rejectedmaps' => $rejectedmaps,
        ];
    }

    /**
     * Resolve course module.
     *
     * @param int $cmid
     * @return \stdClass|null
     */
    private static function course_module(int $cmid): ?\stdClass {
        global $DB;

        return $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Parse H5P xAPI scoring.
     *
     * @param array $statement
     * @return array
     */
    private static function h5p_statement_score(array $statement): array {
        $result = $statement['result'] ?? [];
        $scoreinfo = is_array($result) ? ($result['score'] ?? []) : [];
        $score = null;
        $raw = null;
        if (is_array($scoreinfo) && isset($scoreinfo['scaled'])) {
            $score = self::clamp((float)$scoreinfo['scaled']);
            $raw = $score;
        } else if (is_array($scoreinfo) && isset($scoreinfo['raw'], $scoreinfo['max']) && (float)$scoreinfo['max'] > 0) {
            $raw = (float)$scoreinfo['raw'];
            $score = self::clamp($raw / (float)$scoreinfo['max']);
        } else if (is_array($result) && isset($result['success'])) {
            $score = !empty($result['success']) ? 1.0 : 0.0;
            $raw = $score;
        } else if (is_array($result) && isset($result['completion'])) {
            $score = !empty($result['completion']) ? 1.0 : null;
            $raw = $score;
        }

        return [
            'score' => $score,
            'rawscore' => $raw ?? 0,
            'confidence' => is_array($scoreinfo) && isset($scoreinfo['scaled']) ? 0.75 : 0.60,
            'rubric' => [
                'verb' => $statement['verb']['display']['en-US'] ?? ($statement['verb']['id'] ?? ''),
                'score' => $scoreinfo,
                'success' => $result['success'] ?? null,
                'completion' => $result['completion'] ?? null,
            ],
        ];
    }

    /**
     * Resolve SCORM status to a conservative evidence score.
     *
     * @param string $status
     * @return float|null
     */
    private static function scorm_status_score(string $status): ?float {
        if (in_array($status, ['passed', 'completed'], true)) {
            return 1.0;
        }
        if ($status === 'failed') {
            return 0.35;
        }
        return null;
    }

    /**
     * Clamp score to 0..1.
     *
     * @param float $score
     * @return float
     */
    private static function clamp(float $score): float {
        return max(0.0, min(1.0, $score));
    }
}
