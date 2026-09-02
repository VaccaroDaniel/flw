<?php
// Program 3 Gate A5 Continuous Adaptive Path Engine.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts the frozen A4B resolution into controlled, versioned recommendations.
 */
final class adaptive_path_engine_service {
    /** Program 3 continuous adaptive path gate. */
    public const GATE = 'P3_A5';

    /** Frozen A5 consumer contract. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_CONTINUOUS_ADAPTIVE_PATH_ENGINE_V1';

    /** Version of the recommendation persistence policy. */
    public const ADAPTIVE_PATH_POLICY_VERSION = 'cupkp-continuous-adaptive-path-engine-v1';

    /** Next allowed gate after A5. */
    public const NEXT_ALLOWED_GATE = 'A5B';

    /** Supported adaptive actions. */
    public const ACTIONS = [
        'ADVANCE',
        'SKIP',
        'EXTRA_PRACTICE',
        'REMEDIATION',
        'REVIEW',
        'RETRY',
        'REASSESS',
        'REPRIORITIZE',
    ];

    /** Candidate-action to A5 action mapping. */
    private const CANDIDATE_ACTIONS = [
        'introduce' => 'ADVANCE',
        'confirm' => 'SKIP',
        'practice' => 'EXTRA_PRACTICE',
        'relearn' => 'REMEDIATION',
        'remediate' => 'REMEDIATION',
        'review' => 'REVIEW',
        'retry' => 'RETRY',
        'reassess' => 'REASSESS',
        'repair_prerequisite' => 'REPRIORITIZE',
    ];

    /** Decision-code fallback to A5 action mapping. */
    private const DECISION_ACTIONS = [
        'ADVANCE_READY' => 'ADVANCE',
        'INTRODUCE_TARGET' => 'ADVANCE',
        'REVIEW_REQUIRED' => 'REVIEW',
        'RELEARNING_REQUIRED' => 'REMEDIATION',
        'REMEDIATION_REQUIRED' => 'REMEDIATION',
        'RETRY_RECOMMENDED' => 'RETRY',
        'REASSESSMENT_RECOMMENDED' => 'REASSESS',
        'PLACEMENT_REQUIRED' => 'REASSESS',
        'DIAGNOSTIC_INCOMPLETE' => 'REASSESS',
        'PLACEMENT_REVIEW' => 'REASSESS',
        'PREREQUISITE_REQUIRED' => 'REPRIORITIZE',
        'GOAL_REQUIRED' => 'REPRIORITIZE',
        'GOAL_REVIEW' => 'REPRIORITIZE',
        'FALLBACK_TEACHER_REVIEW' => 'REPRIORITIZE',
    ];

    /** Fields required on the recommendation table for controlled A5 writes. */
    private const REQUIRED_RECOMMENDATION_FIELDS = [
        'courseid',
        'unitcode',
        'cmid',
        'recommendationtype',
        'policyversion',
        'sourcehash',
        'decisioncode',
    ];

    /**
     * Return the frozen A5 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpContinuousAdaptivePathEngineContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                candidate_activity_resolution_service::CONTRACT_VERSION,
                goal_gap_path_service::CONTRACT_VERSION,
                adaptive_decision_policy_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'policy_version' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'pipeline' => [
                'Program 2 event',
                'Program 3 evidence',
                'learner-state update',
                'retention update',
                'goal gap',
                'adaptive decision',
                'candidate eligibility',
                'controlled recommendation',
            ],
            'actions' => self::ACTIONS,
            'write_boundary' => [
                'flwcupkp_recommend',
                'flwcupkp_audit',
            ],
            'persisted_decision_fields' => [
                'goal version',
                'curriculum version',
                'state snapshot',
                'evidence policy',
                'mastery policy',
                'retention policy',
                'adaptive policy',
                'selected target/activity',
                'candidate summary/hash',
                'reason codes',
                'timestamp',
            ],
            'hard_invariants' => [
                'inaccessible_activity_can_never_become_next',
                'unchanged_source_hash_creates_no_duplicate_recommendation',
                'a5_never_mutates_history_evidence_mastery_retention_placement_or_goal_state',
                'only_a5_owned_recommendations_are_superseded',
            ],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'history_v1_source_mutation',
                'evidence_mutation',
                'mastery_state_mutation',
                'retention_state_mutation',
                'placement_state_mutation',
                'learning_goal_mutation',
                'curriculum_or_mapping_mutation',
                'activity_unlocking_or_availability_override',
                'automatic_course_module_completion',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return the visible A5 policy.
     *
     * @return array
     */
    public static function policy(): array {
        return [
            'version' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'source_resolution_policy' => candidate_activity_resolution_service::RESOLUTION_POLICY_VERSION,
            'actions' => self::ACTIONS,
            'action_mapping' => [
                'candidate_actions' => self::CANDIDATE_ACTIONS,
                'decision_codes' => self::DECISION_ACTIONS,
                'default_with_eligible_activity' => 'EXTRA_PRACTICE',
                'default_without_eligible_activity' => 'REPRIORITIZE',
            ],
            'refresh' => [
                'mode' => 'controlled_apply',
                'idempotency_key' => 'policyversion + sourcehash + learner scope',
                'unchanged' => 'return current recommendation without writing',
                'changed' => 'supersede prior A5 recommendation and insert an immutable decision snapshot',
            ],
            'selection' => [
                'next_activity' => 'Exactly the eligible activity selected by the frozen A4B resolver.',
                'diagnostic' => 'Persist an action without objectid/cmid when A4B has no eligible activity.',
                'fallback' => 'Preserve A4B fallback selection and reason codes.',
            ],
            'write_boundary' => self::contract()['write_boundary'],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Readiness and bounded operational status for A5.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0,
            int $limit = 100): array {
        global $DB;

        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 300);
        $a4b = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return candidate_activity_resolution_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $schema = self::schema_status();
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = self::criteria($a4b, $schema, $files, $surface);
        $summary = self::criteria_summary($criteria);
        $scopeparams = self::scope_params($courseid, $unitcode);
        [$scopesql, $params] = self::scope_sql($scopeparams);
        $params['policyversion'] = self::ADAPTIVE_PATH_POLICY_VERSION;
        $current = $schema['ready'] ? (int)$DB->count_records_select('flwcupkp_recommend',
            "policyversion = :policyversion AND status = 'recommended'{$scopesql}", $params) : 0;
        $historical = $schema['ready'] ? (int)$DB->count_records_select('flwcupkp_recommend',
            "policyversion = :policyversion{$scopesql}", $params) : 0;

        return [
            'type' => 'CupkpContinuousAdaptivePathEngineStatus',
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
            'dependency' => self::dependency_summary($a4b),
            'schema' => $schema,
            'files' => $files,
            'surface' => $surface,
            'policy' => self::policy(),
            'recommendations' => [
                'current' => $current,
                'historical' => $historical,
                'superseded' => max(0, $historical - $current),
            ],
            'findings' => self::status_findings($criteria, $a4b),
            'preview_is_read_only' => true,
            'controlled_apply_supported' => true,
            'write_boundary' => self::contract()['write_boundary'],
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => true,
            'continuous_adaptation_allowed' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Preview one learner's current adaptive route and persistence status.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function learner_path(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid > 0) {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 500);
        $resolution = candidate_activity_resolution_service::learner_resolution(
            $userid, $courseid, $unitcode, $frameworkid, $limit
        );
        $recommendation = self::recommendation_from_resolution($resolution);
        $recommendation = staff_intelligence_service::apply_to_recommendation(
            $userid, $courseid, $unitcode, $recommendation, $resolution
        );
        $current = self::current_a5_record($userid, $courseid, $unitcode);
        $persistencestatus = self::persistence_status($current, (string)$recommendation['sourcehash']);

        return [
            'type' => 'CupkpLearnerContinuousAdaptivePath',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policy_version' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'path_status' => $recommendation['path_status'],
            'recommendation_status' => $persistencestatus,
            'recommendation' => $recommendation,
            'current_persisted_recommendation' => $current ? self::serialize_recommendation($current) : null,
            'source_activity_resolution' => $resolution,
            'explainability' => [
                'adaptive_path_hash' => $recommendation['sourcehash'],
                'resolution_hash' => $resolution['explainability']['resolution_hash'] ?? '',
                'a4_path_hash' => $resolution['explainability']['a4_path_hash'] ?? '',
                'reason_codes' => $recommendation['reason_codes'],
                'policy_versions' => $recommendation['snapshot']['policy_versions'],
                'hard_invariants' => self::contract()['hard_invariants'],
                'non_actions' => [
                    'no_history_v1_source_mutation',
                    'no_evidence_mastery_retention_placement_or_goal_mutation',
                    'no_activity_unlocking_or_completion',
                ],
            ],
            'read_only' => true,
            'apply_supported' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'continuous_adaptation_allowed' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return current A5 recommendations for a learner.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $limit
     * @return array
     */
    public static function current_recommendations(int $userid, int $courseid = 0, string $unitcode = '',
            int $limit = 20): array {
        global $DB;

        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $params = [
            'userid' => $userid,
            'policyversion' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'status' => 'recommended',
        ];
        [$scopesql, $scopeparams] = self::scope_sql(self::scope_params($courseid, $unitcode));
        $params += $scopeparams;
        $rows = $DB->get_records_select('flwcupkp_recommend',
            "userid = :userid AND policyversion = :policyversion AND status = :status{$scopesql}",
            $params, 'timemodified DESC, id DESC', '*', 0, self::bounded_limit($limit, 100));

        return array_map([self::class, 'serialize_recommendation'], array_values($rows));
    }

    /**
     * Apply one learner's current A5 recommendation if its source hash changed.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @param string $reason
     * @return array
     */
    public static function apply_learner_path(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100, string $reason = ''): array {
        global $DB, $USER;

        $preview = self::learner_path($userid, $courseid, $unitcode, $frameworkid, $limit);
        $recommendation = $preview['recommendation'];
        $current = self::current_a5_record($userid, $courseid, $unitcode);
        if ($current && hash_equals((string)$current->sourcehash, (string)$recommendation['sourcehash'])) {
            return [
                'type' => 'CupkpAdaptivePathApplyResult',
                'gate' => self::GATE,
                'status' => 'unchanged',
                'userid' => $userid,
                'recommendationid' => (int)$current->id,
                'superseded' => 0,
                'sourcehash' => (string)$current->sourcehash,
                'recommendation' => self::serialize_recommendation($current),
                'preview' => $preview,
                'write_boundary' => self::contract()['write_boundary'],
                'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $superseded = self::supersede_current_rows($userid, $courseid, $unitcode, $now);
        $record = self::recommendation_record($userid, $courseid, $unitcode, $recommendation, $now);
        $recommendationid = (int)$DB->insert_record('flwcupkp_recommend', $record);
        $auditid = repository::audit('adaptive_path_recommendation_applied', 'user', $userid, [
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policyversion' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'recommendationid' => $recommendationid,
            'sourcehash' => $recommendation['sourcehash'],
            'action' => $recommendation['action'],
            'target' => $recommendation['selected_target'],
            'activity' => $recommendation['selected_activity'],
            'reasoncodes' => $recommendation['reason_codes'],
            'operatorreason' => trim($reason),
            'superseded' => $superseded,
            'actorid' => (int)($USER->id ?? 0),
        ]);
        $transaction->allow_commit();
        $stored = $DB->get_record('flwcupkp_recommend', ['id' => $recommendationid], '*', MUST_EXIST);

        return [
            'type' => 'CupkpAdaptivePathApplyResult',
            'gate' => self::GATE,
            'status' => 'applied',
            'userid' => $userid,
            'recommendationid' => $recommendationid,
            'auditid' => $auditid,
            'superseded' => $superseded,
            'sourcehash' => $recommendation['sourcehash'],
            'recommendation' => self::serialize_recommendation($stored),
            'preview' => $preview,
            'write_boundary' => self::contract()['write_boundary'],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return class-level preview and persistence metrics.
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
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_limit($limit, 300);
        $source = candidate_activity_resolution_service::class_summary($courseid, $unitcode, $frameworkid, $limit);
        $rows = [];
        $summary = self::empty_class_summary();
        $summary['learners'] = count($source['learners'] ?? []);

        foreach (($source['learners'] ?? []) as $sourcerow) {
            $learnerid = (int)($sourcerow['userid'] ?? 0);
            if ($learnerid <= 0 || ($sourcerow['resolution_status'] ?? '') === 'skipped_unenrolled') {
                $summary['errors']++;
                continue;
            }
            try {
                $path = self::learner_path($learnerid, $courseid, $unitcode, $frameworkid, min(120, $limit));
                $status = (string)$path['recommendation_status'];
                $action = (string)($path['recommendation']['action'] ?? 'REPRIORITIZE');
                $summary['statuses'][$status] = ($summary['statuses'][$status] ?? 0) + 1;
                $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + 1;
                if (!empty($path['recommendation']['selected_activity'])) {
                    $summary['next_activity_ready']++;
                }
                if (($path['path_status'] ?? '') === 'diagnostic_required') {
                    $summary['diagnostic_required']++;
                }
                if ($status === 'refresh_required') {
                    $summary['refresh_required']++;
                } else if ($status === 'ready_to_apply') {
                    $summary['ready_to_apply']++;
                } else if ($status === 'current') {
                    $summary['current']++;
                }
                $rows[] = [
                    'userid' => $learnerid,
                    'learner' => $sourcerow['learner'] ?? ['id' => $learnerid],
                    'path_status' => $path['path_status'],
                    'recommendation_status' => $status,
                    'action' => $action,
                    'selected_target' => $path['recommendation']['selected_target'],
                    'selected_activity' => $path['recommendation']['selected_activity'],
                    'reason_codes' => $path['recommendation']['reason_codes'],
                    'sourcehash' => $path['recommendation']['sourcehash'],
                ];
            } catch (\Throwable $e) {
                $summary['errors']++;
                $rows[] = [
                    'userid' => $learnerid,
                    'learner' => $sourcerow['learner'] ?? ['id' => $learnerid],
                    'path_status' => 'error',
                    'recommendation_status' => 'error',
                    'reason' => $e->getMessage(),
                ];
            }
        }
        arsort($summary['statuses']);
        arsort($summary['actions']);

        return [
            'type' => 'CupkpClassContinuousAdaptivePathSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policy_version' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'learners' => $rows,
            'read_only' => true,
            'controlled_apply_supported' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Controlled class refresh. Each learner remains individually idempotent and audited.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @param string $reason
     * @return array
     */
    public static function apply_class_paths(int $courseid, string $unitcode = '', int $frameworkid = 0,
            int $limit = 100, string $reason = ''): array {
        $preview = self::class_summary($courseid, $unitcode, $frameworkid, $limit);
        $results = [];
        $summary = [
            'learners' => count($preview['learners']),
            'applied' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'superseded' => 0,
        ];
        foreach ($preview['learners'] as $row) {
            $userid = (int)($row['userid'] ?? 0);
            if ($userid <= 0 || ($row['recommendation_status'] ?? '') === 'error') {
                $summary['failed']++;
                continue;
            }
            try {
                $result = self::apply_learner_path($userid, $courseid, $unitcode, $frameworkid,
                    min(120, $limit), $reason);
                $summary[$result['status']] = ($summary[$result['status']] ?? 0) + 1;
                $summary['superseded'] += (int)($result['superseded'] ?? 0);
                $results[] = [
                    'userid' => $userid,
                    'status' => $result['status'],
                    'recommendationid' => (int)($result['recommendationid'] ?? 0),
                    'sourcehash' => (string)($result['sourcehash'] ?? ''),
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $results[] = [
                    'userid' => $userid,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'type' => 'CupkpAdaptivePathClassApplyResult',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'completed_with_failures' : 'completed',
            'scope' => $preview['scope'],
            'summary' => $summary,
            'results' => $results,
            'write_boundary' => self::contract()['write_boundary'],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build an A5 recommendation from the frozen A4B resolution.
     *
     * @param array $resolution
     * @return array
     */
    private static function recommendation_from_resolution(array $resolution): array {
        $path = is_array($resolution['source_initial_path'] ?? null) ? $resolution['source_initial_path'] : [];
        $target = is_array($resolution['next_target'] ?? null) ? $resolution['next_target'] : null;
        $activity = is_array($resolution['next_activity'] ?? null) ? $resolution['next_activity'] : null;
        $decision = is_array($path['explainability']['selected_a3_decision'] ?? null) ?
            $path['explainability']['selected_a3_decision'] : [];
        $candidateaction = strtolower((string)($activity['candidate_action'] ??
            self::selected_candidate_action($resolution)));
        $decisioncode = strtoupper((string)($decision['code'] ?? $decision['decision_code'] ??
            ($activity['candidate_code'] ?? '')));
        $action = self::action_for($candidateaction, $decisioncode, (bool)$activity);
        $reasoncodes = self::reason_codes($resolution, $path, $candidateaction, $decisioncode, $activity);
        $snapshot = self::persistence_snapshot($resolution, $path, $target, $activity, $reasoncodes);
        $hashpayload = [
            'policy' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'userid' => (int)($resolution['userid'] ?? 0),
            'scope' => $resolution['scope'] ?? [],
            'resolution_hash' => $resolution['explainability']['resolution_hash'] ?? '',
            'a4_path_hash' => $resolution['explainability']['a4_path_hash'] ?? '',
            'adaptive_decision_hash' => $path['explainability']['adaptive_decision_hash'] ?? '',
            'action' => $action,
            'decisioncode' => $decisioncode,
            'target' => self::selected_target_fingerprint($target),
            'activity' => self::selected_activity_fingerprint($activity),
            'reasoncodes' => $reasoncodes,
            'policyversions' => $snapshot['policy_versions'],
            'goalversion' => $snapshot['goal_version'],
            'curriculumversion' => $snapshot['curriculum_version'],
            'statesnapshot' => $snapshot['state_snapshot'],
        ];
        $sourcehash = hash('sha256', json_encode($hashpayload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $pathstatus = $activity ? 'next_activity_ready' : 'diagnostic_required';
        $reason = self::recommendation_reason($action, $target, $activity, $resolution);

        return [
            'action' => $action,
            'decision_code' => $decisioncode ?: $action,
            'path_status' => $pathstatus,
            'selected_target' => $target,
            'selected_activity' => $activity,
            'candidate_summary' => [
                'candidate_targets' => (int)($resolution['summary']['candidate_targets'] ?? 0),
                'eligible_activities' => (int)($resolution['summary']['eligible_activities'] ?? 0),
                'ineligible_activities' => (int)($resolution['summary']['ineligible_activities'] ?? 0),
                'fallback_used' => !empty($resolution['summary']['fallback_used']),
                'diagnostic_required' => !empty($resolution['summary']['diagnostic_required']),
                'resolution_hash' => (string)($resolution['explainability']['resolution_hash'] ?? ''),
            ],
            'reason' => $reason,
            'reason_codes' => $reasoncodes,
            'expected_benefit' => $activity ? 1.0 : 0.25,
            'mastery_gap' => self::mastery_gap($path),
            'snapshot' => $snapshot,
            'sourcehash' => $sourcehash,
        ];
    }

    /**
     * Persisted source and policy snapshot required by A5.
     *
     * @param array $resolution
     * @param array $path
     * @param array|null $target
     * @param array|null $activity
     * @param array $reasoncodes
     * @return array
     */
    private static function persistence_snapshot(array $resolution, array $path, ?array $target,
            ?array $activity, array $reasoncodes): array {
        $goalpayload = is_array($path['inputs']['goal'] ?? null) ? $path['inputs']['goal'] : [];
        $goal = is_array($goalpayload['goal'] ?? null) ? $goalpayload['goal'] : [];
        $scope = is_array($resolution['scope'] ?? null) ? $resolution['scope'] : [];
        $mastery = is_array($path['inputs']['mastery_summary'] ?? null) ? $path['inputs']['mastery_summary'] : [];
        $retention = is_array($path['inputs']['retention_summary'] ?? null) ? $path['inputs']['retention_summary'] : [];

        return [
            'goal_version' => [
                'goalid' => (int)($goal['id'] ?? 0),
                'currentversion' => (int)($goal['currentversion'] ?? 0),
                'activeversionid' => (int)($goal['activeversionid'] ?? 0),
                'checksum' => (string)($goal['checksum'] ?? ''),
                'goalpolicyversion' => (string)($goal['goalpolicyversion'] ?? ''),
                'status' => (string)($goal['status'] ?? ''),
            ],
            'curriculum_version' => [
                'frameworkid' => (int)($scope['frameworkid'] ?? 0),
                'courseid' => (int)($scope['courseid'] ?? 0),
                'unitcode' => (string)($scope['unitcode'] ?? ''),
                'management_contract' => management_v1_contract::CONTRACT_VERSION,
                'foundation_contract' => foundation_v1_contract::CONTRACT_VERSION,
                'content_mapping_contract' => content_evidence_mapping_contract::CONTRACT_VERSION,
            ],
            'state_snapshot' => [
                'mastery_summary' => $mastery,
                'retention_summary' => $retention,
                'source_snapshots' => $path['explainability']['source_snapshots'] ?? [],
            ],
            'policy_versions' => [
                'history_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'history_evidence_adapter' => history_evidence_adapter::CONTRACT_VERSION,
                'evidence_policy' => evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
                'mastery_policy' => mastery_engine::POLICY_VERSION,
                'confidence_policy' => mastery_engine::CONFIDENCE_POLICY_VERSION,
                'retention_policy' => retention_review_service::RETENTION_POLICY_VERSION,
                'adaptive_policy' => adaptive_decision_policy_service::ADAPTIVE_POLICY_VERSION,
                'initial_path_policy' => goal_gap_path_service::PATH_POLICY_VERSION,
                'activity_resolution_policy' => candidate_activity_resolution_service::RESOLUTION_POLICY_VERSION,
                'adaptive_path_policy' => self::ADAPTIVE_PATH_POLICY_VERSION,
                'progress_policy' => progress_goal_readiness_service::PROGRESS_POLICY_VERSION,
            ],
            'selected_target' => $target,
            'selected_activity' => $activity,
            'candidate_summary' => [
                'summary' => $resolution['summary'] ?? [],
                'resolution_hash' => $resolution['explainability']['resolution_hash'] ?? '',
                'a4_path_hash' => $resolution['explainability']['a4_path_hash'] ?? '',
            ],
            'reason_codes' => $reasoncodes,
            'decision_timestamp' => time(),
        ];
    }

    /**
     * Convert the preview into a database record.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param array $recommendation
     * @param int $now
     * @return \stdClass
     */
    private static function recommendation_record(int $userid, int $courseid, string $unitcode,
            array $recommendation, int $now): \stdClass {
        $target = is_array($recommendation['selected_target'] ?? null) ? $recommendation['selected_target'] : [];
        $activity = is_array($recommendation['selected_activity'] ?? null) ?
            $recommendation['selected_activity'] : [];
        return (object)[
            'userid' => $userid,
            'courseid' => $courseid ?: null,
            'unitcode' => $unitcode ?: null,
            'objectid' => !empty($activity['objectid']) ? (int)$activity['objectid'] : null,
            'cmid' => !empty($activity['cmid']) ? (int)$activity['cmid'] : null,
            'targettype' => !empty($target['type']) ? (string)$target['type'] : null,
            'targetid' => !empty($target['id']) ? (int)$target['id'] : null,
            'recommendationtype' => (string)$recommendation['action'],
            'policyversion' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'sourcehash' => (string)$recommendation['sourcehash'],
            'decisioncode' => (string)$recommendation['decision_code'],
            'reason' => (string)$recommendation['reason'],
            'prereqinfo' => json_encode([
                'gate' => self::GATE,
                'contract' => self::CONTRACT_VERSION,
                'reason_codes' => $recommendation['reason_codes'],
                'candidate_summary' => $recommendation['candidate_summary'],
                'snapshot' => $recommendation['snapshot'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'masterygap' => $recommendation['mastery_gap'],
            'expectedbenefit' => (float)$recommendation['expected_benefit'],
            'status' => 'recommended',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }

    /**
     * Mark current A5 rows as superseded while preserving legacy recommendation rows.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $now
     * @return int
     */
    private static function supersede_current_rows(int $userid, int $courseid, string $unitcode, int $now): int {
        global $DB;

        $params = [
            'userid' => $userid,
            'policyversion' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'status' => 'recommended',
        ];
        [$scopesql, $scopeparams] = self::scope_sql(self::scope_params($courseid, $unitcode));
        $params += $scopeparams;
        $rows = $DB->get_records_select('flwcupkp_recommend',
            "userid = :userid AND policyversion = :policyversion AND status = :status{$scopesql}",
            $params, 'id ASC', 'id');
        foreach ($rows as $row) {
            $DB->update_record('flwcupkp_recommend', (object)[
                'id' => (int)$row->id,
                'status' => 'superseded',
                'timemodified' => $now,
            ]);
        }
        return count($rows);
    }

    /**
     * Fetch the current A5 recommendation in a learner scope.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @return \stdClass|null
     */
    private static function current_a5_record(int $userid, int $courseid, string $unitcode): ?\stdClass {
        global $DB;

        $params = [
            'userid' => $userid,
            'policyversion' => self::ADAPTIVE_PATH_POLICY_VERSION,
            'status' => 'recommended',
        ];
        [$scopesql, $scopeparams] = self::scope_sql(self::scope_params($courseid, $unitcode));
        $params += $scopeparams;
        $rows = $DB->get_records_select('flwcupkp_recommend',
            "userid = :userid AND policyversion = :policyversion AND status = :status{$scopesql}",
            $params, 'timemodified DESC, id DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Serialize an A5 row and decode its decision snapshot.
     *
     * @param \stdClass $row
     * @return array
     */
    private static function serialize_recommendation(\stdClass $row): array {
        $details = json_decode((string)($row->prereqinfo ?? ''), true);
        return [
            'id' => (int)$row->id,
            'userid' => (int)$row->userid,
            'courseid' => (int)($row->courseid ?? 0),
            'unitcode' => (string)($row->unitcode ?? ''),
            'objectid' => (int)($row->objectid ?? 0),
            'cmid' => (int)($row->cmid ?? 0),
            'targettype' => (string)($row->targettype ?? ''),
            'targetid' => (int)($row->targetid ?? 0),
            'action' => (string)($row->recommendationtype ?? ''),
            'policyversion' => (string)($row->policyversion ?? ''),
            'sourcehash' => (string)($row->sourcehash ?? ''),
            'decisioncode' => (string)($row->decisioncode ?? ''),
            'reason' => (string)($row->reason ?? ''),
            'details' => is_array($details) ? $details : [],
            'masterygap' => isset($row->masterygap) ? (float)$row->masterygap : null,
            'expectedbenefit' => isset($row->expectedbenefit) ? (float)$row->expectedbenefit : null,
            'status' => (string)$row->status,
            'timecreated' => (int)$row->timecreated,
            'timemodified' => (int)$row->timemodified,
        ];
    }

    /**
     * Determine the recommendation persistence status.
     *
     * @param \stdClass|null $current
     * @param string $sourcehash
     * @return string
     */
    private static function persistence_status(?\stdClass $current, string $sourcehash): string {
        if (!$current) {
            return 'ready_to_apply';
        }
        return hash_equals((string)$current->sourcehash, $sourcehash) ? 'current' : 'refresh_required';
    }

    /**
     * Pick the action from candidate semantics first, then A3 decision code.
     *
     * @param string $candidateaction
     * @param string $decisioncode
     * @param bool $hasactivity
     * @return string
     */
    private static function action_for(string $candidateaction, string $decisioncode, bool $hasactivity): string {
        $action = '';
        if (isset(self::CANDIDATE_ACTIONS[$candidateaction])) {
            $action = self::CANDIDATE_ACTIONS[$candidateaction];
        } else if (isset(self::DECISION_ACTIONS[$decisioncode])) {
            $action = self::DECISION_ACTIONS[$decisioncode];
        } else {
            $action = $hasactivity ? 'EXTRA_PRACTICE' : 'REPRIORITIZE';
        }
        if (!$hasactivity && in_array($action, ['ADVANCE', 'SKIP', 'EXTRA_PRACTICE'], true)) {
            return 'REPRIORITIZE';
        }
        return $action;
    }

    /**
     * Candidate action attached to the selected resolution.
     *
     * @param array $resolution
     * @return string
     */
    private static function selected_candidate_action(array $resolution): string {
        $target = is_array($resolution['next_target'] ?? null) ? $resolution['next_target'] : [];
        foreach (($resolution['target_resolutions'] ?? []) as $row) {
            if ((int)($row['target']['id'] ?? 0) === (int)($target['id'] ?? 0) &&
                    (string)($row['target']['type'] ?? '') === (string)($target['type'] ?? '')) {
                return (string)($row['candidate_action'] ?? '');
            }
        }
        return '';
    }

    /**
     * Stable explainability reason codes.
     *
     * @param array $resolution
     * @param array $path
     * @param string $candidateaction
     * @param string $decisioncode
     * @param array|null $activity
     * @return array
     */
    private static function reason_codes(array $resolution, array $path, string $candidateaction,
            string $decisioncode, ?array $activity): array {
        $codes = [];
        if ($decisioncode !== '') {
            $codes[] = strtolower($decisioncode);
        }
        if ($candidateaction !== '') {
            $codes[] = 'candidate_' . $candidateaction;
        }
        if (!empty($resolution['fallback']['used'])) {
            $codes[] = 'fallback_used';
        }
        if (!$activity) {
            $codes[] = strtolower((string)($resolution['diagnostic']['code'] ?? 'no_eligible_activity'));
        } else {
            $codes[] = 'eligible_activity_selected';
        }
        if (($path['path_status'] ?? '') !== '') {
            $codes[] = 'path_' . strtolower((string)$path['path_status']);
        }
        $codes = array_values(array_unique(array_filter(array_map(static function(string $code): string {
            return trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower($code)), '_');
        }, $codes))));
        sort($codes, SORT_STRING);
        return $codes;
    }

    /**
     * Human-readable recommendation reason.
     *
     * @param string $action
     * @param array|null $target
     * @param array|null $activity
     * @param array $resolution
     * @return string
     */
    private static function recommendation_reason(string $action, ?array $target, ?array $activity,
            array $resolution): string {
        if ($activity) {
            $activitytitle = (string)($activity['title'] ?? 'the selected Moodle activity');
            $targettitle = (string)($target['title'] ?? $target['externalid'] ?? 'the selected target');
            return $action . ': ' . $activitytitle . ' is the highest-ranked currently eligible activity for ' .
                $targettitle . '.';
        }
        $message = trim((string)($resolution['diagnostic']['message'] ?? 'No eligible Moodle activity is available.'));
        return $action . ': ' . $message;
    }

    /**
     * Best available normalized mastery gap.
     *
     * @param array $path
     * @return float|null
     */
    private static function mastery_gap(array $path): ?float {
        $summary = is_array($path['goal_gap_analysis']['summary'] ?? null) ?
            $path['goal_gap_analysis']['summary'] : [];
        if (isset($summary['average_mastery_gap'])) {
            return max(0.0, min(1.0, (float)$summary['average_mastery_gap']));
        }
        $missing = (int)($summary['missing'] ?? 0);
        $total = $missing + (int)($summary['satisfied'] ?? 0);
        return $total > 0 ? round($missing / $total, 5) : null;
    }

    /**
     * Minimal selected-target fingerprint.
     *
     * @param array|null $target
     * @return array|null
     */
    private static function selected_target_fingerprint(?array $target): ?array {
        return $target ? [
            'type' => (string)($target['type'] ?? ''),
            'id' => (int)($target['id'] ?? 0),
            'externalid' => (string)($target['externalid'] ?? ''),
            'status' => (string)($target['status'] ?? ''),
        ] : null;
    }

    /**
     * Minimal selected-activity fingerprint.
     *
     * @param array|null $activity
     * @return array|null
     */
    private static function selected_activity_fingerprint(?array $activity): ?array {
        return $activity ? [
            'objectid' => (int)($activity['objectid'] ?? 0),
            'cmid' => (int)($activity['cmid'] ?? 0),
            'courseid' => (int)($activity['courseid'] ?? 0),
            'targettype' => (string)($activity['targettype'] ?? ''),
            'targetid' => (int)($activity['targetid'] ?? 0),
            'candidate_rank' => (int)($activity['candidate_rank'] ?? 0),
            'candidate_action' => (string)($activity['candidate_action'] ?? ''),
        ] : null;
    }

    /**
     * Scope values used by SQL helpers.
     *
     * @param int $courseid
     * @param string $unitcode
     * @return array
     */
    private static function scope_params(int $courseid, string $unitcode): array {
        return ['courseid' => $courseid, 'unitcode' => $unitcode];
    }

    /**
     * Add nullable course/unit conditions to an aliased recommendation query.
     *
     * @param array $scope
     * @return array{0:string,1:array}
     */
    private static function scope_sql(array $scope): array {
        $sql = '';
        $params = [];
        if ((int)$scope['courseid'] > 0) {
            $sql .= ' AND courseid = :courseid';
            $params['courseid'] = (int)$scope['courseid'];
        } else {
            $sql .= ' AND (courseid IS NULL OR courseid = 0)';
        }
        if ((string)$scope['unitcode'] !== '') {
            $sql .= ' AND unitcode = :unitcode';
            $params['unitcode'] = (string)$scope['unitcode'];
        } else {
            $sql .= " AND (unitcode IS NULL OR unitcode = '')";
        }
        return [$sql, $params];
    }

    /**
     * Recommendation schema readiness.
     *
     * @return array
     */
    private static function schema_status(): array {
        global $DB;

        $columns = $DB->get_columns('flwcupkp_recommend');
        $fields = [];
        foreach (self::REQUIRED_RECOMMENDATION_FIELDS as $field) {
            $fields[$field] = isset($columns[$field]);
        }
        return [
            'table' => (bool)$columns,
            'fields' => $fields,
            'ready' => (bool)$columns && !in_array(false, $fields, true),
        ];
    }

    /**
     * A5 readiness criteria.
     *
     * @param array $a4b
     * @param array $schema
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $a4b, array $schema, array $files, array $surface): array {
        $contract = self::contract();
        return [
            'a4b_resolution_ready' => self::criterion('a4b_resolution_ready',
                ($a4b['status'] ?? '') === 'ready' &&
                    (($a4b['contract']['version'] ?? '') === candidate_activity_resolution_service::CONTRACT_VERSION) &&
                    (($a4b['next_allowed_gate'] ?? '') === 'A5'),
                'A4B must be ready and explicitly hand off to A5.'),
            'recommendation_schema_ready' => self::criterion('recommendation_schema_ready',
                !empty($schema['ready']),
                'The A5 recommendation fields must be installed.'),
            'adaptive_actions_complete' => self::criterion('adaptive_actions_complete',
                self::ACTIONS === ['ADVANCE', 'SKIP', 'EXTRA_PRACTICE', 'REMEDIATION', 'REVIEW', 'RETRY',
                    'REASSESS', 'REPRIORITIZE'],
                'All eight frozen A5 adaptive actions must be supported.'),
            'controlled_write_boundary' => self::criterion('controlled_write_boundary',
                $contract['write_boundary'] === ['flwcupkp_recommend', 'flwcupkp_audit'],
                'A5 writes must remain limited to recommendation and audit rows.'),
            'source_state_boundary_preserved' => self::criterion('source_state_boundary_preserved',
                in_array('history_v1_source_mutation', $contract['does_not_do'], true) &&
                    in_array('mastery_state_mutation', $contract['does_not_do'], true) &&
                    in_array('retention_state_mutation', $contract['does_not_do'], true),
                'A5 must not mutate source history or learner-state inputs.'),
            'engine_files_present' => self::criterion('engine_files_present',
                !empty($files['present']['classes/local/adaptive_path_engine_service.php']) &&
                    !empty($files['present']['adaptive_path.php']) &&
                    !empty($files['present']['cli/adaptive_path.php']),
                'A5 service, page, and CLI must exist.'),
            'engine_surface_present' => self::criterion('engine_surface_present',
                !in_array(false, $surface['methods'], true) && !in_array(false, $surface['external_api'], true),
                'A5 service and external API methods must be available.'),
            'next_gate_frozen' => self::criterion('next_gate_frozen', self::NEXT_ALLOWED_GATE === 'A5B',
                'A5 must stop at A5B trajectory simulation and invariant testing.'),
        ];
    }

    /**
     * Required file status.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'classes/local/adaptive_path_engine_service.php',
            'adaptive_path.php',
            'cli/adaptive_path.php',
            'tests/adaptive_path_engine_service_test.php',
            'classes/external/api.php',
            'db/services.php',
            'classes/privacy/provider.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            if (file_exists($CFG->dirroot . '/local/flwcupkp/' . $file)) {
                $present[$file] = true;
            } else {
                $missing[$file] = false;
            }
        }
        return ['present' => $present, 'missing' => $missing];
    }

    /**
     * Service and external API method status.
     *
     * @return array
     */
    private static function surface_status(): array {
        global $CFG;

        $methods = [];
        foreach (['contract', 'policy', 'status', 'learner_path', 'current_recommendations',
            'apply_learner_path', 'class_summary', 'apply_class_paths'] as $method) {
            $methods[self::class . '::' . $method] = method_exists(self::class, $method);
        }
        $apisource = @file_get_contents($CFG->dirroot . '/local/flwcupkp/classes/external/api.php') ?: '';
        $external = [];
        foreach (['get_adaptive_path_engine_status', 'get_learner_adaptive_path',
            'apply_learner_adaptive_path', 'get_class_adaptive_path_summary',
            'apply_class_adaptive_paths'] as $method) {
            $external[$method] = strpos($apisource, 'function ' . $method . '(') !== false;
        }
        return ['methods' => $methods, 'external_api' => $external];
    }

    /**
     * Criterion row.
     *
     * @param string $code
     * @param bool $pass
     * @param string $message
     * @return array
     */
    private static function criterion(string $code, bool $pass, string $message): array {
        return ['code' => $code, 'pass' => $pass, 'message' => $message];
    }

    /**
     * Criterion totals.
     *
     * @param array $criteria
     * @return array
     */
    private static function criteria_summary(array $criteria): array {
        $passed = count(array_filter($criteria, static function(array $criterion): bool {
            return !empty($criterion['pass']);
        }));
        return ['total' => count($criteria), 'passed' => $passed, 'failed' => count($criteria) - $passed];
    }

    /**
     * Readiness findings, including non-blocking A4B findings.
     *
     * @param array $criteria
     * @param array $dependency
     * @return array
     */
    private static function status_findings(array $criteria, array $dependency): array {
        $findings = [];
        foreach ($criteria as $criterion) {
            if (empty($criterion['pass'])) {
                $findings[] = [
                    'severity' => 'blocker',
                    'source' => self::GATE,
                    'code' => $criterion['code'],
                    'message' => $criterion['message'],
                ];
            }
        }
        foreach (($dependency['findings'] ?? []) as $finding) {
            if (is_array($finding)) {
                $finding['source'] = $finding['source'] ?? candidate_activity_resolution_service::GATE;
                $findings[] = $finding;
            }
        }
        return self::dedupe_findings($findings);
    }

    /**
     * Deduplicate propagated findings.
     *
     * @param array $findings
     * @return array
     */
    private static function dedupe_findings(array $findings): array {
        $seen = [];
        $result = [];
        foreach ($findings as $finding) {
            $key = strtolower(trim((string)($finding['code'] ?? ''))) . '|' .
                strtolower(trim((string)($finding['message'] ?? '')));
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $finding;
        }
        return $result;
    }

    /**
     * Safe dependency status call.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return [
                'status' => 'blocked',
                'findings' => [[
                    'severity' => 'blocker',
                    'code' => 'dependency_exception',
                    'message' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * Compact dependency status.
     *
     * @param array $status
     * @return array
     */
    private static function dependency_summary(array $status): array {
        return [
            'gate' => (string)($status['gate'] ?? candidate_activity_resolution_service::GATE),
            'status' => (string)($status['status'] ?? 'blocked'),
            'contract' => (string)($status['contract']['version'] ?? ''),
            'next_allowed_gate' => (string)($status['next_allowed_gate'] ?? ''),
            'findings' => $status['findings'] ?? [],
        ];
    }

    /**
     * Empty class metrics.
     *
     * @return array
     */
    private static function empty_class_summary(): array {
        return [
            'learners' => 0,
            'current' => 0,
            'ready_to_apply' => 0,
            'refresh_required' => 0,
            'next_activity_ready' => 0,
            'diagnostic_required' => 0,
            'errors' => 0,
            'statuses' => [],
            'actions' => [],
        ];
    }

    /**
     * Bound a caller-controlled limit.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }

    /**
     * Validate optional unit code.
     *
     * @param string $unitcode
     * @return string
     */
    private static function clean_unit_code_optional(string $unitcode): string {
        $unitcode = strtoupper(trim($unitcode));
        if ($unitcode !== '' && !preg_match('/^[A-Z0-9_-]{1,40}$/', $unitcode)) {
            throw new \invalid_parameter_exception('Unit code contains unsupported characters.');
        }
        return $unitcode;
    }
}
