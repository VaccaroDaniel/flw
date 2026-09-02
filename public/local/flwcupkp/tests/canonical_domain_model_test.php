<?php
// PHPUnit tests for Program 3 Gate C1 canonical C-UP-KP domain model.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Canonical domain model tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\canonical_domain_model::class)]
class canonical_domain_model_test extends \advanced_testcase {
    public function test_contract_freezes_meanings_topology_and_history_boundary(): void {
        $contract = \local_flwcupkp\local\canonical_domain_model::contract();

        $this->assertSame('FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1', $contract['version']);
        $this->assertStringContainsString('integrated ability', $contract['entities']['competency']['meaning']);
        $this->assertStringContainsString('Observable use point', $contract['entities']['up']['meaning']);
        $this->assertStringContainsString('knowledge needed', $contract['entities']['kp']['meaning']);
        $this->assertFalse($contract['topology']['strict_tree']);
        $this->assertTrue($contract['topology']['many_to_many']);
        $this->assertSame('many_to_many', $contract['topology']['relations']['competency_to_up']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['source_history_boundary']['normal_source_history_input']
        );
    }

    public function test_semantic_code_policy_accepts_canonical_and_existing_flw_styles(): void {
        $this->assertTrue(\local_flwcupkp\local\canonical_domain_model::semantic_code_status(
            'competency',
            'C-FR-A2-SI-004'
        )['valid']);
        $this->assertTrue(\local_flwcupkp\local\canonical_domain_model::semantic_code_status(
            'up',
            'FLW-REW-B1-UP-038-01'
        )['valid']);
        $this->assertTrue(\local_flwcupkp\local\canonical_domain_model::semantic_code_status(
            'kp',
            'FLW-EN-B1-LEX-038-001'
        )['valid']);
    }

    public function test_semantic_code_policy_rejects_wrong_entity_prefix(): void {
        $status = \local_flwcupkp\local\canonical_domain_model::semantic_code_status(
            'competency',
            'KP-FR-A2-FUNC-031'
        );

        $this->assertFalse($status['valid']);
        $this->assertSame('type_mismatch', $status['status']);
    }

    public function test_cefr_and_stage_are_kept_separate(): void {
        $valid = \local_flwcupkp\local\canonical_domain_model::validate_curriculum_row('competency', [
            'externalid' => 'C-FR-A2-SI-004',
            'cefr' => 'A2',
            'stage' => 'FLW-STAGE-02',
        ]);
        $this->assertTrue($valid['valid']);

        $invalidcefr = \local_flwcupkp\local\canonical_domain_model::validate_curriculum_row('competency', [
            'externalid' => 'C-FR-A2-SI-004',
            'cefr' => 'A2.1',
            'stage' => 'FLW-STAGE-02',
        ]);
        $this->assertFalse($invalidcefr['valid']);

        $invalidstage = \local_flwcupkp\local\canonical_domain_model::validate_curriculum_row('competency', [
            'externalid' => 'C-FR-A2-SI-004',
            'cefr' => 'A2',
            'stage' => 'A2.1',
        ]);
        $this->assertFalse($invalidstage['valid']);
    }

    public function test_learner_mastery_fields_are_rejected_on_curriculum_definitions(): void {
        $result = \local_flwcupkp\local\canonical_domain_model::validate_curriculum_row('kp', [
            'externalid' => 'KP-FR-A2-FUNC-031',
            'cefr' => 'A2',
            'domain' => 'FUNC',
            'masterystate' => 'mastered',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Learner state field', implode(' ', $result['errors']));
    }

    public function test_package_validation_uses_c1_semantics(): void {
        $package = [
            'cupkp_schema_version' => '1.0',
            'cefr_level' => 'A2.1',
            'frameworks' => [[
                'externalid' => 'FW-C1',
                'name' => 'C1 test',
                'cefr_range' => 'A2-B1',
            ]],
            'competencies' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Wrong type',
                'cefr' => 'A2',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-031-04',
                'title' => 'Use point',
                'cefr' => 'A2',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-031',
                'title' => 'Knowledge point',
                'domain' => 'FUNC',
                'cefr' => 'A2',
                'mastery_score' => 1,
            ]],
            'project_evidence' => [[
                'object_externalid' => 'OBJ',
                'competency_externalid' => 'KP-FR-A2-FUNC-031',
            ]],
        ];

        $validation = \local_flwcupkp\local\validator::validate_package($package);

        $this->assertFalse($validation['valid']);
        $joined = implode(' ', $validation['errors']);
        $this->assertStringContainsString('pseudo-CEFR', $joined);
        $this->assertStringContainsString('looks like kp', $joined);
        $this->assertStringContainsString('Learner state field', $joined);
    }

    public function test_curriculum_save_rejects_learner_state_on_definition(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'C1-FW',
            'name' => 'C1 Framework',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        \local_flwcupkp\local\curriculum_manager::save_entity('competency', [
            'frameworkid' => $frameworkid,
            'externalid' => 'C-FR-A2-SI-004',
            'title' => 'Can solve a problem',
            'cefr' => 'A2',
            'stage' => 'FLW-STAGE-02',
            'masterystate' => 'achieved',
        ]);
    }

    public function test_freeze_status_is_read_only(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforeaudit = $DB->count_records('flwcupkp_audit');

        $status = \local_flwcupkp\local\canonical_domain_model::freeze_status((int)$course->id);

        $this->assertSame('CanonicalCupkpDomainModelFreezeStatus', $status['type']);
        $this->assertSame('P3_C1', $status['gate']);
        $this->assertSame('FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1', $status['contract']['version']);
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
    }
}
