<?php
// PHPUnit tests for Program 3 Gate CM1 Core Curriculum Manager.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Core Curriculum Manager tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\core_curriculum_manager::class)]
class core_curriculum_manager_test extends \advanced_testcase {
    public function test_contract_depends_on_foundation_and_excludes_adaptive_logic(): void {
        $contract = \local_flwcupkp\local\core_curriculum_manager::contract();

        $this->assertSame('P3_CM1', $contract['gate']);
        $this->assertSame('FLW_CUPKP_CORE_CURRICULUM_MANAGER_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\foundation_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains('language', $contract['navigation']);
        $this->assertContains('publish', $contract['workflow']);
        $this->assertContains('evidence_coverage', $contract['selected_entity_sections']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertFalse($contract['state_changes_allowed']);
    }

    public function test_status_is_ready_when_foundation_and_cm1_files_are_present(): void {
        $this->resetAfterTest(true);

        $status = \local_flwcupkp\local\core_curriculum_manager::status(0, '', 0, 20);

        $this->assertSame('CupkpCoreCurriculumManagerStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('frozen', $status['foundation']['status']);
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertTrue($status['files']['curriculum.php']);
        $this->assertTrue($status['files']['entity.php']);
        $this->assertFalse($status['state_changes_allowed']);
    }

    public function test_navigation_model_filters_by_frozen_curriculum_facets(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture();

        $navigation = \local_flwcupkp\local\core_curriculum_manager::navigation_model(
            $fixture['frameworkid'],
            'UCM1',
            [
                'entitytype' => 'kp',
                'language' => 'en',
                'cefr' => 'B1',
                'domain' => 'FUNC',
                'q' => 'CM1',
            ],
            20
        );

        $this->assertSame('CupkpCoreCurriculumNavigationModel', $navigation['type']);
        $this->assertSame('kp', $navigation['selected_type']);
        $this->assertArrayHasKey('en', $navigation['facets']['language']);
        $this->assertArrayHasKey('B1', $navigation['facets']['cefr']);
        $this->assertArrayHasKey('FUNC', $navigation['facets']['domain']);
        $this->assertArrayHasKey($fixture['kpid'], $navigation['rows']);
        $this->assertSame(1, $navigation['counts']['kp']);
        $this->assertSame(0, $navigation['counts']['up']);
        $this->assertSame(0, $navigation['counts']['competency']);
        $this->assertFalse($navigation['contract'] === '');
    }

    public function test_entity_detail_reports_selected_entity_sections(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_fixture();

        $detail = \local_flwcupkp\local\core_curriculum_manager::entity_detail(
            'kp',
            $fixture['kpid'],
            $fixture['courseid'],
            'UCM1',
            20
        );

        $this->assertSame('CupkpCoreCurriculumEntityDetail', $detail['type']);
        $this->assertSame('kp', $detail['entity_type']);
        $this->assertSame('KP-EN-B1-FUNC-CM1', $detail['identity']['stable_code']);
        $this->assertSame('1.0', $detail['identity']['version']);
        $this->assertNotEmpty($detail['definition']);
        $this->assertNotEmpty($detail['relationships']['direct_edges']);
        $this->assertNotEmpty($detail['relationships']['where_used']['edges']);
        $this->assertCount(1, $detail['content_usage']);
        $this->assertSame(1, $detail['evidence_coverage']['evidence_rows']);
        $this->assertSame(1, $detail['evidence_coverage']['learner_state_rows']);
        $this->assertArrayHasKey('canonical_domain_model', $detail['validation']['checks']);
        $this->assertArrayHasKey('review', $detail['workflow']);
    }

    public function test_transition_entity_status_is_governed_and_audited(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture();

        $this->expectException(\invalid_parameter_exception::class);
        \local_flwcupkp\local\curriculum_manager::transition_entity_status(
            'competency',
            $fixture['compid'],
            'published'
        );
    }

    public function test_transition_entity_status_allows_valid_step_and_writes_audit(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_fixture();

        $result = \local_flwcupkp\local\curriculum_manager::transition_entity_status(
            'competency',
            $fixture['compid'],
            'review'
        );

        $this->assertSame('draft', $result['from']);
        $this->assertSame('review', $result['to']);
        $this->assertSame('review',
            $DB->get_field('flwcupkp_comp', 'status', ['id' => $fixture['compid']], MUST_EXIST));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'curriculum_entity_status_transitioned',
            'targettype' => 'competency',
            'targetid' => $fixture['compid'],
        ]));
    }

    /**
     * Create a compact C-UP-KP graph with content and evidence.
     *
     * @return array
     */
    private function create_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        $frameworkid = (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'CM1-FW',
            'name' => 'CM1 Framework',
            'courseid' => $course->id,
            'coursecode' => 'CM1',
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $compid = (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'C-EN-B1-SI-CM1',
            'title' => 'CM1 competency',
            'cando' => 'Can complete the CM1 task.',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'domain' => 'speaking',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $upid = (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'UP-EN-B1-SI-CM1',
            'title' => 'CM1 use point',
            'actionstatement' => 'Use the target expression in a spoken decision.',
            'cefr' => 'B1',
            'languagemode' => 'speaking',
            'interactiontype' => 'pair',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $kpid = (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'KP-EN-B1-FUNC-CM1',
            'title' => 'CM1 knowledge point',
            'description' => 'Functional phrase for giving a reason.',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'FUNC',
            'status' => 'draft',
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $objectid = (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => 'CM1-OBJ',
            'courseid' => $course->id,
            'unitcode' => 'UCM1',
            'lesson' => '1',
            'objecttype' => 'quiz',
            'title' => 'CM1 quiz object',
            'cmid' => 12345,
            'purpose' => 'practice',
            'evidencestrength' => 'guided_performance',
            'role' => 'practice',
        ]);

        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $DB->insert_record('flwcupkp_up_kp', (object)[
            'upid' => $upid,
            'kpid' => $kpid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        $DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'role' => 'practice',
            'evidencestrength' => 'guided_performance',
        ]);
        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'unitcode' => 'UCM1',
            'objectid' => $objectid,
            'sourceattempt' => 'cm1-attempt-1',
            'evidencetype' => 'quiz_attempt',
            'targettype' => 'kp',
            'targetid' => $kpid,
            'rawscore' => 0.85,
            'normalizedscore' => 0.85,
            'assessortype' => 'system',
            'confidence' => 0.9,
            'evidencestrength' => 'guided_performance',
            'provenance' => 'test',
            'timecreated' => $now,
        ]);
        $DB->insert_record('flwcupkp_state', (object)[
            'userid' => $user->id,
            'targettype' => 'kp',
            'targetid' => $kpid,
            'masteryscore' => 0.85,
            'masterystate' => 'mastered',
            'confidence' => 0.9,
            'evidencecount' => 1,
            'lastevidence' => $now,
            'ruleversion' => 'test-v1',
            'timemodified' => $now,
        ]);

        return [
            'courseid' => (int)$course->id,
            'userid' => (int)$user->id,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'kpid' => $kpid,
            'objectid' => $objectid,
        ];
    }
}
