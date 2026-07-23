<?php
// Generic teacher performance evidence helpers.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles teacher-scored performance evidence for any mapped unit.
 */
final class performance_service {
    /** @var array Performance object types supported by the generic scoring page. */
    private const OBJECTTYPES = ['speaking_task', 'writing_task', 'project'];

    /**
     * Whether a course/unit has teacher-scoreable performance tasks.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return bool
     */
    public static function has_tasks(int $courseid, string $unitcode): bool {
        return count(self::tasks($courseid, $unitcode)) > 0;
    }

    /**
     * Get performance assessment tasks for a course/unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function tasks(int $courseid, string $unitcode): array {
        global $DB;

        [$typesql, $typeparams] = $DB->get_in_or_equal(self::OBJECTTYPES, SQL_PARAMS_NAMED, 'otype');
        $params = [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
        ] + $typeparams;

        return $DB->get_records_sql(
            "SELECT m.id AS mapid,
                    o.id AS objectid,
                    o.externalid AS objectexternalid,
                    o.title AS objecttitle,
                    o.lesson,
                    o.objecttype,
                    o.cmid,
                    o.courseid,
                    m.targettype,
                    m.targetid,
                    m.role,
                    m.evidencestrength,
                    mod.name AS modname,
                    COALESCE(c.externalid, u.externalid, kp.externalid) AS targetexternalid,
                    COALESCE(c.title, u.title, kp.title) AS targettitle
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
          LEFT JOIN {flwcupkp_comp} c ON c.id = m.targetid AND m.targettype = 'competency'
          LEFT JOIN {flwcupkp_up} u ON u.id = m.targetid AND m.targettype = 'up'
          LEFT JOIN {flwcupkp_kp} kp ON kp.id = m.targetid AND m.targettype = 'kp'
          LEFT JOIN {course_modules} cm ON cm.id = o.cmid
          LEFT JOIN {modules} mod ON mod.id = cm.module
              WHERE o.unitcode = :unitcode
                AND (:courseid = 0 OR o.courseid = :courseid1 OR o.courseid IS NULL)
                AND o.objecttype {$typesql}
                AND m.targettype IN ('kp', 'up', 'competency')
           ORDER BY o.lesson ASC, o.externalid ASC, m.id ASC",
            $params + ['courseid1' => $courseid]
        );
    }

    /**
     * Build learner options for a course/unit.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    public static function learners(int $courseid, string $unitcode): array {
        return unit_report::learners($courseid, $unitcode);
    }

    /**
     * Record teacher-scored performance evidence.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $userid
     * @param int $mapid
     * @param array $scores
     * @param string $note
     * @return array
     */
    public static function record(int $courseid, string $unitcode, int $userid, int $mapid, array $scores,
            string $note = ''): array {
        global $DB;

        if ($courseid <= 0 || $unitcode === '' || $userid <= 0 || $mapid <= 0) {
            throw new \invalid_parameter_exception('Course, unit, learner, and performance task are required.');
        }

        $task = self::task($courseid, $unitcode, $mapid);
        $map = $DB->get_record('flwcupkp_object_map', ['id' => $mapid], '*', MUST_EXIST);
        $object = $DB->get_record('flwcupkp_object', ['id' => (int)$task->objectid], '*', MUST_EXIST);
        evidence_guard::assert_object_map_can_record($object, $map, $userid, $courseid, $unitcode);

        $rubric = self::rubric_for_task($task);
        $normalizedscores = [];
        $rubricout = [];
        foreach ($rubric as $criterion) {
            $key = $criterion['key'];
            $score = self::clamp01((float)($scores[$key] ?? 0));
            $normalizedscores[] = $score;
            $rubricout[$key] = [
                'label' => $criterion['label'],
                'score' => $score,
            ];
        }

        if (!$normalizedscores) {
            throw new \invalid_parameter_exception('At least one rubric score is required.');
        }

        $normalizedscore = round(array_sum($normalizedscores) / count($normalizedscores), 5);
        $note = trim($note);
        $result = mastery_engine::record_evidence((object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'objectid' => (int)$task->objectid,
            'sourceattempt' => 'performance:' . $unitcode . ':' . time() . ':map:' . $mapid . ':user:' . $userid,
            'evidencetype' => 'teacher_performance',
            'targettype' => (string)$task->targettype,
            'targetid' => (int)$task->targetid,
            'rawscore' => $normalizedscore,
            'normalizedscore' => $normalizedscore,
            'rubricjson' => json_encode([
                'rubric' => $rubricout,
                'note' => $note,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'mapid' => $mapid,
                'object_externalid' => $task->objectexternalid,
                'target_externalid' => $task->targetexternalid,
            ]),
            'assessortype' => 'teacher',
            'confidence' => 0.90,
            'evidencestrength' => (string)($task->evidencestrength ?: self::default_strength($task)),
            'provenance' => 'generic_teacher_performance_page',
            'sourceref' => $task->objectexternalid,
        ]);

        repository::audit('performance_evidence_recorded', (string)$task->targettype, (int)$task->targetid, [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'userid' => $userid,
            'mapid' => $mapid,
            'objectid' => (int)$task->objectid,
            'objectexternalid' => $task->objectexternalid,
            'targetexternalid' => $task->targetexternalid,
            'evidenceid' => $result['evidenceid'],
            'normalizedscore' => $normalizedscore,
        ]);

        return $result + [
            'task' => $task,
            'normalizedscore' => $normalizedscore,
        ];
    }

    /**
     * Get one task record.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $mapid
     * @return \stdClass
     */
    public static function task(int $courseid, string $unitcode, int $mapid): \stdClass {
        $tasks = self::tasks($courseid, $unitcode);
        if (!isset($tasks[$mapid])) {
            throw new \invalid_parameter_exception('Selected task is not a performance task in this course/unit.');
        }
        return $tasks[$mapid];
    }

    /**
     * Latest evidence for a learner/task pair.
     *
     * @param int $userid
     * @param \stdClass $task
     * @return \stdClass|null
     */
    public static function latest_evidence(int $userid, \stdClass $task): ?\stdClass {
        global $DB;

        $records = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'objectid' => (int)$task->objectid,
            'targettype' => (string)$task->targettype,
            'targetid' => (int)$task->targetid,
        ], 'timecreated DESC, id DESC', '*', 0, 1);

        $record = reset($records);
        return $record ?: null;
    }

    /**
     * Current state for a learner/task target.
     *
     * @param int $userid
     * @param \stdClass $task
     * @return \stdClass|null
     */
    public static function state(int $userid, \stdClass $task): ?\stdClass {
        global $DB;

        return $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => (string)$task->targettype,
            'targetid' => (int)$task->targetid,
        ], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Rubric criteria for a performance task.
     *
     * @param \stdClass $task
     * @return array
     */
    public static function rubric_for_task(\stdClass $task): array {
        if ((string)$task->objecttype === 'speaking_task') {
            return [
                ['key' => 'problem_clarity', 'label' => get_string('rubric_problem_clarity', 'local_flwcupkp')],
                ['key' => 'option_comparison', 'label' => get_string('rubric_option_comparison', 'local_flwcupkp')],
                ['key' => 'interaction_management', 'label' => get_string('rubric_interaction_management', 'local_flwcupkp')],
                ['key' => 'agreement_summary', 'label' => get_string('rubric_agreement_summary', 'local_flwcupkp')],
            ];
        }
        if ((string)$task->objecttype === 'writing_task') {
            return [
                ['key' => 'problem_clarity', 'label' => get_string('rubric_problem_clarity', 'local_flwcupkp')],
                ['key' => 'cause_decision', 'label' => get_string('rubric_cause_decision', 'local_flwcupkp')],
                ['key' => 'organization', 'label' => get_string('rubric_organization', 'local_flwcupkp')],
                ['key' => 'actionability', 'label' => get_string('rubric_actionability', 'local_flwcupkp')],
            ];
        }

        return [
            ['key' => 'problem_clarity', 'label' => get_string('rubric_problem_clarity', 'local_flwcupkp')],
            ['key' => 'cause_explanation', 'label' => get_string('rubric_cause_explanation', 'local_flwcupkp')],
            ['key' => 'option_comparison', 'label' => get_string('rubric_option_comparison', 'local_flwcupkp')],
            ['key' => 'next_step_note', 'label' => get_string('rubric_next_step_note', 'local_flwcupkp')],
        ];
    }

    /**
     * Default evidence strength when a task mapping has no configured strength.
     *
     * @param \stdClass $task
     * @return string
     */
    private static function default_strength(\stdClass $task): string {
        return (string)$task->targettype === 'competency' ? 'independent_performance' : 'guided_performance';
    }

    /**
     * Clamp a score to 0..1.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }
}
