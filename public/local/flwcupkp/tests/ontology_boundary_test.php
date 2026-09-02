<?php
// PHPUnit tests for Program 3 Gate C1B ontology boundaries.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Ontology boundary tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\ontology_boundary::class)]
class ontology_boundary_test extends \advanced_testcase {
    public function test_contract_extends_c1_and_preserves_history_boundary(): void {
        $contract = \local_flwcupkp\local\ontology_boundary::contract();

        $this->assertSame('P3_C1B', $contract['gate']);
        $this->assertSame('FLW_CUPKP_ONTOLOGY_BOUNDARY_V1', $contract['version']);
        $this->assertSame(\local_flwcupkp\local\canonical_domain_model::CONTRACT_VERSION, $contract['depends_on']);
        $this->assertContains('semantic_duplicate_across_types', $contract['validation']['detects']);
        $this->assertContains('raw_moodle_log_scraping', $contract['validation']['does_not_do']);
        $this->assertArrayHasKey('examples', $contract['authoring_reference']);
        $this->assertArrayHasKey('counterexamples', $contract['authoring_reference']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['source_history_boundary']['normal_source_history_input']
        );
    }

    public function test_multi_skill_language_example_package_passes_boundary_validation(): void {
        $package = [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'FW-C1B-A2',
                'name' => 'C1B A2 Framework',
                'cefr_range' => 'A2-B1',
                'status' => 'draft',
            ]],
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Discuss a local problem and agree on a next step',
                'can_do' => 'Can explain a problem, compare options, and agree on action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C1B-A2',
                'status' => 'draft',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Negotiate a group decision politely',
                'action_statement' => 'Use known suggestion language to move the group to a decision.',
                'observable_action' => 'Compares two options and summarizes the chosen action.',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C1B-A2',
                'status' => 'draft',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Suggestion expressions for alternatives',
                'description' => 'Expressions for suggesting alternatives politely.',
                'domain' => 'FUNC',
                'cefr' => 'A2',
                'framework_externalid' => 'FW-C1B-A2',
                'status' => 'draft',
            ]],
            'competency_up_mappings' => [[
                'competency_externalid' => 'C-FR-A2-SI-004',
                'up_externalid' => 'UP-FR-A2-SI-031-04',
                'role' => 'required',
                'weight' => 1,
            ]],
            'up_kp_mappings' => [[
                'up_externalid' => 'UP-FR-A2-SI-031-04',
                'kp_externalid' => 'KP-FR-A2-FUNC-031',
                'role' => 'required',
                'weight' => 1,
            ]],
            'project_evidence' => [[
                'object_externalid' => 'PROJECT-C1B',
                'competency_externalid' => 'C-FR-A2-SI-004',
            ]],
        ];

        $result = \local_flwcupkp\local\ontology_boundary::validate_package($package);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
        $this->assertSame('FLW_CUPKP_ONTOLOGY_BOUNDARY_V1', $result['contract']);
    }

    public function test_boundary_detects_overly_narrow_competency(): void {
        $result = \local_flwcupkp\local\ontology_boundary::validate_curriculum_row('competency', [
            'externalid' => 'C-FR-A2-SI-004',
            'title' => 'Past tense regular verbs',
            'status' => 'draft',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Overly narrow competency', implode(' ', $result['errors']));
    }

    public function test_boundary_detects_kp_written_as_task(): void {
        $result = \local_flwcupkp\local\ontology_boundary::validate_curriculum_row('kp', [
            'externalid' => 'KP-FR-A2-FUNC-031',
            'title' => 'Write a solution note to your manager',
            'domain' => 'WRITE',
            'status' => 'draft',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('KP written as task', implode(' ', $result['errors']));
    }

    public function test_boundary_detects_up_containing_unmodeled_new_knowledge(): void {
        $result = \local_flwcupkp\local\ontology_boundary::validate_curriculum_row('up', [
            'externalid' => 'UP-FR-A2-SI-031-04',
            'title' => 'Use new vocabulary to discuss delays',
            'action_statement' => 'New vocabulary: deadline, delay, workflow.',
            'form' => 'deadline, delay, workflow',
            'status' => 'draft',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('UP containing unmodeled new knowledge', implode(' ', $result['errors']));
    }

    public function test_boundary_detects_semantic_duplicate_across_types(): void {
        $package = [
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Ask for clarification',
                'status' => 'draft',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Ask for clarification',
                'status' => 'draft',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Clarification expressions',
                'domain' => 'FUNC',
                'status' => 'draft',
            ]],
        ];

        $result = \local_flwcupkp\local\ontology_boundary::validate_package($package);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Semantic duplicate across types', implode(' ', $result['errors']));
    }

    public function test_validator_returns_separate_ontology_result(): void {
        $package = [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'FW-C1B',
                'name' => 'C1B Framework',
                'status' => 'draft',
            ]],
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Past tense regular verbs',
                'status' => 'draft',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Use past tense in a story',
                'status' => 'draft',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-GRAM-031',
                'title' => 'Past tense regular verbs',
                'domain' => 'GRAM',
                'status' => 'draft',
            ]],
            'project_evidence' => [[
                'object_externalid' => 'PROJECT-C1B',
                'competency_externalid' => 'C-FR-A2-SI-004',
            ]],
        ];

        $validation = \local_flwcupkp\local\validator::validate_package($package);

        $this->assertFalse($validation['valid']);
        $this->assertArrayHasKey('ontology', $validation);
        $this->assertFalse($validation['ontology']['valid']);
        $this->assertContains('FLW_CUPKP_ONTOLOGY_BOUNDARY_V1', $validation['ontology']['contracts']);
        $this->assertNotEmpty($validation['ontology']['details']);
    }

    public function test_package_mapping_rejects_cross_framework_and_lifecycle_drift(): void {
        $package = [
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-004',
                'title' => 'Discuss a local problem and agree on a next step',
                'framework_externalid' => 'FW-ONE',
                'status' => 'active',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Negotiate a group decision politely',
                'framework_externalid' => 'FW-TWO',
                'status' => 'archived',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Suggestion expressions',
                'domain' => 'FUNC',
                'framework_externalid' => 'FW-TWO',
                'status' => 'active',
            ]],
            'competency_up_mappings' => [[
                'competency_externalid' => 'C-FR-A2-SI-004',
                'up_externalid' => 'UP-FR-A2-SI-031-04',
                'role' => 'required',
            ]],
        ];

        $result = \local_flwcupkp\local\ontology_boundary::validate_package($package);

        $this->assertFalse($result['valid']);
        $joined = implode(' ', $result['errors']);
        $this->assertStringContainsString('crosses framework boundary', $joined);
        $this->assertStringContainsString('status is archived', $joined);
    }

    public function test_manual_entity_and_mapping_saves_enforce_boundary(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $now = time();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'C1B-FW',
            'name' => 'C1B Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = $DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'C-FR-A2-SI-004',
            'title' => 'Discuss a local problem and agree on a next step',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = $DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-FR-A2-SI-031-04',
            'title' => 'Negotiate a group decision politely',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        try {
            \local_flwcupkp\local\curriculum_manager::save_entity('competency', [
                'frameworkid' => $frameworkid,
                'externalid' => 'C-FR-A2-SI-005',
                'title' => 'Past tense regular verbs',
                'status' => 'draft',
                'version' => '1.0',
            ]);
            $this->fail('Expected narrow competency to be rejected.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Overly narrow competency', $e->getMessage());
        }

        $this->expectException(\invalid_parameter_exception::class);
        \local_flwcupkp\local\curriculum_manager::save_mapping('comp_up', [
            'competencyid' => $compid,
            'upid' => $upid,
            'role' => 'mastery_state',
            'weight' => 1,
        ]);
    }

    public function test_boundary_status_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeaudit = $DB->count_records('flwcupkp_audit');

        $status = \local_flwcupkp\local\ontology_boundary::boundary_status((int)$course->id, 0, 10);

        $this->assertSame('CupkpOntologyBoundaryStatus', $status['type']);
        $this->assertSame('P3_C1B', $status['gate']);
        $this->assertSame('FLW_CUPKP_ONTOLOGY_BOUNDARY_V1', $status['contract']['version']);
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
    }
}

