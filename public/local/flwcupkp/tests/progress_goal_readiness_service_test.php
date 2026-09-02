<?php
// PHPUnit tests for Program 3 Gate A5C progress and goal readiness.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A5C progress semantic contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\progress_goal_readiness_service::class)]
class progress_goal_readiness_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_a5c_read_only_boundary(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\progress_goal_readiness_service::contract();
        $status = \local_flwcupkp\local\progress_goal_readiness_service::status((int)$course->id, 'UA5C', 0);

        $this->assertSame('P3_A5C', $contract['gate']);
        $this->assertSame('FLW_CUPKP_PROGRESS_GOAL_READINESS_CONTRACT_V1', $contract['version']);
        $this->assertSame('cupkp-progress-goal-readiness-v1', $contract['progress_policy_version']);
        $this->assertSame([
            'completion_progress', 'mastery_progress', 'goal_readiness', 'path_progress',
        ], array_keys($contract['metrics']));
        $this->assertSame([
            'numerator', 'denominator', 'weights', 'mandatory_gaps', 'confidence', 'retention',
            'evidence_ceiling', 'missing_evidence', 'policy_version',
        ], $contract['percentage_contract_fields']);
        $this->assertStringContainsString('percentage alone never', $contract['goal_achieved_rule']);
        $this->assertTrue($contract['read_only']);
        $this->assertSame([], $contract['write_boundary']);
        $this->assertSame('UX1', $contract['next_allowed_gate']);

        $this->assertSame('CupkpProgressGoalReadinessStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['files']['present']['progress_readiness.php']);
        $this->assertTrue($status['files']['present']['cli/progress_readiness.php']);
        $this->assertTrue($status['read_only']);
        $this->assertSame([], $status['write_boundary']);
        $this->assertSame('UX1', $status['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_four_metrics_have_distinct_numerators_denominators_and_policy_fields(): void {
        $this->resetAfterTest(true);
        $goal = $this->goal();
        $path = $this->path([
            $this->requirement('competency', 1, 'satisfied', 0.85, 0.80, 3, 'retained', true),
            $this->requirement('up', 2, 'missing', 0.50, 0.60, 2, 'learning'),
            $this->requirement('kp', 3, 'blocked_by_prerequisite', 0.20, 0.40, 1, 'new'),
        ]);
        $completion = [
            'status' => 'history_completion_complete',
            'source_contract' => \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            'eligible_cmids' => [10, 11],
            'completed_cmids' => [10],
            'coverage_complete' => true,
        ];

        $result = \local_flwcupkp\local\progress_goal_readiness_service::calculate_progress(
            $goal, $path, $completion
        );

        $this->assertSame('CupkpProgressGoalReadinessMetrics', $result['type']);
        $this->assertSame([
            'completion_progress', 'mastery_progress', 'goal_readiness', 'path_progress',
        ], array_keys($result['metrics']));
        $this->assertSame(50.0, $result['metrics']['completion_progress']['percentage']);
        $this->assertSame(62.5, $result['metrics']['mastery_progress']['percentage']);
        $this->assertSame(76.4, $result['metrics']['goal_readiness']['percentage']);
        $this->assertSame(33.3, $result['metrics']['path_progress']['percentage']);
        $this->assertSame(6.0, $result['metrics']['mastery_progress']['denominator']);
        $this->assertSame(3.0, $result['metrics']['path_progress']['denominator']);
        $this->assertSame(2.0, $result['metrics']['completion_progress']['denominator']);
        $this->assertSame('goal_readiness', $result['preferred_learner_metric']['metric']);
        $this->assertFalse($result['goal_achievement']['achieved']);
        $this->assertSame('PREREQUISITES_NEEDED', $result['goal_achievement']['milestone']);
        foreach ($result['metrics'] as $metric) {
            foreach (['numerator', 'denominator', 'weights', 'mandatory_gaps', 'confidence', 'retention',
                'evidence_ceiling', 'missing_evidence', 'policy_version'] as $field) {
                $this->assertArrayHasKey($field, $metric);
            }
        }
    }

    public function test_goal_readiness_percentage_is_withheld_when_goal_is_not_defensible(): void {
        $this->resetAfterTest(true);
        $goal = [
            'type' => 'CupkpLearnerLearningGoal',
            'goal' => null,
            'has_goal' => false,
        ];
        $path = $this->path([
            $this->requirement('kp', 3, 'satisfied', 1.0, 1.0, 4, 'retained', true),
        ]);

        $result = \local_flwcupkp\local\progress_goal_readiness_service::calculate_progress($goal, $path);

        $this->assertNull($result['metrics']['goal_readiness']['percentage']);
        $this->assertSame('qualitative', $result['metrics']['goal_readiness']['display_mode']);
        $this->assertFalse($result['metrics']['goal_readiness']['semantically_defensible']);
        $this->assertSame('qualitative_milestone', $result['preferred_learner_metric']['metric']);
        $this->assertNull($result['preferred_learner_metric']['percentage']);
        $this->assertSame('GOAL_NOT_SET', $result['preferred_learner_metric']['milestone']);
        $this->assertFalse($result['goal_achievement']['achieved']);
    }

    public function test_one_hundred_percent_never_marks_goal_achieved_without_semantic_conditions(): void {
        $this->resetAfterTest(true);
        $goal = $this->goal();
        $notsatisfied = $this->path([
            $this->requirement('competency', 1, 'missing', 1.0, 1.0, 4, 'retained', true),
        ]);

        $blocked = \local_flwcupkp\local\progress_goal_readiness_service::calculate_progress($goal, $notsatisfied);

        $this->assertSame(100.0, $blocked['metrics']['goal_readiness']['percentage']);
        $this->assertFalse($blocked['goal_achievement']['achieved']);
        $this->assertFalse($blocked['goal_achievement']['conditions']
            ['every_mandatory_requirement_is_satisfied']);
        $this->assertFalse($blocked['goal_achievement']['percentage_alone_is_sufficient']);
        $this->assertSame('BUILDING_TOWARD_GOAL', $blocked['goal_achievement']['milestone']);

        $satisfied = $this->path([
            $this->requirement('competency', 1, 'satisfied', 1.0, 1.0, 4, 'retained', true),
        ]);
        $achieved = \local_flwcupkp\local\progress_goal_readiness_service::calculate_progress($goal, $satisfied);

        $this->assertSame(100.0, $achieved['metrics']['goal_readiness']['percentage']);
        $this->assertTrue($achieved['goal_achievement']['achieved']);
        $this->assertSame('GOAL_ACHIEVED', $achieved['goal_achievement']['milestone']);
    }

    public function test_learner_and_class_calculation_are_read_only_for_goal_not_set(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $before = $this->mutation_counts();

        $learner = \local_flwcupkp\local\progress_goal_readiness_service::learner_progress(
            (int)$user->id, (int)$course->id, 'UA5CN', 0, 50
        );
        $class = \local_flwcupkp\local\progress_goal_readiness_service::class_summary(
            (int)$course->id, 'UA5CN', 0, 20
        );

        $this->assertSame('CupkpLearnerProgressGoalReadiness', $learner['type']);
        $this->assertSame('qualitative_milestone', $learner['progress']['preferred_learner_metric']['metric']);
        $this->assertSame('GOAL_NOT_SET', $learner['progress']['preferred_learner_metric']['milestone']);
        $this->assertTrue($learner['read_only']);
        $this->assertSame([], $learner['write_boundary']);
        $this->assertSame('CupkpClassProgressGoalReadinessSummary', $class['type']);
        $this->assertGreaterThanOrEqual(1, $class['summary']['learners']);
        $this->assertGreaterThanOrEqual(1, $class['summary']['qualitative_only']);
        $this->assertSame($before, $this->mutation_counts());
    }

    /**
     * A versioned competency-centered goal response.
     *
     * @return array
     */
    private function goal(): array {
        return [
            'type' => 'CupkpLearnerLearningGoal',
            'has_goal' => true,
            'goal' => [
                'id' => 1,
                'status' => 'active',
                'currentversion' => 1,
                'checksum' => str_repeat('a', 64),
                'destination' => [
                    'competencyids' => [1],
                    'upids' => [],
                    'kpids' => [],
                ],
            ],
        ];
    }

    /**
     * A frozen A4 path response.
     *
     * @param array $requirements
     * @return array
     */
    private function path(array $requirements): array {
        return [
            'type' => 'CupkpLearnerGoalGapInitialPath',
            'goal_gap_analysis' => ['all_requirements' => $requirements],
            'explainability' => ['path_hash' => sha1(json_encode($requirements))],
        ];
    }

    /**
     * One A4 requirement row.
     *
     * @param string $type
     * @param int $id
     * @param string $gapstatus
     * @param float $mastery
     * @param float $confidence
     * @param int $evidence
     * @param string $retention
     * @param bool $strong
     * @return array
     */
    private function requirement(string $type, int $id, string $gapstatus, float $mastery,
            float $confidence, int $evidence, string $retention, bool $strong = false): array {
        return [
            'target' => ['type' => $type, 'id' => $id, 'externalid' => strtoupper($type) . '-' . $id],
            'gap_status' => $gapstatus,
            'state' => [
                'has_state' => true,
                'mastery_score' => $mastery,
                'mastery_state' => $strong ? 'strong' : 'developing',
                'strong' => $strong,
                'confidence' => $confidence,
                'evidence_count' => $evidence,
                'performance_modes' => ['assessment' => $evidence],
            ],
            'retention' => ['state' => $retention, 'needs_verification' => $retention !== 'retained'],
            'prerequisites' => ['blocking' => $gapstatus === 'blocked_by_prerequisite' ? [['target' => ['id' => 99]]] : []],
        ];
    }

    /**
     * Counts tables A5C must not mutate.
     *
     * @return array
     */
    private function mutation_counts(): array {
        global $DB;

        return [
            'evidence' => $DB->count_records('flwcupkp_evidence'),
            'state' => $DB->count_records('flwcupkp_state'),
            'recommend' => $DB->count_records('flwcupkp_recommend'),
            'audit' => $DB->count_records('flwcupkp_audit'),
            'goal' => $DB->count_records('flwcupkp_goal'),
            'goalversion' => $DB->count_records('flwcupkp_goal_version'),
            'placement' => $DB->count_records('flwcupkp_placement_state'),
        ];
    }
}
