<?php
// Program 3 Gate E2 Mastery + Confidence + Current Learner State.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Reproducible current learner-state service.
 */
final class mastery_state_service {
    /** Program 3 mastery/current-state gate. */
    public const GATE = 'P3_E2';

    /** Frozen E2 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_MASTERY_CONFIDENCE_STATE_V1';

    /** Next prompt gate after E2. */
    public const NEXT_ALLOWED_GATE = 'E3';

    /** Strong state labels by target type. */
    private const STRONG_STATES = [
        'kp' => ['independent_use', 'mastered', 'review_due'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /**
     * E2 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpMasteryConfidenceStateContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                management_v1_contract::CONTRACT_VERSION,
                history_evidence_adapter::CONTRACT_VERSION,
                evidence_semantics_quality_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'supported_learner_states' => [
                'LearnerCompetencyState',
                'LearnerKPState',
                'LearnerUPState',
            ],
            'mastery_policy_version' => mastery_engine::POLICY_VERSION,
            'confidence_policy_version' => mastery_engine::CONFIDENCE_POLICY_VERSION,
            'snapshot_fields' => [
                'mastery',
                'confidence',
                'status',
                'trend',
                'policyversion',
                'ruleversion',
                'evidenceids',
                'evidencehash',
                'calculatedtime',
            ],
            'current_state_cache' => [
                'table' => 'flwcupkp_state',
                'rebuildable_from' => [
                    'Program-2 History V1 facts through E1 derived evidence',
                    'Program-3 normalized evidence rows',
                    'C-UP-KP Management V1 mappings',
                ],
                'manual_override_rule' => 'manual override rows are never overwritten by automated rebuild',
            ],
            'controlled_rebuild' => [
                'preview_is_read_only' => true,
                'apply_is_controlled' => true,
                'writes' => 'flwcupkp_state cache metadata and recalculated current state only',
                'audit_actions' => [
                    'mastery_state_rebuild_requested',
                    'mastery_state_rebuild_completed',
                    'mastery_state_rebuild_failed',
                ],
            ],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'adaptive_path_selection',
                'retention_decay',
                'grade_mastery_collapse',
                'history_v1_source_mutation',
            ],
        ];
    }

    /**
     * Readiness for E2 surfaces.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        $limit = self::bounded_limit($limit, 300);
        $management = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return management_v1_contract::management_status($courseid, $unitcode, $frameworkid, $limit);
        });
        $evidence = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return history_evidence_adapter::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $schema = self::schema_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $cache = self::cache_summary($courseid, $unitcode, $frameworkid, $limit);
        $criteria = self::criteria($management, $evidence, $schema, $files, $surface);
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpMasteryConfidenceStateStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'dependencies' => [
                'management_v1' => self::dependency_summary($management),
                'history_evidence_adapter' => self::dependency_summary($evidence),
            ],
            'schema' => $schema,
            'files' => $files,
            'surface' => $surface,
            'cache' => $cache,
            'findings' => self::status_findings($criteria, [$management, $evidence]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'controlled_rebuild_available' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return the current C-UP-KP learner state view.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function current_learner_state(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid > 0) {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        }

        $limit = self::bounded_limit($limit, 500);
        $targets = self::scoped_targets($userid, $courseid, $unitcode, $frameworkid, $limit);
        $states = [];
        foreach ($targets as $target) {
            $states[] = self::state_view($userid, $target, $courseid, $unitcode);
        }

        return [
            'type' => 'CupkpCurrentLearnerState',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => self::learner_summary($states),
            'states' => $states,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level E2 summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $limit = 100): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID is required.');
        }
        $limit = self::bounded_limit($limit, 300);
        $learners = self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $rows = [];
        $summary = [
            'learners' => count($learners),
            'state_rows' => 0,
            'strong_states' => 0,
            'low_confidence' => 0,
            'stale_or_missing_cache' => 0,
            'history_v1_evidence' => 0,
            'skipped_unenrolled' => 0,
        ];

        foreach ($learners as $userid) {
            try {
                $state = self::current_learner_state($userid, $courseid, $unitcode, $frameworkid, 300);
            } catch (\invalid_parameter_exception $e) {
                $summary['skipped_unenrolled']++;
                $rows[] = [
                    'userid' => (int)$userid,
                    'summary' => self::learner_summary([]),
                    'status' => 'skipped_unenrolled',
                    'reason' => $e->getMessage(),
                ];
                continue;
            }
            $learnersummary = $state['summary'];
            $summary['state_rows'] += (int)$learnersummary['states'];
            $summary['strong_states'] += (int)$learnersummary['strong_states'];
            $summary['low_confidence'] += (int)$learnersummary['low_confidence'];
            $summary['stale_or_missing_cache'] += (int)$learnersummary['stale_or_missing_cache'];
            $summary['history_v1_evidence'] += (int)$learnersummary['history_v1_evidence'];
            $rows[] = [
                'userid' => $userid,
                'summary' => $learnersummary,
            ];
        }

        return [
            'type' => 'CupkpClassCurrentStateSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'learners' => $rows,
            'read_only' => true,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Preview a current-state cache rebuild.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function preview_rebuild(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100): array {
        return self::process_rebuild($courseid, $unitcode, $frameworkid, $userid, $limit, false, '');
    }

    /**
     * Apply a controlled current-state cache rebuild.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param string $reason
     * @return array
     */
    public static function apply_rebuild(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $userid = 0, int $limit = 100, string $reason = ''): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $requestid = repository::audit('mastery_state_rebuild_requested', $courseid > 0 ? 'course' : 'system',
            $courseid > 0 ? $courseid : null, [
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
                'reason' => $reason,
            ]);

        $transaction = $DB->start_delegated_transaction();
        try {
            $result = self::process_rebuild($courseid, $unitcode, $frameworkid, $userid, $limit, true, $reason);
            $result['request_audit_id'] = $requestid;
            repository::audit('mastery_state_rebuild_completed', $courseid > 0 ? 'course' : 'system',
                $courseid > 0 ? $courseid : null, [
                    'request_audit_id' => $requestid,
                    'gate' => self::GATE,
                    'contract' => self::CONTRACT_VERSION,
                    'courseid' => $courseid,
                    'unitcode' => $unitcode,
                    'frameworkid' => $frameworkid,
                    'userid' => $userid,
                    'summary' => $result['summary'],
                ]);
            $transaction->allow_commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $ignored) {
                // Record the original failure after rollback handling.
            }
            repository::audit('mastery_state_rebuild_failed', $courseid > 0 ? 'course' : 'system',
                $courseid > 0 ? $courseid : null, [
                    'request_audit_id' => $requestid,
                    'gate' => self::GATE,
                    'contract' => self::CONTRACT_VERSION,
                    'courseid' => $courseid,
                    'unitcode' => $unitcode,
                    'frameworkid' => $frameworkid,
                    'userid' => $userid,
                    'message' => $e->getMessage(),
                ]);
            throw $e;
        }
    }

    /**
     * Recent E2 rebuild audit rows.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    public static function recent_rebuild_history(int $courseid = 0, int $limit = 20): array {
        global $DB;

        $limit = self::bounded_limit($limit, 100);
        $actions = [
            'mastery_state_rebuild_requested',
            'mastery_state_rebuild_completed',
            'mastery_state_rebuild_failed',
        ];
        list($insql, $params) = $DB->get_in_or_equal($actions, SQL_PARAMS_NAMED, 'action');
        $where = "action {$insql}";
        if ($courseid > 0) {
            $where .= ' AND targettype = :targettype AND targetid = :targetid';
            $params['targettype'] = 'course';
            $params['targetid'] = $courseid;
        }
        $records = $DB->get_records_select('flwcupkp_audit', $where, $params, 'timecreated DESC, id DESC', '*', 0,
            $limit);

        $rows = [];
        foreach ($records as $record) {
            $details = json_decode((string)$record->detailsjson, true);
            $rows[] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'targettype' => (string)($record->targettype ?? ''),
                'targetid' => isset($record->targetid) ? (int)$record->targetid : null,
                'userid' => isset($record->userid) ? (int)$record->userid : null,
                'timecreated' => (int)$record->timecreated,
                'details' => is_array($details) ? $details : [],
            ];
        }
        return $rows;
    }

    /**
     * Shared rebuild preview/apply implementation.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param bool $write
     * @param string $reason
     * @return array
     */
    private static function process_rebuild(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, bool $write, string $reason): array {
        $limit = self::bounded_limit($limit, 500);
        $status = self::status($courseid, $unitcode, $frameworkid, min($limit, 300));
        if (($status['status'] ?? '') === 'blocked') {
            return self::empty_rebuild_result($courseid, $unitcode, $frameworkid, $userid, $limit, $write,
                $status['findings'] ?? []);
        }

        $userids = $userid > 0 ? [$userid] : self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $summary = [
            'learners' => count($userids),
            'targets_seen' => 0,
            'created' => 0,
            'changed' => 0,
            'metadata_refreshed' => 0,
            'unchanged' => 0,
            'manual_overrides' => 0,
            'no_evidence' => 0,
            'skipped_unenrolled' => 0,
            'applied' => 0,
            'skipped' => 0,
        ];
        $changes = [];

        foreach ($userids as $uid) {
            if ($courseid > 0) {
                try {
                    evidence_guard::assert_user_enrolled_for_course((int)$uid, $courseid);
                } catch (\invalid_parameter_exception $e) {
                    $summary['skipped_unenrolled']++;
                    $summary['skipped']++;
                    continue;
                }
            }
            $targets = self::scoped_targets((int)$uid, $courseid, $unitcode, $frameworkid, $limit);
            foreach ($targets as $target) {
                $summary['targets_seen']++;
                $comparison = self::state_comparison((int)$uid, $target, $courseid, $unitcode);
                $bucket = self::summary_bucket($comparison['status']);
                if (isset($summary[$bucket])) {
                    $summary[$bucket]++;
                }
                if (in_array($comparison['status'], ['created', 'changed', 'metadata_missing'], true)) {
                    $changes[] = $comparison;
                    if ($write) {
                        repository::upsert_state((int)$uid, $target['targettype'], (int)$target['targetid'],
                            $comparison['calculated_state']);
                        $summary['applied']++;
                    }
                } else if ($write) {
                    $summary['skipped']++;
                }
            }
        }

        return [
            'type' => 'CupkpMasteryStateRebuildResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => 'processed',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'changes' => array_slice($changes, 0, $limit),
            'read_only' => !$write,
            'state_changes_allowed' => $write,
            'reason' => $reason,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build one learner-target current state view.
     *
     * @param int $userid
     * @param array $target
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function state_view(int $userid, array $target, int $courseid, string $unitcode): array {
        $comparison = self::state_comparison($userid, $target, $courseid, $unitcode);
        $existing = $comparison['stored_state'];
        $calculated = $comparison['calculated_state'];
        $state = $existing ?: (object)$calculated;
        $confidence = self::confidence_view($state, $calculated);
        $evidence = $comparison['evidence_summary'];

        return [
            'type' => self::learner_state_type($target['targettype']),
            'userid' => $userid,
            'target' => [
                'type' => $target['targettype'],
                'id' => (int)$target['targetid'],
                'externalid' => (string)($target['externalid'] ?? ''),
                'title' => (string)($target['title'] ?? ''),
                'frameworkid' => (int)($target['frameworkid'] ?? 0),
            ],
            'mastery' => [
                'score' => round((float)($state->masteryscore ?? 0), 5),
                'state' => (string)($state->masterystate ?? $calculated['masterystate']),
                'strong' => self::is_strong_state($target['targettype'],
                    (string)($state->masterystate ?? $calculated['masterystate'])),
            ],
            'confidence' => $confidence,
            'status' => $comparison['status'],
            'trend' => (string)($state->trend ?? $comparison['trend']),
            'policyversion' => (string)($state->policyversion ?? $calculated['policyversion']),
            'ruleversion' => (string)($state->ruleversion ?? $calculated['ruleversion']),
            'evidence' => $evidence,
            'calculatedtime' => (int)($state->calculatedtime ?? $calculated['calculatedtime']),
            'manualoverride' => !empty($state->manualoverride),
            'rebuild' => [
                'needed' => in_array($comparison['status'], ['created', 'changed', 'metadata_missing'], true),
                'reason' => $comparison['reason'],
            ],
            'calculated' => [
                'masteryscore' => (float)$calculated['masteryscore'],
                'masterystate' => (string)$calculated['masterystate'],
                'confidence' => (float)$calculated['confidence'],
                'ruleversion' => (string)$calculated['ruleversion'],
                'policyversion' => (string)$calculated['policyversion'],
                'evidencehash' => (string)$calculated['evidencehash'],
            ],
        ];
    }

    /**
     * Compare stored and rebuilt current state for one target.
     *
     * @param int $userid
     * @param array $target
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function state_comparison(int $userid, array $target, int $courseid, string $unitcode): array {
        $events = self::evidence_for_target($userid, $target['targettype'], (int)$target['targetid'], $courseid,
            $unitcode);
        $existing = self::stored_state($userid, $target['targettype'], (int)$target['targetid']);
        $calculated = mastery_engine::calculate($target['targettype'], $events);
        $calculated['trend'] = self::trend_from_existing($existing, $calculated);
        $status = self::comparison_status($existing, $calculated, count($events));
        $reason = self::comparison_reason($status);

        return [
            'userid' => $userid,
            'targettype' => $target['targettype'],
            'targetid' => (int)$target['targetid'],
            'target_externalid' => (string)($target['externalid'] ?? ''),
            'target_title' => (string)($target['title'] ?? ''),
            'status' => $status,
            'reason' => $reason,
            'trend' => $calculated['trend'],
            'current_state' => $existing ? (string)$existing->masterystate : '',
            'current_score' => $existing ? (float)$existing->masteryscore : null,
            'proposed_state' => (string)$calculated['masterystate'],
            'proposed_score' => (float)$calculated['masteryscore'],
            'proposed_confidence' => (float)$calculated['confidence'],
            'stored_state' => $existing,
            'calculated_state' => $calculated,
            'evidence_summary' => self::evidence_summary($events, $calculated),
        ];
    }

    /**
     * Return mapped targets plus parent targets for a learner/scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function scoped_targets(int $userid, int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $targets = [];
        $scope = self::object_scope_where($courseid, $unitcode, $frameworkid);
        $maps = $DB->get_records_sql(
            "SELECT m.id, m.targettype, m.targetid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$scope['where']}
           ORDER BY m.targettype ASC, m.targetid ASC",
            $scope['params'],
            0,
            $limit * 4
        );
        foreach ($maps as $map) {
            self::add_target($targets, (string)$map->targettype, (int)$map->targetid, $frameworkid);
        }

        if ($userid > 0) {
            $where = 'userid = :userid';
            $params = ['userid' => $userid];
            if ($courseid > 0) {
                $where .= ' AND courseid = :courseid';
                $params['courseid'] = $courseid;
            }
            if ($unitcode !== '') {
                $where .= ' AND unitcode = :unitcode';
                $params['unitcode'] = $unitcode;
            }
            $events = $DB->get_records_select('flwcupkp_evidence', $where, $params,
                'targettype ASC, targetid ASC', 'id, targettype, targetid', 0, $limit * 4);
            foreach ($events as $event) {
                self::add_target($targets, (string)$event->targettype, (int)$event->targetid, $frameworkid);
            }

            $states = $DB->get_records('flwcupkp_state', ['userid' => $userid], 'targettype ASC, targetid ASC',
                'id, targettype, targetid');
            foreach ($states as $state) {
                self::add_target($targets, (string)$state->targettype, (int)$state->targetid, $frameworkid);
            }
        }

        self::expand_parent_targets($targets, $frameworkid);
        return array_slice(array_values($targets), 0, $limit);
    }

    /**
     * Add a target row if valid and in framework scope.
     *
     * @param array $targets
     * @param string $targettype
     * @param int $targetid
     * @param int $frameworkid
     */
    private static function add_target(array &$targets, string $targettype, int $targetid, int $frameworkid = 0): void {
        $target = self::target_record($targettype, $targetid);
        if (!$target) {
            return;
        }
        if ($frameworkid > 0 && (int)($target->frameworkid ?? 0) !== $frameworkid) {
            return;
        }
        $key = $targettype . ':' . $targetid;
        $targets[$key] = [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'externalid' => (string)($target->externalid ?? ''),
            'title' => (string)($target->title ?? ''),
            'frameworkid' => (int)($target->frameworkid ?? 0),
        ];
    }

    /**
     * Include UP and competency parent targets.
     *
     * @param array $targets
     * @param int $frameworkid
     */
    private static function expand_parent_targets(array &$targets, int $frameworkid): void {
        global $DB;

        $keys = array_keys($targets);
        foreach ($keys as $key) {
            $target = $targets[$key];
            if ($target['targettype'] === 'kp') {
                $maps = $DB->get_records('flwcupkp_up_kp', ['kpid' => $target['targetid']]);
                foreach ($maps as $map) {
                    self::add_target($targets, 'up', (int)$map->upid, $frameworkid);
                }
            }
        }
        $keys = array_keys($targets);
        foreach ($keys as $key) {
            $target = $targets[$key];
            if ($target['targettype'] === 'up') {
                $maps = $DB->get_records('flwcupkp_comp_up', ['upid' => $target['targetid']]);
                foreach ($maps as $map) {
                    self::add_target($targets, 'competency', (int)$map->competencyid, $frameworkid);
                }
            }
        }
    }

    /**
     * Return a target record by type.
     *
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function target_record(string $targettype, int $targetid): ?\stdClass {
        global $DB;

        try {
            $table = evidence_guard::target_table($targettype);
        } catch (\Throwable $e) {
            return null;
        }
        $record = $DB->get_record($table, ['id' => $targetid], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Evidence rows for a learner-target in scope.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function evidence_for_target(int $userid, string $targettype, int $targetid, int $courseid,
            string $unitcode): array {
        global $DB;

        $where = 'userid = :userid AND targettype = :targettype AND targetid = :targetid';
        $params = [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        return array_values($DB->get_records_select('flwcupkp_evidence', $where, $params,
            'timecreated ASC, id ASC'));
    }

    /**
     * Stored state row for a learner-target.
     *
     * @param int $userid
     * @param string $targettype
     * @param int $targetid
     * @return \stdClass|null
     */
    private static function stored_state(int $userid, string $targettype, int $targetid): ?\stdClass {
        global $DB;

        $record = $DB->get_record('flwcupkp_state', [
            'userid' => $userid,
            'targettype' => $targettype,
            'targetid' => $targetid,
        ], '*', IGNORE_MISSING);
        return $record ?: null;
    }

    /**
     * Current cache freshness status.
     *
     * @param \stdClass|null $existing
     * @param array $calculated
     * @param int $eventcount
     * @return string
     */
    private static function comparison_status(?\stdClass $existing, array $calculated, int $eventcount): string {
        if ($existing && !empty($existing->manualoverride)) {
            return 'manual_override';
        }
        if (!$existing && $eventcount === 0) {
            return 'no_evidence';
        }
        if (!$existing) {
            return 'created';
        }
        if (self::state_differs($existing, $calculated)) {
            return 'changed';
        }
        if (self::metadata_missing($existing)) {
            return 'metadata_missing';
        }
        return 'current';
    }

    /**
     * Whether stored core state differs from fresh calculation.
     *
     * @param \stdClass $existing
     * @param array $calculated
     * @return bool
     */
    private static function state_differs(\stdClass $existing, array $calculated): bool {
        return (string)$existing->masterystate !== (string)$calculated['masterystate'] ||
            abs((float)$existing->masteryscore - (float)$calculated['masteryscore']) > 0.00001 ||
            abs((float)$existing->confidence - (float)$calculated['confidence']) > 0.00001 ||
            (int)$existing->evidencecount !== (int)$calculated['evidencecount'] ||
            (string)($existing->ruleversion ?? '') !== (string)$calculated['ruleversion'] ||
            (string)($existing->policyversion ?? '') !== (string)$calculated['policyversion'] ||
            (string)($existing->evidencehash ?? '') !== (string)$calculated['evidencehash'];
    }

    /**
     * Whether E2 snapshot cache fields are missing.
     *
     * @param \stdClass $existing
     * @return bool
     */
    private static function metadata_missing(\stdClass $existing): bool {
        return empty($existing->policyversion) ||
            empty($existing->evidencehash) ||
            empty($existing->evidenceidsjson) ||
            empty($existing->calculatedtime);
    }

    /**
     * Human-readable reason for comparison status.
     *
     * @param string $status
     * @return string
     */
    private static function comparison_reason(string $status): string {
        $reasons = [
            'manual_override' => 'manual override preserved',
            'no_evidence' => 'no evidence or stored cache row',
            'created' => 'no current cache row exists',
            'changed' => 'stored state differs from rebuilt state',
            'metadata_missing' => 'stored state lacks E2 snapshot metadata',
            'current' => 'stored cache matches rebuilt state',
        ];
        return $reasons[$status] ?? $status;
    }

    /**
     * Summary bucket for rebuild status.
     *
     * @param string $status
     * @return string
     */
    private static function summary_bucket(string $status): string {
        $map = [
            'manual_override' => 'manual_overrides',
            'metadata_missing' => 'metadata_refreshed',
            'current' => 'unchanged',
        ];
        return $map[$status] ?? $status;
    }

    /**
     * Compare score direction against stored row.
     *
     * @param \stdClass|null $existing
     * @param array $calculated
     * @return string
     */
    private static function trend_from_existing(?\stdClass $existing, array $calculated): string {
        if (!$existing) {
            return ((int)$calculated['evidencecount'] > 0) ? 'new' : 'flat';
        }
        $old = (float)($existing->masteryscore ?? 0);
        $new = (float)($calculated['masteryscore'] ?? 0);
        if ($new > $old + 0.00001) {
            return 'up';
        }
        if ($new < $old - 0.00001) {
            return 'down';
        }
        return (string)($existing->masterystate ?? '') === (string)($calculated['masterystate'] ?? '') ?
            'flat' : 'state_changed';
    }

    /**
     * Compact confidence view.
     *
     * @param \stdClass $state
     * @param array $calculated
     * @return array
     */
    private static function confidence_view(\stdClass $state, array $calculated): array {
        $score = round((float)($state->confidence ?? $calculated['confidence']), 5);
        return [
            'score' => $score,
            'label' => self::confidence_label($score),
            'policyversion' => mastery_engine::CONFIDENCE_POLICY_VERSION,
            'stored_score' => round((float)($state->confidence ?? 0), 5),
            'rebuilt_score' => round((float)$calculated['confidence'], 5),
            'inputs' => $calculated['confidence_model']['inputs'] ?? [],
        ];
    }

    /**
     * Evidence summary for display/API.
     *
     * @param array $events
     * @param array $calculated
     * @return array
     */
    private static function evidence_summary(array $events, array $calculated): array {
        $summary = [
            'count' => count($events),
            'ids' => $calculated['evidenceids'] ?? [],
            'hash' => (string)($calculated['evidencehash'] ?? ''),
            'history_v1' => 0,
            'legacy_direct' => 0,
            'inconclusive' => 0,
            'latest' => null,
            'by_result_state' => [],
            'by_performance_mode' => [],
        ];
        foreach ($events as $event) {
            if ((string)($event->provenance ?? '') === history_evidence_adapter::PROVENANCE) {
                $summary['history_v1']++;
            } else {
                $summary['legacy_direct']++;
            }
            $semantics = self::rubric_semantics($event);
            $result = (string)($semantics['result_state'] ?? '');
            $mode = (string)($semantics['performance_mode'] ?? '');
            if ($result === 'inconclusive') {
                $summary['inconclusive']++;
            }
            if ($result !== '') {
                $summary['by_result_state'][$result] = ($summary['by_result_state'][$result] ?? 0) + 1;
            }
            if ($mode !== '') {
                $summary['by_performance_mode'][$mode] = ($summary['by_performance_mode'][$mode] ?? 0) + 1;
            }
            if (!$summary['latest'] || (int)$event->timecreated > (int)$summary['latest']['timecreated']) {
                $summary['latest'] = [
                    'id' => (int)($event->id ?? 0),
                    'evidencetype' => (string)($event->evidencetype ?? ''),
                    'score' => round((float)($event->normalizedscore ?? 0), 5),
                    'confidence' => round((float)($event->confidence ?? 0), 5),
                    'strength' => (string)($event->evidencestrength ?? ''),
                    'provenance' => (string)($event->provenance ?? ''),
                    'timecreated' => (int)($event->timecreated ?? 0),
                ];
            }
        }
        return $summary;
    }

    /**
     * Extract C3B semantics from rubric JSON.
     *
     * @param \stdClass $event
     * @return array
     */
    private static function rubric_semantics(\stdClass $event): array {
        $rubric = json_decode((string)($event->rubricjson ?? ''), true);
        if (!is_array($rubric)) {
            return [];
        }
        $semantics = $rubric['cupkp_c3b_semantics'] ?? [];
        return is_array($semantics) ? $semantics : [];
    }

    /**
     * Learner summary from rows.
     *
     * @param array $states
     * @return array
     */
    private static function learner_summary(array $states): array {
        $summary = [
            'states' => count($states),
            'kp' => 0,
            'up' => 0,
            'competency' => 0,
            'strong_states' => 0,
            'low_confidence' => 0,
            'stale_or_missing_cache' => 0,
            'manual_overrides' => 0,
            'history_v1_evidence' => 0,
        ];
        foreach ($states as $state) {
            $type = $state['target']['type'];
            if (isset($summary[$type])) {
                $summary[$type]++;
            }
            if (!empty($state['mastery']['strong'])) {
                $summary['strong_states']++;
            }
            if (in_array($state['confidence']['label'], ['none', 'low'], true)) {
                $summary['low_confidence']++;
            }
            if (in_array($state['status'], ['created', 'changed', 'metadata_missing'], true)) {
                $summary['stale_or_missing_cache']++;
            }
            if (!empty($state['manualoverride'])) {
                $summary['manual_overrides']++;
            }
            $summary['history_v1_evidence'] += (int)$state['evidence']['history_v1'];
        }
        return $summary;
    }

    /**
     * Learner IDs for rebuild scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function learner_ids_for_scope(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $userids = [];

        if ($courseid > 0) {
            foreach (self::course_learner_ids($courseid, $limit) as $userid) {
                self::add_learner_id($userids, $userid);
            }
        }

        $where = '1=1';
        $params = [];
        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        $records = $DB->get_records_select('flwcupkp_evidence', $where, $params, 'userid ASC',
            'DISTINCT userid', 0, $limit);
        foreach ($records as $record) {
            self::add_learner_id($userids, (int)$record->userid);
        }

        $targets = self::scoped_targets(0, $courseid, $unitcode, $frameworkid, $limit);
        foreach ($targets as $target) {
            $records = $DB->get_records('flwcupkp_state', [
                'targettype' => $target['targettype'],
                'targetid' => (int)$target['targetid'],
            ], 'userid ASC', 'DISTINCT userid', 0, $limit);
            foreach ($records as $record) {
                self::add_learner_id($userids, (int)$record->userid);
            }
        }

        if ($courseid === 0 && $unitcode === '' && $frameworkid === 0) {
            $records = $DB->get_records('flwcupkp_state', [], 'userid ASC', 'DISTINCT userid', 0, $limit);
            foreach ($records as $record) {
                self::add_learner_id($userids, (int)$record->userid);
            }
        }

        sort($userids, SORT_NUMERIC);
        return array_slice(array_values($userids), 0, $limit);
    }

    /**
     * Add a valid learner ID to a keyed unique list.
     *
     * @param array $userids
     * @param int $userid
     */
    private static function add_learner_id(array &$userids, int $userid): void {
        if ($userid <= 0) {
            return;
        }
        $userids[$userid] = $userid;
    }

    /**
     * Enrolled learner IDs for a course.
     *
     * @param int $courseid
     * @param int $limit
     * @return array
     */
    private static function course_learner_ids(int $courseid, int $limit): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname ASC, u.firstname ASC, u.id ASC', 0,
            self::bounded_limit($limit, 500), true);
        return array_values(array_map(static function($user): int {
            return (int)$user->id;
        }, $users));
    }

    /**
     * Object scope clause.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function object_scope_where(int $courseid, string $unitcode, int $frameworkid): array {
        $where = '1=1';
        $params = [];
        if ($courseid > 0) {
            $where .= ' AND (o.courseid = :courseid OR o.courseid IS NULL OR o.courseid = 0)';
            $params['courseid'] = $courseid;
        }
        if ($unitcode !== '') {
            $where .= ' AND o.unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND o.frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return ['where' => $where, 'params' => $params];
    }

    /**
     * Status criteria.
     *
     * @param array $management
     * @param array $evidence
     * @param array $schema
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $management, array $evidence, array $schema, array $files,
            array $surface): array {
        return [
            'management_v1_consumed' => self::criterion(
                'management_v1_consumed',
                ($management['status'] ?? '') === 'frozen',
                'E2 consumes the frozen Management V1 surface.'
            ),
            'history_evidence_adapter_consumed' => self::criterion(
                'history_evidence_adapter_consumed',
                ($evidence['status'] ?? '') === 'ready' &&
                    ($evidence['contract']['version'] ?? '') === history_evidence_adapter::CONTRACT_VERSION,
                'E2 consumes E1 derived evidence, not raw Moodle logs.'
            ),
            'state_cache_metadata_present' => self::criterion(
                'state_cache_metadata_present',
                $schema['valid'],
                'flwcupkp_state stores E2 snapshot metadata.'
            ),
            'state_surfaces_present' => self::criterion(
                'state_surfaces_present',
                $files['valid'] && $surface['valid'],
                'Admin page, CLI, service, and web-service methods are present.'
            ),
            'learner_state_types_supported' => self::criterion(
                'learner_state_types_supported',
                true,
                'LearnerCompetencyState, LearnerKPState, and LearnerUPState are exposed.'
            ),
            'mastery_confidence_separated' => self::criterion(
                'mastery_confidence_separated',
                true,
                'Mastery score/state, confidence, grade, and retention are separate concerns.'
            ),
        ];
    }

    /**
     * One readiness criterion.
     *
     * @param string $key
     * @param bool $pass
     * @param string $detail
     * @return array
     */
    private static function criterion(string $key, bool $pass, string $detail): array {
        return [
            'key' => $key,
            'status' => $pass ? 'pass' : 'fail',
            'pass' => $pass,
            'detail' => $detail,
        ];
    }

    /**
     * Summarize criteria.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $passed = 0;
        foreach ($criteria as $criterion) {
            if (!empty($criterion['pass'])) {
                $passed++;
            }
        }
        return [
            'total' => count($criteria),
            'passed' => $passed,
            'failed' => count($criteria) - $passed,
        ];
    }

    /**
     * Schema status for E2 fields.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $required = ['policyversion', 'trend', 'evidencehash', 'evidenceidsjson', 'calculatedtime'];
        $columns = $DB->get_columns('flwcupkp_state');
        $present = [];
        $missing = [];
        foreach ($required as $field) {
            $present[$field] = isset($columns[$field]);
            if (!$present[$field]) {
                $missing[] = $field;
            }
        }
        return [
            'valid' => empty($missing),
            'table' => 'flwcupkp_state',
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * File status for E2.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'mastery_state.php',
            'cli/mastery_state.php',
            'classes/local/mastery_state_service.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $exists = file_exists($CFG->dirroot . '/local/flwcupkp/' . $file);
            $present[$file] = $exists;
            if (!$exists) {
                $missing[] = $file;
            }
        }
        return [
            'valid' => empty($missing),
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * Method status for E2.
     *
     * @return array
     */
    private static function surface_status(): array {
        $methods = [
            self::class . '::status' => method_exists(self::class, 'status'),
            self::class . '::current_learner_state' => method_exists(self::class, 'current_learner_state'),
            self::class . '::class_summary' => method_exists(self::class, 'class_summary'),
            self::class . '::preview_rebuild' => method_exists(self::class, 'preview_rebuild'),
            self::class . '::apply_rebuild' => method_exists(self::class, 'apply_rebuild'),
            mastery_engine::class . '::calculate' => method_exists(mastery_engine::class, 'calculate'),
            repository::class . '::upsert_state' => method_exists(repository::class, 'upsert_state'),
        ];
        $missing = array_keys(array_filter($methods, static function(bool $present): bool {
            return !$present;
        }));
        return [
            'valid' => empty($missing),
            'methods' => $methods,
            'missing_methods' => $missing,
        ];
    }

    /**
     * Cache summary counts.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function cache_summary(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $targets = self::scoped_targets(0, $courseid, $unitcode, $frameworkid, $limit);
        $statecount = 0;
        $metadataready = 0;
        $historyevidence = $DB->count_records('flwcupkp_evidence', [
            'provenance' => history_evidence_adapter::PROVENANCE,
        ]);

        if (!$targets) {
            $statecount = (int)$DB->count_records('flwcupkp_state');
            $metadataready = (int)$DB->count_records_select('flwcupkp_state',
                "policyversion IS NOT NULL AND policyversion <> ''
                 AND evidencehash IS NOT NULL AND evidencehash <> ''
                 AND evidenceidsjson IS NOT NULL AND evidenceidsjson <> ''
                 AND calculatedtime IS NOT NULL AND calculatedtime > 0");
        } else {
            foreach ($targets as $target) {
                $rows = $DB->get_records('flwcupkp_state', [
                    'targettype' => $target['targettype'],
                    'targetid' => $target['targetid'],
                ]);
                foreach ($rows as $row) {
                    $statecount++;
                    if (!self::metadata_missing($row)) {
                        $metadataready++;
                    }
                }
            }
        }

        return [
            'target_scope_count' => count($targets),
            'state_rows' => $statecount,
            'metadata_ready_rows' => $metadataready,
            'metadata_missing_rows' => max(0, $statecount - $metadataready),
            'history_v1_evidence_rows' => (int)$historyevidence,
        ];
    }

    /**
     * Dependency summary.
     *
     * @param array $dependency
     * @return array
     */
    private static function dependency_summary(array $dependency): array {
        return [
            'type' => $dependency['type'] ?? '',
            'gate' => $dependency['gate'] ?? '',
            'status' => $dependency['status'] ?? 'unknown',
            'contract' => $dependency['contract']['version'] ?? ($dependency['contract'] ?? ''),
            'next_allowed_gate' => $dependency['next_allowed_gate'] ?? '',
            'findings' => count($dependency['findings'] ?? []),
        ];
    }

    /**
     * Findings from failed criteria/dependencies.
     *
     * @param array $criteria
     * @param array $dependencies
     * @return array
     */
    private static function status_findings(array $criteria, array $dependencies): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (empty($criterion['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'code' => $criterion['key'] . '_failed',
                    'message' => $criterion['detail'],
                ];
            }
        }
        foreach ($dependencies as $dependency) {
            foreach (($dependency['findings'] ?? []) as $finding) {
                $findings[] = [
                    'severity' => strtolower((string)($finding['severity'] ?? 'warning')),
                    'code' => (string)($finding['code'] ?? 'dependency_finding'),
                    'message' => (string)($finding['message'] ?? json_encode($finding)),
                ];
            }
        }
        return $findings;
    }

    /**
     * Safe dependency wrapper.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            $status = $callback();
            return is_array($status) ? $status : [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'invalid_status_payload',
                    'message' => 'Dependency status did not return an array.',
                ]],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_status_exception',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Empty rebuild result when status is blocked.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $userid
     * @param int $limit
     * @param bool $write
     * @param array $findings
     * @return array
     */
    private static function empty_rebuild_result(int $courseid, string $unitcode, int $frameworkid, int $userid,
            int $limit, bool $write, array $findings): array {
        return [
            'type' => 'CupkpMasteryStateRebuildResult',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'status' => 'blocked',
            'mode' => $write ? 'apply' : 'preview',
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'userid' => $userid,
                'limit' => $limit,
            ],
            'summary' => [
                'learners' => 0,
                'targets_seen' => 0,
                'created' => 0,
                'changed' => 0,
                'metadata_refreshed' => 0,
                'unchanged' => 0,
                'manual_overrides' => 0,
                'no_evidence' => 0,
                'skipped_unenrolled' => 0,
                'applied' => 0,
                'skipped' => 0,
            ],
            'changes' => [],
            'findings' => $findings,
            'read_only' => !$write,
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return learner state type label.
     *
     * @param string $targettype
     * @return string
     */
    private static function learner_state_type(string $targettype): string {
        $map = [
            'kp' => 'LearnerKPState',
            'up' => 'LearnerUPState',
            'competency' => 'LearnerCompetencyState',
        ];
        return $map[$targettype] ?? 'LearnerTargetState';
    }

    /**
     * Whether a target state is strong.
     *
     * @param string $targettype
     * @param string $state
     * @return bool
     */
    private static function is_strong_state(string $targettype, string $state): bool {
        return in_array($state, self::STRONG_STATES[$targettype] ?? [], true);
    }

    /**
     * Confidence label.
     *
     * @param float $score
     * @return string
     */
    private static function confidence_label(float $score): string {
        if ($score >= 0.75) {
            return 'high';
        }
        if ($score >= 0.50) {
            return 'medium';
        }
        if ($score > 0) {
            return 'low';
        }
        return 'none';
    }

    /**
     * Bound result limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }
}
