<?php
// Program 3 Gate A4 Goal-Gap + Initial Personalized Path.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Computes an explainable initial target-level path without resolving Moodle activities.
 */
final class goal_gap_path_service {
    /** Program 3 goal-gap path gate. */
    public const GATE = 'P3_A4';

    /** Frozen A4 service contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_GOAL_GAP_INITIAL_PATH_V1';

    /** Deterministic path policy version. */
    public const PATH_POLICY_VERSION = 'cupkp-goal-gap-initial-path-v1';

    /** Next allowed gate after A4. */
    public const NEXT_ALLOWED_GATE = 'A4B';

    /** @var array Strong mastery states by target type. */
    private const STRONG_STATES = [
        'kp' => ['independent_use', 'mastered', 'review_due'],
        'up' => ['demonstrated', 'stable', 'transfer_ready'],
        'competency' => ['achieved', 'sustained'],
    ];

    /** @var array Stable target-type order used for path tie-breaking. */
    private const TARGET_TYPE_ORDER = [
        'kp' => 10,
        'up' => 20,
        'competency' => 30,
    ];

    /** @var array Stable candidate action order. Lower number wins. */
    private const ACTION_ORDER = [
        'relearn' => 10,
        'review' => 20,
        'repair_prerequisite' => 30,
        'remediate' => 40,
        'retry' => 50,
        'introduce' => 60,
        'reassess' => 70,
        'confirm' => 80,
    ];

    /**
     * Return the frozen A4 contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpGoalGapInitialPathContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                adaptive_decision_policy_service::CONTRACT_VERSION,
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
            'path_policy_version' => self::PATH_POLICY_VERSION,
            'inputs' => [
                'A1 learner goal',
                'E2 current learner state',
                'C-UP-KP requirements from C2 relationship traversal',
                'C2 prerequisites',
                'E3 retention state',
                'A3 adaptive policy decision',
            ],
            'gap_dimensions' => self::gap_dimensions(),
            'outputs' => [
                'goal_gap_analysis',
                'missing KP/UP/C',
                'satisfied KP/UP/C',
                'blocked-by-prerequisite items',
                'candidate next targets',
                'initial personalized path',
                'NEXT TARGET',
                'PROJECTED ROADMAP',
                'DESTINATION',
            ],
            'read_only_surface' => [
                'contract',
                'policy',
                'status',
                'learner_path',
                'class_summary',
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
                'continuous_adaptation',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Visible path policy used by the A4 runtime.
     *
     * @return array
     */
    public static function policy(): array {
        $a3policy = self::safe_policy();
        return [
            'version' => self::PATH_POLICY_VERSION,
            'adaptive_policy_version' => adaptive_decision_policy_service::ADAPTIVE_POLICY_VERSION,
            'thresholds' => $a3policy['thresholds'] ?? self::fallback_thresholds(),
            'gap_dimensions' => self::gap_dimensions(),
            'candidate_priority' => [
                'a3_selected_target_bonus' => true,
                'goal_target_bonus' => true,
                'action_order' => self::ACTION_ORDER,
                'target_type_order' => self::TARGET_TYPE_ORDER,
                'blocked_goal_target_promotes_missing_hard_prerequisite' => true,
            ],
            'roadmap' => [
                'max_candidate_steps' => 6,
                'always_ends_with_destination' => true,
                'activity_resolution' => 'not_allowed_until_A4B',
                'continuous_adaptation' => 'not_enabled_until_A5',
            ],
            'tie_breaking' => [
                'candidate_priority_ascending',
                'a3_selected_target_before_other_targets',
                'explicit_goal_target_before_derived_requirement',
                'target_type_order_kp_up_competency',
                'target_externalid_ascending',
                'target_id_ascending',
            ],
        ];
    }

    /**
     * Readiness status for A4.
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
        $a3 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid, $limit): array {
            return adaptive_decision_policy_service::status($courseid, $unitcode, $frameworkid, $limit);
        });
        $graph = self::safe_status_call(static function() use ($courseid, $frameworkid, $limit): array {
            return relationship_graph_contract::graph_status($courseid, $frameworkid, $limit);
        });
        $history = self::safe_status_call(static function() use ($courseid): array {
            return history_v1_consumer_contract::contract_status($courseid, 1);
        });
        $policy = self::policy();
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = self::criteria($a3, $graph, $history, $files, $surface, $policy);
        $criteriasummary = self::criteria_summary($criteria);
        $classsummary = $courseid > 0 ?
            self::class_summary($courseid, $unitcode, $frameworkid, min(50, $limit))['summary'] :
            self::empty_class_summary();

        return [
            'type' => 'CupkpGoalGapInitialPathStatus',
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
                'adaptive_decision_policy_service' => self::dependency_summary($a3),
                'relationship_graph_contract' => self::dependency_summary($graph),
                'history_v1' => self::dependency_summary($history),
            ],
            'policy' => $policy,
            'files' => $files,
            'surface' => $surface,
            'summary' => $classsummary,
            'findings' => self::status_findings($criteria, [$a3, $graph, $history]),
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Compute one learner's initial target-level path.
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
        $limit = self::bounded_limit($limit, 500);

        $goal = self::safe_current_call('learning_goal', static function() use ($userid, $courseid, $unitcode,
                $frameworkid): array {
            return learning_goal_service::current_goal($userid, $courseid, $unitcode, $frameworkid, 20);
        });
        $mastery = self::safe_current_call('mastery_state', static function() use ($userid, $courseid, $unitcode,
                $frameworkid, $limit): array {
            return mastery_state_service::current_learner_state($userid, $courseid, $unitcode, $frameworkid, $limit);
        });
        $retention = self::safe_current_call('retention_review', static function() use ($userid, $courseid,
                $unitcode, $frameworkid, $limit): array {
            return retention_review_service::current_retention_state($userid, $courseid, $unitcode, $frameworkid,
                $limit);
        });
        $decision = self::safe_current_call('adaptive_decision', static function() use ($userid, $courseid,
                $unitcode, $frameworkid, $limit): array {
            return adaptive_decision_policy_service::learner_decision($userid, $courseid, $unitcode, $frameworkid,
                $limit);
        });

        $stateindex = self::state_index($mastery['states'] ?? []);
        $retentionindex = self::retention_index($retention['states'] ?? []);
        $requirements = self::requirements_for_goal($goal, $courseid, $unitcode, $frameworkid, $limit);
        if (!$requirements) {
            $requirements = self::fallback_scope_requirements($goal, $decision, $courseid, $unitcode, $frameworkid,
                $limit);
        }

        $analysis = self::goal_gap_analysis($requirements, $stateindex, $retentionindex, $frameworkid);
        $candidates = self::candidate_targets($analysis, $decision);
        $nexttarget = self::next_target($candidates, $decision);
        $destination = self::destination($goal, $decision);
        $roadmap = self::projected_roadmap($decision, $analysis, $candidates, $destination);
        $pathhash = self::path_hash($userid, $courseid, $unitcode, $frameworkid, $destination, $analysis,
            $candidates, $decision);

        return [
            'type' => 'CupkpLearnerGoalGapInitialPath',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'path_policy_version' => self::PATH_POLICY_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'path_status' => self::path_status($goal, $decision, $analysis, $candidates),
            'goal_gap_analysis' => $analysis,
            'candidate_next_targets' => $candidates,
            'initial_personalized_path' => [
                'next_target' => $nexttarget,
                'projected_roadmap' => $roadmap,
                'destination' => $destination,
            ],
            'next_target' => $nexttarget,
            'projected_roadmap' => $roadmap,
            'destination' => $destination,
            'explainability' => [
                'path_hash' => $pathhash,
                'gap_hash' => sha1(json_encode($analysis['summary'], JSON_UNESCAPED_SLASHES)),
                'adaptive_decision_hash' => $decision['explainability']['decision_hash'] ?? '',
                'selected_a3_decision' => $decision['decision'] ?? null,
                'path_policy_version' => self::PATH_POLICY_VERSION,
                'adaptive_policy_version' => $decision['policy_version'] ??
                    adaptive_decision_policy_service::ADAPTIVE_POLICY_VERSION,
                'considered_dimensions' => self::gap_dimensions(),
                'tie_breaking' => self::policy()['tie_breaking'],
                'source_snapshots' => self::source_snapshots($goal, $mastery, $retention, $decision),
                'non_actions' => [
                    'no_moodle_activity_resolution',
                    'no_continuous_adaptation',
                    'no_persistent_path_generation',
                    'no_recommendation_row_write',
                    'no_mastery_or_retention_mutation',
                    'no_history_v1_source_mutation',
                ],
            ],
            'inputs' => [
                'goal' => $goal,
                'mastery_summary' => $mastery['summary'] ?? [],
                'retention_summary' => $retention['summary'] ?? [],
                'adaptive_decision' => $decision['decision'] ?? null,
            ],
            'read_only' => true,
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Class-level A4 path summary.
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
                $path = self::learner_path((int)$learnerid, $courseid, $unitcode, $frameworkid, 120);
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

            $status = (string)($path['path_status'] ?? 'unknown');
            $summary['statuses'][$status] = ($summary['statuses'][$status] ?? 0) + 1;
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            $gapsummary = $path['goal_gap_analysis']['summary'] ?? [];
            $summary['missing_kp'] += (int)($gapsummary['missing']['kp'] ?? 0);
            $summary['missing_up'] += (int)($gapsummary['missing']['up'] ?? 0);
            $summary['missing_competency'] += (int)($gapsummary['missing']['competency'] ?? 0);
            $summary['blocked_kp'] += (int)($gapsummary['blocked_by_prerequisite']['kp'] ?? 0);
            $summary['blocked_up'] += (int)($gapsummary['blocked_by_prerequisite']['up'] ?? 0);
            $summary['blocked_competency'] += (int)($gapsummary['blocked_by_prerequisite']['competency'] ?? 0);
            $summary['satisfied_kp'] += (int)($gapsummary['satisfied']['kp'] ?? 0);
            $summary['satisfied_up'] += (int)($gapsummary['satisfied']['up'] ?? 0);
            $summary['satisfied_competency'] += (int)($gapsummary['satisfied']['competency'] ?? 0);
            $summary['candidate_target_count'] += count($path['candidate_next_targets'] ?? []);
            if (!empty($path['next_target'])) {
                $summary['next_target_count']++;
            }

            $rows[] = [
                'userid' => (int)$learnerid,
                'learner' => self::learner_identity((int)$learnerid),
                'path_status' => $status,
                'summary' => $gapsummary,
                'next_target' => $path['next_target'],
                'destination' => $path['destination'],
                'path_hash' => $path['explainability']['path_hash'],
            ];
        }

        arsort($summary['statuses']);
        return [
            'type' => 'CupkpClassGoalGapInitialPathSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'path_policy_version' => self::PATH_POLICY_VERSION,
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
            'path_persistence_allowed' => false,
            'moodle_activity_resolution_allowed' => false,
            'continuous_adaptation_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * A4 prompt dimensions.
     *
     * @return array
     */
    private static function gap_dimensions(): array {
        return [
            'mastery_deficit',
            'confidence_deficit',
            'missing_performance_mode',
            'retention_verification',
            'missing_prerequisite',
            'goal_priority',
        ];
    }

    /**
     * Build explicit and relationship-derived requirements from the A1 goal.
     *
     * @param array $goal
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function requirements_for_goal(array $goal, int $courseid, string $unitcode,
            int $frameworkid, int $limit): array {
        $targets = self::goal_targets($goal);
        if (!$targets) {
            return [];
        }
        $requirements = [];
        $order = 0;
        foreach ($targets as $target) {
            $target = self::normalize_target($target);
            self::add_requirement($requirements, $target, 'explicit_goal', null, [], true, 0, $order++);
            foreach (self::dependency_edges($target, $frameworkid) as $edge) {
                $dependency = self::target_from_node((string)($edge['target'] ?? ''));
                if (!$dependency) {
                    continue;
                }
                $source = self::target_from_node((string)($edge['source'] ?? ''));
                self::add_requirement($requirements, self::target_reference($dependency['type'], $dependency['id']),
                    self::level_for_edge($edge), $source, $edge, false,
                    self::depth_for_dependency($target, $dependency), $order++);
            }
        }
        self::sort_requirement_map($requirements);
        return array_slice(array_values($requirements), 0, self::bounded_limit($limit, 500));
    }

    /**
     * Fallback target universe when the goal has a profile but no explicit IDs.
     *
     * @param array $goal
     * @param array $decision
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function fallback_scope_requirements(array $goal, array $decision, int $courseid,
            string $unitcode, int $frameworkid, int $limit): array {
        $requirements = [];
        $order = 0;
        $decisiontarget = $decision['next_target'] ?? null;
        if (is_array($decisiontarget) && !empty($decisiontarget['type']) && !empty($decisiontarget['id'])) {
            self::add_requirement($requirements, self::target_reference((string)$decisiontarget['type'],
                (int)$decisiontarget['id']), 'adaptive_decision_target', null, [], false, 0, $order++);
        }

        if (!empty($goal['has_goal'])) {
            foreach (self::scoped_mapped_targets($courseid, $unitcode, $frameworkid, $limit) as $target) {
                self::add_requirement($requirements, $target, 'scoped_goal_requirement', null, [], false, 1,
                    $order++);
            }
        }

        self::sort_requirement_map($requirements);
        return array_slice(array_values($requirements), 0, self::bounded_limit($limit, 500));
    }

    /**
     * Add or merge a requirement row.
     *
     * @param array $requirements
     * @param array $target
     * @param string $level
     * @param array|null $requiredby
     * @param array $edge
     * @param bool $goaltarget
     * @param int $depth
     * @param int $order
     */
    private static function add_requirement(array &$requirements, array $target, string $level,
            ?array $requiredby, array $edge, bool $goaltarget, int $depth, int $order): void {
        $target = self::normalize_target($target);
        if (empty($target['type']) || empty($target['id'])) {
            return;
        }
        $key = self::target_key($target);
        if (!isset($requirements[$key])) {
            $requirements[$key] = [
                'key' => $key,
                'target' => $target,
                'level' => $level,
                'goal_target' => $goaltarget,
                'required_by' => [],
                'relationship_edges' => [],
                'depth' => $depth,
                'sortorder' => $order,
            ];
        }
        $requirements[$key]['goal_target'] = !empty($requirements[$key]['goal_target']) || $goaltarget;
        $requirements[$key]['depth'] = min((int)$requirements[$key]['depth'], $depth);
        $requirements[$key]['sortorder'] = min((int)$requirements[$key]['sortorder'], $order);
        if ($level === 'explicit_goal') {
            $requirements[$key]['level'] = 'explicit_goal';
        } else if (($requirements[$key]['level'] ?? '') !== 'explicit_goal' &&
                $level === 'prerequisite') {
            $requirements[$key]['level'] = 'prerequisite';
        }
        if ($requiredby) {
            $requirements[$key]['required_by'][self::target_key($requiredby)] = self::normalize_target($requiredby);
        }
        if ($edge) {
            $requirements[$key]['relationship_edges'][] = self::compact_edge($edge);
        }
    }

    /**
     * Run C2 dependency traversal for a target.
     *
     * @param array $target
     * @param int $frameworkid
     * @return array
     */
    private static function dependency_edges(array $target, int $frameworkid): array {
        try {
            $dependencies = relationship_graph_contract::dependencies_for_target(
                (string)($target['type'] ?? ''),
                (int)($target['id'] ?? 0),
                $frameworkid
            );
            return array_values($dependencies['edges'] ?? []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Analyze each requirement into satisfied/missing/blocked buckets.
     *
     * @param array $requirements
     * @param array $stateindex
     * @param array $retentionindex
     * @param int $frameworkid
     * @return array
     */
    private static function goal_gap_analysis(array $requirements, array $stateindex,
            array $retentionindex, int $frameworkid): array {
        $buckets = self::empty_gap_buckets();
        $all = [];
        foreach ($requirements as $requirement) {
            $row = self::analyze_requirement($requirement, $stateindex, $retentionindex, $frameworkid);
            $bucket = $row['gap_status'] === 'blocked_by_prerequisite' ? 'blocked_by_prerequisite' :
                (string)$row['gap_status'];
            if (!isset($buckets[$bucket])) {
                $bucket = 'missing';
            }
            $type = (string)$row['target']['type'];
            $buckets[$bucket][$type][] = $row;
            $all[] = $row;
        }

        return [
            'summary' => self::gap_summary($buckets),
            'missing' => $buckets['missing'],
            'satisfied' => $buckets['satisfied'],
            'blocked_by_prerequisite' => $buckets['blocked_by_prerequisite'],
            'all_requirements' => $all,
            'dimension_legend' => self::gap_dimensions(),
            'activity_resolution' => 'not_allowed_until_A4B',
        ];
    }

    /**
     * Analyze one requirement.
     *
     * @param array $requirement
     * @param array $stateindex
     * @param array $retentionindex
     * @param int $frameworkid
     * @return array
     */
    private static function analyze_requirement(array $requirement, array $stateindex,
            array $retentionindex, int $frameworkid): array {
        $target = self::normalize_target($requirement['target'] ?? []);
        $key = self::target_key($target);
        $state = $stateindex[$key] ?? null;
        $retention = $retentionindex[$key] ?? null;
        $thresholds = self::thresholds_for_target((string)$target['type']);
        $score = is_array($state) ? (float)($state['mastery']['score'] ?? 0) : 0.0;
        $confidence = is_array($state) ? (float)($state['confidence']['score'] ?? 0) : 0.0;
        $strong = is_array($state) && !empty($state['mastery']['strong']);
        $evidencecount = is_array($state) ? (int)($state['evidence']['count'] ?? 0) : 0;
        $modes = is_array($state) && is_array($state['evidence']['by_performance_mode'] ?? null) ?
            $state['evidence']['by_performance_mode'] : [];
        $retentionstate = strtolower((string)($retention['retention']['state'] ??
            ($state['retention']['state'] ?? '')));
        $readiness = self::prerequisite_readiness($target, $stateindex, $frameworkid);
        $missinghard = !empty($readiness['blocking']);
        $retentionneedsverification = in_array($retentionstate, [
            'review_due',
            'retention_uncertain',
            'relearning',
        ], true);
        $masterydeficit = !$strong && $score < (float)$thresholds['mastery']['advance_score'];
        $confidencedeficit = is_array($state) && $confidence > 0 &&
            $confidence < (float)$thresholds['confidence']['low_below'];
        $missingperformance = !is_array($state) || $evidencecount <= 0 || empty($modes);
        $satisfied = is_array($state) && !$missinghard && !$retentionneedsverification &&
            !$confidencedeficit && ($strong || $score >= (float)$thresholds['mastery']['advance_score']);

        $dimensions = [
            'mastery_deficit' => $masterydeficit,
            'confidence_deficit' => $confidencedeficit,
            'missing_performance_mode' => $missingperformance,
            'retention_verification' => $retentionneedsverification,
            'missing_prerequisite' => $missinghard,
            'goal_priority' => !empty($requirement['goal_target']),
        ];
        $reasons = self::gap_reasons($dimensions, $state, $retentionstate, $score, $confidence, $thresholds);
        $gapstatus = $missinghard ? 'blocked_by_prerequisite' : ($satisfied ? 'satisfied' : 'missing');

        return [
            'target' => $target,
            'gap_status' => $gapstatus,
            'level' => (string)($requirement['level'] ?? ''),
            'goal_target' => !empty($requirement['goal_target']),
            'required_by' => array_values($requirement['required_by'] ?? []),
            'relationship_edges' => array_values($requirement['relationship_edges'] ?? []),
            'depth' => (int)($requirement['depth'] ?? 0),
            'sortorder' => (int)($requirement['sortorder'] ?? 0),
            'dimensions' => $dimensions,
            'reasons' => $reasons,
            'state' => [
                'has_state' => is_array($state),
                'mastery_score' => round($score, 5),
                'mastery_state' => is_array($state) ? (string)($state['mastery']['state'] ?? '') : '',
                'strong' => $strong,
                'confidence' => round($confidence, 5),
                'evidence_count' => $evidencecount,
                'performance_modes' => $modes,
            ],
            'retention' => [
                'state' => $retentionstate,
                'needs_verification' => $retentionneedsverification,
            ],
            'prerequisites' => $readiness,
            'activity_resolution' => 'not_allowed_until_A4B',
        ];
    }

    /**
     * Convert gaps to candidate next targets.
     *
     * @param array $analysis
     * @param array $decision
     * @return array
     */
    private static function candidate_targets(array $analysis, array $decision): array {
        $candidates = [];
        $a3targetkey = self::target_key($decision['next_target'] ?? null);

        foreach (self::flatten_buckets($analysis['blocked_by_prerequisite'] ?? []) as $row) {
            foreach (($row['prerequisites']['blocking'] ?? []) as $blocker) {
                $target = $blocker['target'] ?? null;
                if (!is_array($target) || empty($target['type']) || empty($target['id'])) {
                    continue;
                }
                self::add_candidate($candidates, $target, 'PREREQUISITE_REQUIRED', 'repair_prerequisite',
                    'Hard prerequisite blocks ' . self::target_label($row['target']) . '.', $row, $a3targetkey);
            }
        }

        foreach (self::flatten_buckets($analysis['missing'] ?? []) as $row) {
            $action = self::action_for_gap($row);
            self::add_candidate($candidates, $row['target'], $action['code'], $action['action'], $action['reason'],
                $row, $a3targetkey);
        }

        $candidates = array_values($candidates);
        usort($candidates, [self::class, 'compare_candidates']);
        foreach ($candidates as $index => $candidate) {
            $candidates[$index]['rank'] = $index + 1;
        }
        return $candidates;
    }

    /**
     * Add or merge a target candidate.
     *
     * @param array $candidates
     * @param array $target
     * @param string $code
     * @param string $action
     * @param string $reason
     * @param array $gaprow
     * @param string $a3targetkey
     */
    private static function add_candidate(array &$candidates, array $target, string $code, string $action,
            string $reason, array $gaprow, string $a3targetkey): void {
        $target = self::normalize_target($target);
        $key = self::target_key($target);
        if ($key === ':0') {
            return;
        }
        $priority = (self::ACTION_ORDER[$action] ?? 999) +
            (self::TARGET_TYPE_ORDER[(string)$target['type']] ?? 999);
        if ($key === $a3targetkey) {
            $priority -= 25;
        }
        if (!empty($gaprow['goal_target'])) {
            $priority -= 5;
        }
        $source = [
            'gap_target' => $gaprow['target'] ?? $target,
            'gap_status' => $gaprow['gap_status'] ?? '',
            'level' => $gaprow['level'] ?? '',
            'reasons' => $gaprow['reasons'] ?? [],
        ];

        if (!isset($candidates[$key])) {
            $candidates[$key] = [
                'rank' => 0,
                'code' => $code,
                'action' => $action,
                'target' => $target,
                'priority' => $priority,
                'reason' => $reason,
                'source_gaps' => [$source],
                'a3_selected_target' => $key === $a3targetkey,
                'goal_target' => !empty($gaprow['goal_target']),
                'activity_resolution' => 'not_allowed_until_A4B',
                'continuous_adaptation' => 'not_enabled_until_A5',
            ];
            return;
        }

        $candidates[$key]['priority'] = min((int)$candidates[$key]['priority'], $priority);
        $candidates[$key]['a3_selected_target'] = !empty($candidates[$key]['a3_selected_target']) ||
            $key === $a3targetkey;
        $candidates[$key]['goal_target'] = !empty($candidates[$key]['goal_target']) ||
            !empty($gaprow['goal_target']);
        $candidates[$key]['source_gaps'][] = $source;
    }

    /**
     * First candidate target, or null if no target-level next step exists yet.
     *
     * @param array $candidates
     * @param array $decision
     * @return array|null
     */
    private static function next_target(array $candidates, array $decision): ?array {
        if ($candidates) {
            return $candidates[0]['target'];
        }
        return null;
    }

    /**
     * Build a target-level projected roadmap.
     *
     * @param array $decision
     * @param array $analysis
     * @param array $candidates
     * @param array $destination
     * @return array
     */
    private static function projected_roadmap(array $decision, array $analysis, array $candidates,
            array $destination): array {
        $steps = [];
        $step = 1;
        $setup = self::setup_step_from_decision($decision);
        if ($setup) {
            $setup['step'] = $step++;
            $steps[] = $setup;
        }

        foreach (array_slice($candidates, 0, 6) as $candidate) {
            $steps[] = [
                'step' => $step++,
                'stage' => self::stage_for_action((string)$candidate['action']),
                'code' => (string)$candidate['code'],
                'action' => (string)$candidate['action'],
                'target' => $candidate['target'],
                'selected' => $candidate['rank'] === 1 && !$setup,
                'reason' => (string)$candidate['reason'],
                'source_gap_count' => count($candidate['source_gaps'] ?? []),
                'activity_resolution' => 'not_allowed_until_A4B',
                'continuous_adaptation' => 'not_enabled_until_A5',
            ];
        }

        if (!$candidates && !$setup) {
            $steps[] = [
                'step' => $step++,
                'stage' => 'confirm',
                'code' => 'DESTINATION_READY',
                'action' => 'confirm_goal_readiness',
                'target' => null,
                'selected' => true,
                'reason' => 'No missing or prerequisite-blocked A4 requirements remain in this scope.',
                'activity_resolution' => 'not_allowed_until_A4B',
                'continuous_adaptation' => 'not_enabled_until_A5',
            ];
        }

        $steps[] = [
            'step' => $step,
            'stage' => 'destination',
            'code' => 'DESTINATION',
            'action' => 'reach_goal',
            'target' => null,
            'selected' => false,
            'destination' => $destination,
            'gap_summary' => $analysis['summary'] ?? [],
            'activity_resolution' => 'not_allowed_until_A4B',
            'continuous_adaptation' => 'not_enabled_until_A5',
        ];
        return $steps;
    }

    /**
     * Return setup/current-blocker step from A3.
     *
     * @param array $decision
     * @return array|null
     */
    private static function setup_step_from_decision(array $decision): ?array {
        $selected = $decision['decision'] ?? [];
        if (!is_array($selected)) {
            return null;
        }
        $code = (string)($selected['code'] ?? '');
        if (!in_array($code, [
                'GOAL_REQUIRED',
                'GOAL_REVIEW',
                'PLACEMENT_REQUIRED',
                'DIAGNOSTIC_INCOMPLETE',
                'PLACEMENT_REVIEW',
            ], true)) {
            return null;
        }
        return [
            'stage' => 'setup',
            'code' => $code,
            'action' => (string)($selected['action'] ?? 'review'),
            'target' => null,
            'selected' => true,
            'reason' => (string)($selected['rule'] ?? 'A3 requires setup before target-level routing can execute.'),
            'activity_resolution' => 'not_allowed_until_A4B',
            'continuous_adaptation' => 'not_enabled_until_A5',
        ];
    }

    /**
     * Destination snapshot from A1/A3.
     *
     * @param array $goal
     * @param array $decision
     * @return array
     */
    private static function destination(array $goal, array $decision): array {
        if (!empty($decision['destination']) && is_array($decision['destination'])) {
            return $decision['destination'];
        }
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
     * Determine path status.
     *
     * @param array $goal
     * @param array $decision
     * @param array $analysis
     * @param array $candidates
     * @return string
     */
    private static function path_status(array $goal, array $decision, array $analysis, array $candidates): string {
        if (empty($goal['has_goal'])) {
            return 'needs_goal';
        }
        $code = (string)($decision['decision']['code'] ?? '');
        if (in_array($code, [
                'GOAL_REVIEW',
                'PLACEMENT_REQUIRED',
                'DIAGNOSTIC_INCOMPLETE',
                'PLACEMENT_REVIEW',
            ], true)) {
            return 'needs_setup';
        }
        $summary = $analysis['summary'] ?? [];
        if ((int)($summary['blocked_total'] ?? 0) > 0) {
            return 'blocked_by_prerequisite';
        }
        if ((int)($summary['missing_total'] ?? 0) > 0 || $candidates) {
            return 'ready_to_work';
        }
        return 'destination_ready';
    }

    /**
     * Readiness for hard C2 prerequisite edges.
     *
     * @param array $target
     * @param array $stateindex
     * @param int $frameworkid
     * @return array
     */
    private static function prerequisite_readiness(array $target, array $stateindex, int $frameworkid): array {
        $thresholds = self::policy()['thresholds']['prerequisite'] ?? self::fallback_thresholds()['prerequisite'];
        $checked = [];
        $blocking = [];
        foreach (self::dependency_edges($target, $frameworkid) as $edge) {
            if (empty($edge['hard_prerequisite'])) {
                continue;
            }
            $dependency = self::target_from_node((string)($edge['target'] ?? ''));
            if (!$dependency) {
                continue;
            }
            $requiredtarget = self::target_reference($dependency['type'], $dependency['id']);
            $state = $stateindex[self::target_key($requiredtarget)] ?? null;
            $score = is_array($state) ? (float)($state['mastery']['score'] ?? 0) : 0.0;
            $strong = is_array($state) && !empty($state['mastery']['strong']);
            $ready = $strong || $score >= (float)$thresholds['hard_min_score'];
            $row = [
                'target' => $requiredtarget,
                'edge' => self::compact_edge($edge),
                'minimum_score' => (float)$thresholds['hard_min_score'],
                'current_score' => round($score, 5),
                'strong_state' => $strong,
                'ready' => $ready,
            ];
            $checked[] = $row;
            if (!$ready) {
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
     * Build a goal target list.
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
        uasort($targets, [self::class, 'compare_targets']);
        return array_values($targets);
    }

    /**
     * Target references from object mappings in scope.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function scoped_mapped_targets(int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
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
            "SELECT DISTINCT m.targettype, m.targetid
               FROM {flwcupkp_object_map} m
               JOIN {flwcupkp_object} o ON o.id = m.objectid
              WHERE {$where}
           ORDER BY m.targettype ASC, m.targetid ASC",
            $params,
            0,
            self::bounded_limit($limit, 500)
        );
        $targets = [];
        foreach ($records as $record) {
            $target = self::target_reference((string)$record->targettype, (int)$record->targetid);
            if (!empty($target['type']) && !empty($target['id'])) {
                $targets[self::target_key($target)] = $target;
            }
        }
        uasort($targets, [self::class, 'compare_targets']);
        return array_values($targets);
    }

    /**
     * Create an E2 state index.
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
            $target = self::normalize_target($state['target'] ?? []);
            if (!empty($target['type']) && !empty($target['id'])) {
                $index[self::target_key($target)] = $state;
            }
        }
        return $index;
    }

    /**
     * Create an E3 retention index.
     *
     * @param array $states
     * @return array
     */
    private static function retention_index(array $states): array {
        $index = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }
            $target = self::normalize_target($state['target'] ?? []);
            if (!empty($target['type']) && !empty($target['id'])) {
                $index[self::target_key($target)] = $state;
            }
        }
        return $index;
    }

    /**
     * Choose action/code for a missing gap row.
     *
     * @param array $row
     * @return array
     */
    private static function action_for_gap(array $row): array {
        $retention = (string)($row['retention']['state'] ?? '');
        if ($retention === 'relearning') {
            return [
                'code' => 'RELEARNING_REQUIRED',
                'action' => 'relearn',
                'reason' => 'Retention state requires relearning while preserving mastery.',
            ];
        }
        if ($retention === 'review_due') {
            return [
                'code' => 'REVIEW_REQUIRED',
                'action' => 'review',
                'reason' => 'Retention verification is due.',
            ];
        }
        if ($retention === 'retention_uncertain') {
            return [
                'code' => 'REASSESSMENT_RECOMMENDED',
                'action' => 'reassess',
                'reason' => 'Retention confidence is uncertain.',
            ];
        }
        if (!empty($row['dimensions']['confidence_deficit'])) {
            return [
                'code' => 'REASSESSMENT_RECOMMENDED',
                'action' => 'reassess',
                'reason' => 'Confidence is below the visible A3 threshold.',
            ];
        }
        if (empty($row['state']['has_state']) || !empty($row['dimensions']['missing_performance_mode'])) {
            return [
                'code' => 'INTRODUCE_TARGET',
                'action' => 'introduce',
                'reason' => 'No target-appropriate performance evidence is available yet.',
            ];
        }
        $score = (float)($row['state']['mastery_score'] ?? 0);
        $thresholds = self::thresholds_for_target((string)($row['target']['type'] ?? 'kp'));
        if ($score < (float)$thresholds['mastery']['remediate_below']) {
            return [
                'code' => 'REMEDIATION_REQUIRED',
                'action' => 'remediate',
                'reason' => 'Mastery is below the remediation threshold.',
            ];
        }
        return [
            'code' => 'RETRY_RECOMMENDED',
            'action' => 'retry',
            'reason' => 'Mastery is partial but not yet advance-ready.',
        ];
    }

    /**
     * Reason strings for one gap.
     *
     * @param array $dimensions
     * @param array|null $state
     * @param string $retentionstate
     * @param float $score
     * @param float $confidence
     * @param array $thresholds
     * @return array
     */
    private static function gap_reasons(array $dimensions, ?array $state, string $retentionstate,
            float $score, float $confidence, array $thresholds): array {
        $reasons = [];
        if (!$state) {
            $reasons[] = 'No current learner-state row exists for this target.';
        }
        if (!empty($dimensions['mastery_deficit'])) {
            $reasons[] = 'Mastery score ' . round($score, 2) . ' is below advance threshold ' .
                round((float)$thresholds['mastery']['advance_score'], 2) . '.';
        }
        if (!empty($dimensions['confidence_deficit'])) {
            $reasons[] = 'Confidence ' . round($confidence, 2) . ' is below low-confidence threshold ' .
                round((float)$thresholds['confidence']['low_below'], 2) . '.';
        }
        if (!empty($dimensions['missing_performance_mode'])) {
            $reasons[] = 'No target-appropriate performance mode is represented in current evidence.';
        }
        if (!empty($dimensions['retention_verification'])) {
            $reasons[] = 'Retention state requires verification: ' . $retentionstate . '.';
        }
        if (!empty($dimensions['missing_prerequisite'])) {
            $reasons[] = 'A mandatory C2 prerequisite is not ready.';
        }
        if (!$reasons) {
            $reasons[] = 'Requirement is satisfied under the visible A4 policy.';
        }
        return $reasons;
    }

    /**
     * Summary from gap buckets.
     *
     * @param array $buckets
     * @return array
     */
    private static function gap_summary(array $buckets): array {
        $summary = [
            'missing' => self::bucket_counts($buckets['missing']),
            'satisfied' => self::bucket_counts($buckets['satisfied']),
            'blocked_by_prerequisite' => self::bucket_counts($buckets['blocked_by_prerequisite']),
        ];
        $summary['missing_total'] = array_sum($summary['missing']);
        $summary['satisfied_total'] = array_sum($summary['satisfied']);
        $summary['blocked_total'] = array_sum($summary['blocked_by_prerequisite']);
        $summary['requirement_total'] = $summary['missing_total'] + $summary['satisfied_total'] +
            $summary['blocked_total'];
        return $summary;
    }

    /**
     * Count rows by target type.
     *
     * @param array $bucket
     * @return array
     */
    private static function bucket_counts(array $bucket): array {
        return [
            'kp' => count($bucket['kp'] ?? []),
            'up' => count($bucket['up'] ?? []),
            'competency' => count($bucket['competency'] ?? []),
        ];
    }

    /**
     * Empty gap bucket structure.
     *
     * @return array
     */
    private static function empty_gap_buckets(): array {
        return [
            'missing' => ['kp' => [], 'up' => [], 'competency' => []],
            'satisfied' => ['kp' => [], 'up' => [], 'competency' => []],
            'blocked_by_prerequisite' => ['kp' => [], 'up' => [], 'competency' => []],
        ];
    }

    /**
     * Flatten target-type buckets.
     *
     * @param array $bucket
     * @return array
     */
    private static function flatten_buckets(array $bucket): array {
        $rows = [];
        foreach (['kp', 'up', 'competency'] as $type) {
            foreach (($bucket[$type] ?? []) as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Compact source snapshots for explanation.
     *
     * @param array $goal
     * @param array $mastery
     * @param array $retention
     * @param array $decision
     * @return array
     */
    private static function source_snapshots(array $goal, array $mastery, array $retention, array $decision): array {
        return [
            'goal' => [
                'has_goal' => !empty($goal['has_goal']),
                'status' => $goal['goal']['status'] ?? null,
                'contract' => $goal['contract'] ?? null,
            ],
            'mastery' => [
                'summary' => $mastery['summary'] ?? [],
                'contract' => $mastery['contract'] ?? null,
            ],
            'retention' => [
                'summary' => $retention['summary'] ?? [],
                'contract' => $retention['contract'] ?? null,
            ],
            'adaptive_decision' => [
                'code' => $decision['decision']['code'] ?? null,
                'target' => $decision['next_target'] ?? null,
                'contract' => $decision['contract'] ?? null,
                'decision_hash' => $decision['explainability']['decision_hash'] ?? '',
            ],
        ];
    }

    /**
     * Deterministic path hash.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $destination
     * @param array $analysis
     * @param array $candidates
     * @param array $decision
     * @return string
     */
    private static function path_hash(int $userid, int $courseid, string $unitcode, int $frameworkid,
            array $destination, array $analysis, array $candidates, array $decision): string {
        $fingerprint = [
            'policy' => self::PATH_POLICY_VERSION,
            'adaptive_decision_hash' => $decision['explainability']['decision_hash'] ?? '',
            'userid' => $userid,
            'courseid' => $courseid,
            'unitcode' => $unitcode,
            'frameworkid' => $frameworkid,
            'destination' => $destination,
            'gap_summary' => $analysis['summary'] ?? [],
            'candidates' => array_map(static function(array $candidate): array {
                return [
                    'code' => $candidate['code'] ?? '',
                    'action' => $candidate['action'] ?? '',
                    'target' => $candidate['target'] ?? null,
                    'priority' => $candidate['priority'] ?? 0,
                ];
            }, $candidates),
        ];
        return sha1(json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Learner IDs for class summary.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    private static function learner_ids_for_scope(int $courseid, string $unitcode, int $frameworkid,
            int $limit): array {
        global $DB;

        $limit = self::bounded_limit($limit, 500);
        $userids = [];
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname ASC, u.firstname ASC, u.id ASC', 0,
            $limit, true);
        foreach ($users as $user) {
            self::add_learner_id($userids, (int)$user->id);
        }

        $where = 'courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($unitcode !== '') {
            $where .= ' AND unitcode = :unitcode';
            $params['unitcode'] = $unitcode;
        }
        if ($frameworkid > 0) {
            $where .= ' AND frameworkid = :frameworkid';
            $params['frameworkid'] = $frameworkid;
        }
        $goals = $DB->get_records_select('flwcupkp_goal', $where, $params, 'userid ASC', 'DISTINCT userid', 0,
            $limit);
        foreach ($goals as $goal) {
            self::add_learner_id($userids, (int)$goal->userid);
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
     * Learner identity for summaries.
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
     * Empty class summary.
     *
     * @return array
     */
    private static function empty_class_summary(): array {
        return [
            'learners' => 0,
            'skipped_unenrolled' => 0,
            'needs_goal' => 0,
            'needs_setup' => 0,
            'ready_to_work' => 0,
            'blocked_by_prerequisite' => 0,
            'destination_ready' => 0,
            'missing_kp' => 0,
            'missing_up' => 0,
            'missing_competency' => 0,
            'blocked_kp' => 0,
            'blocked_up' => 0,
            'blocked_competency' => 0,
            'satisfied_kp' => 0,
            'satisfied_up' => 0,
            'satisfied_competency' => 0,
            'candidate_target_count' => 0,
            'next_target_count' => 0,
            'statuses' => [],
        ];
    }

    /**
     * Sort requirement map in place.
     *
     * @param array $requirements
     */
    private static function sort_requirement_map(array &$requirements): void {
        uasort($requirements, static function(array $a, array $b): int {
            $depth = (int)$a['depth'] <=> (int)$b['depth'];
            if ($depth !== 0) {
                return $depth;
            }
            $order = (int)$a['sortorder'] <=> (int)$b['sortorder'];
            if ($order !== 0) {
                return $order;
            }
            return goal_gap_path_service::compare_targets($a['target'] ?? null, $b['target'] ?? null);
        });
        foreach ($requirements as $key => $requirement) {
            $requirements[$key]['required_by'] = array_values($requirement['required_by'] ?? []);
        }
    }

    /**
     * Compare candidates by priority then target.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private static function compare_candidates(array $a, array $b): int {
        $priority = (int)$a['priority'] <=> (int)$b['priority'];
        if ($priority !== 0) {
            return $priority;
        }
        return self::compare_targets($a['target'] ?? null, $b['target'] ?? null);
    }

    /**
     * Compare targets by stable A4 order.
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
     * Normalize a target reference.
     *
     * @param array $target
     * @return array
     */
    private static function normalize_target(array $target): array {
        $type = (string)($target['type'] ?? ($target['targettype'] ?? ''));
        return [
            'type' => self::normalize_target_type($type),
            'id' => (int)($target['id'] ?? ($target['targetid'] ?? 0)),
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
            'frameworkid' => (int)($target['frameworkid'] ?? 0),
        ];
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

        $type = self::normalize_target_type($type);
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
     * Target table.
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
        return $tables[self::normalize_target_type($type)] ?? '';
    }

    /**
     * Normalize known target type aliases.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_target_type(string $type): string {
        $type = strtolower(trim($type));
        $aliases = [
            'knowledge_point' => 'kp',
            'knowledgepoint' => 'kp',
            'use_point' => 'up',
            'usepoint' => 'up',
            'competence' => 'competency',
            'comp' => 'competency',
        ];
        return $aliases[$type] ?? $type;
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
        $target = self::normalize_target($target);
        return (string)$target['type'] . ':' . (int)$target['id'];
    }

    /**
     * Parse a graph node key.
     *
     * @param string $node
     * @return array|null
     */
    private static function target_from_node(string $node): ?array {
        if (strpos($node, ':') === false) {
            return null;
        }
        [$type, $id] = explode(':', $node, 2);
        $type = self::normalize_target_type($type);
        if (!isset(self::TARGET_TYPE_ORDER[$type]) || (int)$id <= 0) {
            return null;
        }
        return [
            'type' => $type,
            'id' => (int)$id,
        ];
    }

    /**
     * Compact C2 edge for output.
     *
     * @param array $edge
     * @return array
     */
    private static function compact_edge(array $edge): array {
        return [
            'mappingtype' => (string)($edge['mappingtype'] ?? ''),
            'mappingid' => (int)($edge['mappingid'] ?? 0),
            'relation' => (string)($edge['relation'] ?? ''),
            'source' => (string)($edge['source'] ?? ''),
            'target' => (string)($edge['target'] ?? ''),
            'hard_prerequisite' => !empty($edge['hard_prerequisite']),
        ];
    }

    /**
     * Requirement level label from graph edge.
     *
     * @param array $edge
     * @return string
     */
    private static function level_for_edge(array $edge): string {
        return (string)($edge['mappingtype'] ?? '') === 'kp_prereq' ? 'prerequisite' : 'relationship_requirement';
    }

    /**
     * Approximate graph dependency depth from source/target type.
     *
     * @param array $root
     * @param array $dependency
     * @return int
     */
    private static function depth_for_dependency(array $root, array $dependency): int {
        $rootorder = self::TARGET_TYPE_ORDER[(string)($root['type'] ?? '')] ?? 0;
        $deporder = self::TARGET_TYPE_ORDER[(string)($dependency['type'] ?? '')] ?? 0;
        if ((string)($root['type'] ?? '') === (string)($dependency['type'] ?? '')) {
            return 1;
        }
        return max(1, (int)(abs($deporder - $rootorder) / 10));
    }

    /**
     * Stage label by candidate action.
     *
     * @param string $action
     * @return string
     */
    private static function stage_for_action(string $action): string {
        $map = [
            'relearn' => 'rebuild',
            'review' => 'review',
            'repair_prerequisite' => 'prerequisite',
            'remediate' => 'rebuild',
            'retry' => 'practice',
            'introduce' => 'learn',
            'reassess' => 'verify',
            'confirm' => 'confirm',
        ];
        return $map[$action] ?? 'learn';
    }

    /**
     * Human target label.
     *
     * @param array|null $target
     * @return string
     */
    private static function target_label(?array $target): string {
        if (!$target) {
            return '';
        }
        $target = self::normalize_target($target);
        $label = trim((string)$target['externalid'] . ' ' . (string)$target['title']);
        return $label !== '' ? $label : (string)$target['type'] . ':' . (int)$target['id'];
    }

    /**
     * Thresholds for a target type.
     *
     * @param string $targettype
     * @return array
     */
    private static function thresholds_for_target(string $targettype): array {
        $thresholds = self::policy()['thresholds'];
        $targettype = self::normalize_target_type($targettype);
        return [
            'mastery' => $thresholds['mastery'][$targettype] ?? $thresholds['mastery']['kp'],
            'confidence' => $thresholds['confidence'],
            'prerequisite' => $thresholds['prerequisite'],
            'retention' => $thresholds['retention'] ?? [],
        ];
    }

    /**
     * Fallback thresholds matching A3 defaults if policy read fails.
     *
     * @return array
     */
    private static function fallback_thresholds(): array {
        return [
            'mastery' => [
                'kp' => ['advance_score' => 0.80, 'remediate_below' => 0.55, 'retry_from' => 0.55],
                'up' => ['advance_score' => 0.75, 'remediate_below' => 0.50, 'retry_from' => 0.50],
                'competency' => ['advance_score' => 0.75, 'remediate_below' => 0.50, 'retry_from' => 0.50],
            ],
            'confidence' => [
                'low_below' => 0.50,
                'stable_from' => 0.75,
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
     * Safely read A3 policy.
     *
     * @return array
     */
    private static function safe_policy(): array {
        try {
            $policy = adaptive_decision_policy_service::policy();
            return is_array($policy) ? $policy : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Status criteria.
     *
     * @param array $a3
     * @param array $graph
     * @param array $history
     * @param array $files
     * @param array $surface
     * @param array $policy
     * @return array
     */
    private static function criteria(array $a3, array $graph, array $history, array $files,
            array $surface, array $policy): array {
        return [
            'adaptive_policy_ready' => self::criterion(
                'adaptive_policy_ready',
                ($a3['status'] ?? '') === 'ready' &&
                    ($a3['contract']['version'] ?? '') === adaptive_decision_policy_service::CONTRACT_VERSION,
                'A4 must consume the frozen A3 adaptive decision policy.'
            ),
            'relationship_graph_frozen' => self::criterion(
                'relationship_graph_frozen',
                in_array((string)($graph['status'] ?? ''), ['frozen', 'ready'], true),
                'A4 requirement and prerequisite traversal must use frozen C2 graph semantics.'
            ),
            'history_v1_boundary_preserved' => self::criterion(
                'history_v1_boundary_preserved',
                ($history['requiredcontract'] ?? '') === history_v1_consumer_contract::REQUIRED_CONTRACT &&
                    ($history['status'] ?? '') !== 'blocked',
                'A4 consumes History V1 only through trusted downstream services and never scrapes raw Moodle logs.'
            ),
            'gap_dimensions_are_visible' => self::criterion(
                'gap_dimensions_are_visible',
                empty(array_diff(self::gap_dimensions(), $policy['gap_dimensions'] ?? [])),
                'A4 exposes mastery, confidence, performance-mode, retention, prerequisite, and goal-priority dimensions.'
            ),
            'path_outputs_are_frozen' => self::criterion(
                'path_outputs_are_frozen',
                !empty($policy['roadmap']['always_ends_with_destination']),
                'A4 outputs NEXT TARGET, PROJECTED ROADMAP, and DESTINATION.'
            ),
            'moodle_activity_resolution_stopped' => self::criterion(
                'moodle_activity_resolution_stopped',
                ($policy['roadmap']['activity_resolution'] ?? '') === 'not_allowed_until_A4B',
                'A4 does not resolve target-level path steps to Moodle activities.'
            ),
            'surface_present' => self::criterion(
                'surface_present',
                $files['valid'] && $surface['valid'],
                'Admin page, CLI, service methods, and web-service methods are present.'
            ),
            'read_only_no_writes' => self::criterion(
                'read_only_no_writes',
                empty(self::contract()['write_boundary']),
                'A4 is read-only and does not write learner states, recommendations, or path rows.'
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
     * File status for A4 artifacts.
     *
     * @return array
     */
    private static function file_status(): array {
        global $CFG;

        $files = [
            'initial_path.php',
            'cli/initial_path.php',
            'classes/local/goal_gap_path_service.php',
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
            self::class . '::learner_path' => method_exists(self::class, 'learner_path'),
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
            'get_goal_gap_path_status',
            'get_learner_initial_path',
            'get_class_initial_path_summary',
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
            throw new \coding_exception($source . ' payload did not return an array.');
        } catch (\Throwable $e) {
            return [
                'type' => 'CupkpUnavailableInput',
                'status' => 'unavailable',
                'source' => $source,
                'message' => $e->getMessage(),
                'read_only' => true,
                'state_changes_allowed' => false,
            ];
        }
    }

    /**
     * Bound user-provided limits.
     *
     * @param int $limit
     * @param int $max
     * @return int
     */
    private static function bounded_limit(int $limit, int $max): int {
        return max(1, min($max, $limit));
    }
}
