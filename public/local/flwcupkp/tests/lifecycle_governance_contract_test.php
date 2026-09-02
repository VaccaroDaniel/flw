<?php
// PHPUnit tests for Program 3 Gate C4 lifecycle governance.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Lifecycle governance tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\lifecycle_governance_contract::class)]
class lifecycle_governance_contract_test extends \advanced_testcase {
    public function test_contract_freezes_c4_lifecycle_and_history_boundary(): void {
        $contract = \local_flwcupkp\local\lifecycle_governance_contract::contract();

        $this->assertSame('P3_C4', $contract['gate']);
        $this->assertSame('FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1', $contract['version']);
        $this->assertContains('published_semantic_overwrite', $contract['validation']['detects']);
        $this->assertContains('deprecated', $contract['deprecation']['source_state']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $contract['normal_source_history_input']
        );
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
    }

    public function test_package_governance_requires_published_evidence_routes(): void {
        $package = self::published_package();
        array_pop($package['activity_mappings']);

        $result = \local_flwcupkp\local\lifecycle_governance_contract::validate_package_governance($package);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('evidence route', implode(' ', $result['errors']));
    }

    public function test_validator_returns_governance_result(): void {
        $validation = \local_flwcupkp\local\validator::validate_package(self::published_package());

        $this->assertTrue($validation['valid'], implode(' ', $validation['errors']));
        $this->assertArrayHasKey('governance', $validation);
        $this->assertSame(
            \local_flwcupkp\local\lifecycle_governance_contract::CONTRACT_VERSION,
            $validation['governance']['contract']
        );
    }

    public function test_curriculum_save_rejects_published_semantic_overwrite(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('FW-C4-IMMUTABLE');
        $compid = self::create_comp($frameworkid, 'C-FR-A2-SI-404', 'published');

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('Published competency semantic changes');
        \local_flwcupkp\local\curriculum_manager::save_entity('competency', [
            'id' => $compid,
            'frameworkid' => $frameworkid,
            'externalid' => 'C-FR-A2-SI-404',
            'title' => 'Read a notice and infer an unstated purpose',
            'cando' => 'Can identify required actions in a short workplace notice.',
            'status' => 'published',
            'version' => '1.0',
        ]);
    }

    public function test_curriculum_save_allows_published_deprecation_without_semantic_change(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('FW-C4-DEPRECATE');
        $compid = self::create_comp($frameworkid, 'C-FR-A2-SI-405', 'published');
        $existing = $DB->get_record('flwcupkp_comp', ['id' => $compid], '*', MUST_EXIST);

        \local_flwcupkp\local\curriculum_manager::save_entity('competency', [
            'id' => $compid,
            'frameworkid' => $frameworkid,
            'externalid' => $existing->externalid,
            'title' => $existing->title,
            'cando' => $existing->cando,
            'status' => 'deprecated',
            'version' => $existing->version,
        ]);

        $this->assertSame('deprecated', $DB->get_field('flwcupkp_comp', 'status', ['id' => $compid], MUST_EXIST));
    }

    public function test_replaced_by_rejects_non_deprecated_source(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('FW-C4-REPLACE');
        $sourceid = self::create_kp($frameworkid, 'KP-FR-A2-FUNC-401', 'published');
        $targetid = self::create_kp($frameworkid, 'KP-FR-A2-FUNC-402', 'approved');

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('REPLACED_BY source must be DEPRECATED');
        \local_flwcupkp\local\curriculum_manager::save_mapping('kp_prereq', [
            'kpid' => $sourceid,
            'prereqkpid' => $targetid,
            'relationshiptype' => 'replaced_by',
            'strength' => 1,
            'requirement' => 'recommended',
        ]);
    }

    public function test_replaced_by_accepts_deprecated_source_and_approved_successor(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $frameworkid = self::create_framework('FW-C4-REPLACE-OK');
        $sourceid = self::create_kp($frameworkid, 'KP-FR-A2-FUNC-411', 'deprecated');
        $targetid = self::create_kp($frameworkid, 'KP-FR-A2-FUNC-412', 'approved');

        $id = \local_flwcupkp\local\curriculum_manager::save_mapping('kp_prereq', [
            'kpid' => $sourceid,
            'prereqkpid' => $targetid,
            'relationshiptype' => 'replaced_by',
            'strength' => 1,
            'requirement' => 'recommended',
        ]);
        $this->assertGreaterThan(0, $id);
    }

    public function test_object_mapping_with_learner_evidence_cannot_be_deleted(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $frameworkid = self::create_framework('FW-C4-DELETE');
        $kpid = self::create_kp($frameworkid, 'KP-FR-A2-FUNC-403', 'published');
        $objectid = self::create_object($frameworkid, 'OBJ-C4-DELETE', (int)$course->id);
        $mapid = $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'assessment',
            'evidencestrength' => 'strong',
        ]);
        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'unitcode' => 'U-C4',
            'objectid' => $objectid,
            'sourceattempt' => 'ATT-C4',
            'evidencetype' => 'quiz_attempt',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 1,
            'normalizedscore' => 1,
            'rubricjson' => '{}',
            'assessortype' => 'system',
            'confidence' => 1,
            'evidencestrength' => 'strong',
            'provenance' => 'phpunit',
            'sourceref' => 'C4',
            'overrideflag' => 0,
            'timecreated' => time(),
            'usermodified' => get_admin()->id,
        ]);

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessage('cannot be physically deleted');
        \local_flwcupkp\local\curriculum_manager::delete_mapping('object_map', $mapid);
    }

    public function test_governance_status_is_read_only_and_points_to_c5(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');

        $status = \local_flwcupkp\local\lifecycle_governance_contract::governance_status(0, 0, '', 20);

        $this->assertSame('P3_C4', $status['gate']);
        $this->assertSame('C5', $status['next_allowed_gate']);
        $this->assertSame('frozen', $status['status']);
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
    }

    private static function published_package(): array {
        return [
            'cupkp_schema_version' => '1.0',
            'frameworks' => [[
                'externalid' => 'FW-C4',
                'name' => 'C4 Framework',
                'cefr_range' => 'B1',
                'status' => 'published',
                'version' => 'reference-1.0',
            ]],
            'competencies' => [[
                'externalid' => 'C-FR-A2-SI-404',
                'title' => 'Read a workplace notice and identify required actions',
                'can_do' => 'Can identify required actions in a short workplace notice.',
                'cefr' => 'B1',
                'framework_externalid' => 'FW-C4',
                'status' => 'published',
                'version' => '1.0',
            ]],
            'use_points' => [[
                'externalid' => 'UP-FR-A2-SI-404-01',
                'title' => 'Use the notice to decide the next action',
                'action_statement' => 'Use the given notice to select the required follow-up action.',
                'observable_action' => 'Selects the required action and supporting detail.',
                'cefr' => 'B1',
                'framework_externalid' => 'FW-C4',
                'status' => 'published',
                'version' => '1.0',
            ]],
            'knowledge_points' => [[
                'externalid' => 'KP-FR-A2-FUNC-404',
                'title' => 'Workplace action vocabulary',
                'description' => 'Vocabulary used to identify deadlines, requests, and required actions.',
                'domain' => 'LEX',
                'cefr' => 'B1',
                'framework_externalid' => 'FW-C4',
                'status' => 'published',
                'version' => '1.0',
            ]],
            'competency_up_mappings' => [[
                'competency_externalid' => 'C-FR-A2-SI-404',
                'up_externalid' => 'UP-FR-A2-SI-404-01',
                'role' => 'required',
                'weight' => 1,
            ]],
            'up_kp_mappings' => [[
                'up_externalid' => 'UP-FR-A2-SI-404-01',
                'kp_externalid' => 'KP-FR-A2-FUNC-404',
                'role' => 'required',
                'weight' => 1,
            ]],
            'learning_objects' => [[
                'externalid' => 'OBJ-C4-ASSESS',
                'title' => 'C4 assessment object',
                'unit_code' => 'U-C4',
                'object_type' => 'quiz',
                'purpose' => 'assessment',
                'role' => 'assessment',
            ]],
            'activity_mappings' => [
                [
                    'object_externalid' => 'OBJ-C4-ASSESS',
                    'target_type' => 'competency',
                    'target_externalid' => 'C-FR-A2-SI-404',
                    'role' => 'assessment',
                    'evidence_strength' => 'strong',
                ],
                [
                    'object_externalid' => 'OBJ-C4-ASSESS',
                    'target_type' => 'up',
                    'target_externalid' => 'UP-FR-A2-SI-404-01',
                    'role' => 'assessment',
                    'evidence_strength' => 'strong',
                ],
                [
                    'object_externalid' => 'OBJ-C4-ASSESS',
                    'target_type' => 'kp',
                    'target_externalid' => 'KP-FR-A2-FUNC-404',
                    'role' => 'assessment',
                    'evidence_strength' => 'strong',
                ],
            ],
            'project_evidence' => [[
                'object_externalid' => 'OBJ-C4-ASSESS',
                'competency_externalid' => 'C-FR-A2-SI-404',
            ]],
        ];
    }

    private static function create_framework(string $externalid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => $externalid,
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_comp(int $frameworkid, string $externalid, string $status): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => 'Read a workplace notice and identify required actions',
            'cando' => 'Can identify required actions in a short workplace notice.',
            'status' => $status,
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_kp(int $frameworkid, string $externalid, string $status): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => 'Workplace action vocabulary',
            'description' => 'Vocabulary used to identify deadlines and required actions.',
            'domain' => 'LEX',
            'status' => $status,
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    private static function create_object(int $frameworkid, string $externalid, int $courseid): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'courseid' => $courseid,
            'unitcode' => 'U-C4',
            'objecttype' => 'quiz',
            'title' => $externalid,
            'purpose' => 'assessment',
            'role' => 'assessment',
            'metadatajson' => '{}',
        ]);
    }
}
