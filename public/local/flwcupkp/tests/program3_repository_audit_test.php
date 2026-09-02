<?php
// PHPUnit tests for Program 3 Gate C0 repository audit.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Program 3 Gate C0 repository audit tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\program3_repository_audit::class)]
class program3_repository_audit_test extends \advanced_testcase {
    public function test_subsystem_classification_covers_c0_prompt_items(): void {
        $classification = \local_flwcupkp\local\program3_repository_audit::subsystem_classification();
        $expected = [
            'schema',
            'c_kp_up',
            'mappings',
            'prerequisites',
            'evidence',
            'mastery',
            'learner_state',
            'goal',
            'placement',
            'recommendation',
            'timeline',
            'teacher_admin_ui',
            'tests',
            'privacy',
            'backup_restore',
        ];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $classification);
            $this->assertNotEmpty($classification[$key]['current']);
            $this->assertNotEmpty($classification[$key]['next']);
            foreach ($classification[$key]['classification'] as $value) {
                $this->assertContains($value, \local_flwcupkp\local\program3_repository_audit::CLASSIFICATIONS);
            }
        }

        $this->assertContains('KEEP', $classification['evidence']['classification']);
        $this->assertContains('UNKNOWN', $classification['backup_restore']['classification']);
    }

    public function test_foundation_gaps_cover_c1_through_c5(): void {
        $gaps = \local_flwcupkp\local\program3_repository_audit::foundation_gaps();

        foreach (['C1', 'C1B', 'C2', 'C3', 'C3B', 'C4', 'C5'] as $gate) {
            $this->assertArrayHasKey($gate, $gaps);
            $this->assertNotEmpty($gaps[$gate]['gate']);
            $this->assertNotEmpty($gaps[$gate]['gaps']);
        }

        $this->assertStringContainsString('History V1', implode(' ', $gaps['C3B']['gaps']));
        $this->assertStringContainsString('Foundation V1', implode(' ', $gaps['C5']['gaps']));
    }

    public function test_audit_status_is_read_only_and_preserves_history_boundary(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $audit = \local_flwcupkp\local\program3_repository_audit::audit_status((int)$course->id);

        $this->assertSame('Program3C0RepositoryAudit', $audit['type']);
        $this->assertSame('P3_C0', $audit['gate']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $audit['normal_source_rule']
        );
        $this->assertSame('diagnostic_only', $audit['boundary']['raw_moodle_logs']);
        $this->assertFalse($audit['boundary']['state_changes_allowed_in_gate']);
        $this->assertSame([], $audit['boundary']['write_boundary']);
        $this->assertNull($audit['boundary']['next_allowed_gate']);
        $this->assertSame([], $audit['boundary']['not_started']);
        $this->assertTrue($audit['boundary']['final_gate']);
        $this->assertTrue($audit['boundary']['production_readiness_requires_scope_validation']);
        $this->assertNotContains('teacher_admin_explainability_and_override', $audit['boundary']['not_started']);
        $this->assertNotContains('learner_ux_simplification', $audit['boundary']['not_started']);
        $this->assertNotContains('past_present_future_dashboard_integration', $audit['boundary']['not_started']);
        $this->assertNotContains('trajectory_simulation_and_invariant_testing', $audit['boundary']['not_started']);
        $this->assertNotContains('progress_goal_readiness_contract', $audit['boundary']['not_started']);
        $this->assertNotContains('goal_gap_initial_personalized_path', $audit['boundary']['not_started']);
        $this->assertNotContains('candidate_activity_resolution', $audit['boundary']['not_started']);
        $this->assertArrayHasKey('C1', $audit['foundation_gaps']);
        $this->assertArrayHasKey('flwcupkp_goal', $audit['runtime']['tables']);
        $this->assertArrayHasKey('flwcupkp_goal_version', $audit['runtime']['tables']);
        $this->assertArrayHasKey('flwcupkp_placement_state', $audit['runtime']['tables']);
        $this->assertArrayHasKey('flwcupkp_intervention', $audit['runtime']['tables']);
        $this->assertArrayHasKey('flwcupkp_evidence', $audit['runtime']['tables']);
        $this->assertTrue($audit['runtime']['classes']['learning_goal_service']);
        $this->assertTrue($audit['runtime']['classes']['placement_diagnostic_service']);
        $this->assertTrue($audit['runtime']['classes']['adaptive_decision_policy_service']);
        $this->assertTrue($audit['runtime']['classes']['goal_gap_path_service']);
        $this->assertTrue($audit['runtime']['classes']['candidate_activity_resolution_service']);
        $this->assertTrue($audit['runtime']['classes']['adaptive_path_engine_service']);
        $this->assertTrue($audit['runtime']['classes']['trajectory_invariant_service']);
        $this->assertTrue($audit['runtime']['classes']['progress_goal_readiness_service']);
        $this->assertTrue($audit['runtime']['classes']['student_learning_timeline_view_service']);
        $this->assertTrue($audit['runtime']['classes']['student_learning_timeline_renderer']);
        $this->assertTrue($audit['runtime']['classes']['learner_experience_service']);
        $this->assertTrue($audit['runtime']['classes']['learner_experience_renderer']);
        $this->assertTrue($audit['runtime']['classes']['staff_intelligence_service']);
        $this->assertTrue($audit['runtime']['classes']['staff_intelligence_renderer']);
        $this->assertTrue($audit['runtime']['classes']['production_validation_service']);
        $this->assertTrue($audit['runtime']['classes']['evidence_semantics_quality_contract']);
        $this->assertTrue($audit['runtime']['classes']['lifecycle_governance_contract']);
        $this->assertTrue($audit['runtime']['classes']['foundation_v1_contract']);
        $this->assertTrue($audit['runtime']['classes']['core_curriculum_manager']);
        $this->assertTrue($audit['runtime']['classes']['relationship_where_used_manager']);
        $this->assertTrue($audit['runtime']['classes']['management_v1_contract']);
        $this->assertTrue($audit['runtime']['classes']['history_evidence_adapter']);
        $this->assertTrue($audit['runtime']['classes']['mastery_state_service']);
        $this->assertTrue($audit['runtime']['classes']['retention_review_service']);
        $this->assertTrue($audit['runtime']['files']['present']['foundation.php']);
        $this->assertTrue($audit['runtime']['files']['present']['entity.php']);
        $this->assertTrue($audit['runtime']['files']['present']['management.php']);
        $this->assertTrue($audit['runtime']['files']['present']['history_evidence.php']);
        $this->assertTrue($audit['runtime']['files']['present']['mastery_state.php']);
        $this->assertTrue($audit['runtime']['files']['present']['retention_review.php']);
        $this->assertTrue($audit['runtime']['files']['present']['learning_goal.php']);
        $this->assertTrue($audit['runtime']['files']['present']['placement_diagnostic.php']);
        $this->assertTrue($audit['runtime']['files']['present']['adaptive_decision.php']);
        $this->assertTrue($audit['runtime']['files']['present']['initial_path.php']);
        $this->assertTrue($audit['runtime']['files']['present']['activity_resolution.php']);
        $this->assertTrue($audit['runtime']['files']['present']['adaptive_path.php']);
        $this->assertTrue($audit['runtime']['files']['present']['trajectory_simulation.php']);
        $this->assertTrue($audit['runtime']['files']['present']['progress_readiness.php']);
        $this->assertTrue($audit['runtime']['files']['present']['learning_timeline.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/learning_timeline.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/learner_experience.php']);
        $this->assertTrue($audit['runtime']['files']['present']['staff_intelligence.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/staff_intelligence.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/production_validation.php']);

        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }
}
