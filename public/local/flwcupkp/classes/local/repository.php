<?php
// Repository helpers for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin repository layer around Moodle DML.
 */
class repository {
    /** @var array Maps package entity keys to Moodle table names. */
    private const TABLES = [
        'frameworks' => 'flwcupkp_framework',
        'competencies' => 'flwcupkp_comp',
        'use_points' => 'flwcupkp_up',
        'knowledge_points' => 'flwcupkp_kp',
        'learning_objects' => 'flwcupkp_object',
    ];

    /**
     * Return table name for an import entity key.
     *
     * @param string $entitykey
     * @return string
     */
    public static function table_for_entity(string $entitykey): string {
        if (!isset(self::TABLES[$entitykey])) {
            throw new \coding_exception('Unknown C-UP-KP entity key: ' . $entitykey);
        }
        return self::TABLES[$entitykey];
    }

    /**
     * Insert or update by externalid.
     *
     * @param string $table
     * @param \stdClass $record
     * @return int
     */
    public static function upsert_by_externalid(string $table, \stdClass $record): int {
        global $DB, $USER;

        if (empty($record->externalid)) {
            throw new \invalid_parameter_exception('externalid is required');
        }

        $now = time();
        $record->timemodified = $now;
        $record->usermodified = $USER->id ?? 0;

        if ($existing = $DB->get_record($table, ['externalid' => $record->externalid])) {
            $record->id = $existing->id;
            if (isset($existing->timecreated) && empty($record->timecreated)) {
                $record->timecreated = $existing->timecreated;
            }
            $DB->update_record($table, $record);
            return (int)$existing->id;
        }

        if (empty($record->timecreated)) {
            $record->timecreated = $now;
        }
        return (int)$DB->insert_record($table, $record);
    }

    /**
     * Fetch ID by externalid.
     *
     * @param string $table
     * @param string $externalid
     * @return int|null
     */
    public static function get_id_by_externalid(string $table, string $externalid): ?int {
        global $DB;

        $id = $DB->get_field($table, 'id', ['externalid' => $externalid], IGNORE_MISSING);
        return $id === false ? null : (int)$id;
    }

    /**
     * Upsert a mapping table by unique fields.
     *
     * @param string $table
     * @param array $keys
     * @param \stdClass $record
     * @return int
     */
    public static function upsert_mapping(string $table, array $keys, \stdClass $record): int {
        global $DB;

        if ($existing = $DB->get_record($table, $keys)) {
            $record->id = $existing->id;
            $DB->update_record($table, $record);
            return (int)$existing->id;
        }

        return (int)$DB->insert_record($table, $record);
    }

    /**
     * Store an audit entry.
     *
     * @param string $action
     * @param string|null $targettype
     * @param int|null $targetid
     * @param array $details
     * @return int
     */
    public static function audit(string $action, ?string $targettype = null, ?int $targetid = null, array $details = []): int {
        global $DB, $USER;

        $record = (object)[
            'action' => $action,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'detailsjson' => json_encode($details),
            'userid' => $USER->id ?? 0,
            'timecreated' => time(),
        ];

        return (int)$DB->insert_record('flwcupkp_audit', $record);
    }

    /**
     * Store learner state.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param array $state
     * @return int
     */
    public static function upsert_state(int $userid, string $targettype, int $targetid, array $state): int {
        global $DB;

        $record = (object)[
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'masteryscore' => $state['masteryscore'] ?? 0,
            'masterystate' => $state['masterystate'] ?? 'not_started',
            'confidence' => $state['confidence'] ?? 0,
            'evidencecount' => $state['evidencecount'] ?? 0,
            'lastevidence' => $state['lastevidence'] ?? null,
            'lastsuccess' => $state['lastsuccess'] ?? null,
            'nextreview' => $state['nextreview'] ?? null,
            'manualoverride' => $state['manualoverride'] ?? 0,
            'overridereason' => $state['overridereason'] ?? null,
            'ruleversion' => $state['ruleversion'] ?? 'default-v1',
            'timemodified' => time(),
        ];

        $keys = ['userid' => $userid, 'targettype' => $targettype, 'targetid' => $targetid];
        if ($existing = $DB->get_record('flwcupkp_state', $keys)) {
            if (!empty($existing->manualoverride)) {
                return (int)$existing->id;
            }
            $record->id = $existing->id;
            $DB->update_record('flwcupkp_state', $record);
            return (int)$existing->id;
        }

        return (int)$DB->insert_record('flwcupkp_state', $record);
    }
}
