<?php
// Recommendation engine for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Explainable recommendation generation.
 */
class recommendation_engine {
    /**
     * Generate recommendations from current learner states.
     *
     * @param int $userid
     * @param int|null $courseid
     * @param int $limit
     * @return array
     */
    public static function generate(int $userid, ?int $courseid = null, int $limit = 5): array {
        global $DB;

        $now = time();
        $states = $DB->get_records('flwcupkp_state', ['userid' => $userid], 'targettype ASC, masteryscore ASC');
        $recommendations = [];

        foreach ($states as $state) {
            if (count($recommendations) >= $limit) {
                break;
            }

            $reason = self::reason_for_state($state, $now);
            if ($reason === null) {
                continue;
            }

            $objectid = self::find_candidate_object($state->targettype, (int)$state->targetid, $userid, $courseid);
            $record = (object)[
                'userid' => $userid,
                'objectid' => $objectid,
                'targettype' => $state->targettype,
                'targetid' => $state->targetid,
                'reason' => $reason,
                'prereqinfo' => null,
                'masterygap' => max(0, 0.85 - (float)$state->masteryscore),
                'expectedbenefit' => 'Improve ' . $state->targettype . ' readiness using mapped practice or performance evidence.',
                'status' => 'recommended',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('flwcupkp_recommend', $record);
            $recommendations[] = $record;
        }

        if (empty($recommendations)) {
            $record = (object)[
                'userid' => $userid,
                'objectid' => null,
                'targettype' => null,
                'targetid' => null,
                'reason' => 'No mapped activity is currently available. Continue the next scheduled lesson and collect more evidence.',
                'prereqinfo' => null,
                'masterygap' => null,
                'expectedbenefit' => 'Safe fallback while the curriculum map is expanded.',
                'status' => 'recommended',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('flwcupkp_recommend', $record);
            $recommendations[] = $record;
        }

        repository::audit('recommendations_generated', 'user', $userid, ['count' => count($recommendations)]);
        return $recommendations;
    }

    /**
     * Explain why a state needs work.
     *
     * @param \stdClass $state
     * @param int $now
     * @return string|null
     */
    private static function reason_for_state(\stdClass $state, int $now): ?string {
        if ($state->targettype === 'kp' && !empty($state->nextreview) && (int)$state->nextreview <= $now) {
            return 'Review due for this Learning Point.';
        }
        if ((float)$state->confidence < 0.45 && (int)$state->evidencecount > 0) {
            return 'Low-confidence evidence; collect another mapped performance.';
        }
        if ((float)$state->masteryscore < 0.70) {
            return 'Mastery gap detected for this target.';
        }
        if ($state->targettype === 'up' && !in_array($state->masterystate, ['demonstrated', 'stable', 'transfer_ready'], true)) {
            return 'Communication Goal needs direct performance evidence.';
        }
        if ($state->targettype === 'competency' && !in_array($state->masterystate, ['achieved', 'sustained'], true)) {
            return 'Can-do Goal needs integrated performance evidence.';
        }
        return null;
    }

    /**
     * Find a mapped learning object, avoiding recently recommended duplicates.
     *
     * @param string $targettype
     * @param int $targetid
     * @param int $userid
     * @param int|null $courseid
     * @return int|null
     */
    private static function find_candidate_object(string $targettype, int $targetid, int $userid, ?int $courseid): ?int {
        global $DB;

        $params = ['targettype' => $targettype, 'targetid' => $targetid, 'userid' => $userid];
        $coursesql = '';
        if ($courseid !== null) {
            $coursesql = ' AND o.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "SELECT om.objectid
                  FROM {flwcupkp_object_map} om
                  JOIN {flwcupkp_object} o ON o.id = om.objectid
             LEFT JOIN {flwcupkp_recommend} r
                    ON r.objectid = om.objectid AND r.userid = :userid AND r.status = 'recommended'
                 WHERE om.targettype = :targettype
                   AND om.targetid = :targetid
                   {$coursesql}
                   AND r.id IS NULL
              ORDER BY o.role DESC, o.id ASC";

        $id = $DB->get_field_sql($sql, $params, IGNORE_MULTIPLE);
        if ($id !== false) {
            return (int)$id;
        }

        return $DB->get_field('flwcupkp_object_map', 'objectid', ['targettype' => $targettype, 'targetid' => $targetid], IGNORE_MULTIPLE) ?: null;
    }
}
