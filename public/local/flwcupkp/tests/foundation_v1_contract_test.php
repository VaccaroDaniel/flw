<?php
// PHPUnit tests for Program 3 Gate C5 Foundation V1.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Foundation V1 freeze tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\foundation_v1_contract::class)]
class foundation_v1_contract_test extends \advanced_testcase {
    public function test_contract_records_required_versions_and_boundaries(): void {
        $contract = \local_flwcupkp\local\foundation_v1_contract::contract();
        $versions = $contract['recorded_versions'];
        $api = $contract['adaptive_api_contract'];

        $this->assertSame('P3_C5', $contract['gate']);
        $this->assertSame('FLW_CUPKP_FOUNDATION_V1', $contract['version']);
        $this->assertSame(
            \local_flwcupkp\local\foundation_v1_contract::CURRICULUM_CONTRACT_VERSION,
            $versions['curriculum_contract_version']
        );
        $this->assertSame(
            \local_flwcupkp\local\relationship_graph_contract::CONTRACT_VERSION,
            $versions['relationship_contract_version']
        );
        $this->assertSame(
            \local_flwcupkp\local\evidence_semantics_quality_contract::EVIDENCE_POLICY_VERSION,
            $versions['evidence_policy_version']
        );
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['normal_source_history_input']
        );
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertContains('relationship_graph_contract::dependencies_for_target', $api['allowed_read_apis']);
        $this->assertContains('foundation_v1_contract::foundation_status', $api['allowed_read_apis']);
        $this->assertContains('adaptive path ranking or selection', $api['forbidden_until_later_gates']);
        $this->assertFalse($api['state_changes_allowed']);
    }

    public function test_adaptive_api_contract_covers_the_ten_foundation_reliance_items(): void {
        $api = \local_flwcupkp\local\foundation_v1_contract::adaptive_api_contract();

        $expected = [
            'competency_identification',
            'kp_identification',
            'up_identification',
            'relationship_queries',
            'prerequisite_queries',
            'content_mapping_queries',
            'evidence_representation',
            'deprecated_record_behavior',
            'version_behavior',
            'read_only_foundation_status',
        ];

        foreach ($expected as $item) {
            $this->assertContains($item, $api['may_rely_on']);
        }
        $this->assertFalse($api['state_changes_allowed']);
    }

    public function test_authoritative_implementation_status_has_no_missing_services(): void {
        $status = \local_flwcupkp\local\foundation_v1_contract::authoritative_implementation_status();

        $this->assertTrue($status['valid'], json_encode($status['findings']));
        foreach ([
            'competency_identification',
            'kp_identification',
            'up_identification',
            'relationships_and_prerequisites',
            'content_mappings',
            'evidence_mappings_and_semantics',
            'lifecycle_versioning_governance',
            'validation',
        ] as $area) {
            $this->assertArrayHasKey($area, $status['areas']);
            $this->assertTrue($status['areas'][$area]['valid'], $area);
        }
        $this->assertTrue($status['legacy_duplicate_checks']['mandatory_cycle_detection_centralized']);
    }

    public function test_foundation_status_is_read_only_and_points_to_e2_after_e1(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $status = \local_flwcupkp\local\foundation_v1_contract::foundation_status(0, '', 0, 20);

        $this->assertSame('P3_C5', $status['gate']);
        $this->assertSame('frozen', $status['status'], json_encode($status['findings']));
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertSame(0, $status['unresolved_blocker_high_count'], json_encode($status['findings']));
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertArrayHasKey('migration_readiness', $status);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_foundation_status_blocks_unresolved_high_dependency_findings(): void {
        $this->resetAfterTest(true);
        self::create_framework('FW-C5-BAD', 'published', '');

        $status = \local_flwcupkp\local\foundation_v1_contract::foundation_status(0, '', 0, 20);
        $severities = array_column($status['findings'], 'severity');

        $this->assertSame('blocked', $status['status']);
        $this->assertGreaterThan(0, $status['unresolved_blocker_high_count']);
        $this->assertContains('HIGH', $severities);
    }

    public function test_repository_audit_recognizes_management_v1(): void {
        $audit = \local_flwcupkp\local\program3_repository_audit::audit_status();

        $this->assertNull($audit['boundary']['next_allowed_gate']);
        $this->assertTrue($audit['boundary']['final_gate']);
        $this->assertTrue($audit['runtime']['classes']['foundation_v1_contract']);
        $this->assertTrue($audit['runtime']['classes']['core_curriculum_manager']);
        $this->assertTrue($audit['runtime']['classes']['relationship_where_used_manager']);
        $this->assertTrue($audit['runtime']['classes']['management_v1_contract']);
        $this->assertTrue($audit['runtime']['classes']['mastery_state_service']);
        $this->assertTrue($audit['runtime']['classes']['retention_review_service']);
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
        $this->assertTrue($audit['runtime']['files']['present']['foundation.php']);
        $this->assertTrue($audit['runtime']['files']['present']['entity.php']);
        $this->assertTrue($audit['runtime']['files']['present']['management.php']);
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
        $this->assertTrue($audit['runtime']['files']['present']['cli/learner_experience.php']);
        $this->assertTrue($audit['runtime']['files']['present']['staff_intelligence.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/staff_intelligence.php']);
        $this->assertTrue($audit['runtime']['files']['present']['cli/production_validation.php']);
        $this->assertStringContainsString('Complete as of Program 3 Gate C5',
            implode(' ', $audit['foundation_gaps']['C5']['gaps']));
    }

    private static function create_framework(string $externalid, string $status, string $version): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => $externalid,
            'version' => $version,
            'status' => $status,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
