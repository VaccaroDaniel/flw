<?php
// DTO normalizers for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts Moodle/FLW source rows into neutral history DTOs.
 */
class normalizer {
    /**
     * Normalize a Moodle event to a source event DTO.
     *
     * @param \core\event\base $event Moodle event.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function moodle_event_to_source_event(\core\event\base $event, array $extra = []): array {
        return array_merge(source_identity::from_moodle_event($event), $extra);
    }

    /**
     * Normalize a quiz attempt row to an attempt DTO.
     *
     * @param \stdClass $attempt Moodle quiz_attempts-like row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function quiz_attempt_to_attempt(\stdClass $attempt, array $extra = []): array {
        $sourceversion = (string)($attempt->timemodified ?? $attempt->timefinish ?? time());
        $rawscore = isset($attempt->sumgrades) ? (float)$attempt->sumgrades : null;
        $maxscore = isset($extra['maxscore']) ? (float)$extra['maxscore'] : null;
        $scaledscore = isset($extra['scaledscore']) ? (float)$extra['scaledscore'] : null;
        if ($scaledscore === null && $rawscore !== null && $maxscore !== null && $maxscore > 0) {
            $scaledscore = round(max(0.0, min(1.0, $rawscore / $maxscore)), 5);
        }
        $timestart = isset($attempt->timestart) ? (int)$attempt->timestart : null;
        $timefinish = isset($attempt->timefinish) ? (int)$attempt->timefinish : null;
        $duration = ($timestart !== null && $timefinish !== null && $timefinish > 0 && $timefinish >= $timestart)
            ? $timefinish - $timestart
            : null;
        $record = [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'quiz_attempt',
            'sourceid' => (string)$attempt->id,
            'sourceversion' => $sourceversion,
            'eventtype' => 'quiz_attempt_state',
            'userid' => (int)$attempt->userid,
            'sourceattemptid' => (string)$attempt->id,
            'attemptno' => isset($attempt->attempt) ? (int)$attempt->attempt : null,
            'attemptstate' => (string)($attempt->state ?? 'unknown'),
            'rawscore' => $rawscore,
            'maxscore' => $maxscore,
            'scaledscore' => $scaledscore,
            'timestart' => $timestart,
            'timefinish' => $timefinish,
            'summaryjson' => [
                'quiz' => $attempt->quiz ?? null,
                'uniqueid' => $attempt->uniqueid ?? null,
                'layout' => $attempt->layout ?? null,
                'durationseconds' => $duration,
                'result' => $extra['result'] ?? null,
                'pass' => $extra['pass'] ?? null,
                'gradepass' => $extra['gradepass'] ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }

    /**
     * Normalize a Moodle question attempt row.
     *
     * @param \stdClass $questionattempt Question attempt row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function question_attempt_to_record(\stdClass $questionattempt, array $extra = []): array {
        $steptime = (int)($extra['steptime'] ?? time());
        $record = [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'quiz',
            'sourcetype' => 'question_attempt',
            'sourceid' => (string)$questionattempt->id,
            'sourceversion' => (string)$steptime,
            'eventtype' => 'question_attempt_state',
            'userid' => (int)($extra['userid'] ?? 0),
            'courseid' => $extra['courseid'] ?? null,
            'cmid' => $extra['cmid'] ?? null,
            'questionusageid' => isset($questionattempt->questionusageid) ? (int)$questionattempt->questionusageid : null,
            'questionattemptid' => (int)$questionattempt->id,
            'slot' => isset($questionattempt->slot) ? (int)$questionattempt->slot : null,
            'questionid' => isset($questionattempt->questionid) ? (string)$questionattempt->questionid : null,
            'maxmark' => isset($questionattempt->maxmark) ? (float)$questionattempt->maxmark : null,
            'resultstate' => $extra['resultstate'] ?? null,
            'steptime' => $steptime,
            'summaryjson' => [
                'behaviour' => $questionattempt->behaviour ?? null,
                'variant' => $questionattempt->variant ?? null,
                'minfraction' => $questionattempt->minfraction ?? null,
                'maxfraction' => $questionattempt->maxfraction ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }

    /**
     * Normalize a Moodle grade row to a grade version DTO.
     *
     * @param \stdClass $grade Moodle grade_grades-like row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function grade_to_grade_version(\stdClass $grade, array $extra = []): array {
        $gradetime = (int)($grade->timemodified ?? time());
        $record = [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'gradebook',
            'sourcetype' => 'grade_grade',
            'sourceid' => (string)$grade->id,
            'sourceversion' => (string)$gradetime,
            'eventtype' => 'grade_recorded',
            'userid' => (int)$grade->userid,
            'gradegradeid' => (int)$grade->id,
            'gradeitemid' => isset($grade->itemid) ? (int)$grade->itemid : null,
            'rawgrade' => isset($grade->rawgrade) ? (float)$grade->rawgrade : null,
            'finalgrade' => isset($grade->finalgrade) ? (float)$grade->finalgrade : null,
            'graderid' => isset($grade->usermodified) ? (int)$grade->usermodified : null,
            'action' => 'recorded',
            'gradetime' => $gradetime,
            'summaryjson' => [
                'aggregationstatus' => $grade->aggregationstatus ?? null,
                'aggregationweight' => $grade->aggregationweight ?? null,
                'overridden' => $grade->overridden ?? null,
                'excluded' => $grade->excluded ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }

    /**
     * Normalize a completion row to a completion DTO.
     *
     * @param \stdClass $completion Completion row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function completion_to_record(\stdClass $completion, array $extra = []): array {
        $completiontime = (int)($completion->timemodified ?? $completion->timecompleted ?? time());
        $record = [
            'sourcesystem' => 'moodle',
            'sourcefamily' => 'completion',
            'sourcetype' => 'completion',
            'sourceid' => (string)($completion->id ?? (($completion->coursemoduleid ?? 0) . ':' . ($completion->userid ?? 0))),
            'sourceversion' => (string)$completiontime,
            'eventtype' => 'completion_changed',
            'userid' => (int)$completion->userid,
            'courseid' => $extra['courseid'] ?? null,
            'cmid' => isset($completion->coursemoduleid) ? (int)$completion->coursemoduleid : null,
            'completionstate' => isset($completion->completionstate) ? (int)$completion->completionstate : null,
            'viewed' => isset($completion->viewed) ? (int)$completion->viewed : null,
            'overrideby' => isset($completion->overrideby) ? (int)$completion->overrideby : null,
            'completiontime' => $completiontime,
            'detailsjson' => [
                'reaggregate' => $completion->reaggregate ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }

    /**
     * Normalize a FLW placement row to an attempt DTO.
     *
     * @param \stdClass $placement Placement source row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function placement_to_attempt(\stdClass $placement, array $extra = []): array {
        $modified = (int)($placement->timemodified ?? $placement->timecreated ?? time());
        $record = [
            'sourcesystem' => 'flwplacement',
            'sourcefamily' => 'placement',
            'sourcetype' => 'placement_attempt',
            'sourceid' => (string)$placement->id,
            'sourceversion' => (string)$modified,
            'eventtype' => 'placement_recorded',
            'userid' => (int)$placement->userid,
            'courseid' => isset($placement->courseid) ? (int)$placement->courseid : null,
            'attemptstate' => (string)($placement->status ?? 'recorded'),
            'rawscore' => isset($placement->score) ? (float)$placement->score : null,
            'timefinish' => $modified,
            'summaryjson' => [
                'profileid' => $placement->profileid ?? null,
                'level' => $placement->level ?? null,
                'attemptjson' => $placement->attemptjson ?? null,
            ],
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }

    /**
     * Normalize a FLW placement row to a placement-history DTO.
     *
     * @param \stdClass $placement Placement source row.
     * @param array $extra Extra fields.
     * @return array
     */
    public static function placement_to_history(\stdClass $placement, array $extra = []): array {
        $modified = (int)($placement->timemodified ?? $placement->timecreated ?? time());
        $record = [
            'sourcesystem' => 'flwplacement',
            'sourcefamily' => 'placement',
            'sourcetype' => 'placement',
            'sourceid' => (string)$placement->id,
            'sourceversion' => (string)$modified,
            'eventtype' => 'placement_recorded',
            'userid' => (int)$placement->userid,
            'courseid' => isset($placement->courseid) ? (int)$placement->courseid : null,
            'currentlevel' => $placement->level ?? $placement->currentlevel ?? null,
            'previouslevel' => $placement->previouslevel ?? null,
            'placementstatus' => (string)($placement->status ?? 'recorded'),
            'score' => isset($placement->score) ? (float)$placement->score : null,
            'confidence' => isset($placement->confidence) ? (float)$placement->confidence : null,
            'profilejson' => [
                'profileid' => $placement->profileid ?? null,
                'attemptjson' => $placement->attemptjson ?? null,
            ],
            'placementtime' => $modified,
            'normpolicyversion' => history_policy::NORMALIZATION_POLICY_VERSION,
        ];
        $record = source_identity::normalise_record($record);
        return array_merge($record, $extra);
    }
}
