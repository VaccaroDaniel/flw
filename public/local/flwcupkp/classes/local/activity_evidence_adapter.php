<?php
// Generic Moodle activity evidence adapter.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts mapped Moodle activity completion events into C-UP-KP evidence.
 */
final class activity_evidence_adapter {
    /**
     * Process course module completion for any mapped activity.
     *
     * @param \core\event\course_module_completion_updated $event
     * @return array
     */
    public static function process_completion(\core\event\course_module_completion_updated $event): array {
        global $DB, $USER;

        $cmid = (int)$event->contextinstanceid;
        $userid = (int)$event->relateduserid;
        if ($cmid <= 0 || $userid <= 0) {
            return ['status' => 'ignored', 'reason' => 'missing_cmid_or_user'];
        }

        $modname = $DB->get_field_sql(
            "SELECT m.name
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.id = :cmid",
            ['cmid' => $cmid]
        );
        if ($modname === 'quiz') {
            return ['status' => 'ignored', 'reason' => 'quiz_uses_attempt_adapter', 'cmid' => $cmid];
        }

        $completion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cmid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);
        if (!$completion || !in_array((int)$completion->completionstate, [1, 2], true)) {
            return ['status' => 'ignored', 'reason' => 'activity_not_completed', 'cmid' => $cmid, 'userid' => $userid];
        }

        $object = $DB->get_record('flwcupkp_object', ['cmid' => $cmid], '*', IGNORE_MISSING);
        if (!$object) {
            return ['status' => 'ignored', 'reason' => 'unmapped_cmid', 'cmid' => $cmid];
        }
        try {
            evidence_guard::assert_object_scope($object, (int)$event->courseid);
            evidence_guard::assert_user_enrolled_for_course($userid, (int)$event->courseid);
        } catch (\invalid_parameter_exception $e) {
            return ['status' => 'ignored', 'reason' => 'evidence_scope_rejected', 'message' => $e->getMessage()];
        }

        $maps = $DB->get_records('flwcupkp_object_map', ['objectid' => $object->id]);
        if (!$maps) {
            return ['status' => 'ignored', 'reason' => 'object_has_no_targets', 'objectid' => (int)$object->id];
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

            $sourceattempt = 'completion:' . $cmid . ':user:' . $userid . ':target:' . $map->targettype . ':' . $map->targetid;
            if ($DB->record_exists('flwcupkp_evidence', [
                'objectid' => $object->id,
                'sourceattempt' => $sourceattempt,
                'targettype' => $map->targettype,
                'targetid' => $map->targetid,
            ])) {
                continue;
            }

            $result = mastery_engine::record_evidence((object)[
                'userid' => $userid,
                'courseid' => (int)$event->courseid,
                'unitcode' => $object->unitcode,
                'objectid' => (int)$object->id,
                'sourceattempt' => $sourceattempt,
                'evidencetype' => 'activity_completion',
                'targettype' => $map->targettype,
                'targetid' => (int)$map->targetid,
                'rawscore' => 1.0,
                'normalizedscore' => 1.0,
                'rubricjson' => json_encode([
                    'cmid' => $cmid,
                    'completionstate' => (int)$completion->completionstate,
                    'timemodified' => (int)$completion->timemodified,
                ]),
                'assessortype' => 'moodle_completion',
                'confidence' => 0.55,
                'evidencestrength' => $map->evidencestrength ?: ($object->evidencestrength ?: 'exposure'),
                'provenance' => 'core_course_module_completion_updated',
                'sourceref' => 'cm_completion:' . $cmid,
                'timecreated' => (int)$completion->timemodified ?: time(),
                'usermodified' => $USER->id ?? 0,
            ]);
            $evidenceids[] = $result['evidenceid'];
        }

        return [
            'status' => 'processed',
            'cmid' => $cmid,
            'userid' => $userid,
            'objectid' => (int)$object->id,
            'evidenceids' => $evidenceids,
            'rejectedmaps' => $rejectedmaps,
        ];
    }
}
