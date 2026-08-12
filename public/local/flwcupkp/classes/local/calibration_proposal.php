<?php
// Threshold calibration proposal workflow.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates, previews, and activates threshold calibration proposals.
 */
final class calibration_proposal {
    /** @var array Ordered numeric threshold fields by target type. */
    private const FIELDS = [
        'kp' => ['introduced', 'practiced', 'controlled_use', 'independent_use', 'mastered'],
        'up' => ['emerging', 'developing', 'demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['developing', 'provisionally_achieved', 'achieved', 'sustained'],
    ];

    /** @var array Strong states used in the preview summary. */
    private const STRONG = [
        'kp' => ['mastered'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /**
     * Return available target types.
     *
     * @return array
     */
    public static function target_types(): array {
        return ['kp', 'up', 'competency'];
    }

    /**
     * Ordered threshold fields for one target type.
     *
     * @param string $targettype
     * @return array
     */
    public static function fields(string $targettype): array {
        return self::FIELDS[$targettype] ?? [];
    }

    /**
     * Starting thresholds for a proposal.
     *
     * @param string $targettype
     * @return array
     */
    public static function current_thresholds(string $targettype): array {
        return array_intersect_key(mastery_engine::rules_for($targettype), array_flip(self::fields($targettype)));
    }

    /**
     * Normalize and validate submitted threshold values.
     *
     * @param string $targettype
     * @param array $values
     * @return array
     */
    public static function normalize_thresholds(string $targettype, array $values): array {
        $fields = self::fields($targettype);
        if (!$fields) {
            throw new \invalid_parameter_exception('Unsupported target type.');
        }

        $thresholds = [];
        $previous = 0.0;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                throw new \invalid_parameter_exception('Missing threshold: ' . $field);
            }
            $value = round((float)$values[$field], 5);
            if ($value < 0 || $value > 1) {
                throw new \invalid_parameter_exception('Thresholds must be between 0 and 1.');
            }
            if ($value < $previous) {
                throw new \invalid_parameter_exception('Thresholds must stay in ascending order.');
            }
            $thresholds[$field] = $value;
            $previous = $value;
        }

        if ($targettype === 'kp') {
            $thresholds['review_after_days'] = (int)(mastery_engine::rules_for('kp')['review_after_days'] ?? 21);
        }
        if ($targettype === 'competency') {
            $thresholds['direct_evidence_required'] =
                (bool)(mastery_engine::rules_for('competency')['direct_evidence_required'] ?? true);
        }
        $thresholds['calibration_status'] = 'calibrated';
        $thresholds['target_type'] = $targettype;

        return $thresholds;
    }

    /**
     * Preview state outcome changes from a saved snapshot.
     *
     * @param \stdClass $snapshot
     * @param string $targettype
     * @param array $thresholds
     * @return array
     */
    public static function preview(\stdClass $snapshot, string $targettype, array $thresholds): array {
        $rows = array_values(array_filter(
            calibration_report::snapshot_state_details($snapshot),
            static function(array $row) use ($targettype): bool {
                return (string)($row['targettype'] ?? '') === $targettype;
            }
        ));

        $current = [];
        $proposed = [];
        $transitions = [];
        $changed = 0;
        $strongcurrent = 0;
        $strongproposed = 0;

        foreach ($rows as $row) {
            $oldstate = (string)($row['masterystate'] ?? '');
            $newstate = self::state_name($targettype, (float)($row['masteryscore'] ?? 0), $thresholds);
            $current[$oldstate] = ($current[$oldstate] ?? 0) + 1;
            $proposed[$newstate] = ($proposed[$newstate] ?? 0) + 1;
            $transition = $oldstate . ' -> ' . $newstate;
            $transitions[$transition] = ($transitions[$transition] ?? 0) + 1;
            if ($oldstate !== $newstate) {
                $changed++;
            }
            if (in_array($oldstate, self::STRONG[$targettype] ?? [], true)) {
                $strongcurrent++;
            }
            if (in_array($newstate, self::STRONG[$targettype] ?? [], true)) {
                $strongproposed++;
            }
        }

        ksort($current);
        ksort($proposed);
        ksort($transitions);

        return [
            'snapshotid' => (int)$snapshot->id,
            'targettype' => $targettype,
            'total_states' => count($rows),
            'changed_states' => $changed,
            'strong_current' => $strongcurrent,
            'strong_proposed' => $strongproposed,
            'strong_delta' => $strongproposed - $strongcurrent,
            'current_outcomes' => $current,
            'proposed_outcomes' => $proposed,
            'transitions' => $transitions,
            'note' => 'Preview applies proposed thresholds to saved state scores; it does not rewrite learner states.',
        ];
    }

    /**
     * Save a draft proposal.
     *
     * @param int $snapshotid
     * @param string $targettype
     * @param string $name
     * @param string $note
     * @param array $thresholds
     * @return int
     */
    public static function save(int $snapshotid, string $targettype, string $name, string $note,
            array $thresholds): int {
        global $DB, $USER;

        $snapshot = calibration_report::snapshot($snapshotid);
        if (!$snapshot) {
            throw new \invalid_parameter_exception('Snapshot is required.');
        }

        $thresholds = self::normalize_thresholds($targettype, $thresholds);
        $preview = self::preview($snapshot, $targettype, $thresholds);
        $now = time();
        $name = trim($name) !== '' ? trim($name) : 'Calibration proposal ' . date('Ymd-His', $now);
        $version = self::version($targettype, $now);

        $record = (object)[
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'name' => substr($name, 0, 120),
            'version' => $version,
            'status' => 'draft',
            'thresholdsjson' => json_encode($thresholds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'previewjson' => json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'activatedruleid' => null,
            'note' => trim($note),
            'userid' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $proposalid = (int)$DB->insert_record('flwcupkp_calproposal', $record);
        repository::audit('calibration_proposal_saved', 'calibration_proposal', $proposalid, [
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'version' => $version,
            'preview' => $preview,
        ]);

        return $proposalid;
    }

    /**
     * Fetch one proposal.
     *
     * @param int $proposalid
     * @return \stdClass|null
     */
    public static function proposal(int $proposalid): ?\stdClass {
        global $DB;

        return $DB->get_record('flwcupkp_calproposal', ['id' => $proposalid], '*', IGNORE_MISSING) ?: null;
    }

    /**
     * Proposals saved for a snapshot.
     *
     * @param int $snapshotid
     * @return array
     */
    public static function proposals_for_snapshot(int $snapshotid): array {
        global $DB;

        return array_values($DB->get_records('flwcupkp_calproposal', ['snapshotid' => $snapshotid],
            'timecreated DESC, id DESC'));
    }

    /**
     * Decode a proposal preview.
     *
     * @param \stdClass $proposal
     * @return array
     */
    public static function proposal_preview(\stdClass $proposal): array {
        $preview = json_decode((string)$proposal->previewjson, true);
        return is_array($preview) ? $preview : [];
    }

    /**
     * Decode proposal thresholds.
     *
     * @param \stdClass $proposal
     * @return array
     */
    public static function proposal_thresholds(\stdClass $proposal): array {
        $thresholds = json_decode((string)$proposal->thresholdsjson, true);
        return is_array($thresholds) ? $thresholds : [];
    }

    /**
     * Activate a reviewed proposal as the target type's calibrated rule.
     *
     * @param int $proposalid
     * @return int
     */
    public static function activate(int $proposalid): int {
        global $DB;

        $proposal = $DB->get_record('flwcupkp_calproposal', ['id' => $proposalid], '*', MUST_EXIST);
        if ((string)$proposal->status === 'activated' && !empty($proposal->activatedruleid)) {
            return (int)$proposal->activatedruleid;
        }

        $targettype = (string)$proposal->targettype;
        $ruletype = $targettype . '_mastery';
        $thresholds = self::proposal_thresholds($proposal);
        $thresholds['source_proposalid'] = $proposalid;
        $thresholds['source_snapshotid'] = (int)$proposal->snapshotid;
        $thresholds['calibration_status'] = 'active_calibrated';
        $thresholds['target_type'] = $targettype;

        $now = time();
        $active = $DB->get_records('flwcupkp_rule', ['ruletype' => $ruletype, 'status' => 'active']);
        foreach ($active as $record) {
            $record->status = 'archived';
            $record->timemodified = $now;
            $DB->update_record('flwcupkp_rule', $record);
        }

        $rule = (object)[
            'ruletype' => $ruletype,
            'name' => (string)$proposal->name,
            'version' => (string)$proposal->version,
            'configjson' => json_encode($thresholds, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'status' => 'active',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        if ($existing = $DB->get_record('flwcupkp_rule', ['ruletype' => $ruletype, 'version' => $rule->version],
                IGNORE_MISSING)) {
            $rule->id = $existing->id;
            $rule->timecreated = $existing->timecreated;
            $DB->update_record('flwcupkp_rule', $rule);
            $ruleid = (int)$existing->id;
        } else {
            $ruleid = (int)$DB->insert_record('flwcupkp_rule', $rule);
        }

        $proposal->status = 'activated';
        $proposal->activatedruleid = $ruleid;
        $proposal->timemodified = $now;
        $DB->update_record('flwcupkp_calproposal', $proposal);

        repository::audit('calibration_proposal_activated', 'calibration_proposal', $proposalid, [
            'ruleid' => $ruleid,
            'ruletype' => $ruletype,
            'version' => $rule->version,
            'preview' => self::proposal_preview($proposal),
        ]);

        return $ruleid;
    }

    /**
     * Simulate recalculating current learner states under an activated proposal.
     *
     * @param int $proposalid
     * @return array
     */
    public static function recalculation_simulation(int $proposalid, bool $limitrows = true): array {
        $proposal = self::proposal($proposalid);
        if (!$proposal) {
            throw new \invalid_parameter_exception('Proposal is required.');
        }

        $thresholds = self::proposal_thresholds($proposal);
        $thresholds['version'] = (string)$proposal->version;
        $targettype = (string)$proposal->targettype;
        $rows = [];
        $summary = [
            'proposalid' => $proposalid,
            'targettype' => $targettype,
            'ruleversion' => (string)$proposal->version,
            'proposal_status' => (string)$proposal->status,
            'total' => 0,
            'changed' => 0,
            'created' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'manual_overrides' => 0,
            'rows' => [],
        ];

        foreach (self::candidate_states($proposal) as $candidate) {
            $summary['total']++;
            if ($targettype === 'kp') {
                $result = self::preview_direct_state($candidate, $thresholds);
            } else if ($targettype === 'up') {
                $result = rollup_engine::preview_up((int)$candidate->userid, (int)$candidate->targetid);
            } else {
                $result = rollup_engine::preview_competency((int)$candidate->userid, (int)$candidate->targetid);
            }
            $result['targetexternalid'] = (string)($candidate->targetexternalid ?? '');
            $result['targettitle'] = (string)($candidate->targettitle ?? '');
            $result['manualoverride'] = !empty($candidate->manualoverride) ? 1 : 0;

            if ($result['status'] === 'changed') {
                $summary['changed']++;
            } else if ($result['status'] === 'created') {
                $summary['created']++;
            } else if ($result['status'] === 'unchanged') {
                $summary['unchanged']++;
            } else {
                $summary['skipped']++;
            }
            if (!empty($candidate->manualoverride)) {
                $summary['manual_overrides']++;
            }
            $rows[] = $result;
        }

        usort($rows, static function(array $a, array $b): int {
            $rank = ['changed' => 0, 'created' => 1, 'skipped' => 2, 'unchanged' => 3];
            return ($rank[$a['status']] ?? 9) <=> ($rank[$b['status']] ?? 9);
        });
        $summary['rows'] = $limitrows ? array_slice($rows, 0, 80) : $rows;

        return $summary;
    }

    /**
     * Apply a controlled recalculation for changed rows under an activated proposal.
     *
     * @param int $proposalid
     * @return array
     */
    public static function apply_recalculation(int $proposalid): array {
        $runid = self::create_recalculation_run($proposalid, 'immediate', 'queued');
        return self::process_recalculation_run($runid);
    }

    /**
     * Queue controlled recalculation for scheduled processing.
     *
     * @param int $proposalid
     * @return int
     */
    public static function queue_recalculation(int $proposalid): int {
        $runid = self::create_recalculation_run($proposalid, 'queued', 'queued');
        repository::audit('calibration_recalculation_queued', 'calibration_proposal', $proposalid, ['runid' => $runid]);
        return $runid;
    }

    /**
     * Recalculation runs for one proposal.
     *
     * @param int $proposalid
     * @param int $limit
     * @return array
     */
    public static function recalculation_runs(int $proposalid, int $limit = 10): array {
        global $DB;

        return array_values($DB->get_records('flwcupkp_calrecalc', ['proposalid' => $proposalid],
            'timecreated DESC, id DESC', '*', 0, max(1, $limit)));
    }

    /**
     * Process oldest queued recalculation runs.
     *
     * @param int $limit
     * @return array
     */
    public static function process_next_recalculation(int $limit = 1): array {
        global $DB;

        $processed = [];
        $runs = $DB->get_records_sql(
            "SELECT *
               FROM {flwcupkp_calrecalc}
              WHERE status = :status
           ORDER BY timecreated ASC, id ASC",
            ['status' => 'queued'],
            0,
            max(1, $limit)
        );
        foreach ($runs as $run) {
            $processed[] = self::process_recalculation_run((int)$run->id);
        }
        return $processed;
    }

    /**
     * Process one recalculation run.
     *
     * @param int $runid
     * @return array
     */
    public static function process_recalculation_run(int $runid): array {
        global $DB;

        $run = $DB->get_record('flwcupkp_calrecalc', ['id' => $runid], '*', MUST_EXIST);
        if (!in_array((string)$run->status, ['queued', 'running'], true)) {
            $result = json_decode((string)$run->resultjson, true);
            return is_array($result) ? $result : [
                'runid' => $runid,
                'proposalid' => (int)$run->proposalid,
                'status' => (string)$run->status,
            ];
        }

        $proposalid = (int)$run->proposalid;
        $proposal = self::proposal($proposalid);
        if (!$proposal) {
            throw new \invalid_parameter_exception('Proposal is required.');
        }
        if ((string)$proposal->status !== 'activated') {
            throw new \invalid_parameter_exception('Only activated proposals can recalculate learner states.');
        }

        $now = time();
        $run->status = 'running';
        $run->timestarted = $run->timestarted ?: $now;
        $run->timemodified = $now;
        $DB->update_record('flwcupkp_calrecalc', $run);

        try {
            $simulation = self::recalculation_simulation($proposalid, false);
            $applied = 0;
            $errors = [];
            foreach ($simulation['rows'] as $row) {
                if (!in_array($row['status'], ['changed', 'created'], true)) {
                    continue;
                }
                try {
                    self::apply_row($proposal, $row);
                    $applied++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'userid' => (int)($row['userid'] ?? 0),
                        'targettype' => (string)($row['targettype'] ?? ''),
                        'targetid' => (int)($row['targetid'] ?? 0),
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $result = [
                'runid' => $runid,
                'proposalid' => $proposalid,
                'targettype' => (string)$proposal->targettype,
                'ruleversion' => (string)$proposal->version,
                'candidate_total' => (int)$simulation['total'],
                'changed_or_created' => (int)$simulation['changed'] + (int)$simulation['created'],
                'applied' => $applied,
                'skipped' => (int)$simulation['skipped'],
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            $simulation = [];
            $errors = [['message' => $e->getMessage()]];
            $result = [
                'runid' => $runid,
                'proposalid' => $proposalid,
                'targettype' => (string)$proposal->targettype,
                'ruleversion' => (string)$proposal->version,
                'candidate_total' => 0,
                'changed_or_created' => 0,
                'applied' => 0,
                'skipped' => 0,
                'errors' => $errors,
            ];
        }

        $now = time();
        $run->status = empty($errors) ? 'completed' :
            (empty($simulation) ? 'failed' : 'completed_with_errors');
        $run->candidate_total = (int)$result['candidate_total'];
        $run->changed_or_created = (int)$result['changed_or_created'];
        $run->applied = (int)$result['applied'];
        $run->skipped = (int)$result['skipped'];
        $run->simulationjson = json_encode($simulation, JSON_UNESCAPED_SLASHES);
        $run->resultjson = json_encode($result, JSON_UNESCAPED_SLASHES);
        $run->errorsjson = json_encode($errors, JSON_UNESCAPED_SLASHES);
        $run->timecompleted = $now;
        $run->timemodified = $now;
        $DB->update_record('flwcupkp_calrecalc', $run);

        repository::audit('calibration_recalculation_applied', 'calibration_proposal', $proposalid, $result);
        return $result;
    }

    /**
     * Create a recalculation run record from the current preview.
     *
     * @param int $proposalid
     * @param string $mode
     * @param string $status
     * @return int
     */
    private static function create_recalculation_run(int $proposalid, string $mode, string $status): int {
        global $DB, $USER;

        $proposal = self::proposal($proposalid);
        if (!$proposal) {
            throw new \invalid_parameter_exception('Proposal is required.');
        }
        if ((string)$proposal->status !== 'activated') {
            throw new \invalid_parameter_exception('Only activated proposals can recalculate learner states.');
        }

        $simulation = self::recalculation_simulation($proposalid, true);
        $now = time();
        $record = (object)[
            'proposalid' => $proposalid,
            'status' => $status,
            'mode' => $mode,
            'candidate_total' => (int)$simulation['total'],
            'changed_or_created' => (int)$simulation['changed'] + (int)$simulation['created'],
            'applied' => 0,
            'skipped' => (int)$simulation['skipped'],
            'simulationjson' => json_encode($simulation, JSON_UNESCAPED_SLASHES),
            'resultjson' => '',
            'errorsjson' => '[]',
            'userid' => $USER->id ?? 0,
            'timecreated' => $now,
            'timestarted' => null,
            'timecompleted' => null,
            'timemodified' => $now,
        ];

        return (int)$DB->insert_record('flwcupkp_calrecalc', $record);
    }

    /**
     * Candidate states in the proposal snapshot scope.
     *
     * @param \stdClass $proposal
     * @return array
     */
    private static function candidate_states(\stdClass $proposal): array {
        global $DB;

        $snapshot = calibration_report::snapshot((int)$proposal->snapshotid);
        if (!$snapshot) {
            return [];
        }
        $filters = self::snapshot_filters($snapshot);
        $targettype = (string)$proposal->targettype;
        $where = ['s.targettype = :targettype'];
        $params = ['targettype' => $targettype];
        $scope = self::state_scope_sql($targettype, $filters, $params);
        if ($scope !== '') {
            $where[] = '(' . $scope . ')';
        }

        $candidates = [];
        foreach ($DB->get_records_sql(
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
                    s.ruleversion,
                    COALESCE(c.externalid, u.externalid, kp.externalid) AS targetexternalid,
                    COALESCE(c.title, u.title, kp.title) AS targettitle
              FROM {flwcupkp_state} s
          LEFT JOIN {flwcupkp_comp} c ON c.id = s.targetid AND s.targettype = 'competency'
          LEFT JOIN {flwcupkp_up} u ON u.id = s.targetid AND s.targettype = 'up'
          LEFT JOIN {flwcupkp_kp} kp ON kp.id = s.targetid AND s.targettype = 'kp'
              WHERE " . implode(' AND ', $where) . "
           ORDER BY s.userid ASC, s.targetid ASC",
            $params
        ) as $candidate) {
            $candidates[self::candidate_key($candidate)] = $candidate;
        }

        foreach (self::evidence_candidate_pairs($targettype, $filters) as $candidate) {
            $key = self::candidate_key($candidate);
            if (isset($candidates[$key])) {
                continue;
            }
            $candidate->id = null;
            $candidate->masteryscore = 0;
            $candidate->masterystate = '';
            $candidate->confidence = 0;
            $candidate->evidencecount = 0;
            $candidate->lastevidence = null;
            $candidate->lastsuccess = null;
            $candidate->nextreview = null;
            $candidate->manualoverride = 0;
            $candidate->ruleversion = '';
            $candidates[$key] = $candidate;
        }

        uasort($candidates, static function(\stdClass $a, \stdClass $b): int {
            return ((int)$a->userid <=> (int)$b->userid) ?: ((int)$a->targetid <=> (int)$b->targetid);
        });

        return array_values($candidates);
    }

    /**
     * Scope existing state candidates to the saved snapshot filters.
     *
     * @param string $targettype
     * @param array $filters
     * @param array $params
     * @return string
     */
    private static function state_scope_sql(string $targettype, array $filters, array &$params): string {
        if (empty($filters['courseid']) && empty($filters['unitcode'])) {
            return '';
        }

        $conditions = [];
        $conditions[] = 'EXISTS (
            SELECT 1
              FROM {flwcupkp_evidence} de
             WHERE de.userid = s.userid
               AND de.targettype = s.targettype
               AND de.targetid = s.targetid' .
            self::evidence_filter_sql('de', 'statedirect', $filters, $params) . '
        )';

        if ($targettype === 'up') {
            $conditions[] = 'EXISTS (
                SELECT 1
                  FROM {flwcupkp_evidence} ke
                  JOIN {flwcupkp_up_kp} uk ON uk.kpid = ke.targetid
                 WHERE ke.userid = s.userid
                   AND ke.targettype = \'kp\'
                   AND uk.upid = s.targetid' .
                self::evidence_filter_sql('ke', 'stateupkp', $filters, $params) . '
            )';
        }

        if ($targettype === 'competency') {
            $conditions[] = 'EXISTS (
                SELECT 1
                  FROM {flwcupkp_evidence} ue
                  JOIN {flwcupkp_comp_up} cu ON cu.upid = ue.targetid
                 WHERE ue.userid = s.userid
                   AND ue.targettype = \'up\'
                   AND cu.competencyid = s.targetid' .
                self::evidence_filter_sql('ue', 'statecompup', $filters, $params) . '
            )';
            $conditions[] = 'EXISTS (
                SELECT 1
                  FROM {flwcupkp_evidence} ke
                  JOIN {flwcupkp_up_kp} uk ON uk.kpid = ke.targetid
                  JOIN {flwcupkp_comp_up} cu ON cu.upid = uk.upid
                 WHERE ke.userid = s.userid
                   AND ke.targettype = \'kp\'
                   AND cu.competencyid = s.targetid' .
                self::evidence_filter_sql('ke', 'statecompkp', $filters, $params) . '
            )';
        }

        return implode(' OR ', $conditions);
    }

    /**
     * Candidate user/target pairs found from evidence in the saved snapshot scope.
     *
     * @param string $targettype
     * @param array $filters
     * @return array
     */
    private static function evidence_candidate_pairs(string $targettype, array $filters): array {
        global $DB;

        $params = [];
        $parts = [];
        if ($targettype === 'kp') {
            $parts[] = "SELECT e.userid, 'kp' AS targettype, e.targetid
                          FROM {flwcupkp_evidence} e
                         WHERE e.targettype = 'kp'" .
                self::evidence_filter_sql('e', 'evkp', $filters, $params);
        } else if ($targettype === 'up') {
            $parts[] = "SELECT e.userid, 'up' AS targettype, e.targetid
                          FROM {flwcupkp_evidence} e
                         WHERE e.targettype = 'up'" .
                self::evidence_filter_sql('e', 'evupdirect', $filters, $params);
            $parts[] = "SELECT e.userid, 'up' AS targettype, uk.upid AS targetid
                          FROM {flwcupkp_evidence} e
                          JOIN {flwcupkp_up_kp} uk ON uk.kpid = e.targetid
                         WHERE e.targettype = 'kp'" .
                self::evidence_filter_sql('e', 'evupkp', $filters, $params);
        } else if ($targettype === 'competency') {
            $parts[] = "SELECT e.userid, 'competency' AS targettype, e.targetid
                          FROM {flwcupkp_evidence} e
                         WHERE e.targettype = 'competency'" .
                self::evidence_filter_sql('e', 'evcompdirect', $filters, $params);
            $parts[] = "SELECT e.userid, 'competency' AS targettype, cu.competencyid AS targetid
                          FROM {flwcupkp_evidence} e
                          JOIN {flwcupkp_comp_up} cu ON cu.upid = e.targetid
                         WHERE e.targettype = 'up'" .
                self::evidence_filter_sql('e', 'evcompup', $filters, $params);
            $parts[] = "SELECT e.userid, 'competency' AS targettype, cu.competencyid AS targetid
                          FROM {flwcupkp_evidence} e
                          JOIN {flwcupkp_up_kp} uk ON uk.kpid = e.targetid
                          JOIN {flwcupkp_comp_up} cu ON cu.upid = uk.upid
                         WHERE e.targettype = 'kp'" .
                self::evidence_filter_sql('e', 'evcompkp', $filters, $params);
        }

        if (!$parts) {
            return [];
        }

        $rows = [];
        $recordset = $DB->get_recordset_sql(
            "SELECT base.userid,
                    base.targettype,
                    base.targetid,
                    COALESCE(c.externalid, u.externalid, kp.externalid) AS targetexternalid,
                    COALESCE(c.title, u.title, kp.title) AS targettitle
               FROM (" . implode(' UNION ', $parts) . ") base
          LEFT JOIN {flwcupkp_comp} c ON c.id = base.targetid AND base.targettype = 'competency'
          LEFT JOIN {flwcupkp_up} u ON u.id = base.targetid AND base.targettype = 'up'
          LEFT JOIN {flwcupkp_kp} kp ON kp.id = base.targetid AND base.targettype = 'kp'
           ORDER BY base.userid ASC, base.targetid ASC",
            $params
        );
        foreach ($recordset as $row) {
            $rows[self::candidate_key($row)] = $row;
        }
        $recordset->close();

        return array_values($rows);
    }

    /**
     * SQL fragment for course/unit evidence filters.
     *
     * @param string $alias
     * @param string $prefix
     * @param array $filters
     * @param array $params
     * @return string
     */
    private static function evidence_filter_sql(string $alias, string $prefix, array $filters, array &$params): string {
        $where = [];
        if (!empty($filters['courseid'])) {
            $key = $prefix . 'courseid';
            $where[] = $alias . '.courseid = :' . $key;
            $params[$key] = (int)$filters['courseid'];
        }
        if (!empty($filters['unitcode'])) {
            $key = $prefix . 'unitcode';
            $where[] = $alias . '.unitcode = :' . $key;
            $params[$key] = (string)$filters['unitcode'];
        }

        return $where ? ' AND ' . implode(' AND ', $where) : '';
    }

    /**
     * Stable candidate key.
     *
     * @param \stdClass $candidate
     * @return string
     */
    private static function candidate_key(\stdClass $candidate): string {
        return (int)$candidate->userid . ':' . (string)$candidate->targettype . ':' . (int)$candidate->targetid;
    }

    /**
     * Read snapshot filters from a saved payload.
     *
     * @param \stdClass $snapshot
     * @return array
     */
    private static function snapshot_filters(\stdClass $snapshot): array {
        $payload = calibration_report::snapshot_payload($snapshot);
        $filters = $payload['filters'] ?? [];
        return [
            'courseid' => (int)($filters['courseid'] ?? $snapshot->courseid ?? 0),
            'unitcode' => (string)($filters['unitcode'] ?? $snapshot->unitcode ?? ''),
            'targettype' => (string)($filters['targettype'] ?? $snapshot->targettype ?? ''),
        ];
    }

    /**
     * Preview one direct KP state recalculation.
     *
     * @param \stdClass $candidate
     * @param array $thresholds
     * @return array
     */
    private static function preview_direct_state(\stdClass $candidate, array $thresholds): array {
        $state = self::direct_calculated_state((int)$candidate->userid, (string)$candidate->targettype,
            (int)$candidate->targetid, $thresholds);
        if ($state === null) {
            return self::comparison_row($candidate, null, 'skipped', 'no_evidence');
        }
        if (!empty($candidate->manualoverride)) {
            return self::comparison_row($candidate, $state, 'skipped', 'manual_override');
        }

        $status = empty($candidate->id) ? 'created' :
            (self::same_direct_state($candidate, $state) ? 'unchanged' : 'changed');
        return self::comparison_row($candidate, $state, $status);
    }

    /**
     * Recalculate a direct state from evidence without storing it.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param array $thresholds
     * @return array|null
     */
    private static function direct_calculated_state(int $userid, string $targettype, int $targetid,
            array $thresholds): ?array {
        global $DB;

        $events = $DB->get_records('flwcupkp_evidence', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], 'timecreated ASC, id ASC');
        if (!$events) {
            return null;
        }

        return mastery_engine::calculate($targettype, array_values($events), $thresholds);
    }

    /**
     * Apply one changed simulation row.
     *
     * @param \stdClass $proposal
     * @param array $row
     */
    private static function apply_row(\stdClass $proposal, array $row): void {
        $targettype = (string)$proposal->targettype;
        $userid = (int)$row['userid'];
        $targetid = (int)$row['targetid'];
        if ($targettype === 'kp') {
            $thresholds = self::proposal_thresholds($proposal);
            $thresholds['version'] = (string)$proposal->version;
            $state = self::direct_calculated_state($userid, 'kp', $targetid, $thresholds);
            if ($state !== null) {
                repository::upsert_state($userid, 'kp', $targetid, $state);
                rollup_engine::recalculate_dependents($userid, 'kp', $targetid, true);
            }
            return;
        }
        if ($targettype === 'up') {
            rollup_engine::recalculate_dependents($userid, 'up', $targetid, true);
            return;
        }
        rollup_engine::recalculate_competency($userid, $targetid, true);
    }

    /**
     * Build one comparison row.
     *
     * @param \stdClass $candidate
     * @param array|null $state
     * @param string $status
     * @param string $reason
     * @return array
     */
    private static function comparison_row(\stdClass $candidate, ?array $state, string $status,
            string $reason = ''): array {
        return [
            'status' => $status,
            'reason' => $reason,
            'userid' => (int)$candidate->userid,
            'targettype' => (string)$candidate->targettype,
            'targetid' => (int)$candidate->targetid,
            'current_state' => (string)$candidate->masterystate,
            'current_score' => (float)$candidate->masteryscore,
            'current_ruleversion' => (string)($candidate->ruleversion ?? ''),
            'proposed_state' => $state ? (string)$state['masterystate'] : '',
            'proposed_score' => $state ? (float)$state['masteryscore'] : null,
            'proposed_ruleversion' => $state ? (string)$state['ruleversion'] : '',
            'evidencecount' => $state ? (int)$state['evidencecount'] : (int)$candidate->evidencecount,
        ];
    }

    /**
     * Compare a direct recalculation with a stored state.
     *
     * @param \stdClass $candidate
     * @param array $state
     * @return bool
     */
    private static function same_direct_state(\stdClass $candidate, array $state): bool {
        return (string)$candidate->masterystate === (string)$state['masterystate'] &&
            abs((float)$candidate->masteryscore - (float)$state['masteryscore']) < 0.00001 &&
            abs((float)$candidate->confidence - (float)$state['confidence']) < 0.00001 &&
            (int)$candidate->evidencecount === (int)$state['evidencecount'] &&
            (string)$candidate->ruleversion === (string)$state['ruleversion'];
    }

    /**
     * Preview state name from score thresholds.
     *
     * @param string $targettype
     * @param float $score
     * @param array $thresholds
     * @return string
     */
    private static function state_name(string $targettype, float $score, array $thresholds): string {
        $states = array_reverse(self::fields($targettype));
        foreach ($states as $state) {
            if ($score >= (float)($thresholds[$state] ?? 1.1)) {
                return $state;
            }
        }
        return $targettype === 'kp' ? 'not_introduced' : ($targettype === 'up' ? 'not_observed' : 'not_started');
    }

    /**
     * Stable calibrated rule version string.
     *
     * @param string $targettype
     * @param int $time
     * @return string
     */
    private static function version(string $targettype, int $time): string {
        return 'cal-' . $targettype . '-' . date('YmdHis', $time);
    }
}
