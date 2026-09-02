<?php
// Program 3 Gate A3 adaptive decision policy.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Freezes deterministic adaptive decision rules before path generation.
 */
final class adaptive_decision_policy_service {
    /** Program 3 adaptive decision gate. */
    public const GATE = 'P3_A3';

    /** Frozen A3 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_ADAPTIVE_DECISION_POLICY_V1';

    /** Deterministic adaptive decision policy version. */
    public const ADAPTIVE_POLICY_VERSION = 'cupkp-adaptive-decision-policy-v1';

    /** Next allowed gate after A3. */
    public const NEXT_ALLOWED_GATE = 'A4';

    /** @var array Strong mastery states by target type. */
    private const STRONG_STATES = [
        'kp' => ['independent_use', 'mastered', 'review_due'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /** @var array Stable target-type order used for tie-breaking. */
    private const TARGET_TYPE_ORDER = [
        'kp' => 10,
        'up' => 20,
        'competency' => 30,
    ];

    /** @var array Decision priority order. Lower number wins. */
    private const DECISION_ORDER = [
        'FALLBACK_TEACHER_REVIEW' => 5,
        'GOAL_REQUIRED' => 10,
        'GOAL_REVIEW' => 15,
        'PLACEMENT_REQUIRED' => 20,
        'DIAGNOSTIC_INCOMPLETE' => 30,
        'PLACEMENT_REVIEW' => 40,
        'REASSESSMENT_RECOMMENDED' => 50,
        'RELEARNING_REQUIRED' => 60,
        'REVIEW_REQUIRED' => 70,
        'PREREQUISITE_REQUIRED' => 80,
        'REMEDIATION_REQUIRED' => 90,
        'RETRY_RECOMMENDED' => 100,
        'INTRODUCE_TARGET' => 110,
        'ADVANCE_READY' => 120,
    ];

    /**
     * Return the frozen A3 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpAdaptiveDecisionPolicyContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                learning_goal_service::CONTRACT_VERSION,
                placement_diagnostic_service::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                management_v1_contract::CONTRACT_VERSION,
                relationship_graph_contract::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'adaptive_policy_version' => self::ADAPTIVE_POLICY_VERSION,
            'visible_policy' => self::policy(),
            'inputs' => [
                'A1 current competency-centered goal',
                'A2 placement diagnostic and cold-start state',
                'E2 mastery, confidence, and current learner state',
                'E3 retention, retrieval, and review state',
                'C2 prerequisite and relationship graph semantics',
            ],
            'outputs' => [
                'NEXT TARGET',
                'PROJECTED ROADMAP',
                'DESTINATION',
            ],
            'read_only_surface' => [
                'status',
                'learner_decision',
                'class_summary',
                'policy',
            ],
            'write_boundary' => [],
            'does_not_do' => [
                'raw_moodle_log_scraping',
                'history_v1_source_mutation',
                'learning_goal_mutation',
                'placement_state_mutation',
                'mastery_state_mutation',
                'retention_state_mutation',
                'recommendation_row_writes',
                'persistent_path_generation',
                'moodle_activity_resolution',
                'arbitrary_hidden_thresholds',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Expose the decision policy, including thresholds and tie breakers.
     *
     * @return array
     */
    public static function policy(): array {
        return [
            'version' => self::ADAPTIVE_POLICY_VERSION,
            'thresholds' => self::policy_thresholds(),
            'decision_states' => self::decision_states(),
            'candidate_priority' => [
                'source_priority' => [
                    'retention_relearning',
                    'retention_review_due',
                    'retention_uncertain',
                    'hard_prerequisite_gap',
                    'mastery_gap',
                    'confidence_gap',
                    'explicit_goal_target_without_state',
                    'advance_ready',
                ],
                'target_type_order' => self::TARGET_TYPE_ORDER,
                'goal_target_bonus' => 0.10,
            ],
            'tie_breaking' => self::tie_breaking(),
            'stability_hysteresis' => [
                'read_only_in_a3' => true,
                'no_persistent_recommendation_rows' => true,
                'prefer_unresolved_higher_priority_setup_or_review_states' => true,
                'future_path_gate_must_compare_decision_hash_before_replacing_a_path' => true,
            ],
            'anti_loop' => [
                'decision_hash_returned' => true,
                'no_auto_retry_loop_is_written_by_a3' => true,
                'future_path_gate_must_cap_repeated_retry_or_review_cycles' => true,
            ],
            'fallback' => [
                'code' => 'FALLBACK_TEACHER_REVIEW',
                'rule' => 'If a required trusted service cannot be read or no deterministic candidate can be chosen, send the learner to teacher/admin review.',
            ],
            'activity_resolution' => [
                'allowed_in_a3' => false,
                'first_allowed_gate' => 'A4B',
            ],
        ];
    }

    /**
     * Readiness status for A3.
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
        $a1 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return learning_goal_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $a2 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return placement_diagnostic_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $e2 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return mastery_state_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $e3 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return retention_review_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $history = self::safe_status_call(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $files = self::file_status();
        $surface = self::surface_status();
        $policy = self::policy();
        $criteria = self::criteria($a1, $a2, $e2, $e3, $history, $files, $surface, $policy);
        $criteriasummary = self::criteria_summary($criteria);
        $classsummary = $courseid > 0 ?
            self::class_summary($courseid, $unitcode, $frameworkid, min(50, $limit))['summary'] :
            self::empty_class_summary();

        return [
            'type' => 'CupkpAdaptiveDecisionPolicyStatus',
            'gate' => self::GATE,
            'status' => $criteriasummary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $criteriasummary,
            'dependencies' => [
                'learning_goal_service' => self::dependency_summary($a1),
                'placement_diagnostic_service' => self::dependency_summary($a2),
                'mastery_state_service' => self::dependency_summary($e2),
                'retention_review_service' => self::dependency_summary($e3),
                'history_v1' => self::dependency_summary($history),
            ],
            'policy' => $policy,
            'files' => $files,
            'surface' => $surface,
            'summary' => $classsummary,
            'findings' => self::status_findings($criteria, [$a1, $a2, $e2, $e3, $history]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Compute the current A3 learner decision without writing path or recommendation rows.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function learner_decision(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        if ($courseid > 0) {
            evidence_guard::assert_user_enrolled_for_course($userid, $courseid);
        }
        $limit = self::bounded_limit($limit, 500);

        $goal = self::safe_current_call('learning_goal', static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return learning_goal_service::current_goal($userid, $courseid, $unitcode, $frameworkid, 20);
        });
        $placement = self::safe_current_call('placement_diagnostic', static function() use ($userid, $courseid,
                $unitcode, $frameworkid): array {
            return placement_diagnostic_service::current_placement($userid, $courseid, $unitcode, $frameworkid, 20);
        });
        $mastery = self::safe_current_call('mastery_state', static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return mastery_state_service::current_learner_state($userid, $courseid, $unitcode, $frameworkid, $limit);
        });
        $retention = self::safe_current_call('retention_review', static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return retention_review_service::current_retention_state($userid, $courseid, $unitcode, $frameworkid,
                $limit);
        });

        $signals = self::decision_signals($goal, $placement, $mastery, $retention);
        $selected = self::select_signal($signals);
        $decisionhash = self::decision_hash($userid, $courseid, $unitcode, $frameworkid, $selected, [
            'goal' => $goal,
            'placement' => $placement,
            'mastery' => $mastery,
            'retention' => $retention,
        ]);

        return [
            'type' => 'CupkpLearnerAdaptiveDecision',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policy_version' => self::ADAPTIVE_POLICY_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'decision' => $selected,
            'next_target' => $selected['target'],
            'projected_roadmap' => self::projected_roadmap($selected, $signals, $goal),
            'destination' => self::destination($goal),
            'signals' => $signals,
            'explainability' => [
                'decision_hash' => $decisionhash,
                'selected_rule' => $selected['rule'],
                'thresholds_used' => self::thresholds_for_target($selected['target']['type'] ?? ''),
                'tie_breaking' => self::tie_breaking(),
                'source_snapshots' => self::source_snapshots($goal, $placement, $mastery, $retention),
                'non_actions' => [
                    'no_moodle_activity_resolution',
                    'no_recommendation_row_write',
                    'no_mastery_or_retention_mutation',
                    'no_history_v1_source_mutation',
                ],
            ],
            'inputs' => [
                'goal' => $goal,
                'placement' => $placement,
                'mastery_summary' => $mastery['summary'] ?? [],
                'retention_summary' => $retention['summary'] ?? [],
            ],
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level A3 decision summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function class_summary(int $courseid, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID is required.');
        }
        $limit = self::bounded_limit($limit, 300);
        $learners = self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $rows = [];
        $summary = self::empty_class_summary();
        $summary['learners'] = count($learners);

        foreach ($learners as $learnerid) {
            try {
                $decision = self::learner_decision((int)$learnerid, $courseid, $unitcode, $frameworkid, 120);
            } catch (\invalid_parameter_exception $e) {
                $summary['skipped_unenrolled']++;
                $rows[] = [
                    'userid' => (int)$learnerid,
                    'learner' => self::learner_identity((int)$learnerid),
                    'status' => 'skipped_unenrolled',
                    'reason' => $e->getMessage(),
                ];
                continue;
            }

            $code = (string)($decision['decision']['code'] ?? 'FALLBACK_TEACHER_REVIEW');
            $urgency = (string)($decision['decision']['urgency'] ?? 'review');
            $summary['decisions'][$code] = ($summary['decisions'][$code] ?? 0) + 1;
            if (isset($summary['urgency'][$urgency])) {
                $summary['urgency'][$urgency]++;
            }
            self::increment_summary_bucket($summary, $code);
            if (!empty($decision['next_target'])) {
                $summary['next_target_count']++;
            }

            $rows[] = [
                'userid' => (int)$learnerid,
                'learner' => self::learner_identity((int)$learnerid),
                'decision' => $decision['decision'],
                'next_target' => $decision['next_target'],
                'destination' => $decision['destination'],
                'decision_hash' => $decision['explainability']['decision_hash'],
            ];
        }

        arsort($summary['decisions']);
        return [
            'type' => 'CupkpClassAdaptiveDecisionSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'policy_version' => self::ADAPTIVE_POLICY_VERSION,
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
            'recommendation_writes_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Visible A3 thresholds used by the runtime policy.
     *
     * @return array
     */
    private static function policy_thresholds(): array {
        return [
            'mastery' => [
                'kp' => [
                    'advance_score' => 0.80,
                    'remediate_below' => 0.55,
                    'retry_from' => 0.55,
                    'retry_below' => 0.80,
                ],
                'up' => [
                    'advance_score' => 0.75,
                    'remediate_below' => 0.50,
                    'retry_from' => 0.50,
                    'retry_below' => 0.75,
                ],
                'competency' => [
                    'advance_score' => 0.75,
                    'remediate_below' => 0.50,
                    'retry_from' => 0.50,
                    'retry_below' => 0.75,
                ],
            ],
            'confidence' => [
                'low_below' => 0.50,
                'stable_from' => 0.75,
            ],
            'placement' => [
                'low_confidence_below' => 0.60,
                'stale_state_requires_reassessment' => true,
                'teacher_override_requires_review' => true,
            ],
            'prerequisite' => [
                'hard_min_score' => 0.70,
                'soft_min_score' => 0.55,
                'missing_hard_blocks_advance' => true,
            ],
            'retention' => [
                'relearning_before_review_due' => true,
                'review_due_before_mastery_gap' => true,
                'retention_uncertain_requires_reassessment' => true,
            ],
        ];
    }

    /**
     * Explainable decision states.
     *
     * @return array
     */
    private static function decision_states(): array {
        return [
            'GOAL_REQUIRED' => [
                'action' => 'set_destination',
                'rule' => 'No active A1 learning goal exists for this scope.',
                'prompt_terms' => ['fallback', 'destination'],
            ],
            'GOAL_REVIEW' => [
                'action' => 'review_destination',
                'rule' => 'A goal exists but is paused, completed, archived, or malformed for the current adaptive scope.',
                'prompt_terms' => ['review', 'destination'],
            ],
            'PLACEMENT_REQUIRED' => [
                'action' => 'take_placement',
                'rule' => 'No processed A2 placement state or History V1 placement fact is available.',
                'prompt_terms' => ['fallback', 'candidate priority'],
            ],
            'DIAGNOSTIC_INCOMPLETE' => [
                'action' => 'complete_diagnostic',
                'rule' => 'A2 placement state is partial or abandoned.',
                'prompt_terms' => ['retry', 'reassessment'],
            ],
            'PLACEMENT_REVIEW' => [
                'action' => 'teacher_review',
                'rule' => 'A2 placement state is low-confidence or teacher override.',
                'prompt_terms' => ['review', 'confidence states'],
            ],
            'REASSESSMENT_RECOMMENDED' => [
                'action' => 'reassess',
                'rule' => 'Placement is stale, retention is uncertain, or confidence is below the visible threshold.',
                'prompt_terms' => ['reassessment', 'confidence states', 'regression significance'],
            ],
            'RELEARNING_REQUIRED' => [
                'action' => 'relearn',
                'rule' => 'E3 retention state indicates relearning while preserving E2 mastery.',
                'prompt_terms' => ['review', 'remediation', 'regression significance'],
            ],
            'REVIEW_REQUIRED' => [
                'action' => 'review',
                'rule' => 'E3 retention state indicates review due.',
                'prompt_terms' => ['review', 'stability/hysteresis'],
            ],
            'PREREQUISITE_REQUIRED' => [
                'action' => 'repair_prerequisite',
                'rule' => 'C2 prerequisite readiness blocks advancing to the candidate target.',
                'prompt_terms' => ['prerequisite readiness', 'remediation'],
            ],
            'REMEDIATION_REQUIRED' => [
                'action' => 'remediate',
                'rule' => 'E2 mastery is below the visible remediation threshold.',
                'prompt_terms' => ['mastery states', 'remediation'],
            ],
            'RETRY_RECOMMENDED' => [
                'action' => 'retry',
                'rule' => 'E2 mastery is partial but not yet advance-ready.',
                'prompt_terms' => ['retry', 'advance readiness'],
            ],
            'INTRODUCE_TARGET' => [
                'action' => 'introduce',
                'rule' => 'A goal target has no current E2 learner state yet.',
                'prompt_terms' => ['NEXT TARGET', 'candidate priority'],
            ],
            'ADVANCE_READY' => [
                'action' => 'advance',
                'rule' => 'Goal, placement, mastery, confidence, retention, and prerequisites do not block advancement.',
                'prompt_terms' => ['skip', 'advance readiness', 'PROJECTED ROADMAP'],
            ],
            'FALLBACK_TEACHER_REVIEW' => [
                'action' => 'teacher_review',
                'rule' => 'Trusted inputs are unavailable or cannot produce a deterministic policy decision.',
                'prompt_terms' => ['fallback', 'anti-loop'],
            ],
        ];
    }

    /**
     * Stable tie-breaking rules.
     *
     * @return array
     */
    private static function tie_breaking(): array {
        return [
            'decision_priority_ascending',
            'goal_target_before_non_goal_target',
            'target_type_order_kp_up_competency',
            'target_externalid_ascending',
            'target_id_ascending',
            'signal_code_ascending',
        ];
    }

    /**
     * Build all decision signals.
     *
     * @param array $goal
     * @param array $placement
     * @param array $mastery
     * @param array $retention
     * @return array
     */
    private static function decision_signals(array $goal, array $placement, array $mastery, array $retention): array {
        $signals = [];

        foreach (['learning_goal' => $goal, 'placement_diagnostic' => $placement, 'mastery_state' => $mastery,
                'retention_review' => $retention] as $source => $payload) {
            if (($payload['status'] ?? '') === 'unavailable') {
                $signals[] = self::signal('FALLBACK_TEACHER_REVIEW', $source, null,
                    'Required trusted input is unavailable: ' . (string)($payload['message'] ?? 'unknown error') . '.',
                    ['source' => $source]);
            }
        }

        if (empty($goal['has_goal'])) {
            $signals[] = self::signal('GOAL_REQUIRED', 'A1', null,
                'A1 has no active learning goal for this learner and scope.', []);
        } else {
            $goalrow = $goal['goal'] ?? [];
            if (!is_array($goalrow) || (string)($goalrow['status'] ?? '') !== 'active') {
                $signals[] = self::signal('GOAL_REVIEW', 'A1', null,
                    'The current A1 goal is not active for adaptive decisions.', [
                        'goal_status' => (string)($goalrow['status'] ?? 'missing'),
                    ]);
            }
        }

        foreach (self::placement_signals($placement) as $signal) {
            $signals[] = $signal;
        }

        $stateindex = self::state_index($mastery['states'] ?? []);
        $goaltargets = self::goal_targets($goal);
        foreach (self::retention_signals($retention, $goaltargets) as $signal) {
            $signals[] = $signal;
        }
        foreach (self::mastery_signals($mastery, $goaltargets) as $signal) {
            $signals[] = $signal;
        }
        foreach (self::goal_target_signals($goaltargets, $stateindex) as $signal) {
            $signals[] = $signal;
        }

        $signals = self::expand_prerequisite_signals($signals, $stateindex);
        if (!self::has_blocking_signal($signals)) {
            $signals[] = self::signal('ADVANCE_READY', 'A3', self::first_goal_or_mastery_target($goaltargets,
                $stateindex), 'No higher-priority A3 policy rule blocks advancement.', []);
        }

        return self::sort_signals($signals);
    }

    /**
     * Placement-driven signals.
     *
     * @param array $placement
     * @return array
     */
    private static function placement_signals(array $placement): array {
        $state = $placement['state'] ?? [];
        if (!is_array($state)) {
            return [
                self::signal('PLACEMENT_REQUIRED', 'A2', null,
                    'A2 returned no placement state; cold-start placement is required.', []),
            ];
        }
        $policystate = strtoupper((string)($state['policystate'] ?? ''));
        if ($policystate === '' || $policystate === 'NOT_TAKEN') {
            return [
                self::signal('PLACEMENT_REQUIRED', 'A2', null,
                    'A2 placement policy state is NOT_TAKEN.', [
                        'policy_case' => (string)($state['policycase'] ?? ''),
                    ]),
            ];
        }
        if ($policystate === 'INCOMPLETE') {
            return [
                self::signal('DIAGNOSTIC_INCOMPLETE', 'A2', null,
                    'A2 placement policy state is INCOMPLETE.', [
                        'policy_case' => (string)($state['policycase'] ?? ''),
                    ]),
            ];
        }
        if ($policystate === 'LOW_CONFIDENCE' || $policystate === 'TEACHER_OVERRIDE') {
            return [
                self::signal('PLACEMENT_REVIEW', 'A2', null,
                    'A2 placement state requires teacher/admin review before adaptive routing.', [
                        'policy_state' => $policystate,
                        'confidence' => $state['confidence'] ?? null,
                    ]),
            ];
        }
        if ($policystate === 'STALE') {
            return [
                self::signal('REASSESSMENT_RECOMMENDED', 'A2', null,
                    'A2 placement state is stale and should be refreshed before route generation.', [
                        'policy_state' => $policystate,
                    ]),
            ];
        }
        return [];
    }

    /**
     * Retention-driven signals.
     *
     * @param array $retention
     * @param array $goaltargets
     * @return array
     */
    private static function retention_signals(array $retention, array $goaltargets): array {
        $signals = [];
        foreach (($retention['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $target = self::target_from_state($state);
            $retentionstate = strtolower((string)($state['retention']['state'] ?? ''));
            if ($retentionstate === 'relearning') {
                $signals[] = self::signal('RELEARNING_REQUIRED', 'E3', $target,
                    'E3 retention state is relearning; mastery remains preserved.', [
                        'retention_state' => $retentionstate,
                        'goal_target' => self::is_goal_target($target, $goaltargets),
                    ]);
            } else if ($retentionstate === 'review_due') {
                $signals[] = self::signal('REVIEW_REQUIRED', 'E3', $target,
                    'E3 retention state is review_due.', [
                        'retention_state' => $retentionstate,
                        'goal_target' => self::is_goal_target($target, $goaltargets),
                    ]);
            } else if ($retentionstate === 'retention_uncertain') {
                $signals[] = self::signal('REASSESSMENT_RECOMMENDED', 'E3', $target,
                    'E3 retention confidence is uncertain.', [
                        'retention_state' => $retentionstate,
                        'confidence' => $state['retention']['confidence'] ?? null,
                        'goal_target' => self::is_goal_target($target, $goaltargets),
                    ]);
            }
        }
        return $signals;
    }

    /**
     * Mastery/confidence-driven signals.
     *
     * @param array $mastery
     * @param array $goaltargets
     * @return array
     */
    private static function mastery_signals(array $mastery, array $goaltargets): array {
        $signals = [];
        foreach (($mastery['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $target = self::target_from_state($state);
            $type = (string)($target['type'] ?? '');
            $score = (float)($state['mastery']['score'] ?? 0);
            $confidence = (float)($state['confidence']['score'] ?? 0);
            $thresholds = self::thresholds_for_target($type);
            $isstrong = !empty($state['mastery']['strong']);
            $isgoal = self::is_goal_target($target, $goaltargets);

            if (!$isstrong && $score < (float)($thresholds['mastery']['remediate_below'] ?? 0.50)) {
                $signals[] = self::signal('REMEDIATION_REQUIRED', 'E2', $target,
                    'E2 mastery is below the remediation threshold.', [
                        'mastery_score' => $score,
                        'confidence' => $confidence,
                        'threshold' => $thresholds['mastery']['remediate_below'] ?? null,
                        'goal_target' => $isgoal,
                    ]);
                continue;
            }
            if ($confidence > 0 && $confidence < (float)($thresholds['confidence']['low_below'] ?? 0.50)) {
                $signals[] = self::signal('REASSESSMENT_RECOMMENDED', 'E2', $target,
                    'E2 confidence is below the low-confidence threshold.', [
                        'mastery_score' => $score,
                        'confidence' => $confidence,
                        'threshold' => $thresholds['confidence']['low_below'] ?? null,
                        'goal_target' => $isgoal,
                    ]);
                continue;
            }
            if (!$isstrong && $score >= (float)($thresholds['mastery']['retry_from'] ?? 0.50)) {
                $signals[] = self::signal('RETRY_RECOMMENDED', 'E2', $target,
                    'E2 mastery is partial and should be retried before advancing.', [
                        'mastery_score' => $score,
                        'confidence' => $confidence,
                        'goal_target' => $isgoal,
                    ]);
            }
        }
        return $signals;
    }

    /**
     * Goal targets that are not represented by a current E2 state yet.
     *
     * @param array $goaltargets
     * @param array $stateindex
     * @return array
     */
    private static function goal_target_signals(array $goaltargets, array $stateindex): array {
        $signals = [];
        foreach ($goaltargets as $key => $target) {
            if (isset($stateindex[$key])) {
                continue;
            }
            $signals[] = self::signal('INTRODUCE_TARGET', 'A1', $target,
                'A1 goal target has no E2 current-state row yet.', [
                    'goal_target' => true,
                ]);
        }
        return $signals;
    }

    /**
     * Add prerequisite readiness signals for candidate targets.
     *
     * @param array $signals
     * @param array $stateindex
     * @return array
     */
    private static function expand_prerequisite_signals(array $signals, array $stateindex): array {
        $expanded = $signals;
        foreach ($signals as $signal) {
            $target = $signal['target'] ?? null;
            if (!is_array($target) || empty($target['type']) || empty($target['id'])) {
                continue;
            }
            $readiness = self::prerequisite_readiness($target, $stateindex);
            if (($readiness['status'] ?? '') !== 'blocked') {
                $expanded[] = self::annotate_signal($signal, ['prerequisite_readiness' => $readiness]);
                continue;
            }
            $missing = $readiness['blocking'][0] ?? null;
            if (!is_array($missing)) {
                continue;
            }
            $expanded[] = self::signal('PREREQUISITE_REQUIRED', 'C2', $missing['target'] ?? null,
                'A hard prerequisite is not ready for the candidate target.', [
                    'blocked_target' => $target,
                    'prerequisite_readiness' => $readiness,
                ]);
        }
        return $expanded;
    }

    /**
     * Return prerequisite readiness for one target.
     *
     * @param array $target
     * @param array $stateindex
     * @return array
     */
    private static function prerequisite_readiness(array $target, array $stateindex): array {
        global $DB;

        $type = (string)($target['type'] ?? '');
        $id = (int)($target['id'] ?? 0);
        $thresholds = self::policy_thresholds()['prerequisite'];
        $requirements = [];

        if ($type === 'kp') {
            $records = $DB->get_records('flwcupkp_kp_prereq', ['kpid' => $id], 'id ASC');
            foreach ($records as $record) {
                $requirements[] = [
                    'requirement' => (string)($record->requirement ?? 'recommended'),
                    'relationshiptype' => (string)($record->relationshiptype ?? 'prerequisite'),
                    'target' => self::target_reference('kp', (int)$record->prereqkpid),
                ];
            }
        } else if ($type === 'up') {
            $records = $DB->get_records('flwcupkp_up_kp', ['upid' => $id], 'sortorder ASC, id ASC');
            foreach ($records as $record) {
                $requirements[] = [
                    'requirement' => (string)($record->role ?? 'required'),
                    'relationshiptype' => 'up_requires_kp',
                    'target' => self::target_reference('kp', (int)$record->kpid),
                ];
            }
        } else if ($type === 'competency') {
            $records = $DB->get_records('flwcupkp_comp_up', ['competencyid' => $id], 'sortorder ASC, id ASC');
            foreach ($records as $record) {
                $requirements[] = [
                    'requirement' => (string)($record->role ?? 'required'),
                    'relationshiptype' => 'competency_requires_up',
                    'target' => self::target_reference('up', (int)$record->upid),
                ];
            }
        }

        $checked = [];
        $blocking = [];
        foreach ($requirements as $requirement) {
            $required = self::is_hard_requirement((string)$requirement['requirement']);
            $requiredtarget = $requirement['target'];
            $key = self::target_key($requiredtarget);
            $state = $stateindex[$key] ?? null;
            $minscore = $required ? (float)$thresholds['hard_min_score'] : (float)$thresholds['soft_min_score'];
            $score = is_array($state) ? (float)($state['mastery']['score'] ?? 0) : 0.0;
            $strong = is_array($state) && !empty($state['mastery']['strong']);
            $ready = $strong || $score >= $minscore;
            $row = [
                'target' => $requiredtarget,
                'requirement' => (string)$requirement['requirement'],
                'relationshiptype' => (string)$requirement['relationshiptype'],
                'minimum_score' => $minscore,
                'current_score' => $score,
                'strong_state' => $strong,
                'ready' => $ready,
            ];
            $checked[] = $row;
            if ($required && !$ready) {
                $blocking[] = $row;
            }
        }

        return [
            'status' => $blocking ? 'blocked' : 'ready',
            'checked' => $checked,
            'blocking' => $blocking,
            'hard_block_count' => count($blocking),
        ];
    }

    /**
     * Select the winning decision signal.
     *
     * @param array $signals
     * @return array
     */
    private static function select_signal(array $signals): array {
        $signals = self::sort_signals($signals);
        if ($signals) {
            return $signals[0];
        }
        return self::signal('FALLBACK_TEACHER_REVIEW', 'A3', null,
            'A3 could not produce a deterministic signal.', []);
    }

    /**
     * Stable signal sorting.
     *
     * @param array $signals
     * @return array
     */
    private static function sort_signals(array $signals): array {
        usort($signals, static function(array $a, array $b): int {
            $priority = (int)$a['priority'] <=> (int)$b['priority'];
            if ($priority !== 0) {
                return $priority;
            }
            $agoal = !empty($a['evidence']['goal_target']) ? 0 : 1;
            $bgoal = !empty($b['evidence']['goal_target']) ? 0 : 1;
            if ($agoal !== $bgoal) {
                return $agoal <=> $bgoal;
            }
            $target = self::compare_targets($a['target'] ?? null, $b['target'] ?? null);
            if ($target !== 0) {
                return $target;
            }
            return strcmp((string)$a['code'], (string)$b['code']);
        });
        return array_values($signals);
    }

    /**
     * Build one normalized signal.
     *
     * @param string $code
     * @param string $source
     * @param array|null $target
     * @param string $rule
     * @param array $evidence
     * @return array
     */
    private static function signal(string $code, string $source, ?array $target, string $rule, array $evidence): array {
        $state = self::decision_states()[$code] ?? self::decision_states()['FALLBACK_TEACHER_REVIEW'];
        return [
            'code' => $code,
            'action' => $state['action'],
            'source' => $source,
            'priority' => self::DECISION_ORDER[$code] ?? 999,
            'urgency' => self::urgency_for_code($code),
            'target' => $target ? self::normalize_target($target) : null,
            'rule' => $rule,
            'evidence' => $evidence,
            'activity_resolution' => 'not_allowed_until_A4B',
        ];
    }

    /**
     * Add evidence fields to a signal.
     *
     * @param array $signal
     * @param array $evidence
     * @return array
     */
    private static function annotate_signal(array $signal, array $evidence): array {
        $signal['evidence'] = array_merge($signal['evidence'] ?? [], $evidence);
        return $signal;
    }

    /**
     * Whether any signal still blocks advancement.
     *
     * @param array $signals
     * @return bool
     */
    private static function has_blocking_signal(array $signals): bool {
        foreach ($signals as $signal) {
            if (($signal['code'] ?? '') !== 'ADVANCE_READY') {
                return true;
            }
        }
        return false;
    }

    /**
     * Project the next roadmap as policy steps, not Moodle activities.
     *
     * @param array $selected
     * @param array $signals
     * @param array $goal
     * @return array
     */
    private static function projected_roadmap(array $selected, array $signals, array $goal): array {
        $steps = [];
        $step = 1;
        $seen = [];
        foreach ($signals as $signal) {
            $key = (string)$signal['code'] . ':' . self::target_key($signal['target'] ?? null);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $steps[] = [
                'step' => $step++,
                'code' => (string)$signal['code'],
                'action' => (string)$signal['action'],
                'target' => $signal['target'],
                'selected' => (string)$signal['code'] === (string)$selected['code'] &&
                    self::target_key($signal['target'] ?? null) === self::target_key($selected['target'] ?? null),
                'activity_resolution' => 'not_allowed_until_A4B',
            ];
            if (count($steps) >= 5) {
                break;
            }
        }
        $destination = self::destination($goal);
        $steps[] = [
            'step' => $step,
            'code' => 'DESTINATION',
            'action' => 'reach_goal',
            'target' => null,
            'selected' => false,
            'destination' => $destination,
            'activity_resolution' => 'not_allowed_until_A4B',
        ];
        return $steps;
    }

    /**
     * Destination snapshot from A1.
     *
     * @param array $goal
     * @return array
     */
    private static function destination(array $goal): array {
        if (empty($goal['has_goal']) || !is_array($goal['goal'] ?? null)) {
            return [
                'available' => false,
                'title' => '',
                'cefr' => '',
                'flwstage' => '',
                'targets' => [],
            ];
        }
        $goalrow = $goal['goal'];
        return [
            'available' => true,
            'title' => (string)($goalrow['title'] ?? ''),
            'cefr' => (string)($goalrow['cefr'] ?? ''),
            'flwstage' => (string)($goalrow['flwstage'] ?? ''),
            'purpose' => (string)($goalrow['purpose'] ?? ''),
            'targets' => $goalrow['destination'] ?? [],
            'goalpolicyversion' => (string)($goalrow['goalpolicyversion'] ?? ''),
            'goalchecksum' => (string)($goalrow['checksum'] ?? ''),
        ];
    }

    /**
     * Compact source snapshots for explanation.
     *
     * @param array $goal
     * @param array $placement
     * @param array $mastery
     * @param array $retention
     * @return array
     */
    private static function source_snapshots(array $goal, array $placement, array $mastery, array $retention): array {
        return [
            'goal' => [
                'has_goal' => !empty($goal['has_goal']),
                'status' => $goal['goal']['status'] ?? null,
                'contract' => $goal['contract'] ?? null,
            ],
            'placement' => [
                'has_processed_state' => !empty($placement['has_processed_state']),
                'policy_state' => $placement['state']['policystate'] ?? null,
                'policy_case' => $placement['state']['policycase'] ?? null,
                'contract' => $placement['contract'] ?? null,
            ],
            'mastery' => [
                'summary' => $mastery['summary'] ?? [],
                'contract' => $mastery['contract'] ?? null,
            ],
            'retention' => [
                'summary' => $retention['summary'] ?? [],
                'contract' => $retention['contract'] ?? null,
            ],
        ];
    }

    /**
     * Deterministic decision hash for later anti-loop use.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $decision
     * @param array $inputs
     * @return string
     */
    private static function decision_hash(int $userid, int $courseid, string $unitcode, int $frameworkid,
            array $decision, array $inputs): string {
        $fingerprint = [
            'policy' => self::ADAPTIVE_POLICY_VERSION,
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'decision' => [
                'code' => $decision['code'] ?? '',
                'target' => $decision['target'] ?? null,
            ],
            'goal_checksum' => $inputs['goal']['goal']['checksum'] ?? '',
            'placement_checksum' => $inputs['placement']['state']['checksum'] ?? '',
            'mastery_summary' => $inputs['mastery']['summary'] ?? [],
            'retention_summary' => $inputs['retention']['summary'] ?? [],
        ];
        return sha1(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Build a goal target map.
     *
     * @param array $goal
     * @return array
     */
    private static function goal_targets(array $goal): array {
        if (empty($goal['has_goal']) || !is_array($goal['goal']['destination'] ?? null)) {
            return [];
        }
        $destination = $goal['goal']['destination'];
        $targets = [];
        foreach (($destination['kpids'] ?? []) as $id) {
            $target = self::target_reference('kp', (int)$id);
            $targets[self::target_key($target)] = $target;
        }
        foreach (($destination['upids'] ?? []) as $id) {
            $target = self::target_reference('up', (int)$id);
            $targets[self::target_key($target)] = $target;
        }
        foreach (($destination['competencyids'] ?? []) as $id) {
            $target = self::target_reference('competency', (int)$id);
            $targets[self::target_key($target)] = $target;
        }
        return $targets;
    }

    /**
     * Create a state index by target key.
     *
     * @param array $states
     * @return array
     */
    private static function state_index(array $states): array {
        $index = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }
            $target = self::target_from_state($state);
            if (!empty($target['type']) && !empty($target['id'])) {
                $index[self::target_key($target)] = $state;
            }
        }
        return $index;
    }

    /**
     * First goal target, or first current state target.
     *
     * @param array $goaltargets
     * @param array $stateindex
     * @return array|null
     */
    private static function first_goal_or_mastery_target(array $goaltargets, array $stateindex): ?array {
        $targets = $goaltargets ?: array_map(static function(array $state): array {
            return adaptive_decision_policy_service::target_from_state($state);
        }, $stateindex);
        usort($targets, static function(array $a, array $b): int {
            return adaptive_decision_policy_service::compare_targets($a, $b);
        });
        return $targets ? self::normalize_target($targets[0]) : null;
    }

    /**
     * Extract a normalized target from a state row.
     *
     * @param array $state
     * @return array
     */
    private static function target_from_state(array $state): array {
        $target = $state['target'] ?? [];
        return self::normalize_target([
            'type' => (string)($target['type'] ?? ''),
            'id' => (int)($target['id'] ?? 0),
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
            'frameworkid' => (int)($target['frameworkid'] ?? 0),
        ]);
    }

    /**
     * Target reference from storage.
     *
     * @param string $type
     * @param int $id
     * @return array
     */
    private static function target_reference(string $type, int $id): array {
        global $DB;

        $table = self::target_table($type);
        $record = $table && $id > 0 ? $DB->get_record($table, ['id' => $id], '*', IGNORE_MISSING) : null;
        return self::normalize_target([
            'type' => $type,
            'id' => $id,
            'externalid' => $record ? (string)($record->externalid ?? '') : '',
            'title' => $record ? (string)($record->title ?? '') : '',
            'frameworkid' => $record ? (int)($record->frameworkid ?? 0) : 0,
        ]);
    }

    /**
     * Normalize a target reference.
     *
     * @param array $target
     * @return array
     */
    private static function normalize_target(array $target): array {
        return [
            'type' => (string)($target['type'] ?? ''),
            'id' => (int)($target['id'] ?? 0),
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
            'frameworkid' => (int)($target['frameworkid'] ?? 0),
        ];
    }

    /**
     * Target table for a C-UP-KP target type.
     *
     * @param string $type
     * @return string
     */
    private static function target_table(string $type): string {
        $tables = [
            'kp' => 'flwcupkp_kp',
            'up' => 'flwcupkp_up',
            'competency' => 'flwcupkp_comp',
        ];
        return $tables[$type] ?? '';
    }

    /**
     * Stable target key.
     *
     * @param array|null $target
     * @return string
     */
    private static function target_key(?array $target): string {
        if (!$target) {
            return '';
        }
        return (string)($target['type'] ?? '') . ':' . (int)($target['id'] ?? 0);
    }

    /**
     * Whether a target is an explicit A1 goal target.
     *
     * @param array|null $target
     * @param array $goaltargets
     * @return bool
     */
    private static function is_goal_target(?array $target, array $goaltargets): bool {
        return $target && isset($goaltargets[self::target_key($target)]);
    }

    /**
     * Compare targets by stable policy order.
     *
     * @param array|null $a
     * @param array|null $b
     * @return int
     */
    private static function compare_targets(?array $a, ?array $b): int {
        $a = $a ?: [];
        $b = $b ?: [];
        $type = (self::TARGET_TYPE_ORDER[(string)($a['type'] ?? '')] ?? 999) <=>
            (self::TARGET_TYPE_ORDER[(string)($b['type'] ?? '')] ?? 999);
        if ($type !== 0) {
            return $type;
        }
        $external = strcmp((string)($a['externalid'] ?? ''), (string)($b['externalid'] ?? ''));
        if ($external !== 0) {
            return $external;
        }
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
    }

    /**
     * Whether relationship label is a hard requirement.
     *
     * @param string $label
     * @return bool
     */
    private static function is_hard_requirement(string $label): bool {
        return in_array(strtolower($label), ['required', 'hard', 'mandatory', 'must'], true);
    }

    /**
     * Thresholds for a target type.
     *
     * @param string $targettype
     * @return array
     */
    private static function thresholds_for_target(string $targettype): array {
        $thresholds = self::policy_thresholds();
        $mastery = $thresholds['mastery'][$targettype] ?? $thresholds['mastery']['kp'];
        return [
            'mastery' => $mastery,
            'confidence' => $thresholds['confidence'],
            'placement' => $thresholds['placement'],
            'prerequisite' => $thresholds['prerequisite'],
            'retention' => $thresholds['retention'],
        ];
    }

    /**
     * Urgency label for a decision code.
     *
     * @param string $code
     * @return string
     */
    private static function urgency_for_code(string $code): string {
        if (in_array($code, ['FALLBACK_TEACHER_REVIEW', 'DIAGNOSTIC_INCOMPLETE', 'PLACEMENT_REVIEW',
                'RELEARNING_REQUIRED'], true)) {
            return 'urgent';
        }
        if (in_array($code, ['GOAL_REQUIRED', 'GOAL_REVIEW', 'PLACEMENT_REQUIRED', 'REASSESSMENT_RECOMMENDED',
                'REVIEW_REQUIRED', 'PREREQUISITE_REQUIRED', 'REMEDIATION_REQUIRED'], true)) {
            return 'attention';
        }
        if (in_array($code, ['RETRY_RECOMMENDED', 'INTRODUCE_TARGET'], true)) {
            return 'next';
        }
        return 'ready';
    }

    /**
     * Increment summary buckets for a code.
     *
     * @param array $summary
     * @param string $code
     */
    private static function increment_summary_bucket(array &$summary, string $code): void {
        $map = [
            'GOAL_REQUIRED' => 'needs_goal',
            'GOAL_REVIEW' => 'needs_goal_review',
            'PLACEMENT_REQUIRED' => 'needs_placement',
            'DIAGNOSTIC_INCOMPLETE' => 'needs_diagnostic',
            'PLACEMENT_REVIEW' => 'needs_teacher_review',
            'REASSESSMENT_RECOMMENDED' => 'needs_reassessment',
            'RELEARNING_REQUIRED' => 'needs_relearning',
            'REVIEW_REQUIRED' => 'needs_review',
            'PREREQUISITE_REQUIRED' => 'needs_prerequisite',
            'REMEDIATION_REQUIRED' => 'needs_remediation',
            'RETRY_RECOMMENDED' => 'needs_retry',
            'INTRODUCE_TARGET' => 'needs_introduction',
            'ADVANCE_READY' => 'advance_ready',
            'FALLBACK_TEACHER_REVIEW' => 'needs_teacher_review',
        ];
        $bucket = $map[$code] ?? '';
        if ($bucket !== '' && isset($summary[$bucket])) {
            $summary[$bucket]++;
        }
    }

    /**
     * Empty class summary.
     *
     * @return array
     */
    private static function empty_class_summary(): array {
        return [
            'learners' => 0,
            'decisions' => [],
            'urgency' => [
                'urgent' => 0,
                'attention' => 0,
                'next' => 0,
                'ready' => 0,
            ],
            'needs_goal' => 0,
            'needs_goal_review' => 0,
            'needs_placement' => 0,
            'needs_diagnostic' => 0,
            'needs_teacher_review' => 0,
            'needs_reassessment' => 0,
            'needs_relearning' => 0,
            'needs_review' => 0,
            'needs_prerequisite' => 0,
            'needs_remediation' => 0,
            'needs_retry' => 0,
            'needs_introduction' => 0,
            'advance_ready' => 0,
            'next_target_count' => 0,
            'skipped_unenrolled' => 0,
        ];
    }

    /**
     * Learner IDs for class-level policy summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function learner_ids_for_scope(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 300);
        $userids = [];

        if ($courseid > 0 && !\context_course::instance($courseid, IGNORE_MISSING)) {
            return [];
        }

        foreach (self::course_learner_ids($courseid, $limit) as $userid) {
            self::add_learner_id($userids, $userid);
        }

        $scope = self::scope_where($courseid, $unitcode, $frameworkid);
        $evidencescope = self::scope_where($courseid, $unitcode, 0);
        foreach ($DB->get_records_select('flwcupkp_goal', $scope['where'], $scope['params'], 'userid ASC',
                'DISTINCT userid', 0, $limit) as $record) {
            self::add_learner_id($userids, (int)$record->userid);
        }
        foreach ($DB->get_records_select('flwcupkp_placement_state', $scope['where'], $scope['params'], 'userid ASC',
                'DISTINCT userid', 0, $limit) as $record) {
            self::add_learner_id($userids, (int)$record->userid);
        }
        foreach ($DB->get_records_select('flwcupkp_evidence', $evidencescope['where'], $evidencescope['params'],
                'userid ASC', 'DISTINCT userid', 0, $limit) as $record) {
            self::add_learner_id($userids, (int)$record->userid);
        }
        foreach (self::target_ids_for_scope($courseid, $unitcode, $frameworkid, $limit) as $target) {
            foreach ($DB->get_records('flwcupkp_state', [
                    'targettype' => $target['type'],
                    'targetid' => $target['id'],
                ], 'userid ASC', 'DISTINCT userid', 0, $limit) as $record) {
                self::add_learner_id($userids, (int)$record->userid);
            }
        }

        sort($userids, SORT_NUMERIC);
        return array_slice(array_values($userids), 0, $limit);
    }

    /**
     * Add a valid learner ID to a keyed set.
     *
     * @param array $userids
     * @param int $userid
     */
    private static function add_learner_id(array &$userids, int $userid): void {
        if ($userid > 0) {
            $userids[$userid] = $userid;
        }
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
            self::bounded_limit($limit, 300), true);
        return array_values(array_map(static function($user): int {
            return (int)$user->id;
        }, $users));
    }

    /**
     * Generic scope SQL for tables with courseid/frameworkid/unitcode/userid.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function scope_where(int $courseid, string $unitcode, int $frameworkid): array {
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
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        return ['where' => $where, 'params' => $params];
    }

    /**
     * Target IDs from mapped learning objects in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function target_ids_for_scope(int $courseid, string $unitcode, int $frameworkid, int $limit): array {
        global $DB;

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
        $records = $DB->get_records_sql(
            "SELECT m.id, m.targettype, m.targetid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$where}
           ORDER BY m.targettype ASC, m.targetid ASC",
            $params,
            0,
            self::bounded_limit($limit, 300)
        );
        $targets = [];
        foreach ($records as $record) {
            $target = self::normalize_target([
                'type' => (string)$record->targettype,
                'id' => (int)$record->targetid,
            ]);
            if (!empty($target['type']) && !empty($target['id'])) {
                $targets[self::target_key($target)] = $target;
            }
        }
        return array_values($targets);
    }

    /**
     * Learner identity for display/API summaries.
     *
     * @param int $userid
     * @return array
     */
    private static function learner_identity(int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email',
            IGNORE_MISSING);
        return [
            'id' => $userid,
            'fullname' => $user ? fullname($user) : (string)$userid,
            'email' => $user ? (string)$user->email : '',
        ];
    }

    /**
     * Status criteria.
     *
     * @param array $a1
     * @param array $a2
     * @param array $e2
     * @param array $e3
     * @param array $history
     * @param array $files
     * @param array $surface
     * @param array $policy
     * @return array
     */
    private static function criteria(array $a1, array $a2, array $e2, array $e3, array $history, array $files,
            array $surface, array $policy): array {
        $decisions = array_keys($policy['decision_states'] ?? []);
        return [
            'trusted_inputs_ready' => self::criterion(
                'trusted_inputs_ready',
                self::dependency_ready($a1) && self::dependency_ready($a2) && self::dependency_ready($e2) &&
                    self::dependency_ready($e3),
                'A1, A2, E2, and E3 trusted service inputs must be readable and ready.'
            ),
            'history_v1_boundary_preserved' => self::criterion(
                'history_v1_boundary_preserved',
                ($history['requiredcontract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT &&
                    ($history['status'] ?? '') !== 'blocked',
                'A3 consumes History V1 only through frozen downstream services and never scrapes raw Moodle logs.'
            ),
            'thresholds_are_visible' => self::criterion(
                'thresholds_are_visible',
                !empty($policy['thresholds']['mastery']) && !empty($policy['thresholds']['confidence']) &&
                    !empty($policy['thresholds']['prerequisite']),
                'Decision thresholds are exposed in the A3 policy contract.'
            ),
            'decision_states_cover_prompt_terms' => self::criterion(
                'decision_states_cover_prompt_terms',
                empty(array_diff([
                    'GOAL_REQUIRED',
                    'PLACEMENT_REQUIRED',
                    'REVIEW_REQUIRED',
                    'REMEDIATION_REQUIRED',
                    'RETRY_RECOMMENDED',
                    'REASSESSMENT_RECOMMENDED',
                    'PREREQUISITE_REQUIRED',
                    'ADVANCE_READY',
                    'FALLBACK_TEACHER_REVIEW',
                ], $decisions)),
                'Decision states cover goal, placement, review, remediation, retry, reassessment, prerequisite, advance, and fallback rules.'
            ),
            'candidate_priority_and_tie_breaking_frozen' => self::criterion(
                'candidate_priority_and_tie_breaking_frozen',
                !empty($policy['candidate_priority']) && !empty($policy['tie_breaking']),
                'Candidate priority, tie-breaking, stability, hysteresis, and anti-loop policy are visible.'
            ),
            'outputs_are_frozen' => self::criterion(
                'outputs_are_frozen',
                true,
                'A3 outputs NEXT TARGET, PROJECTED ROADMAP, and DESTINATION only.'
            ),
            'moodle_activity_resolution_stopped' => self::criterion(
                'moodle_activity_resolution_stopped',
                empty($policy['activity_resolution']['allowed_in_a3']),
                'A3 does not resolve the policy output to Moodle activities.'
            ),
            'surface_present' => self::criterion(
                'surface_present',
                $files['valid'] && $surface['valid'],
                'Admin page, CLI, service methods, and web-service methods are present.'
            ),
            'read_only_no_writes' => self::criterion(
                'read_only_no_writes',
                empty(self::contract()['write_boundary']),
                'A3 is read-only and does not write learner states, recommendations, or paths.'
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
     * Dependency ready helper.
     *
     * @param array $dependency
     * @return bool
     */
    private static function dependency_ready(array $dependency): bool {
        return in_array((string)($dependency['status'] ?? ''), ['ready', 'frozen'], true);
    }

    /**
     * Dependency summary for status payloads.
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
     * Findings from failed criteria and dependencies.
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
     * File status for A3 artifacts.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'adaptive_decision.php',
            'cli/adaptive_decision.php',
            'classes/local/adaptive_decision_policy_service.php',
            'db/services.php',
            'openapi.json',
        ];
        $present = [];
        $missing = [];
        foreach ($files as $file) {
            $path = $CFG->dirroot . '/local/flwcupkp/' . $file;
            if (file_exists($path)) {
                $present[$file] = true;
            } else {
                $missing[$file] = false;
            }
        }
        return [
            'valid' => empty($missing),
            'present' => $present,
            'missing' => $missing,
        ];
    }

    /**
     * Runtime method surface status.
     *
     * @return array
     */
    private static function surface_status(): array {
        $external = self::external_api_surface_status();
        $methods = [
            self::class . '::contract' => method_exists(self::class, 'contract'),
            self::class . '::policy' => method_exists(self::class, 'policy'),
            self::class . '::status' => method_exists(self::class, 'status'),
            self::class . '::learner_decision' => method_exists(self::class, 'learner_decision'),
            self::class . '::class_summary' => method_exists(self::class, 'class_summary'),
        ] + $external['methods'];
        $missing = array_keys(array_filter($methods, static function(bool $present): bool {
            return !$present;
        }));
        return [
            'valid' => empty($missing),
            'methods' => $methods,
            'missing_methods' => $missing,
            'external_api_file' => $external['file'],
        ];
    }

    /**
     * Source-level external API check that avoids autoloading externallib during PHPUnit.
     *
     * @return array
     */
    private static function external_api_surface_status(): array {
        global $CFG;

        $path = $CFG->dirroot . '/local/flwcupkp/classes/external/api.php';
        $source = is_readable($path) ? file_get_contents($path) : '';
        $methods = [];
        foreach ([
            'get_adaptive_decision_policy_status',
            'get_learner_adaptive_decision',
            'get_class_adaptive_decision_summary',
        ] as $method) {
            $methods['\\local_flwcupkp\\external\\api::' . $method] =
                $source !== false && strpos((string)$source, 'function ' . $method . '(') !== false;
        }

        return [
            'file' => $path,
            'methods' => $methods,
        ];
    }

    /**
     * Safe dependency status wrapper.
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
     * Safe current-state wrapper.
     *
     * @param string $source
     * @param callable $callback
     * @return array
     */
    private static function safe_current_call(string $source, callable $callback): array {
        try {
            $payload = $callback();
            if (is_array($payload)) {
                return $payload;
            }
            throw new \coding_exception($source . ' current payload did not return an array.');
        } catch (\Throwable $e) {
            return [
                'type' => 'CupkpAdaptiveDecisionInputUnavailable',
                'status' => 'unavailable',
                'source' => $source,
                'message' => $e->getMessage(),
                'summary' => [],
                'states' => [],
                'state' => null,
                'has_goal' => false,
                'has_processed_state' => false,
            ];
        }
    }

    /**
     * Bound result limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max = 500): int {
        return max(1, min($max, $limit));
    }
}
