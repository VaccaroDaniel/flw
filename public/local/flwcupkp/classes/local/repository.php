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

        $keys = ['userid' => $userid, 'targettype' => $targettype, 'targetid' => $targetid];
        $existing = $DB->get_record('flwcupkp_state', $keys);
        $calculatedtime = (int)($state['calculatedtime'] ?? time());

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
            'policyversion' => $state['policyversion'] ?? null,
            'trend' => $state['trend'] ?? self::state_trend($existing ?: null, $state),
            'evidencehash' => $state['evidencehash'] ?? null,
            'evidenceidsjson' => self::state_evidence_ids_json($state),
            'calculatedtime' => $calculatedtime > 0 ? $calculatedtime : time(),
            'retentionstate' => self::state_value($state, $existing ?: null, 'retentionstate'),
            'retentionconfidence' => self::state_value($state, $existing ?: null, 'retentionconfidence', 0),
            'retentionnextreview' => self::state_value($state, $existing ?: null, 'retentionnextreview'),
            'retentionlastretrieval' => self::state_value($state, $existing ?: null, 'retentionlastretrieval'),
            'retentionretrievalcount' => self::state_value($state, $existing ?: null, 'retentionretrievalcount', 0),
            'retentionpolicyversion' => self::state_value($state, $existing ?: null, 'retentionpolicyversion'),
            'retentionevidencehash' => self::state_value($state, $existing ?: null, 'retentionevidencehash'),
            'retentionevidenceidsjson' => self::retention_evidence_ids_json($state, $existing ?: null),
            'retentioncalculatedtime' => self::state_value($state, $existing ?: null, 'retentioncalculatedtime'),
            'timemodified' => time(),
        ];

        if ($existing) {
            if (!empty($existing->manualoverride)) {
                return (int)$existing->id;
            }
            $record->id = $existing->id;
            $DB->update_record('flwcupkp_state', $record);
            return (int)$existing->id;
        }

        return (int)$DB->insert_record('flwcupkp_state', $record);
    }

    /**
     * Update only retention/retrieval/review snapshot fields on an existing state row.
     *
     * @param int $stateid
     * @param array $retention
     * @return bool
     */
    public static function update_retention_state(int $stateid, array $retention): bool {
        global $DB;

        if ($stateid <= 0) {
            return false;
        }

        $existing = $DB->get_record('flwcupkp_state', ['id' => $stateid], '*', IGNORE_MISSING);
        if (!$existing) {
            return false;
        }

        $record = (object)[
            'id' => $stateid,
            'retentionstate' => $retention['retentionstate'] ?? null,
            'retentionconfidence' => $retention['retentionconfidence'] ?? 0,
            'retentionnextreview' => $retention['retentionnextreview'] ?? null,
            'retentionlastretrieval' => $retention['retentionlastretrieval'] ?? null,
            'retentionretrievalcount' => $retention['retentionretrievalcount'] ?? 0,
            'retentionpolicyversion' => $retention['retentionpolicyversion'] ?? null,
            'retentionevidencehash' => $retention['retentionevidencehash'] ?? null,
            'retentionevidenceidsjson' => self::retention_evidence_ids_json($retention, $existing),
            'retentioncalculatedtime' => $retention['retentioncalculatedtime'] ?? time(),
            'timemodified' => time(),
        ];

        $DB->update_record('flwcupkp_state', $record);
        return true;
    }

    /**
     * Encode the evidence references attached to a state calculation.
     *
     * @param array $state
     * @return string|null
     */
    private static function state_evidence_ids_json(array $state): ?string {
        if (isset($state['evidenceidsjson']) && is_string($state['evidenceidsjson'])) {
            return $state['evidenceidsjson'];
        }
        if (!empty($state['evidenceids']) && is_array($state['evidenceids'])) {
            return json_encode(array_values(array_map('intval', $state['evidenceids'])), JSON_UNESCAPED_SLASHES);
        }
        return null;
    }

    /**
     * Read a value from a new state payload while preserving existing cache data.
     *
     * @param array $state
     * @param \stdClass|null $existing
     * @param string $field
     * @param mixed $default
     * @return mixed
     */
    private static function state_value(array $state, ?\stdClass $existing, string $field, $default = null) {
        if (array_key_exists($field, $state)) {
            return $state[$field];
        }
        if ($existing && property_exists($existing, $field)) {
            return $existing->$field;
        }
        return $default;
    }

    /**
     * Encode retention evidence references while preserving existing values when absent.
     *
     * @param array $state
     * @param \stdClass|null $existing
     * @return string|null
     */
    private static function retention_evidence_ids_json(array $state, ?\stdClass $existing): ?string {
        if (isset($state['retentionevidenceidsjson']) && is_string($state['retentionevidenceidsjson'])) {
            return $state['retentionevidenceidsjson'];
        }
        if (!empty($state['retentionevidenceids']) && is_array($state['retentionevidenceids'])) {
            return json_encode(array_values(array_map('intval', $state['retentionevidenceids'])), JSON_UNESCAPED_SLASHES);
        }
        if ($existing && property_exists($existing, 'retentionevidenceidsjson')) {
            return $existing->retentionevidenceidsjson;
        }
        return null;
    }

    /**
     * Compare a stored row with a freshly calculated state.
     *
     * @param \stdClass|null $existing
     * @param array $state
     * @return string
     */
    private static function state_trend(?\stdClass $existing, array $state): string {
        if (!$existing) {
            return ((int)($state['evidencecount'] ?? 0) > 0) ? 'new' : 'flat';
        }

        $old = (float)($existing->masteryscore ?? 0);
        $new = (float)($state['masteryscore'] ?? 0);
        if ($new > $old + 0.00001) {
            return 'up';
        }
        if ($new < $old - 0.00001) {
            return 'down';
        }
        return ((string)($existing->masterystate ?? '') === (string)($state['masterystate'] ?? '')) ? 'flat' :
            'state_changed';
    }
}
