<?php
// Evidence calibration reporting for local_flwcupkp.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds read-only evidence calibration reports for production review.
 */
final class calibration_report {
    /** @var array Evidence strengths treated as direct performance. */
    private const PERFORMANCE_STRENGTHS = [
        'guided_performance',
        'independent_performance',
        'transfer_performance',
    ];

    /** @var array States that should be read as achieved/strong outcomes. */
    private const STRONG_STATES = [
        'mastered',
        'independent_use',
        'stable',
        'transfer_ready',
        'demonstrated',
        'achieved',
        'sustained',
    ];

    /**
     * Unit options discovered from imported objects.
     *
     * @return array
     */
    public static function unit_options(): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT unitcode
               FROM {flwcupkp_object}
              WHERE unitcode IS NOT NULL
                AND unitcode <> ''
           ORDER BY unitcode ASC"
        );

        $options = [];
        foreach ($records as $record) {
            $options[(string)$record->unitcode] = (string)$record->unitcode;
        }
        return $options;
    }

    /**
     * Course options discovered from mapped objects and evidence rows.
     *
     * @return array
     */
    public static function course_options(): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.shortname, c.fullname
               FROM {course} c
               JOIN (
                    SELECT courseid FROM {flwcupkp_object} WHERE courseid IS NOT NULL AND courseid > 0
                    UNION
                    SELECT courseid FROM {flwcupkp_evidence} WHERE courseid IS NOT NULL AND courseid > 0
               ) mapped ON mapped.courseid = c.id
           ORDER BY c.shortname ASC"
        );

        $options = [];
        foreach ($records as $record) {
            $options[(int)$record->id] = $record->shortname . ' - ' . $record->fullname;
        }
        return $options;
    }

    /**
     * Build the calibration report.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    public static function report(int $courseid = 0, string $unitcode = '', string $targettype = ''): array {
        $evidence = self::evidence_rows($courseid, $unitcode, $targettype);
        $states = self::state_rows($courseid, $unitcode, $targettype);

        return [
            'summary' => self::summary($evidence, $states),
            'rules' => self::rules(),
            'evidence_by_type' => self::group_count($evidence, 'targettype', 'evidence_count'),
            'evidence_by_strength' => self::group_count($evidence, 'evidencestrength', 'evidence_count'),
            'evidence_by_source' => self::group_count($evidence, 'evidencetype', 'evidence_count'),
            'score_bands' => self::score_bands($evidence),
            'state_outcomes' => self::state_outcomes($states),
            'edge_cases' => self::edge_cases($evidence, $states),
        ];
    }

    /**
     * Build an exportable report payload with stable metadata.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    public static function export_payload(int $courseid = 0, string $unitcode = '', string $targettype = ''): array {
        return [
            'component' => 'local_flwcupkp',
            'format' => 'calibration_report_v2',
            'generatedat' => time(),
            'filters' => self::filters($courseid, $unitcode, $targettype),
            'report' => self::report($courseid, $unitcode, $targettype),
            'state_details' => self::state_details($courseid, $unitcode, $targettype),
        ];
    }

    /**
     * Save the current calibration report as a database snapshot.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @param string $name
     * @param string $note
     * @return int
     */
    public static function save_snapshot(int $courseid, string $unitcode, string $targettype, string $name = '',
            string $note = ''): int {
        global $DB, $USER;

        $payload = self::export_payload($courseid, $unitcode, $targettype);
        $reportjson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $summaryjson = json_encode($payload['report']['summary'], JSON_UNESCAPED_SLASHES);
        $checksum = hash('sha256', $reportjson);
        $now = time();
        $name = trim($name);
        if ($name === '') {
            $name = self::scope_label($courseid, $unitcode, $targettype) . ' ' . date('Ymd-His', $now);
        }

        $record = (object)[
            'name' => substr($name, 0, 120),
            'courseid' => $courseid > 0 ? $courseid : null,
            'unitcode' => $unitcode !== '' ? $unitcode : null,
            'targettype' => $targettype !== '' ? $targettype : null,
            'summaryjson' => $summaryjson,
            'reportjson' => $reportjson,
            'checksum' => $checksum,
            'note' => trim($note),
            'userid' => $USER->id ?? 0,
            'timecreated' => $now,
        ];

        $snapshotid = (int)$DB->insert_record('flwcupkp_calsnapshot', $record);
        repository::audit('calibration_snapshot_saved', 'calibration_snapshot', $snapshotid, [
            'filters' => $payload['filters'],
            'checksum' => $checksum,
        ]);

        return $snapshotid;
    }

    /**
     * Fetch one saved snapshot.
     *
     * @param int $snapshotid
     * @return \stdClass|null
     */
    public static function snapshot(int $snapshotid): ?\stdClass {
        global $DB;

        $snapshot = $DB->get_record('flwcupkp_calsnapshot', ['id' => $snapshotid], '*', IGNORE_MISSING);
        return $snapshot ?: null;
    }

    /**
     * Recent snapshots matching the current optional scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @param int $limit
     * @return array
     */
    public static function snapshots(int $courseid = 0, string $unitcode = '', string $targettype = '',
            int $limit = 10): array {
        global $DB;

        $where = [];
        $params = [];
        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($targettype !== '') {
            $where[] = 'targettype = :targettype';
            $params['targettype'] = $targettype;
        }
        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return array_values($DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_calsnapshot}
              {$wheresql}
           ORDER BY timecreated DESC, id DESC",
            $params,
            0,
            max(1, $limit)
        ));
    }

    /**
     * Decode the stored snapshot report payload.
     *
     * @param \stdClass $snapshot
     * @return array
     */
    public static function snapshot_payload(\stdClass $snapshot): array {
        $payload = json_decode((string)$snapshot->reportjson, true);
        return is_array($payload) ? $payload : [];
    }

    /**
     * Detailed state rows for previewing threshold proposals.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    public static function state_details(int $courseid = 0, string $unitcode = '', string $targettype = ''): array {
        return array_map([self::class, 'state_detail_array'], self::state_rows($courseid, $unitcode, $targettype));
    }

    /**
     * State details stored in a snapshot, with current-scope fallback for older snapshots.
     *
     * @param \stdClass $snapshot
     * @return array
     */
    public static function snapshot_state_details(\stdClass $snapshot): array {
        $payload = self::snapshot_payload($snapshot);
        if (!empty($payload['state_details']) && is_array($payload['state_details'])) {
            return $payload['state_details'];
        }

        $filters = $payload['filters'] ?? [];
        return self::state_details(
            (int)($filters['courseid'] ?? $snapshot->courseid ?? 0),
            (string)($filters['unitcode'] ?? $snapshot->unitcode ?? ''),
            (string)($filters['targettype'] ?? $snapshot->targettype ?? '')
        );
    }

    /**
     * Convert an export payload to a multi-section CSV document.
     *
     * @param array $payload
     * @return string
     */
    public static function csv(array $payload): string {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'section',
            'metric',
            'label',
            'target_type',
            'state',
            'value',
            'count',
            'average_score',
            'average_confidence',
            'average_evidence_count',
            'priority',
            'kind',
            'user_id',
            'target',
            'evidence_id',
            'source',
            'strength',
            'message',
        ]);

        foreach (($payload['filters'] ?? []) as $key => $value) {
            self::csv_row($handle, ['section' => 'filters', 'metric' => $key, 'value' => $value]);
        }
        foreach (($payload['report']['summary'] ?? []) as $key => $value) {
            self::csv_row($handle, ['section' => 'summary', 'metric' => $key, 'value' => $value]);
        }
        foreach (($payload['report']['rules'] ?? []) as $rule) {
            self::csv_row($handle, [
                'section' => 'active_rules',
                'metric' => $rule['ruletype'] ?? '',
                'label' => $rule['name'] ?? '',
                'state' => $rule['calibration_status'] ?? '',
                'value' => $rule['version'] ?? '',
                'message' => $rule['thresholds'] ?? '',
            ]);
        }

        $distributionkeys = [
            'evidence_by_type' => 'evidence_by_type',
            'evidence_by_strength' => 'evidence_by_strength',
            'evidence_by_source' => 'evidence_by_source',
        ];
        foreach ($distributionkeys as $key => $section) {
            foreach (($payload['report'][$key] ?? []) as $row) {
                self::csv_row($handle, [
                    'section' => $section,
                    'label' => $row['label'] ?? '',
                    'count' => $row['evidence_count'] ?? 0,
                    'average_score' => $row['average_score'] ?? 0,
                ]);
            }
        }
        foreach (($payload['report']['score_bands'] ?? []) as $row) {
            self::csv_row($handle, [
                'section' => 'score_bands',
                'label' => $row['band'] ?? '',
                'target_type' => $row['targettype'] ?? '',
                'count' => $row['count'] ?? 0,
            ]);
        }
        foreach (($payload['report']['state_outcomes'] ?? []) as $row) {
            self::csv_row($handle, [
                'section' => 'state_outcomes',
                'target_type' => $row['targettype'] ?? '',
                'state' => $row['state'] ?? '',
                'count' => $row['count'] ?? 0,
                'average_score' => $row['average_score'] ?? 0,
                'average_confidence' => $row['average_confidence'] ?? 0,
                'average_evidence_count' => $row['average_evidence_count'] ?? 0,
            ]);
        }
        foreach (($payload['state_details'] ?? []) as $row) {
            self::csv_row($handle, [
                'section' => 'state_details',
                'target_type' => $row['targettype'] ?? '',
                'state' => $row['masterystate'] ?? '',
                'value' => $row['masteryscore'] ?? 0,
                'count' => $row['evidencecount'] ?? 0,
                'average_confidence' => $row['confidence'] ?? 0,
                'user_id' => $row['userid'] ?? '',
                'target' => ($row['targettype'] ?? '') . ':' . ($row['targetexternalid'] ?? ''),
                'message' => $row['manualoverride'] ? 'manual_override' : '',
            ]);
        }
        foreach (($payload['report']['edge_cases'] ?? []) as $row) {
            self::csv_row($handle, [
                'section' => 'edge_cases',
                'target_type' => $row['targettype'] ?? '',
                'state' => $row['state'] ?? '',
                'value' => $row['score'] ?? 0,
                'count' => $row['evidencecount'] ?? 0,
                'average_confidence' => $row['confidence'] ?? 0,
                'priority' => $row['priority'] ?? '',
                'kind' => $row['kind'] ?? '',
                'user_id' => $row['userid'] ?? '',
                'target' => ($row['targettype'] ?? '') . ':' . ($row['targetexternalid'] ?? ''),
                'evidence_id' => $row['latest_evidenceid'] ?? '',
                'source' => $row['latest_evidencetype'] ?? '',
                'strength' => $row['latest_strength'] ?? '',
                'message' => $row['message'] ?? '',
            ]);
        }

        rewind($handle);
        return (string)stream_get_contents($handle);
    }

    /**
     * Stable filter metadata for exports and snapshots.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    private static function filters(int $courseid, string $unitcode, string $targettype): array {
        return [
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'targettype' => $targettype,
        ];
    }

    /**
     * Human-readable scope label for auto-named snapshots.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return string
     */
    private static function scope_label(int $courseid, string $unitcode, string $targettype): string {
        $parts = ['Calibration'];
        if ($courseid > 0) {
            $parts[] = 'course-' . $courseid;
        }
        if ($unitcode !== '') {
            $parts[] = $unitcode;
        }
        if ($targettype !== '') {
            $parts[] = $targettype;
        }
        return implode('-', $parts);
    }

    /**
     * Add one normalized row to the calibration CSV.
     *
     * @param resource $handle
     * @param array $row
     */
    private static function csv_row($handle, array $row): void {
        $columns = [
            'section',
            'metric',
            'label',
            'target_type',
            'state',
            'value',
            'count',
            'average_score',
            'average_confidence',
            'average_evidence_count',
            'priority',
            'kind',
            'user_id',
            'target',
            'evidence_id',
            'source',
            'strength',
            'message',
        ];

        $values = [];
        foreach ($columns as $column) {
            $values[] = $row[$column] ?? '';
        }
        fputcsv($handle, $values);
    }

    /**
     * Evidence rows in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    private static function evidence_rows(int $courseid, string $unitcode, string $targettype): array {
        global $DB;

        $where = [];
        $params = [];
        if ($courseid > 0) {
            $where[] = 'e.courseid = :ecourseid';
            $params['ecourseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'e.unitcode = :eunitcode';
            $params['eunitcode'] = $unitcode;
        }
        if ($targettype !== '') {
            $where[] = 'e.targettype = :etargettype';
            $params['etargettype'] = $targettype;
        }
        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return array_values($DB->get_records_sql(
            "SELECT e.id,
                    e.userid,
                    e.courseid,
                    e.unitcode,
                    e.objectid,
                    e.evidencetype,
                    e.targettype,
                    e.targetid,
                    e.normalizedscore,
                    e.confidence,
                    e.evidencestrength,
                    e.provenance,
                    e.sourceref,
                    e.timecreated,
                    o.externalid AS objectexternalid,
                    o.title AS objecttitle,
                    o.cmid,
                    COALESCE(c.externalid, u.externalid, kp.externalid) AS targetexternalid,
                    COALESCE(c.title, u.title, kp.title) AS targettitle
               FROM {flwcupkp_evidence} e
          LEFT JOIN {flwcupkp_object} o ON o.id = e.objectid
          LEFT JOIN {flwcupkp_comp} c ON c.id = e.targetid AND e.targettype = 'competency'
          LEFT JOIN {flwcupkp_up} u ON u.id = e.targetid AND e.targettype = 'up'
          LEFT JOIN {flwcupkp_kp} kp ON kp.id = e.targetid AND e.targettype = 'kp'
              {$wheresql}
           ORDER BY e.timecreated DESC, e.id DESC",
            $params
        ));
    }

    /**
     * State rows in scope, joined to mapped objects where available.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param string $targettype
     * @return array
     */
    private static function state_rows(int $courseid, string $unitcode, string $targettype): array {
        global $DB;

        $where = [];
        $params = [];
        if ($targettype !== '') {
            $where[] = 's.targettype = :stargettype';
            $params['stargettype'] = $targettype;
        }
        if ($courseid > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                  FROM {flwcupkp_evidence} se
                 WHERE se.userid = s.userid
                   AND se.targettype = s.targettype
                   AND se.targetid = s.targetid
                   AND se.courseid = :scourseid
            )';
            $params['scourseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                  FROM {flwcupkp_evidence} ue
                 WHERE ue.userid = s.userid
                   AND ue.targettype = s.targettype
                   AND ue.targetid = s.targetid
                   AND ue.unitcode = :sunitcode
            )';
            $params['sunitcode'] = $unitcode;
        }
        $wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return array_values($DB->get_records_sql(
            "SELECT s.id,
                    s.userid,
                    s.targettype,
                    s.targetid,
                    s.masteryscore,
                    s.masterystate,
                    s.confidence,
                    s.evidencecount,
                    s.lastevidence,
                    s.lastsuccess,
                    s.nextreview,
                    s.manualoverride,
                    s.overridereason,
                    s.ruleversion,
                    s.timemodified,
                    COALESCE(c.externalid, u.externalid, kp.externalid) AS targetexternalid,
                    COALESCE(c.title, u.title, kp.title) AS targettitle
               FROM {flwcupkp_state} s
          LEFT JOIN {flwcupkp_comp} c ON c.id = s.targetid AND s.targettype = 'competency'
          LEFT JOIN {flwcupkp_up} u ON u.id = s.targetid AND s.targettype = 'up'
          LEFT JOIN {flwcupkp_kp} kp ON kp.id = s.targetid AND s.targettype = 'kp'
              {$wheresql}
           ORDER BY s.timemodified DESC, s.id DESC",
            $params
        ));
    }

    /**
     * Convert a state row to an export-safe array.
     *
     * @param \stdClass $state
     * @return array
     */
    private static function state_detail_array(\stdClass $state): array {
        return [
            'userid' => (int)$state->userid,
            'targettype' => (string)$state->targettype,
            'targetid' => (int)$state->targetid,
            'targetexternalid' => (string)($state->targetexternalid ?? ''),
            'targettitle' => (string)($state->targettitle ?? ''),
            'masteryscore' => (float)$state->masteryscore,
            'masterystate' => (string)$state->masterystate,
            'confidence' => (float)$state->confidence,
            'evidencecount' => (int)$state->evidencecount,
            'manualoverride' => !empty($state->manualoverride) ? 1 : 0,
            'ruleversion' => (string)($state->ruleversion ?? ''),
        ];
    }

    /**
     * Top-line report metrics.
     *
     * @param array $evidence
     * @param array $states
     * @return array
     */
    private static function summary(array $evidence, array $states): array {
        $learners = [];
        $targets = [];
        $performance = 0;
        foreach ($evidence as $row) {
            $learners[(int)$row->userid] = true;
            $targets[$row->targettype . ':' . $row->targetid] = true;
            if (in_array((string)$row->evidencestrength, self::PERFORMANCE_STRENGTHS, true)) {
                $performance++;
            }
        }

        $manual = 0;
        $lowconfidence = 0;
        $reviewdue = 0;
        foreach ($states as $state) {
            if (!empty($state->manualoverride)) {
                $manual++;
            }
            if ((float)$state->confidence < 0.60) {
                $lowconfidence++;
            }
            if (!empty($state->nextreview) && (int)$state->nextreview < time()) {
                $reviewdue++;
            }
        }

        return [
            'evidence_total' => count($evidence),
            'learner_count' => count($learners),
            'targets_with_evidence' => count($targets),
            'state_total' => count($states),
            'performance_evidence' => $performance,
            'manual_overrides' => $manual,
            'low_confidence_states' => $lowconfidence,
            'review_due_states' => $reviewdue,
        ];
    }

    /**
     * Active rules and calibration status.
     *
     * @return array
     */
    private static function rules(): array {
        global $DB;

        $records = $DB->get_records('flwcupkp_rule', ['status' => 'active'], 'ruletype ASC, name ASC');
        $rules = [];
        foreach ($records as $record) {
            $config = json_decode((string)$record->configjson, true);
            if (!is_array($config)) {
                $config = [];
            }
            $rules[] = [
                'name' => (string)$record->name,
                'ruletype' => (string)$record->ruletype,
                'version' => (string)$record->version,
                'status' => (string)$record->status,
                'calibration_status' => (string)($config['calibration_status'] ?? 'unknown'),
                'thresholds' => self::threshold_summary($config),
            ];
        }
        return $rules;
    }

    /**
     * Summarize numeric/bool rule config.
     *
     * @param array $config
     * @return string
     */
    private static function threshold_summary(array $config): string {
        $parts = [];
        foreach ($config as $key => $value) {
            if ($key === 'calibration_status') {
                continue;
            }
            if (is_bool($value)) {
                $parts[] = $key . '=' . ($value ? 'true' : 'false');
            } else if (is_scalar($value)) {
                $parts[] = $key . '=' . $value;
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Count rows by a field.
     *
     * @param array $rows
     * @param string $field
     * @param string $countkey
     * @return array
     */
    private static function group_count(array $rows, string $field, string $countkey): array {
        $groups = [];
        foreach ($rows as $row) {
            $value = (string)($row->{$field} ?? '');
            if ($value === '') {
                $value = 'unknown';
            }
            if (!isset($groups[$value])) {
                $groups[$value] = ['label' => $value, $countkey => 0, 'average_score' => 0.0, '_sum' => 0.0];
            }
            $groups[$value][$countkey]++;
            $groups[$value]['_sum'] += (float)($row->normalizedscore ?? 0);
        }
        foreach ($groups as &$group) {
            $group['average_score'] = $group[$countkey] > 0 ? round($group['_sum'] / $group[$countkey], 3) : 0.0;
            unset($group['_sum']);
        }
        return array_values($groups);
    }

    /**
     * Evidence score bands by target type.
     *
     * @param array $evidence
     * @return array
     */
    private static function score_bands(array $evidence): array {
        $bands = [
            '0.00-0.34' => [0.0, 0.34999],
            '0.35-0.69' => [0.35, 0.69999],
            '0.70-0.84' => [0.70, 0.84999],
            '0.85-1.00' => [0.85, 1.0],
        ];
        $rows = [];
        foreach (['kp', 'up', 'competency'] as $targettype) {
            foreach ($bands as $label => $range) {
                $rows[$targettype . ':' . $label] = [
                    'targettype' => $targettype,
                    'band' => $label,
                    'count' => 0,
                ];
            }
        }
        foreach ($evidence as $row) {
            $score = (float)$row->normalizedscore;
            foreach ($bands as $label => $range) {
                if ($score >= $range[0] && $score <= $range[1]) {
                    $key = (string)$row->targettype . ':' . $label;
                    if (!isset($rows[$key])) {
                        $rows[$key] = ['targettype' => (string)$row->targettype, 'band' => $label, 'count' => 0];
                    }
                    $rows[$key]['count']++;
                    break;
                }
            }
        }
        return array_values($rows);
    }

    /**
     * State outcomes by target type/state.
     *
     * @param array $states
     * @return array
     */
    private static function state_outcomes(array $states): array {
        $groups = [];
        foreach ($states as $state) {
            $key = $state->targettype . ':' . $state->masterystate;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'targettype' => (string)$state->targettype,
                    'state' => (string)$state->masterystate,
                    'count' => 0,
                    'average_score' => 0.0,
                    'average_confidence' => 0.0,
                    'average_evidence_count' => 0.0,
                    '_score' => 0.0,
                    '_confidence' => 0.0,
                    '_evidence' => 0,
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['_score'] += (float)$state->masteryscore;
            $groups[$key]['_confidence'] += (float)$state->confidence;
            $groups[$key]['_evidence'] += (int)$state->evidencecount;
        }
        foreach ($groups as &$group) {
            $count = max(1, (int)$group['count']);
            $group['average_score'] = round($group['_score'] / $count, 3);
            $group['average_confidence'] = round($group['_confidence'] / $count, 3);
            $group['average_evidence_count'] = round($group['_evidence'] / $count, 2);
            unset($group['_score'], $group['_confidence'], $group['_evidence']);
        }
        return array_values($groups);
    }

    /**
     * Prioritized suspicious/interesting rows for calibration review.
     *
     * @param array $evidence
     * @param array $states
     * @return array
     */
    private static function edge_cases(array $evidence, array $states): array {
        $latestevidence = [];
        foreach ($evidence as $row) {
            $key = $row->userid . ':' . $row->targettype . ':' . $row->targetid;
            if (!isset($latestevidence[$key]) || (int)$row->timecreated > (int)$latestevidence[$key]->timecreated) {
                $latestevidence[$key] = $row;
            }
        }

        $cases = [];
        $now = time();
        foreach ($states as $state) {
            $key = $state->userid . ':' . $state->targettype . ':' . $state->targetid;
            $latest = $latestevidence[$key] ?? null;
            $score = (float)$state->masteryscore;
            $strong = in_array((string)$state->masterystate, self::STRONG_STATES, true);

            if (!empty($state->manualoverride)) {
                $cases[] = self::case_row('manual_override', 'review', $state, $latest,
                    'Teacher override is active: ' . trim((string)$state->overridereason));
            }
            if ((float)$state->confidence < 0.60) {
                $cases[] = self::case_row('low_confidence', 'watch', $state, $latest,
                    'Confidence is below 0.60.');
            }
            if ((int)$state->evidencecount < 2 && $strong) {
                $cases[] = self::case_row('strong_single_evidence', 'watch', $state, $latest,
                    'Strong state is based on fewer than two evidence events.');
            }
            if ($score >= 0.85 && !$strong) {
                $cases[] = self::case_row('high_score_not_strong', 'review', $state, $latest,
                    'Score is high but the state is not a strong outcome.');
            }
            if ($score < 0.70 && $strong) {
                $cases[] = self::case_row('low_score_strong_state', 'review', $state, $latest,
                    'Strong state has a score below 0.70.');
            }
            if (!empty($state->nextreview) && (int)$state->nextreview < $now) {
                $cases[] = self::case_row('review_due', 'act', $state, $latest,
                    'The review date has passed.');
            }
        }

        foreach ($evidence as $row) {
            $key = $row->userid . ':' . $row->targettype . ':' . $row->targetid;
            if (!isset($latestevidence[$key]) || (int)$latestevidence[$key]->id !== (int)$row->id) {
                continue;
            }
            $isperformance = in_array((string)$row->evidencestrength, self::PERFORMANCE_STRENGTHS, true);
            if ($isperformance && (float)$row->normalizedscore < 0.70) {
                $cases[] = [
                    'kind' => 'low_performance_evidence',
                    'priority' => 'review',
                    'userid' => (int)$row->userid,
                    'targettype' => (string)$row->targettype,
                    'targetid' => (int)$row->targetid,
                    'targetexternalid' => (string)($row->targetexternalid ?? ''),
                    'state' => '',
                    'score' => (float)$row->normalizedscore,
                    'confidence' => (float)$row->confidence,
                    'evidencecount' => 1,
                    'latest_evidenceid' => (int)$row->id,
                    'latest_evidencetype' => (string)$row->evidencetype,
                    'latest_strength' => (string)$row->evidencestrength,
                    'message' => 'Latest direct performance evidence is below 0.70.',
                ];
            }
        }

        usort($cases, static function(array $a, array $b): int {
            $priority = ['act' => 0, 'review' => 1, 'watch' => 2];
            return ($priority[$a['priority']] ?? 9) <=> ($priority[$b['priority']] ?? 9);
        });

        return array_slice($cases, 0, 40);
    }

    /**
     * Build one state edge-case row.
     *
     * @param string $kind
     * @param string $priority
     * @param \stdClass $state
     * @param \stdClass|null $latest
     * @param string $message
     * @return array
     */
    private static function case_row(string $kind, string $priority, \stdClass $state, ?\stdClass $latest,
            string $message): array {
        return [
            'kind' => $kind,
            'priority' => $priority,
            'userid' => (int)$state->userid,
            'targettype' => (string)$state->targettype,
            'targetid' => (int)$state->targetid,
            'targetexternalid' => (string)($state->targetexternalid ?? ''),
            'state' => (string)$state->masterystate,
            'score' => (float)$state->masteryscore,
            'confidence' => (float)$state->confidence,
            'evidencecount' => (int)$state->evidencecount,
            'latest_evidenceid' => $latest ? (int)$latest->id : null,
            'latest_evidencetype' => $latest ? (string)$latest->evidencetype : '',
            'latest_strength' => $latest ? (string)$latest->evidencestrength : '',
            'message' => $message,
        ];
    }
}
