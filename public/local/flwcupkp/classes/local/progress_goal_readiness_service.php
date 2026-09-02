<?php
// Program 3 Gate A5C Progress and Goal Readiness Contract.

namespace local_flwcupkp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only, versioned progress and semantic goal-readiness metrics.
 */
final class progress_goal_readiness_service {
    /** Program 3 progress contract gate. */
    public const GATE = 'P3_A5C';

    /** Frozen A5C contract version. */
    public const CONTRACT_VERSION = 'FLW_CUPKP_PROGRESS_GOAL_READINESS_CONTRACT_V1';

    /** Versioned calculation policy. */
    public const PROGRESS_POLICY_VERSION = 'cupkp-progress-goal-readiness-v1';

    /** Next allowed gate. */
    public const NEXT_ALLOWED_GATE = 'UX1';

    /** The four metrics that must never be presented as interchangeable. */
    public const METRICS = [
        'completion_progress',
        'mastery_progress',
        'goal_readiness',
        'path_progress',
    ];

    /** Maximum History V1 completion facts inspected for one calculation. */
    private const COMPLETION_FACT_LIMIT = 5000;

    /** Weight the integrated ability and demonstrated use above supporting knowledge. */
    private const TARGET_WEIGHTS = [
        'kp' => 1.0,
        'up' => 2.0,
        'competency' => 3.0,
    ];

    /** Goal achievement thresholds. */
    private const CONFIDENCE_THRESHOLD = 0.70;
    private const MASTERY_THRESHOLD = 0.70;
    private const MIN_EVIDENCE_FOR_UNCAPPED_READINESS = 3;

    /**
     * Return the frozen A5C contract.
     *
     * @return array
     */
    public static function contract(): array {
        return [
            'type' => 'CupkpProgressGoalReadinessContract',
            'gate' => self::GATE,
            'version' => self::CONTRACT_VERSION,
            'depends_on' => [
                trajectory_invariant_service::CONTRACT_VERSION,
                adaptive_path_engine_service::CONTRACT_VERSION,
                goal_gap_path_service::CONTRACT_VERSION,
                learning_goal_service::CONTRACT_VERSION,
                mastery_state_service::CONTRACT_VERSION,
                retention_review_service::CONTRACT_VERSION,
                history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'normal_source_history_input' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'normal_source_rule' => history_v1_consumer_contract::CONSUMPTION_RULE,
            'progress_policy_version' => self::PROGRESS_POLICY_VERSION,
            'metrics' => [
                'completion_progress' => [
                    'meaning' => 'Completed History V1 activity facts among goal-relevant mapped Moodle activities.',
                    'not_equivalent_to' => ['mastery', 'readiness', 'goal_achievement'],
                ],
                'mastery_progress' => [
                    'meaning' => 'Evidence-ceiling-capped mastery across weighted goal/path requirements.',
                    'not_equivalent_to' => ['activity_completion', 'goal_achievement'],
                ],
                'goal_readiness' => [
                    'meaning' => 'Weighted readiness limited by mastery, confidence, evidence sufficiency, and retention.',
                    'percentage_rule' => 'Show only for a versioned goal with semantic destination targets and requirements.',
                ],
                'path_progress' => [
                    'meaning' => 'Satisfied mandatory path requirements among all current path requirements.',
                    'not_equivalent_to' => ['activity_completion', 'mastery_score'],
                ],
            ],
            'percentage_contract_fields' => [
                'numerator',
                'denominator',
                'weights',
                'mandatory_gaps',
                'confidence',
                'retention',
                'evidence_ceiling',
                'missing_evidence',
                'policy_version',
            ],
            'preferred_learner_metric' => [
                'metric' => 'goal_readiness',
                'condition' => 'Only when the current versioned goal and requirement denominator are semantically defensible.',
                'fallback' => 'qualitative_milestone_without_percentage',
            ],
            'goal_achieved_semantic_conditions' => [
                'current_goal_is_semantically_defensible',
                'every_mandatory_requirement_is_satisfied',
                'no_hard_prerequisite_is_blocked',
                'mastery_threshold_is_met_for_every_requirement',
                'confidence_threshold_is_met_for_every_requirement',
                'minimum_evidence_is_met_for_every_requirement',
                'retention_is_retained_for_every_requirement',
                'no_missing_evidence_remains',
            ],
            'goal_achieved_rule' => 'All semantic conditions must pass; percentage alone never marks a goal achieved.',
            'read_only_surface' => ['contract', 'policy', 'status', 'calculate_progress', 'learner_progress',
                'class_summary'],
            'read_only' => true,
            'write_boundary' => [],
            'does_not_do' => [
                'goal_status_write',
                'mastery_state_write',
                'retention_state_write',
                'recommendation_write',
                'history_v1_source_mutation',
                'raw_moodle_log_scraping',
                'ux1_dashboard_composition',
            ],
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return the visible A5C metric policy.
     *
     * @return array
     */
    public static function policy(): array {
        return [
            'version' => self::PROGRESS_POLICY_VERSION,
            'target_weights' => self::TARGET_WEIGHTS,
            'thresholds' => [
                'mastery' => self::MASTERY_THRESHOLD,
                'confidence' => self::CONFIDENCE_THRESHOLD,
                'minimum_evidence_for_uncapped_readiness' => self::MIN_EVIDENCE_FOR_UNCAPPED_READINESS,
            ],
            'evidence_ceilings' => [
                '0' => 0.0,
                '1' => 0.55,
                '2' => 0.80,
                '3_or_more' => 1.0,
            ],
            'retention_ceilings' => [
                'new' => 0.55,
                'learning' => 0.65,
                'consolidating' => 0.90,
                'retained' => 1.0,
                'review_due' => 0.70,
                'retention_uncertain' => 0.60,
                'relearning' => 0.45,
                'missing' => 0.75,
            ],
            'completion' => [
                'numerator' => 'Distinct goal-relevant mapped cmids completed by the learner.',
                'denominator' => 'Distinct goal-relevant mapped cmids in the current course/unit scope.',
                'weights' => 'Equal per mapped cmid.',
                'source' => history_v1_consumer_contract::REQUIRED_CONTRACT,
                'incomplete_source_coverage' => 'Withhold percentage and show a qualitative incomplete-coverage state.',
            ],
            'mastery' => [
                'numerator' => 'Sum of target weight multiplied by mastery score capped by evidence sufficiency.',
                'denominator' => 'Sum of target weights for unique current requirements.',
            ],
            'goal_readiness' => [
                'numerator' => 'Sum of target weight multiplied by the minimum mastery, confidence, evidence, and retention attainment.',
                'denominator' => 'Sum of target weights for unique mandatory goal requirements.',
                'defensible_goal_statuses' => ['active', 'completed'],
            ],
            'path' => [
                'numerator' => 'Count of requirements classified satisfied by the frozen A4 gap contract.',
                'denominator' => 'Count of unique current A4 requirements.',
                'weights' => 'Equal per requirement.',
            ],
            'qualitative_milestones' => [
                'GOAL_NOT_SET',
                'GOAL_PAUSED',
                'GOAL_SCOPE_INCOMPLETE',
                'PREREQUISITES_NEEDED',
                'EVIDENCE_NEEDED',
                'RETENTION_CHECK_NEEDED',
                'BUILDING_TOWARD_GOAL',
                'READY_FOR_GOAL_CONFIRMATION',
                'GOAL_ACHIEVED',
            ],
            'read_only' => true,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return gate readiness without calculating or persisting learner metrics.
     *
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    public static function status(int $courseid = 0, string $unitcode = '', int $frameworkid = 0): array {
        $unitcode = self::clean_unit_code_optional($unitcode);
        $a5b = self::safe_status_call(static function() use ($courseid, $unitcode, $frameworkid): array {
            return trajectory_invariant_service::status($courseid, $unitcode, $frameworkid);
        });
        $files = self::file_status();
        $surface = self::surface_status();
        $criteria = self::criteria($a5b, $files, $surface);
        $summary = self::criteria_summary($criteria);

        return [
            'type' => 'CupkpProgressGoalReadinessStatus',
            'gate' => self::GATE,
            'status' => $summary['failed'] > 0 ? 'blocked' : 'ready',
            'contract' => self::contract(),
            'scope' => ['courseid' => $courseid, 'unitcode' => $unitcode, 'frameworkid' => $frameworkid],
            'criteria' => $criteria,
            'criteria_summary' => $summary,
            'dependency' => [
                'gate' => $a5b['gate'] ?? trajectory_invariant_service::GATE,
                'status' => $a5b['status'] ?? 'blocked',
                'contract' => $a5b['contract']['version'] ?? null,
                'next_allowed_gate' => $a5b['next_allowed_gate'] ?? null,
            ],
            'policy' => self::policy(),
            'files' => $files,
            'surface' => $surface,
            'findings' => self::status_findings($criteria, $a5b),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Pure metric calculation from frozen A1/A4 inputs and trusted completion context.
     *
     * @param array $goal A1 current-goal response.
     * @param array $path A4 learner-path response.
     * @param array $completioncontext Trusted History V1 completion context.
     * @return array
     */
    public static function calculate_progress(array $goal, array $path, array $completioncontext = []): array {
        $requirements = self::unique_requirements($path['goal_gap_analysis']['all_requirements'] ?? []);
        $goalrecord = is_array($goal['goal'] ?? null) ? $goal['goal'] : null;
        $goaldefence = self::goal_defensibility($goalrecord, $requirements);
        $weightdenominator = 0.0;
        $masterynumerator = 0.0;
        $readinessnumerator = 0.0;
        $weightedconfidence = 0.0;
        $satisfied = 0;
        $mandatorygaps = [];
        $blocked = [];
        $missingevidence = [];
        $insufficientevidence = [];
        $lowconfidence = [];
        $masterybelow = [];
        $retentiongaps = [];
        $retentiondue = [];
        $details = [];

        foreach ($requirements as $requirement) {
            $target = self::normalize_target($requirement['target'] ?? []);
            $key = self::target_key($target);
            $weight = self::TARGET_WEIGHTS[$target['type']] ?? 1.0;
            $state = is_array($requirement['state'] ?? null) ? $requirement['state'] : [];
            $score = self::clamp((float)($state['mastery_score'] ?? 0));
            $confidence = self::clamp((float)($state['confidence'] ?? 0));
            $evidencecount = max(0, (int)($state['evidence_count'] ?? 0));
            $strong = !empty($state['strong']);
            $retentionstate = strtolower(trim((string)($requirement['retention']['state'] ?? '')));
            $evidenceceiling = self::evidence_ceiling($evidencecount);
            $retentionceiling = self::retention_ceiling($retentionstate);
            $masteryattainment = $strong ? 1.0 : self::clamp($score / self::MASTERY_THRESHOLD);
            $confidenceattainment = self::clamp($confidence / self::CONFIDENCE_THRESHOLD);
            $readiness = min($masteryattainment, $confidenceattainment, $evidenceceiling, $retentionceiling);
            $masteryeffective = min($score, $evidenceceiling);
            $gapstatus = strtolower((string)($requirement['gap_status'] ?? 'missing'));

            $weightdenominator += $weight;
            $masterynumerator += $weight * $masteryeffective;
            $readinessnumerator += $weight * $readiness;
            $weightedconfidence += $weight * $confidence;
            if ($gapstatus === 'satisfied') {
                $satisfied++;
            } else {
                $mandatorygaps[] = $key;
            }
            if ($gapstatus === 'blocked_by_prerequisite') {
                $blocked[] = $key;
            }
            if ($evidencecount <= 0) {
                $missingevidence[] = $key;
            }
            if ($evidencecount < self::MIN_EVIDENCE_FOR_UNCAPPED_READINESS) {
                $insufficientevidence[] = $key;
            }
            if ($confidence < self::CONFIDENCE_THRESHOLD) {
                $lowconfidence[] = $key;
            }
            if (!$strong && $score < self::MASTERY_THRESHOLD) {
                $masterybelow[] = $key;
            }
            if ($retentionstate !== 'retained') {
                $retentiongaps[] = $key;
            }
            if (in_array($retentionstate, ['review_due', 'retention_uncertain', 'relearning'], true)) {
                $retentiondue[] = $key;
            }
            $details[] = [
                'target' => $target,
                'key' => $key,
                'weight' => $weight,
                'gap_status' => $gapstatus,
                'mastery_score' => round($score, 5),
                'mastery_attainment' => round($masteryattainment, 5),
                'confidence' => round($confidence, 5),
                'confidence_attainment' => round($confidenceattainment, 5),
                'evidence_count' => $evidencecount,
                'evidence_ceiling' => $evidenceceiling,
                'retention_state' => $retentionstate === '' ? 'missing' : $retentionstate,
                'retention_ceiling' => $retentionceiling,
                'readiness' => round($readiness, 5),
            ];
        }

        $requirementcount = count($requirements);
        $averageconfidence = $weightdenominator > 0 ? $weightedconfidence / $weightdenominator : 0.0;
        $masterypercentage = $weightdenominator > 0 ? ($masterynumerator / $weightdenominator) * 100 : null;
        $pathpercentage = $requirementcount > 0 ? ($satisfied / $requirementcount) * 100 : null;
        $readinesspercentage = $goaldefence['defensible'] && $weightdenominator > 0 ?
            ($readinessnumerator / $weightdenominator) * 100 : null;

        $completion = self::completion_metric($completioncontext);
        $mastery = self::metric('mastery_progress', $masterynumerator, $weightdenominator,
            $masterypercentage, $weightdenominator > 0 ? 'percentage' : 'qualitative', [
                'weights' => self::TARGET_WEIGHTS,
                'mandatory_gaps' => $mandatorygaps,
                'confidence' => [
                    'role' => 'reported_separately_not_multiplied_into_mastery_progress',
                    'weighted_average' => round($averageconfidence, 5),
                    'threshold' => self::CONFIDENCE_THRESHOLD,
                ],
                'retention' => ['role' => 'reported_separately_not_mastery_decay', 'gaps' => $retentiongaps],
                'evidence_ceiling' => self::policy()['evidence_ceilings'],
                'missing_evidence' => $missingevidence,
            ]);
        $pathmetric = self::metric('path_progress', (float)$satisfied, (float)$requirementcount,
            $pathpercentage, $requirementcount > 0 ? 'percentage' : 'qualitative', [
                'weights' => ['each_requirement' => 1.0],
                'mandatory_gaps' => $mandatorygaps,
                'confidence' => ['role' => 'does_not_change_A4_satisfied_classification'],
                'retention' => ['role' => 'already_reflected_in_A4_gap_classification', 'gaps' => $retentiongaps],
                'evidence_ceiling' => ['role' => 'not_applied_beyond_A4_gap_classification'],
                'missing_evidence' => $missingevidence,
            ]);
        $goalreadiness = self::metric('goal_readiness', $readinessnumerator, $weightdenominator,
            $readinesspercentage, $goaldefence['defensible'] ? 'percentage' : 'qualitative', [
                'weights' => self::TARGET_WEIGHTS,
                'mandatory_gaps' => $mandatorygaps,
                'confidence' => [
                    'role' => 'readiness_limiter',
                    'weighted_average' => round($averageconfidence, 5),
                    'threshold' => self::CONFIDENCE_THRESHOLD,
                    'below_threshold' => $lowconfidence,
                ],
                'retention' => [
                    'role' => 'readiness_limiter_and_achievement_condition',
                    'not_retained' => $retentiongaps,
                    'review_or_relearning' => $retentiondue,
                ],
                'evidence_ceiling' => self::policy()['evidence_ceilings'],
                'missing_evidence' => $missingevidence,
            ]);
        $goalreadiness['semantically_defensible'] = $goaldefence['defensible'];
        $goalreadiness['defensibility'] = $goaldefence;

        $conditions = [
            'current_goal_is_semantically_defensible' => $goaldefence['defensible'],
            'every_mandatory_requirement_is_satisfied' => $requirementcount > 0 && empty($mandatorygaps),
            'no_hard_prerequisite_is_blocked' => empty($blocked),
            'mastery_threshold_is_met_for_every_requirement' => $requirementcount > 0 && empty($masterybelow),
            'confidence_threshold_is_met_for_every_requirement' => $requirementcount > 0 && empty($lowconfidence),
            'minimum_evidence_is_met_for_every_requirement' => $requirementcount > 0 && empty($insufficientevidence),
            'retention_is_retained_for_every_requirement' => $requirementcount > 0 && empty($retentiongaps),
            'no_missing_evidence_remains' => $requirementcount > 0 && empty($missingevidence),
        ];
        $achieved = !in_array(false, $conditions, true);
        $milestone = self::milestone($goalrecord, $goaldefence, $requirementcount, $blocked, $missingevidence,
            $retentiondue, $mandatorygaps, $achieved);
        $preferred = $goaldefence['defensible'] ? [
            'metric' => 'goal_readiness',
            'display_mode' => 'percentage',
            'percentage' => $goalreadiness['percentage'],
            'milestone' => $milestone,
        ] : [
            'metric' => 'qualitative_milestone',
            'display_mode' => 'qualitative',
            'percentage' => null,
            'milestone' => $milestone,
        ];

        return [
            'type' => 'CupkpProgressGoalReadinessMetrics',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'progress_policy_version' => self::PROGRESS_POLICY_VERSION,
            'metrics' => [
                'completion_progress' => $completion,
                'mastery_progress' => $mastery,
                'goal_readiness' => $goalreadiness,
                'path_progress' => $pathmetric,
            ],
            'preferred_learner_metric' => $preferred,
            'goal_achievement' => [
                'achieved' => $achieved,
                'milestone' => $milestone,
                'conditions' => $conditions,
                'failed_conditions' => array_keys(array_filter($conditions, static function(bool $pass): bool {
                    return !$pass;
                })),
                'percentage_alone_is_sufficient' => false,
            ],
            'requirements' => [
                'total' => $requirementcount,
                'satisfied' => $satisfied,
                'mandatory_gaps' => $mandatorygaps,
                'blocked' => $blocked,
                'missing_evidence' => $missingevidence,
                'insufficient_evidence' => $insufficientevidence,
                'details' => $details,
            ],
            'source_hash' => hash('sha256', json_encode([
                'policy' => self::PROGRESS_POLICY_VERSION,
                'goal' => $goalrecord['checksum'] ?? null,
                'goalversion' => $goalrecord['currentversion'] ?? null,
                'path' => $path['explainability']['path_hash'] ?? null,
                'requirements' => $details,
                'completion' => $completioncontext,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Calculate one learner's current A5C metrics from trusted services.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param int $limit
     * @return array
     */
    public static function learner_progress(int $userid, int $courseid = 0, string $unitcode = '',
            int $frameworkid = 0, int $limit = 100): array {
        if ($userid <= 0) {
            throw new \invalid_parameter_exception('Learner ID is required.');
        }
        $unitcode = self::clean_unit_code_optional($unitcode);
        $limit = self::bounded_int($limit, 1, 500);
        $goal = learning_goal_service::current_goal($userid, $courseid, $unitcode, $frameworkid, 20);
        $path = goal_gap_path_service::learner_path($userid, $courseid, $unitcode, $frameworkid, $limit);
        $completion = self::completion_context($userid, $courseid, $unitcode, $frameworkid,
            $path['goal_gap_analysis']['all_requirements'] ?? []);
        $metrics = self::calculate_progress($goal, $path, $completion);

        return [
            'type' => 'CupkpLearnerProgressGoalReadiness',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'progress_policy_version' => self::PROGRESS_POLICY_VERSION,
            'userid' => $userid,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'progress' => $metrics,
            'goal' => $goal,
            'path' => [
                'status' => $path['path_status'] ?? 'unknown',
                'next_target' => $path['next_target'] ?? null,
                'destination' => $path['destination'] ?? null,
                'path_hash' => $path['explainability']['path_hash'] ?? '',
            ],
            'completion_context' => $completion,
            'sources' => [
                'goal_contract' => $goal['contract'] ?? learning_goal_service::CONTRACT_VERSION,
                'path_contract' => $path['contract'] ?? goal_gap_path_service::CONTRACT_VERSION,
                'history_contract' => $completion['source_contract'] ?? history_v1_consumer_contract::REQUIRED_CONTRACT,
            ],
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Return bounded class readiness metrics.
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
        $limit = self::bounded_int($limit, 1, 300);
        $userids = self::learner_ids_for_scope($courseid, $unitcode, $frameworkid, $limit);
        $summary = [
            'learners' => count($userids),
            'calculated' => 0,
            'failed' => 0,
            'goal_readiness_percentage_available' => 0,
            'qualitative_only' => 0,
            'goal_achieved' => 0,
            'average_goal_readiness' => null,
            'milestones' => [],
        ];
        $readinesstotal = 0.0;
        $rows = [];
        foreach ($userids as $userid) {
            try {
                $result = self::learner_progress((int)$userid, $courseid, $unitcode, $frameworkid, 150);
                $preferred = $result['progress']['preferred_learner_metric'];
                $achievement = $result['progress']['goal_achievement'];
                $summary['calculated']++;
                $milestone = (string)$achievement['milestone'];
                $summary['milestones'][$milestone] = ($summary['milestones'][$milestone] ?? 0) + 1;
                if ($preferred['metric'] === 'goal_readiness' && $preferred['percentage'] !== null) {
                    $summary['goal_readiness_percentage_available']++;
                    $readinesstotal += (float)$preferred['percentage'];
                } else {
                    $summary['qualitative_only']++;
                }
                if (!empty($achievement['achieved'])) {
                    $summary['goal_achieved']++;
                }
                $rows[] = [
                    'userid' => (int)$userid,
                    'learner' => self::learner_identity((int)$userid),
                    'preferred_metric' => $preferred,
                    'goal_achievement' => $achievement,
                    'metrics' => $result['progress']['metrics'],
                    'source_hash' => $result['progress']['source_hash'],
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $rows[] = [
                    'userid' => (int)$userid,
                    'learner' => self::learner_identity((int)$userid),
                    'error' => $e->getMessage(),
                ];
            }
        }
        if ($summary['goal_readiness_percentage_available'] > 0) {
            $summary['average_goal_readiness'] = round(
                $readinesstotal / $summary['goal_readiness_percentage_available'], 1
            );
        }
        ksort($summary['milestones']);

        return [
            'type' => 'CupkpClassProgressGoalReadinessSummary',
            'gate' => self::GATE,
            'contract' => self::CONTRACT_VERSION,
            'progress_policy_version' => self::PROGRESS_POLICY_VERSION,
            'scope' => [
                'courseid' => $courseid,
                'unitcode' => $unitcode,
                'frameworkid' => $frameworkid,
                'limit' => $limit,
            ],
            'summary' => $summary,
            'learners' => $rows,
            'read_only' => true,
            'write_boundary' => [],
            'state_changes_allowed' => false,
            'next_allowed_gate' => self::NEXT_ALLOWED_GATE,
        ];
    }

    /**
     * Build one metric with all percentage-contract fields present.
     *
     * @param string $code
     * @param float $numerator
     * @param float $denominator
     * @param float|null $percentage
     * @param string $displaymode
     * @param array $semantics
     * @return array
     */
    private static function metric(string $code, float $numerator, float $denominator, ?float $percentage,
            string $displaymode, array $semantics): array {
        return [
            'code' => $code,
            'status' => $percentage === null ? 'qualitative_only' : 'available',
            'display_mode' => $displaymode,
            'percentage' => $percentage === null ? null : round(self::clamp_percent($percentage), 1),
            'numerator' => round($numerator, 5),
            'denominator' => round($denominator, 5),
            'weights' => $semantics['weights'],
            'mandatory_gaps' => array_values($semantics['mandatory_gaps']),
            'confidence' => $semantics['confidence'],
            'retention' => $semantics['retention'],
            'evidence_ceiling' => $semantics['evidence_ceiling'],
            'missing_evidence' => array_values($semantics['missing_evidence']),
            'policy_version' => self::PROGRESS_POLICY_VERSION,
        ];
    }

    /**
     * Completion metric from a trusted History V1 context.
     *
     * @param array $context
     * @return array
     */
    private static function completion_metric(array $context): array {
        $eligible = array_values(array_unique(array_map('intval', $context['eligible_cmids'] ?? [])));
        $completed = array_values(array_intersect($eligible,
            array_unique(array_map('intval', $context['completed_cmids'] ?? []))));
        $coveragecomplete = !empty($context['coverage_complete']);
        $denominator = count($eligible);
        $percentage = $coveragecomplete && $denominator > 0 ? (count($completed) / $denominator) * 100 : null;
        $metric = self::metric('completion_progress', (float)count($completed), (float)$denominator,
            $percentage, $percentage === null ? 'qualitative' : 'percentage', [
                'weights' => ['each_mapped_cmid' => 1.0],
                'mandatory_gaps' => array_values(array_diff($eligible, $completed)),
                'confidence' => ['role' => 'not_applicable_to_activity_completion'],
                'retention' => ['role' => 'not_applicable_to_activity_completion'],
                'evidence_ceiling' => ['role' => 'not_applicable_to_activity_completion'],
                'missing_evidence' => array_values(array_diff($eligible, $completed)),
            ]);
        $metric['source_contract'] = (string)($context['source_contract'] ??
            history_v1_consumer_contract::REQUIRED_CONTRACT);
        $metric['coverage_complete'] = $coveragecomplete;
        $metric['coverage_status'] = (string)($context['status'] ?? 'unavailable');
        return $metric;
    }

    /**
     * Explain whether Goal Readiness has a semantic denominator.
     *
     * @param array|null $goal
     * @param array $requirements
     * @return array
     */
    private static function goal_defensibility(?array $goal, array $requirements): array {
        $status = strtolower((string)($goal['status'] ?? ''));
        $destination = is_array($goal['destination'] ?? null) ? $goal['destination'] : [];
        $targetcount = count($destination['competencyids'] ?? []) + count($destination['upids'] ?? []) +
            count($destination['kpids'] ?? []);
        $checks = [
            'current_versioned_goal_exists' => $goal !== null && (int)($goal['currentversion'] ?? 0) > 0,
            'goal_status_is_defensible' => in_array($status, ['active', 'completed'], true),
            'semantic_destination_target_exists' => $targetcount > 0,
            'requirement_denominator_exists' => count($requirements) > 0,
        ];
        return [
            'defensible' => !in_array(false, $checks, true),
            'checks' => $checks,
            'goal_status' => $status,
            'destination_target_count' => $targetcount,
            'requirement_count' => count($requirements),
        ];
    }

    /**
     * Qualitative milestone used when percentage is not defensible and alongside it when it is.
     *
     * @param array|null $goal
     * @param array $defence
     * @param int $requirements
     * @param array $blocked
     * @param array $missingevidence
     * @param array $retentiondue
     * @param array $mandatorygaps
     * @param bool $achieved
     * @return string
     */
    private static function milestone(?array $goal, array $defence, int $requirements, array $blocked,
            array $missingevidence, array $retentiondue, array $mandatorygaps, bool $achieved): string {
        if ($goal === null) {
            return 'GOAL_NOT_SET';
        }
        if (strtolower((string)($goal['status'] ?? '')) === 'paused') {
            return 'GOAL_PAUSED';
        }
        if ($requirements <= 0 || empty($defence['checks']['semantic_destination_target_exists'])) {
            return 'GOAL_SCOPE_INCOMPLETE';
        }
        if ($achieved) {
            return 'GOAL_ACHIEVED';
        }
        if ($blocked) {
            return 'PREREQUISITES_NEEDED';
        }
        if ($missingevidence) {
            return 'EVIDENCE_NEEDED';
        }
        if ($retentiondue) {
            return 'RETENTION_CHECK_NEEDED';
        }
        if ($mandatorygaps) {
            return 'BUILDING_TOWARD_GOAL';
        }
        return 'READY_FOR_GOAL_CONFIRMATION';
    }

    /**
     * Build trusted completion context without querying raw Moodle logs.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @param array $requirements
     * @return array
     */
    private static function completion_context(int $userid, int $courseid, string $unitcode, int $frameworkid,
            array $requirements): array {
        $eligible = self::mapped_cmids($requirements, $courseid, $unitcode, $frameworkid);
        $context = [
            'status' => $eligible ? 'history_completion_pending' : 'no_goal_relevant_mapped_activities',
            'source_contract' => history_v1_consumer_contract::REQUIRED_CONTRACT,
            'eligible_cmids' => $eligible,
            'completed_cmids' => [],
            'inprogress_cmids' => [],
            'coverage_complete' => $courseid > 0,
            'facts_inspected' => 0,
            'fact_total' => 0,
        ];
        if ($courseid <= 0 || !$eligible) {
            return $context;
        }
        $adapter = '\\local_flwhistory\\local\\evidence_source_adapter';
        if (!class_exists($adapter) || !method_exists($adapter, 'completions_for_course')) {
            $context['status'] = 'history_v1_completion_service_unavailable';
            $context['coverage_complete'] = false;
            return $context;
        }

        $eligibleindex = array_fill_keys($eligible, true);
        $seen = [];
        $offset = 0;
        try {
            do {
                $page = $adapter::completions_for_course($courseid, 500, $offset);
                if (($page['contract'] ?? '') !== history_v1_consumer_contract::REQUIRED_CONTRACT) {
                    $context['status'] = 'history_v1_contract_mismatch';
                    $context['coverage_complete'] = false;
                    break;
                }
                $pagination = $page['pagination'] ?? [];
                $context['fact_total'] = (int)($pagination['total'] ?? 0);
                foreach (($page['records'] ?? []) as $fact) {
                    $context['facts_inspected']++;
                    if ((int)($fact['userid'] ?? 0) !== $userid) {
                        continue;
                    }
                    $cmid = (int)($fact['cmid'] ?? 0);
                    if ($cmid <= 0 || empty($eligibleindex[$cmid]) || isset($seen[$cmid])) {
                        continue;
                    }
                    $seen[$cmid] = true;
                    if ((int)($fact['completionstate'] ?? 0) > 0) {
                        $context['completed_cmids'][] = $cmid;
                    } else if (!empty($fact['viewed'])) {
                        $context['inprogress_cmids'][] = $cmid;
                    }
                }
                $offset += count($page['records'] ?? []);
                $hasmore = !empty($pagination['hasmore']);
            } while ($hasmore && $offset < self::COMPLETION_FACT_LIMIT);

            if ($hasmore ?? false) {
                $context['coverage_complete'] = false;
                $context['status'] = 'history_completion_fact_limit_reached';
            } else if ($context['coverage_complete']) {
                $context['status'] = 'history_completion_complete';
            }
        } catch (\Throwable $e) {
            $context['status'] = 'history_completion_error';
            $context['coverage_complete'] = false;
            $context['error'] = $e->getMessage();
        }
        sort($context['completed_cmids'], SORT_NUMERIC);
        sort($context['inprogress_cmids'], SORT_NUMERIC);
        return $context;
    }

    /**
     * Resolve goal/path requirements to mapped Moodle cmids.
     *
     * @param array $requirements
     * @param int $courseid
     * @param string $unitcode
     * @param int $frameworkid
     * @return array
     */
    private static function mapped_cmids(array $requirements, int $courseid, string $unitcode,
            int $frameworkid): array {
        global $DB;

        $targets = ['kp' => [], 'up' => [], 'competency' => []];
        foreach (self::unique_requirements($requirements) as $requirement) {
            $target = self::normalize_target($requirement['target'] ?? []);
            if ($target['id'] > 0 && isset($targets[$target['type']])) {
                $targets[$target['type']][$target['id']] = $target['id'];
            }
        }
        $cmids = [];
        foreach ($targets as $type => $ids) {
            if (!$ids) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED, 'target');
            $types = $type === 'competency' ? ['competency', 'comp'] : [$type];
            [$typesql, $typeparams] = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
            $where = "m.targettype {$typesql} AND m.targetid {$insql} AND o.cmid IS NOT NULL AND o.cmid > 0";
            $params = $inparams + $typeparams;
            if ($courseid > 0) {
                $where .= ' AND o.courseid = :courseid';
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
            $sql = "SELECT DISTINCT o.cmid
                      FROM {flwcupkp_object_map} m
                      JOIN {flwcupkp_object} o ON o.id = m.objectid
                     WHERE {$where}";
            foreach ($DB->get_fieldset_sql($sql, $params) as $cmid) {
                $cmids[(int)$cmid] = (int)$cmid;
            }
        }
        sort($cmids, SORT_NUMERIC);
        return array_values($cmids);
    }

    /**
     * Unique requirements keyed by canonical target identity.
     *
     * @param array $rows
     * @return array
     */
    private static function unique_requirements(array $rows): array {
        $unique = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $target = self::normalize_target($row['target'] ?? []);
            $key = self::target_key($target);
            if ($target['id'] <= 0 || isset($unique[$key])) {
                continue;
            }
            $row['target'] = $target;
            $unique[$key] = $row;
        }
        ksort($unique);
        return array_values($unique);
    }

    /**
     * Normalize target identity.
     *
     * @param array $target
     * @return array
     */
    private static function normalize_target(array $target): array {
        $type = strtolower(trim((string)($target['type'] ?? $target['targettype'] ?? '')));
        if ($type === 'comp' || $type === 'competence') {
            $type = 'competency';
        }
        if (!isset(self::TARGET_WEIGHTS[$type])) {
            $type = 'kp';
        }
        return [
            'type' => $type,
            'id' => max(0, (int)($target['id'] ?? $target['targetid'] ?? 0)),
            'externalid' => (string)($target['externalid'] ?? ''),
            'title' => (string)($target['title'] ?? ''),
        ];
    }

    /**
     * Canonical target key.
     *
     * @param array $target
     * @return string
     */
    private static function target_key(array $target): string {
        return (string)$target['type'] . ':' . (string)$target['id'];
    }

    /**
     * Evidence count ceiling.
     *
     * @param int $count
     * @return float
     */
    private static function evidence_ceiling(int $count): float {
        if ($count <= 0) {
            return 0.0;
        }
        if ($count === 1) {
            return 0.55;
        }
        if ($count === 2) {
            return 0.80;
        }
        return 1.0;
    }

    /**
     * Retention ceiling.
     *
     * @param string $state
     * @return float
     */
    private static function retention_ceiling(string $state): float {
        $ceilings = self::policy()['retention_ceilings'];
        return (float)($ceilings[$state === '' ? 'missing' : $state] ?? $ceilings['missing']);
    }

    /**
     * Learner IDs from current enrollment plus scoped goals.
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

        $userids = [];
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            $users = get_enrolled_users($context, '', 0, 'u.id', 'u.lastname ASC, u.firstname ASC, u.id ASC',
                0, $limit, true);
            foreach ($users as $user) {
                $userids[(int)$user->id] = (int)$user->id;
            }
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
        foreach ($DB->get_records_select('flwcupkp_goal', $where, $params, 'userid ASC', 'DISTINCT userid',
                0, $limit) as $goal) {
            $userids[(int)$goal->userid] = (int)$goal->userid;
        }
        sort($userids, SORT_NUMERIC);
        return array_slice(array_values($userids), 0, $limit);
    }

    /**
     * Compact learner identity.
     *
     * @param int $userid
     * @return array
     */
    private static function learner_identity(int $userid): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid],
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email', IGNORE_MISSING);
        return [
            'id' => $userid,
            'fullname' => $user ? fullname($user) : (string)$userid,
            'email' => $user ? (string)$user->email : '',
        ];
    }

    /**
     * A5C readiness criteria.
     *
     * @param array $a5b
     * @param array $files
     * @param array $surface
     * @return array
     */
    private static function criteria(array $a5b, array $files, array $surface): array {
        $contract = self::contract();
        return [
            'a5b_frozen' => self::criterion('a5b_frozen',
                ($a5b['status'] ?? '') === 'ready' &&
                    (($a5b['contract']['version'] ?? '') === trajectory_invariant_service::CONTRACT_VERSION) &&
                    (($a5b['next_allowed_gate'] ?? '') === 'A5C'),
                'The frozen A5B invariant gate must be ready and hand off to A5C.'),
            'metric_separation_frozen' => self::criterion('metric_separation_frozen',
                array_keys($contract['metrics']) === self::METRICS,
                'Completion, mastery, goal readiness, and path progress must remain separate.'),
            'percentage_semantics_complete' => self::criterion('percentage_semantics_complete',
                $contract['percentage_contract_fields'] === ['numerator', 'denominator', 'weights',
                    'mandatory_gaps', 'confidence', 'retention', 'evidence_ceiling', 'missing_evidence',
                    'policy_version'],
                'Every percentage must expose all required semantic fields.'),
            'goal_achievement_semantic' => self::criterion('goal_achievement_semantic',
                count($contract['goal_achieved_semantic_conditions']) >= 8 &&
                    strpos($contract['goal_achieved_rule'], 'percentage alone never') !== false,
                'Goal achievement must require semantic conditions beyond a percentage.'),
            'history_v1_boundary_preserved' => self::criterion('history_v1_boundary_preserved',
                $contract['normal_source_history_input'] === history_v1_consumer_contract::REQUIRED_CONTRACT &&
                    in_array('raw_moodle_log_scraping', $contract['does_not_do'], true),
                'History V1 remains the only normal source-history input.'),
            'read_only_boundary_preserved' => self::criterion('read_only_boundary_preserved',
                !empty($contract['read_only']) && empty($contract['write_boundary']),
                'A5C must not persist progress, change goal status, or mutate learner state.'),
            'files_present' => self::criterion('files_present',
                !empty($files['present']['classes/local/progress_goal_readiness_service.php']) &&
                    !empty($files['present']['progress_readiness.php']) &&
                    !empty($files['present']['cli/progress_readiness.php']),
                'A5C service, Moodle page, and CLI must exist.'),
            'surface_present' => self::criterion('surface_present',
                !in_array(false, $surface['methods'], true) && !in_array(false, $surface['external_api'], true),
                'A5C service and read-only external API methods must be present.'),
            'next_gate_frozen' => self::criterion('next_gate_frozen', self::NEXT_ALLOWED_GATE === 'UX1',
                'A5C must stop before Program 3 UX1 dashboard composition.'),
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
            'classes/local/progress_goal_readiness_service.php',
            'progress_readiness.php',
            'cli/progress_readiness.php',
            'tests/progress_goal_readiness_service_test.php',
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
     * Runtime method status.
     *
     * @return array
     */
    private static function surface_status(): array {
        global $CFG;

        $methods = [];
        foreach (['contract', 'policy', 'status', 'calculate_progress', 'learner_progress', 'class_summary'] as $method) {
            $methods[self::class . '::' . $method] = method_exists(self::class, $method);
        }
        $source = @file_get_contents($CFG->dirroot . '/local/flwcupkp/classes/external/api.php') ?: '';
        $external = [];
        foreach (['get_progress_readiness_status', 'get_learner_progress_readiness',
            'get_class_progress_readiness_summary'] as $method) {
            $external[$method] = strpos($source, 'function ' . $method . '(') !== false;
        }
        return ['methods' => $methods, 'external_api' => $external];
    }

    /**
     * One criterion.
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
     * Criteria totals.
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
     * Findings from failed A5C criteria plus upstream non-blocking context.
     *
     * @param array $criteria
     * @param array $a5b
     * @return array
     */
    private static function status_findings(array $criteria, array $a5b): array {
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
        foreach (($a5b['findings'] ?? []) as $finding) {
            if (is_array($finding)) {
                $finding['source'] = $finding['source'] ?? trajectory_invariant_service::GATE;
                $findings[] = $finding;
            }
        }
        return $findings;
    }

    /**
     * Safely call an upstream status method.
     *
     * @param callable $callback
     * @return array
     */
    private static function safe_status_call(callable $callback): array {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return ['status' => 'blocked', 'findings' => [[
                'severity' => 'blocker',
                'code' => 'upstream_status_error',
                'message' => $e->getMessage(),
            ]]];
        }
    }

    /**
     * Normalize an optional unit code.
     *
     * @param string $unitcode
     * @return string
     */
    private static function clean_unit_code_optional(string $unitcode): string {
        $unitcode = strtoupper(trim($unitcode));
        if ($unitcode !== '' && !preg_match('/^[A-Z0-9][A-Z0-9_-]{0,39}$/', $unitcode)) {
            throw new \invalid_parameter_exception('Invalid unit code.');
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
     * Clamp a normalized value.
     *
     * @param float $value
     * @return float
     */
    private static function clamp(float $value): float {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Clamp a percentage.
     *
     * @param float $value
     * @return float
     */
    private static function clamp_percent(float $value): float {
        return max(0.0, min(100.0, $value));
    }
}
