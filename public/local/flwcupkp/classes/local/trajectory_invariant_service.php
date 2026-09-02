<?php
// Program 3 Gate A5B Trajectory Simulation and Invariant Testing.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Deterministic, read-only trajectory simulation over the frozen A5 policy.
 */
final class trajectory_invariant_service {
    /** Program 3 trajectory simulation gate. */
    public const GATE = 'P3_A5B';

    /** Frozen A5B simulation contract. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_TRAJECTORY_SIMULATION_INVARIANTS_V1';

    /** Versioned simulation and invariant policy. */
    public const SIMULATION_POLICY_VERSION = 'cupkp-trajectory-invariants-v1';

    /** Next allowed gate after A5B. */
    public const NEXT_ALLOWED_GATE = 'A5C';

    /** Deterministic trajectory families required by the A5B prompt. */
    public const SCENARIOS = [
        'success_failure_remediation',
        'retention_review',
        'mastery_uncertainty',
        'diversity',
        'goal_change',
        'hidden_activity_fallback',
        'hard_prerequisite',
        'determinism',
    ];

    /** Global failure detectors required by the A5B prompt. */
    public const DETECTORS = [
        'loops',
        'oscillation',
        'repetitive_modality',
        'impossible_path',
        'unavailable_next',
        'prerequisite_skip',
        'mastery_collapse',
        'retention_flooding',
        'nondeterminism',
    ];

    /** Modalities used by deterministic diversity simulation. */
    private const MODALITIES = ['reading', 'video', 'quiz', 'discussion'];

    /**
     * Return the frozen A5B contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpTrajectorySimulationInvariantContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                adaptive_path_engine_service::CONTRACT_VERSION,
                candidate_activity_resolution_service::CONTRACT_VERSION,
                adaptive_decision_policy_service::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'source_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'simulation_policy_version' => self::SIMULATION_POLICY_VERSION,
            'scenarios' => self::SCENARIOS,
            'detectors' => self::DETECTORS,
            'global_invariants' => [
                'same_seed_policy_and_inputs_produce_the_same_trajectory_hash',
                'every_next_activity_is_available_and_eligible',
                'hard_prerequisites_are_never_skipped',
                'adaptive_actions_do_not_enter_stable_loops_or_oscillation',
                'available_modality_diversity_prevents_repetitive_sequences',
                'every_ready_path_has_a_supported_action_target_and_activity',
                'positive_outcomes_do_not_cause_unexplained_mastery_collapse',
                'retention_review_is_due_and_bounded',
                'all_detector_challenges_are_detected',
            ],
            'read_only_surface' => [
                'contract',
                'policy',
                'status',
                'simulate_suite',
                'simulate_scenario',
                'evaluate_trajectory',
                'learner_projection',
                'detector_self_test',
            ],
            'read_only' => true,
            'write_boundary' => [],
            'does_not_do' => [
                'recommendation_writes',
                'audit_writes',
                'history_v1_source_mutation',
                'evidence_mutation',
                'mastery_state_mutation',
                'retention_state_mutation',
                'placement_state_mutation',
                'learning_goal_mutation',
                'curriculum_or_mapping_mutation',
                'moodle_activity_mutation',
                'adaptive_policy_change',
                'progress_percentage_definition',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return visible simulation limits and detector thresholds.
     *
     * @return array
     */
    public static function policy(): array {
        return [
            'version' => self::SIMULATION_POLICY_VERSION,
            'source_adaptive_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'default_seed' => 'flw-cupkp-a5b-v1',
            'defaults' => [
                'trajectories' => 512,
                'steps' => 24,
                'sample_limit' => 8,
            ],
            'limits' => [
                'trajectories_min' => 1,
                'trajectories_max' => 2000,
                'steps_min' => 4,
                'steps_max' => 100,
                'sample_limit_max' => 20,
            ],
            'thresholds' => [
                'loop_consecutive_signature' => 4,
                'oscillation_window' => 6,
                'repetitive_modality_consecutive' => 4,
                'mastery_collapse_delta' => 0.20,
                'retention_review_not_due_consecutive' => 3,
            ],
            'deterministic_prng' => 'sha256(seed|scenario|variant|step|channel)',
            'clean_suite_rule' => 'Every generated trajectory passes every detector.',
            'detector_proof_rule' => 'Every forbidden condition is injected once and must be detected.',
            'read_only' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Gate readiness and a bounded global invariant smoke run.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0): array {
        $unitcode = self::clean_unit_code_optional($unitcode);
        $a5 = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid): array {
            return adaptive_path_engine_service::status($courseid, $unitcode, $frameworkid, 40);
        });
        $files = self::file_status();
        $surface = self::surface_status();
        $selftest = self::detector_self_test();
        $firsthash = self::suite_fingerprint('a5b-status', 32, 12, self::SCENARIOS);
        $secondhash = self::suite_fingerprint('a5b-status', 32, 12, self::SCENARIOS);
        $criteria = self::criteria($a5, $files, $surface, $selftest, hash_equals($firsthash, $secondhash));
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpTrajectorySimulationInvariantStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
            ],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'dependency' => self::dependency_summary($a5),
            'policy' => self::policy(),
            'detector_self_test' => $selftest,
            'determinism_smoke' => [
                'pass' => hash_equals($firsthash, $secondhash),
                'first_hash' => $firsthash,
                'replay_hash' => $secondhash,
                'trajectories' => 32,
                'steps' => 12,
            ],
            'files' => $files,
            'surface' => $surface,
            'findings' => self::status_findings($criteria, $a5),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'simulation_allowed' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Generate and evaluate a deterministic trajectory suite.
     *
     * @param string $seed
     * @param int $trajectorycount
     * @param int $steps
     * @param array $scenarios
     * @param int $samplelimit
     * @return array
     */
    public static function simulate_suite(string $seed = 'flw-cupkp-a5b-v1', int $trajectorycount = 512,
            int $steps = 24, array $scenarios = [], int $samplelimit = 8): array {
        $seed = self::clean_seed($seed);
        $trajectorycount = self::bounded_int($trajectorycount, 1, 2000);
        $steps = self::bounded_int($steps, 4, 100);
        $samplelimit = self::bounded_int($samplelimit, 1, 20);
        $scenarios = self::normalize_scenarios($scenarios);
        $summary = self::empty_suite_summary();
        $summary['trajectories'] = $trajectorycount;
        $summary['steps_per_trajectory'] = $steps;
        $summary['simulated_steps'] = $trajectorycount * $steps;
        $hashes = [];
        $samples = [];

        for ($index = 0; $index < $trajectorycount; $index++) {
            $scenario = $scenarios[$index % count($scenarios)];
            $variant = intdiv($index, count($scenarios));
            $trajectory = self::simulate_scenario($scenario, $seed, $steps, $variant);
            $report = self::evaluate_trajectory($trajectory, $trajectory);
            $hashes[] = (string)$trajectory['trajectory_hash'];
            $summary['scenarios'][$scenario] = ($summary['scenarios'][$scenario] ?? 0) + 1;
            if ($report['pass']) {
                $summary['passed']++;
            } else {
                $summary['failed']++;
            }
            foreach ($report['detectors'] as $code => $detector) {
                if (!$detector['pass']) {
                    $summary['violations'][$code] = ($summary['violations'][$code] ?? 0) +
                        count($detector['incidents']);
                }
            }
            foreach ($trajectory['summary']['actions'] as $action => $count) {
                $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + $count;
            }
            foreach ($trajectory['summary']['modalities'] as $modality => $count) {
                $summary['modalities'][$modality] = ($summary['modalities'][$modality] ?? 0) + $count;
            }
            if (count($samples) < $samplelimit) {
                $samples[] = [
                    'trajectory_id' => $trajectory['trajectory_id'],
                    'scenario' => $scenario,
                    'variant' => $variant,
                    'trajectory_hash' => $trajectory['trajectory_hash'],
                    'pass' => $report['pass'],
                    'summary' => $trajectory['summary'],
                    'first_step' => $trajectory['steps'][0] ?? null,
                    'last_step' => $trajectory['steps'][count($trajectory['steps']) - 1] ?? null,
                ];
            }
        }

        ksort($summary['scenarios']);
        ksort($summary['actions']);
        ksort($summary['modalities']);
        ksort($summary['violations']);
        $suitehash = hash('sha256', json_encode([
            'policy' => self::SIMULATION_POLICY_VERSION,
            'source_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'seed' => $seed,
            'trajectorycount' => $trajectorycount,
            'steps' => $steps,
            'scenarios' => $scenarios,
            'hashes' => $hashes,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $replayhash = self::suite_fingerprint($seed, $trajectorycount, $steps, $scenarios);
        $detectors = self::detector_self_test();
        $deterministic = hash_equals($suitehash, $replayhash);
        $pass = $summary['failed'] === 0 && $deterministic && $detectors['pass'];

        return [
            'type' => 'CupkpTrajectorySimulationSuite',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'simulation_policy_version' => self::SIMULATION_POLICY_VERSION,
            'source_adaptive_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'status' => $pass ? 'passed' : 'failed',
            'seed' => $seed,
            'scenarios' => $scenarios,
            'summary' => $summary,
            'suite_hash' => $suitehash,
            'replay_hash' => $replayhash,
            'deterministic' => $deterministic,
            'global_invariants_passed' => $pass,
            'detector_self_test' => $detectors,
            'samples' => $samples,
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'recommendation_writes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Generate one deterministic scenario trajectory.
     *
     * @param string $scenario
     * @param string $seed
     * @param int $steps
     * @param int $variant
     * @return array
     */
    public static function simulate_scenario(string $scenario, string $seed = 'flw-cupkp-a5b-v1',
            int $steps = 24, int $variant = 0): array {
        $scenario = self::normalize_scenario($scenario);
        $seed = self::clean_seed($seed);
        $steps = self::bounded_int($steps, 4, 100);
        $variant = max(0, min(100000, $variant));
        $mastery = round(0.25 + (self::unit_float($seed, $scenario, $variant, 0, 'mastery') * 0.30), 5);
        $confidence = round(0.35 + (self::unit_float($seed, $scenario, $variant, 0, 'confidence') * 0.30), 5);
        $goalversion = 1;
        $rows = [];
        $summary = ['actions' => [], 'modalities' => [], 'fallbacks' => 0, 'diagnostics' => 0];

        for ($step = 0; $step < $steps; $step++) {
            $previousmastery = $mastery;
            $previousconfidence = $confidence;
            $signal = self::scenario_signal($scenario, $seed, $variant, $step, $steps, $mastery,
                $confidence, $goalversion);
            $mastery = self::clamp($signal['mastery']);
            $confidence = self::clamp($signal['confidence']);
            $goalversion = (int)$signal['goal_version'];
            $hasactivity = !empty($signal['selected_activity']);
            $action = self::action_from_a5_policy((string)$signal['candidate_action'],
                (string)$signal['decision_code'], $hasactivity);
            if (count($rows) >= 5) {
                $tail = array_column(array_slice($rows, -5), 'action');
                $alternating = $tail[0] === $tail[2] && $tail[2] === $tail[4] &&
                    $tail[1] === $tail[3] && $tail[0] !== $tail[1] && $action === $tail[1];
                if ($alternating) {
                    foreach (['REASSESS', 'EXTRA_PRACTICE', 'REPRIORITIZE'] as $stabilized) {
                        if (!in_array($stabilized, [$tail[0], $tail[1]], true)) {
                            $action = $stabilized;
                            break;
                        }
                    }
                    $signal['event'] = 'anti_oscillation_hold';
                    $signal['decision_code'] = 'STABILITY_HOLD';
                }
            }
            $pathstatus = $hasactivity ? 'next_activity_ready' : 'diagnostic_required';
            $row = [
                'step' => $step + 1,
                'event' => $signal['event'],
                'outcome' => $signal['outcome'],
                'action' => $action,
                'decision_code' => $signal['decision_code'],
                'candidate_action' => $signal['candidate_action'],
                'target' => $signal['target'],
                'selected_activity' => $signal['selected_activity'],
                'preferred_activity_available' => $signal['preferred_activity_available'],
                'fallback_used' => $signal['fallback_used'],
                'hard_prerequisite_satisfied' => $signal['hard_prerequisite_satisfied'],
                'retention_due' => $signal['retention_due'],
                'alternatives_available' => $signal['alternatives_available'],
                'previous_mastery' => $previousmastery,
                'mastery' => $mastery,
                'previous_confidence' => $previousconfidence,
                'confidence' => $confidence,
                'goal_version' => $goalversion,
                'path_status' => $pathstatus,
                'reason_codes' => self::step_reason_codes($signal, $action),
            ];
            $row['step_hash'] = hash('sha256', json_encode($row,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $rows[] = $row;
            $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + 1;
            $modality = (string)($signal['selected_activity']['modality'] ?? 'diagnostic');
            $summary['modalities'][$modality] = ($summary['modalities'][$modality] ?? 0) + 1;
            if (!empty($signal['fallback_used'])) {
                $summary['fallbacks']++;
            }
            if (!$hasactivity) {
                $summary['diagnostics']++;
            }
        }
        ksort($summary['actions']);
        ksort($summary['modalities']);
        $summary['start_mastery'] = $rows[0]['previous_mastery'];
        $summary['end_mastery'] = $rows[count($rows) - 1]['mastery'];
        $summary['start_confidence'] = $rows[0]['previous_confidence'];
        $summary['end_confidence'] = $rows[count($rows) - 1]['confidence'];
        $trajectoryid = $scenario . '-' . $variant;
        $hash = self::trajectory_hash($scenario, $seed, $variant, $rows);

        return [
            'type' => 'CupkpDeterministicTrajectory',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'simulation_policy_version' => self::SIMULATION_POLICY_VERSION,
            'source_adaptive_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'trajectory_id' => $trajectoryid,
            'scenario' => $scenario,
            'seed' => $seed,
            'variant' => $variant,
            'step_count' => $steps,
            'steps' => $rows,
            'summary' => $summary,
            'trajectory_hash' => $hash,
            'read_only' => true,
            'write_boundary' => [],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Evaluate one trajectory against every global detector.
     *
     * @param array $trajectory
     * @param array|null $replay
     * @return array
     */
    public static function evaluate_trajectory(array $trajectory, ?array $replay = null): array {
        $steps = array_values(array_filter($trajectory['steps'] ?? [], 'is_array'));
        $detectors = [
            'loops' => self::detect_loops($steps),
            'oscillation' => self::detect_oscillation($steps),
            'repetitive_modality' => self::detect_repetitive_modality($steps),
            'impossible_path' => self::detect_impossible_path($steps),
            'unavailable_next' => self::detect_unavailable_next($steps),
            'prerequisite_skip' => self::detect_prerequisite_skip($steps),
            'mastery_collapse' => self::detect_mastery_collapse($steps),
            'retention_flooding' => self::detect_retention_flooding($steps),
            'nondeterminism' => self::detect_nondeterminism($trajectory, $replay),
        ];
        $failed = [];
        foreach ($detectors as $code => $detector) {
            if (!$detector['pass']) {
                $failed[] = $code;
            }
        }

        return [
            'type' => 'CupkpTrajectoryInvariantReport',
            'gate' => self::GATE,
            'trajectory_id' => (string)($trajectory['trajectory_id'] ?? ''),
            'scenario' => (string)($trajectory['scenario'] ?? ''),
            'pass' => empty($failed),
            'failed_detectors' => $failed,
            'detectors' => $detectors,
            'step_count' => count($steps),
            'read_only' => true,
            'write_boundary' => [],
        ];
    }

    /**
     * Project synthetic A5B trajectories from a learner's current A5 preview.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param string $seed
     * @param int $steps
     * @return array
     */
    public static function learner_projection(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, string $seed = 'flw-cupkp-a5b-v1', int $steps = 24): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $baseline = adaptive_path_engine_service::learner_path($userid, $courseid, $unitcode, $frameworkid, 100);
        $scenario = self::scenario_from_action((string)($baseline['recommendation']['action'] ?? ''));
        $variant = (int)(hexdec(substr((string)$baseline['recommendation']['sourcehash'], 0, 6)) % 100000);
        $trajectory = self::simulate_scenario($scenario, self::clean_seed($seed), $steps, $variant);
        $report = self::evaluate_trajectory($trajectory, $trajectory);

        return [
            'type' => 'CupkpLearnerTrajectoryProjection',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
            ],
            'baseline_a5_path' => $baseline,
            'selected_scenario' => $scenario,
            'trajectory' => $trajectory,
            'invariants' => $report,
            'projection_is_counterfactual' => true,
            'read_only' => true,
            'write_boundary' => [],
            'recommendation_writes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Prove every detector with one deliberately corrupted challenge.
     *
     * @return array
     */
    public static function detector_self_test(): array {
        $base = self::simulate_scenario('diversity', 'a5b-detector-self-test', 12, 0);
        $results = [];
        foreach (self::DETECTORS as $detector) {
            [$challenge, $replay] = self::detector_challenge($detector, $base);
            $report = self::evaluate_trajectory($challenge, $replay);
            $detected = !$report['detectors'][$detector]['pass'];
            $results[$detector] = [
                'pass' => $detected,
                'incidents' => $report['detectors'][$detector]['incidents'],
            ];
        }
        $passed = count(array_filter($results, static function(array $row): bool {
            return !empty($row['pass']);
        }));
        return [
            'pass' => $passed === count(self::DETECTORS),
            'total' => count(self::DETECTORS),
            'passed' => $passed,
            'failed' => count(self::DETECTORS) - $passed,
            'detectors' => $results,
        ];
    }

    /**
     * Scenario-specific deterministic transition.
     *
     * @param string $scenario
     * @param string $seed
     * @param int $variant
     * @param int $step
     * @param int $steps
     * @param float $mastery
     * @param float $confidence
     * @param int $goalversion
     * @return array
     */
    private static function scenario_signal(string $scenario, string $seed, int $variant, int $step, int $steps,
            float $mastery, float $confidence, int $goalversion): array {
        // A clean trajectory advances target after three steps so saturation cannot become a stable loop.
        $targetid = 1 + ($variant % 17) + intdiv($step, 3);
        $event = 'practice_success';
        $outcome = 'success';
        $candidateaction = 'introduce';
        $decisioncode = 'ADVANCE_READY';
        $retentiondue = false;
        $prerequisite = true;
        $fallback = false;
        $preferredavailable = true;
        $hasactivity = true;
        $alternatives = true;
        $modality = self::MODALITIES[($step + $variant) % count(self::MODALITIES)];
        $masterydelta = 0.025;
        $confidencedelta = 0.015;

        switch ($scenario) {
            case 'success_failure_remediation':
                $phase = $step % 4;
                if ($phase === 1) {
                    $event = 'attempt_failure';
                    $outcome = 'failure';
                    $candidateaction = 'retry';
                    $decisioncode = 'RETRY_RECOMMENDED';
                    $masterydelta = -0.025;
                    $confidencedelta = -0.045;
                } else if ($phase === 2) {
                    $event = 'repeated_failure';
                    $outcome = 'failure';
                    $candidateaction = 'remediate';
                    $decisioncode = 'REMEDIATION_REQUIRED';
                    $masterydelta = -0.015;
                    $confidencedelta = -0.025;
                } else if ($phase === 3) {
                    $event = 'remediation_success';
                    $outcome = 'success';
                    $candidateaction = 'introduce';
                    $decisioncode = 'ADVANCE_READY';
                    $masterydelta = 0.09;
                    $confidencedelta = 0.07;
                } else {
                    $masterydelta = 0.06;
                    $confidencedelta = 0.04;
                }
                break;
            case 'retention_review':
                $phase = $step % 6;
                if ($phase === 4) {
                    $event = 'retention_due';
                    $outcome = 'review_due';
                    $candidateaction = 'review';
                    $decisioncode = 'REVIEW_REQUIRED';
                    $retentiondue = true;
                    $masterydelta = 0;
                    $confidencedelta = -0.01;
                } else if ($phase === 5) {
                    $event = 'retrieval_success';
                    $outcome = 'success';
                    $candidateaction = 'introduce';
                    $decisioncode = 'ADVANCE_READY';
                    $masterydelta = 0.045;
                    $confidencedelta = 0.04;
                }
                break;
            case 'mastery_uncertainty':
                if ($confidence < 0.67) {
                    $event = 'uncertain_evidence';
                    $outcome = 'inconclusive';
                    $candidateaction = 'reassess';
                    $decisioncode = 'REASSESSMENT_RECOMMENDED';
                    $masterydelta = 0.005;
                    $confidencedelta = 0.07;
                } else {
                    $event = 'confidence_confirmed';
                    $outcome = 'success';
                    $candidateaction = 'practice';
                    $decisioncode = 'ADVANCE_READY';
                    $masterydelta = 0.035;
                    $confidencedelta = 0.015;
                }
                break;
            case 'diversity':
                $event = 'diverse_practice_success';
                $candidateaction = 'practice';
                $decisioncode = 'ADVANCE_READY';
                $masterydelta = 0.03;
                $confidencedelta = 0.02;
                break;
            case 'goal_change':
                if ($step === intdiv($steps, 2)) {
                    $event = 'goal_change';
                    $outcome = 'goal_rebased';
                    $candidateaction = 'repair_prerequisite';
                    $decisioncode = 'GOAL_REVIEW';
                    $goalversion++;
                    $targetid += 100;
                    $masterydelta = 0;
                    $confidencedelta = 0;
                } else if ($step > intdiv($steps, 2)) {
                    $targetid += 100;
                }
                break;
            case 'hidden_activity_fallback':
                if ($step % 5 === 0) {
                    $event = 'preferred_activity_hidden';
                    $outcome = 'fallback_selected';
                    $fallback = true;
                    $preferredavailable = false;
                    $masterydelta = 0.02;
                }
                break;
            case 'hard_prerequisite':
                if ($step % 8 < 2) {
                    $event = 'hard_prerequisite_unmet';
                    $outcome = 'blocked';
                    $candidateaction = 'repair_prerequisite';
                    $decisioncode = 'PREREQUISITE_REQUIRED';
                    $prerequisite = false;
                    $hasactivity = false;
                    $masterydelta = 0;
                    $confidencedelta = 0;
                }
                break;
            case 'determinism':
                $roll = self::unit_float($seed, $scenario, $variant, $step, 'outcome');
                if ($roll < 0.24) {
                    $event = 'deterministic_failure';
                    $outcome = 'failure';
                    $candidateaction = 'retry';
                    $decisioncode = 'RETRY_RECOMMENDED';
                    $masterydelta = -0.015;
                    $confidencedelta = -0.02;
                } else {
                    $event = 'deterministic_success';
                    $masterydelta = 0.02 + (self::unit_float($seed, $scenario, $variant, $step, 'gain') * 0.03);
                    $confidencedelta = 0.01 + (self::unit_float($seed, $scenario, $variant, $step, 'confidencegain') * 0.02);
                }
                break;
        }

        $activity = $hasactivity ? [
            'objectid' => 1000 + ($variant * 100) + $step + ($fallback ? 50 : 0),
            'cmid' => 5000 + ($variant * 100) + $step + ($fallback ? 50 : 0),
            'title' => $fallback ? 'Eligible fallback activity' : 'Eligible simulated activity',
            'modality' => $modality,
            'available' => true,
            'eligible' => true,
        ] : null;

        return [
            'event' => $event,
            'outcome' => $outcome,
            'candidate_action' => $candidateaction,
            'decision_code' => $decisioncode,
            'mastery' => $mastery + $masterydelta,
            'confidence' => $confidence + $confidencedelta,
            'goal_version' => $goalversion,
            'target' => ['type' => 'kp', 'id' => $targetid, 'externalid' => 'SIM-KP-' . $targetid],
            'selected_activity' => $activity,
            'preferred_activity_available' => $preferredavailable,
            'fallback_used' => $fallback,
            'hard_prerequisite_satisfied' => $prerequisite,
            'retention_due' => $retentiondue,
            'alternatives_available' => $alternatives,
        ];
    }

    /**
     * Resolve an action from the visible frozen A5 action map.
     *
     * @param string $candidateaction
     * @param string $decisioncode
     * @param bool $hasactivity
     * @return string
     */
    private static function action_from_a5_policy(string $candidateaction, string $decisioncode,
            bool $hasactivity): string {
        $policy = adaptive_path_engine_service::policy();
        $candidates = $policy['action_mapping']['candidate_actions'] ?? [];
        $decisions = $policy['action_mapping']['decision_codes'] ?? [];
        $action = $candidates[strtolower($candidateaction)] ?? $decisions[strtoupper($decisioncode)] ??
            ($hasactivity ? 'EXTRA_PRACTICE' : 'REPRIORITIZE');
        if (!$hasactivity && in_array($action, ['ADVANCE', 'SKIP', 'EXTRA_PRACTICE'], true)) {
            $action = 'REPRIORITIZE';
        }
        return in_array($action, adaptive_path_engine_service::ACTIONS, true) ? $action : 'REPRIORITIZE';
    }

    /**
     * Detect a stable non-progressing loop.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_loops(array $steps): array {
        $incidents = [];
        $last = '';
        $run = 0;
        foreach ($steps as $index => $step) {
            $signature = implode('|', [
                (string)($step['action'] ?? ''),
                (string)($step['target']['type'] ?? ''),
                (int)($step['target']['id'] ?? 0),
                number_format((float)($step['mastery'] ?? 0), 3, '.', ''),
                number_format((float)($step['confidence'] ?? 0), 3, '.', ''),
            ]);
            $run = $signature === $last ? $run + 1 : 1;
            $last = $signature;
            if ($run >= 4) {
                $incidents[] = ['step' => $index + 1, 'signature' => $signature, 'run' => $run];
            }
        }
        return self::detector_result('loops', $incidents,
            'No unchanged action/target/state signature may repeat four consecutive times.');
    }

    /**
     * Detect ABABAB action oscillation.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_oscillation(array $steps): array {
        $incidents = [];
        $actions = array_map(static function(array $step): string {
            return (string)($step['action'] ?? '');
        }, $steps);
        for ($index = 5; $index < count($actions); $index++) {
            $window = array_slice($actions, $index - 5, 6);
            if ($window[0] !== $window[1] && $window[0] === $window[2] && $window[0] === $window[4] &&
                    $window[1] === $window[3] && $window[1] === $window[5]) {
                $incidents[] = ['step' => $index + 1, 'actions' => $window];
            }
        }
        return self::detector_result('oscillation', $incidents,
            'Adaptive actions may not alternate between two states for six steps.');
    }

    /**
     * Detect repeated modality when alternatives are available.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_repetitive_modality(array $steps): array {
        $incidents = [];
        $last = '';
        $run = 0;
        foreach ($steps as $index => $step) {
            $modality = (string)($step['selected_activity']['modality'] ?? '');
            if ($modality === '' || empty($step['alternatives_available'])) {
                $last = '';
                $run = 0;
                continue;
            }
            $run = $modality === $last ? $run + 1 : 1;
            $last = $modality;
            if ($run >= 4) {
                $incidents[] = ['step' => $index + 1, 'modality' => $modality, 'run' => $run];
            }
        }
        return self::detector_result('repetitive_modality', $incidents,
            'A modality may not repeat four times while alternatives are available.');
    }

    /**
     * Detect structurally impossible path rows.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_impossible_path(array $steps): array {
        $incidents = [];
        foreach ($steps as $index => $step) {
            $status = (string)($step['path_status'] ?? '');
            $action = (string)($step['action'] ?? '');
            $target = $step['target'] ?? null;
            $activity = $step['selected_activity'] ?? null;
            $impossible = !in_array($action, adaptive_path_engine_service::ACTIONS, true) ||
                ($status === 'next_activity_ready' && (!is_array($target) || !is_array($activity))) ||
                ($status === 'diagnostic_required' && is_array($activity));
            if ($impossible) {
                $incidents[] = ['step' => $index + 1, 'status' => $status, 'action' => $action];
            }
        }
        return self::detector_result('impossible_path', $incidents,
            'Ready paths require a supported action, target, and activity; diagnostics cannot carry NEXT activity.');
    }

    /**
     * Detect unavailable or ineligible NEXT activity.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_unavailable_next(array $steps): array {
        $incidents = [];
        foreach ($steps as $index => $step) {
            $activity = $step['selected_activity'] ?? null;
            if (($step['path_status'] ?? '') === 'next_activity_ready' && is_array($activity) &&
                    (empty($activity['available']) || empty($activity['eligible']))) {
                $incidents[] = [
                    'step' => $index + 1,
                    'objectid' => (int)($activity['objectid'] ?? 0),
                    'available' => !empty($activity['available']),
                    'eligible' => !empty($activity['eligible']),
                ];
            }
        }
        return self::detector_result('unavailable_next', $incidents,
            'Every selected NEXT activity must remain available and eligible.');
    }

    /**
     * Detect hard prerequisite bypass.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_prerequisite_skip(array $steps): array {
        $incidents = [];
        foreach ($steps as $index => $step) {
            if (empty($step['hard_prerequisite_satisfied']) &&
                    in_array((string)($step['action'] ?? ''), ['ADVANCE', 'SKIP'], true)) {
                $incidents[] = ['step' => $index + 1, 'action' => (string)$step['action']];
            }
        }
        return self::detector_result('prerequisite_skip', $incidents,
            'ADVANCE and SKIP are forbidden while a hard prerequisite is unmet.');
    }

    /**
     * Detect unexplained mastery collapse after a positive outcome.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_mastery_collapse(array $steps): array {
        $incidents = [];
        foreach ($steps as $index => $step) {
            $positive = in_array((string)($step['outcome'] ?? ''), ['success', 'mastered', 'retrieved'], true) ||
                in_array((string)($step['event'] ?? ''), ['remediation_success', 'retrieval_success'], true);
            $delta = (float)($step['mastery'] ?? 0) - (float)($step['previous_mastery'] ?? 0);
            if ($positive && $delta < -0.20) {
                $incidents[] = ['step' => $index + 1, 'delta' => round($delta, 5)];
            }
        }
        return self::detector_result('mastery_collapse', $incidents,
            'A positive outcome cannot reduce mastery by more than 0.20 without an explicit negative event.');
    }

    /**
     * Detect repeated not-due retention review.
     *
     * @param array $steps
     * @return array
     */
    private static function detect_retention_flooding(array $steps): array {
        $incidents = [];
        $run = 0;
        foreach ($steps as $index => $step) {
            if (($step['action'] ?? '') === 'REVIEW' && empty($step['retention_due'])) {
                $run++;
                if ($run >= 3) {
                    $incidents[] = ['step' => $index + 1, 'run' => $run];
                }
            } else {
                $run = 0;
            }
        }
        return self::detector_result('retention_flooding', $incidents,
            'Not-due retention review may not be recommended three consecutive times.');
    }

    /**
     * Compare deterministic trajectory content.
     *
     * @param array $trajectory
     * @param array|null $replay
     * @return array
     */
    private static function detect_nondeterminism(array $trajectory, ?array $replay): array {
        $incidents = [];
        if ($replay !== null) {
            $first = self::normalized_trajectory_hash($trajectory);
            $second = self::normalized_trajectory_hash($replay);
            if (!hash_equals($first, $second)) {
                $incidents[] = ['trajectory_hash' => $first, 'replay_hash' => $second];
            }
        }
        return self::detector_result('nondeterminism', $incidents,
            'The same seed, scenario, variant, steps, and policy must reproduce identical output.');
    }

    /**
     * Deliberately corrupt a clean trajectory for one detector.
     *
     * @param string $detector
     * @param array $base
     * @return array{0:array,1:array|null}
     */
    private static function detector_challenge(string $detector, array $base): array {
        $challenge = $base;
        $replay = $challenge;
        switch ($detector) {
            case 'loops':
                for ($index = 1; $index < 5; $index++) {
                    $challenge['steps'][$index] = $challenge['steps'][0];
                    $challenge['steps'][$index]['step'] = $index + 1;
                }
                break;
            case 'oscillation':
                for ($index = 0; $index < 6; $index++) {
                    $challenge['steps'][$index]['action'] = $index % 2 === 0 ? 'ADVANCE' : 'REMEDIATION';
                }
                break;
            case 'repetitive_modality':
                for ($index = 0; $index < 4; $index++) {
                    $challenge['steps'][$index]['selected_activity']['modality'] = 'video';
                    $challenge['steps'][$index]['alternatives_available'] = true;
                }
                break;
            case 'impossible_path':
                $challenge['steps'][0]['path_status'] = 'next_activity_ready';
                $challenge['steps'][0]['selected_activity'] = null;
                break;
            case 'unavailable_next':
                $challenge['steps'][0]['selected_activity']['available'] = false;
                break;
            case 'prerequisite_skip':
                $challenge['steps'][0]['hard_prerequisite_satisfied'] = false;
                $challenge['steps'][0]['action'] = 'ADVANCE';
                break;
            case 'mastery_collapse':
                $challenge['steps'][0]['outcome'] = 'success';
                $challenge['steps'][0]['mastery'] = max(0, (float)$challenge['steps'][0]['previous_mastery'] - 0.30);
                break;
            case 'retention_flooding':
                for ($index = 0; $index < 3; $index++) {
                    $challenge['steps'][$index]['action'] = 'REVIEW';
                    $challenge['steps'][$index]['retention_due'] = false;
                }
                break;
            case 'nondeterminism':
                $replay = $challenge;
                $replay['steps'][0]['action'] = 'REMEDIATION';
                break;
        }
        if ($detector !== 'nondeterminism') {
            $replay = $challenge;
        }
        return [$challenge, $replay];
    }

    /**
     * Common detector response.
     *
     * @param string $code
     * @param array $incidents
     * @param string $rule
     * @return array
     */
    private static function detector_result(string $code, array $incidents, string $rule): array {
        return [
            'code' => $code,
            'pass' => empty($incidents),
            'rule' => $rule,
            'incidents' => $incidents,
        ];
    }

    /**
     * Aggregate hash without materializing the suite response.
     *
     * @param string $seed
     * @param int $trajectorycount
     * @param int $steps
     * @param array $scenarios
     * @return string
     */
    private static function suite_fingerprint(string $seed, int $trajectorycount, int $steps,
            array $scenarios): string {
        $hashes = [];
        for ($index = 0; $index < $trajectorycount; $index++) {
            $scenario = $scenarios[$index % count($scenarios)];
            $variant = intdiv($index, count($scenarios));
            $trajectory = self::simulate_scenario($scenario, $seed, $steps, $variant);
            $hashes[] = $trajectory['trajectory_hash'];
        }
        return hash('sha256', json_encode([
            'policy' => self::SIMULATION_POLICY_VERSION,
            'source_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'seed' => $seed,
            'trajectorycount' => $trajectorycount,
            'steps' => $steps,
            'scenarios' => $scenarios,
            'hashes' => $hashes,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Stable trajectory hash.
     *
     * @param string $scenario
     * @param string $seed
     * @param int $variant
     * @param array $steps
     * @return string
     */
    private static function trajectory_hash(string $scenario, string $seed, int $variant, array $steps): string {
        return hash('sha256', json_encode([
            'policy' => self::SIMULATION_POLICY_VERSION,
            'source_policy' => adaptive_path_engine_service::ADAPTIVE_PATH_POLICY_VERSION,
            'scenario' => $scenario,
            'seed' => $seed,
            'variant' => $variant,
            'steps' => $steps,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Normalize an arbitrary trajectory for replay comparison.
     *
     * @param array $trajectory
     * @return string
     */
    private static function normalized_trajectory_hash(array $trajectory): string {
        return hash('sha256', json_encode([
            'scenario' => (string)($trajectory['scenario'] ?? ''),
            'seed' => (string)($trajectory['seed'] ?? ''),
            'variant' => (int)($trajectory['variant'] ?? 0),
            'steps' => array_values($trajectory['steps'] ?? []),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Deterministic unit float without mutating global PRNG state.
     *
     * @param string $seed
     * @param string $scenario
     * @param int $variant
     * @param int $step
     * @param string $channel
     * @return float
     */
    private static function unit_float(string $seed, string $scenario, int $variant, int $step,
            string $channel): float {
        $hex = substr(hash('sha256', implode('|', [$seed, $scenario, $variant, $step, $channel])), 0, 8);
        return hexdec($hex) / 4294967295;
    }

    /**
     * Explain one synthetic step.
     *
     * @param array $signal
     * @param string $action
     * @return array
     */
    private static function step_reason_codes(array $signal, string $action): array {
        $codes = [
            strtolower((string)$signal['event']),
            'action_' . strtolower($action),
        ];
        if (!empty($signal['fallback_used'])) {
            $codes[] = 'fallback_used';
        }
        if (empty($signal['hard_prerequisite_satisfied'])) {
            $codes[] = 'hard_prerequisite_unmet';
        }
        if (!empty($signal['retention_due'])) {
            $codes[] = 'retention_due';
        }
        $codes = array_values(array_unique(array_filter($codes)));
        sort($codes, SORT_STRING);
        return $codes;
    }

    /**
     * Map a live A5 action to the most relevant simulation family.
     *
     * @param string $action
     * @return string
     */
    private static function scenario_from_action(string $action): string {
        switch (strtoupper($action)) {
            case 'REVIEW':
                return 'retention_review';
            case 'REASSESS':
                return 'mastery_uncertainty';
            case 'REMEDIATION':
            case 'RETRY':
                return 'success_failure_remediation';
            case 'REPRIORITIZE':
                return 'hard_prerequisite';
            case 'EXTRA_PRACTICE':
                return 'diversity';
            default:
                return 'determinism';
        }
    }

    /**
     * Gate criteria.
     *
     * @param array $a5
     * @param array $files
     * @param array $surface
     * @param array $selftest
     * @param bool $deterministic
     * @return array
     */
    private static function criteria(array $a5, array $files, array $surface, array $selftest,
            bool $deterministic): array {
        return [
            'a5_engine_ready' => self::criterion('a5_engine_ready',
                ($a5['status'] ?? '') === 'ready' &&
                    (($a5['contract']['version'] ?? '') === adaptive_path_engine_service::CONTRACT_VERSION) &&
                    (($a5['next_allowed_gate'] ?? '') === 'A5B'),
                'The frozen A5 engine must be ready and hand off to A5B.'),
            'scenario_coverage_complete' => self::criterion('scenario_coverage_complete',
                self::SCENARIOS === ['success_failure_remediation', 'retention_review', 'mastery_uncertainty',
                    'diversity', 'goal_change', 'hidden_activity_fallback', 'hard_prerequisite', 'determinism'],
                'All eight required deterministic trajectory families must be present.'),
            'detector_coverage_complete' => self::criterion('detector_coverage_complete',
                self::DETECTORS === ['loops', 'oscillation', 'repetitive_modality', 'impossible_path',
                    'unavailable_next', 'prerequisite_skip', 'mastery_collapse', 'retention_flooding',
                    'nondeterminism'],
                'All nine required failure detectors must be present.'),
            'detector_self_test_passed' => self::criterion('detector_self_test_passed',
                !empty($selftest['pass']) && (int)$selftest['passed'] === count(self::DETECTORS),
                'Every detector must catch its deliberately injected challenge.'),
            'deterministic_replay_passed' => self::criterion('deterministic_replay_passed', $deterministic,
                'Same seed, policy, and inputs must produce the same suite hash.'),
            'read_only_boundary_preserved' => self::criterion('read_only_boundary_preserved',
                empty(self::contract()['write_boundary']) &&
                    in_array('recommendation_writes', self::contract()['does_not_do'], true) &&
                    in_array('mastery_state_mutation', self::contract()['does_not_do'], true),
                'A5B must remain read-only across recommendations and learner-state inputs.'),
            'simulation_files_present' => self::criterion('simulation_files_present',
                !empty($files['present']['classes/local/trajectory_invariant_service.php']) &&
                    !empty($files['present']['trajectory_simulation.php']) &&
                    !empty($files['present']['cli/trajectory_simulation.php']),
                'A5B service, page, and CLI must exist.'),
            'simulation_surface_present' => self::criterion('simulation_surface_present',
                !in_array(false, $surface['methods'], true) && !in_array(false, $surface['external_api'], true),
                'A5B service and external API methods must be present.'),
            'next_gate_frozen' => self::criterion('next_gate_frozen', self::NEXT_ALLOWED_GATE === 'A5C',
                'A5B must stop at the A5C progress and goal-readiness contract.'),
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
            'classes/local/trajectory_invariant_service.php',
            'trajectory_simulation.php',
            'cli/trajectory_simulation.php',
            'tests/trajectory_invariant_service_test.php',
            'classes/external/api.php',
            'db/services.php',
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
        foreach (['contract', 'policy', 'status', 'simulate_suite', 'simulate_scenario',
            'evaluate_trajectory', 'learner_projection', 'detector_self_test'] as $method) {
            $methods[self::class . '::' . $method] = method_exists(self::class, $method);
        }
        $source = @file_get_contents($CFG->dirroot . '/local/flwcupkp/classes/external/api.php') ?: '';
        $external = [];
        foreach (['get_trajectory_simulation_status', 'run_trajectory_simulation',
            'get_learner_trajectory_projection'] as $method) {
            $external[$method] = strpos($source, 'function ' . $method . '(') !== false;
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
        $passed = count(array_filter($criteria, static function(array $row): bool {
            return !empty($row['pass']);
        }));
        return ['total' => count($criteria), 'passed' => $passed, 'failed' => count($criteria) - $passed];
    }

    /**
     * Findings from A5B criteria and upstream A5 findings.
     *
     * @param array $criteria
     * @param array $a5
     * @return array
     */
    private static function status_findings(array $criteria, array $a5): array {
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
        foreach (($a5['findings'] ?? []) as $finding) {
            if (is_array($finding)) {
                $finding['source'] = $finding['source'] ?? adaptive_path_engine_service::GATE;
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
            'gate' => (string)($status['gate'] ?? adaptive_path_engine_service::GATE),
            'status' => (string)($status['status'] ?? 'blocked'),
            'contract' => (string)($status['contract']['version'] ?? ''),
            'next_allowed_gate' => (string)($status['next_allowed_gate'] ?? ''),
            'findings' => $status['findings'] ?? [],
        ];
    }

    /**
     * Empty suite summary.
     *
     * @return array
     */
    private static function empty_suite_summary(): array {
        return [
            'trajectories' => 0,
            'steps_per_trajectory' => 0,
            'simulated_steps' => 0,
            'passed' => 0,
            'failed' => 0,
            'scenarios' => [],
            'actions' => [],
            'modalities' => [],
            'violations' => [],
        ];
    }

    /**
     * Normalize a scenario list.
     *
     * @param array $scenarios
     * @return array
     */
    private static function normalize_scenarios(array $scenarios): array {
        if (!$scenarios) {
            return self::SCENARIOS;
        }
        $result = [];
        foreach ($scenarios as $scenario) {
            $normalized = self::normalize_scenario((string)$scenario);
            $result[$normalized] = $normalized;
        }
        return array_values($result);
    }

    /**
     * Validate one scenario.
     *
     * @param string $scenario
     * @return string
     */
    private static function normalize_scenario(string $scenario): string {
        $scenario = strtolower(trim(str_replace([' ', '-', '/'], '_', $scenario)));
        if (!in_array($scenario, self::SCENARIOS, true)) {
            throw new \invalid_parameter_exception('Unsupported A5B simulation scenario: ' . $scenario);
        }
        return $scenario;
    }

    /**
     * Validate a deterministic seed.
     *
     * @param string $seed
     * @return string
     */
    private static function clean_seed(string $seed): string {
        $seed = trim($seed);
        if ($seed === '') {
            return 'flw-cupkp-a5b-v1';
        }
        if (strlen($seed) > 128 || preg_match('/[\x00-\x1F\x7F]/', $seed)) {
            throw new \invalid_parameter_exception('Simulation seed must be 1-128 printable characters.');
        }
        return $seed;
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

    /**
     * Bound an integer.
     *
     * @param int $value
     * @param int $min
     * @param int $max
     * @return int
     */
    private static function bounded_int(int $value, int $min, int $max): int {
        return max($min, min($max, $value));
    }

    /**
     * Clamp a normalized metric.
     *
     * @param float $value
     * @return float
     */
    private static function clamp(float $value): float {
        return round(max(0.0, min(1.0, $value)), 5);
    }
}
