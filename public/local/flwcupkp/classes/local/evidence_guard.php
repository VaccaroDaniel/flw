<?php
// Production safety checks for C-UP-KP evidence writes.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared validation for evidence-producing paths.
 */
final class evidence_guard {
    /** @var array Valid target types and their backing tables. */
    private const TARGET_TABLES = [
        'competency' => 'flwcupkp_comp',
        'up' => 'flwcupkp_up',
        'kp' => 'flwcupkp_kp',
    ];

    /**
     * Return the Moodle table for a C-UP-KP target type.
     *
     * @param string $targettype
     * @return string
     */
    public static function target_table(string $targettype): string {
        if (!isset(self::TARGET_TABLES[$targettype])) {
            throw new \invalid_parameter_exception('Unsupported C-UP-KP target type.');
        }
        return self::TARGET_TABLES[$targettype];
    }

    /**
     * Confirm that a target record exists.
     *
     * @param string $targettype
     * @param int $targetid
     */
    public static function assert_target_exists(string $targettype, int $targetid): void {
        global $DB;

        if ($targetid <= 0) {
            throw new \invalid_parameter_exception('C-UP-KP target id is required.');
        }
        if (!$DB->record_exists(self::target_table($targettype), ['id' => $targetid])) {
            throw new \invalid_parameter_exception('C-UP-KP target does not exist.');
        }
    }

    /**
     * Confirm that a mapped object belongs to the requested scope.
     *
     * @param \stdClass $object
     * @param int $courseid
     * @param string $unitcode
     */
    public static function assert_object_scope(\stdClass $object, int $courseid = 0, string $unitcode = ''): void {
        if ($courseid > 0 && !empty($object->courseid) && (int)$object->courseid !== $courseid) {
            throw new \invalid_parameter_exception('Learning object does not belong to the selected course.');
        }
        if ($unitcode !== '' && (string)($object->unitcode ?? '') !== $unitcode) {
            throw new \invalid_parameter_exception('Learning object does not belong to the selected unit.');
        }
    }

    /**
     * Confirm that an object-map row points at the supplied object and at a valid target.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     */
    public static function assert_object_map(\stdClass $object, \stdClass $map): void {
        if ((int)$map->objectid !== (int)$object->id) {
            throw new \invalid_parameter_exception('C-UP-KP mapping does not belong to the learning object.');
        }
        self::assert_target_exists((string)$map->targettype, (int)$map->targetid);
    }

    /**
     * Confirm that the learner can receive evidence inside a Moodle course.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function assert_user_enrolled_for_course(int $userid, int $courseid): void {
        global $DB;

        $user = $userid > 0 ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING) : false;
        if (!$user) {
            throw new \invalid_parameter_exception('Learner does not exist.');
        }
        if ($courseid <= 0) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !is_enrolled($context, $user, '', true)) {
            throw new \invalid_parameter_exception('Learner is not enrolled in the selected course.');
        }
    }

    /**
     * Confirm that object-scoped evidence is recordable for a learner.
     *
     * @param \stdClass $object
     * @param \stdClass $map
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     */
    public static function assert_object_map_can_record(\stdClass $object, \stdClass $map, int $userid,
            int $courseid = 0, string $unitcode = ''): void {
        self::assert_object_scope($object, $courseid, $unitcode);
        self::assert_object_map($object, $map);

        $resolvedcourseid = $courseid ?: (int)($object->courseid ?? 0);
        self::assert_user_enrolled_for_course($userid, $resolvedcourseid);
    }

    /**
     * Normalize and validate an evidence payload before it reaches storage.
     *
     * @param \stdClass $evidence
     * @return \stdClass
     */
    public static function normalize_evidence(\stdClass $evidence): \stdClass {
        global $DB;

        $evidence->userid = (int)($evidence->userid ?? 0);
        $evidence->courseid = (int)($evidence->courseid ?? 0);
        $evidence->objectid = (int)($evidence->objectid ?? 0);
        $evidence->targettype = (string)($evidence->targettype ?? '');
        $evidence->targetid = (int)($evidence->targetid ?? 0);
        $evidence->rawscore = (float)($evidence->rawscore ?? 0);
        $evidence->normalizedscore = self::clamp01((float)($evidence->normalizedscore ?? 0));
        $evidence->confidence = self::clamp01((float)($evidence->confidence ?? 0));

        self::assert_target_exists($evidence->targettype, $evidence->targetid);

        if ($evidence->objectid > 0) {
            $object = $DB->get_record('flwcupkp_object', ['id' => $evidence->objectid], '*', IGNORE_MISSING);
            if (!$object) {
                throw new \invalid_parameter_exception('Learning object does not exist.');
            }
            if ($evidence->courseid <= 0 && !empty($object->courseid)) {
                $evidence->courseid = (int)$object->courseid;
            }
            if (empty($evidence->unitcode) && !empty($object->unitcode)) {
                $evidence->unitcode = (string)$object->unitcode;
            }
            self::assert_object_scope($object, $evidence->courseid, (string)($evidence->unitcode ?? ''));
            if (!$DB->record_exists('flwcupkp_object_map', [
                'objectid' => $evidence->objectid,
                'targettype' => $evidence->targettype,
                'targetid' => $evidence->targetid,
            ])) {
                throw new \invalid_parameter_exception('Learning object is not mapped to the target.');
            }
        }
        self::assert_user_enrolled_for_course($evidence->userid, $evidence->courseid);

        return $evidence;
    }

    /**
     * Clamp a score into Moodle's normalized mastery range.
     *
     * @param float $value
     * @return float
     */
    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }
}
