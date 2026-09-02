<?php
// PHPUnit tests for Program 3 Gate CM3 coverage and bulk governance.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * CM3 coverage and bulk governance tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\coverage_bulk_governance_manager::class)]
class coverage_bulk_governance_manager_test extends \advanced_testcase {
    public function test_contract_preserves_cm1_cm2_foundation_and_excludes_adaptive_logic(): void {
        $contract = \local_flwcupkp\local\coverage_bulk_governance_manager::contract();

        $this->assertSame('P3_CM3', $contract['gate']);
        $this->assertSame('FLW_CUPKP_COVERAGE_BULK_GOVERNANCE_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\foundation_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\core_curriculum_manager::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\relationship_where_used_manager::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains('competency_coverage', $contract['coverage_areas']);
        $this->assertContains('interaction_target_with_recognition_only_evidence', $contract['detects']);
        $this->assertContains('dry_run_validation', $contract['bulk_management']);
        $this->assertContains('controlled_rollback_request', $contract['bulk_management']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertFalse($contract['state_changes_allowed']);
    }

    public function test_status_is_ready_and_points_to_e2_after_e1(): void {
        $this->resetAfterTest(true);

        $status = \local_flwcupkp\local\coverage_bulk_governance_manager::status(0, '', 0, 20);

        $this->assertSame('CupkpCoverageBulkGovernanceStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame('frozen', $status['foundation']['status']);
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertTrue($status['files']['governance.php']);
        $this->assertFalse($status['state_changes_allowed']);
    }

    public function test_coverage_matrix_detects_scale_governance_findings(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_coverage_fixture();

        $matrix = \local_flwcupkp\local\coverage_bulk_governance_manager::coverage_matrix(
            $fixture['frameworkid'],
            $fixture['courseid'],
            'UCM3',
            200
        );

        $this->assertSame('CupkpCm3CoverageMatrix', $matrix['type']);
        $this->assertSame('E2', $matrix['next_allowed_gate']);
        $this->assertCount(6, $matrix['categories']);
        $this->assertArrayHasKey('competency_coverage', $matrix['categories']);
        $this->assertArrayHasKey('kp_teaching_coverage', $matrix['categories']);
        $this->assertArrayHasKey('up_practice_coverage', $matrix['categories']);
        $this->assertArrayHasKey('up_assessment_coverage', $matrix['categories']);
        $this->assertArrayHasKey('evidence_quality_coverage', $matrix['categories']);
        $this->assertArrayHasKey('production_interaction_coverage', $matrix['categories']);

        $codes = array_column($matrix['findings'], 'code');
        $this->assertContains('orphans', $codes);
        $this->assertContains('taught_not_assessed', $codes);
        $this->assertContains('assessed_not_taught', $codes);
        $this->assertContains('interaction_recognition_only_evidence', $codes);
        $this->assertContains('missing_prerequisite', $codes);
        $this->assertContains('deprecated_references', $codes);
        $this->assertContains('evidence_ceilings', $codes);
        $this->assertContains('coverage_imbalance', $codes);
        $this->assertFalse($matrix['state_changes_allowed']);
    }

    public function test_bulk_csv_preview_apply_duplicate_and_rollback_are_controlled(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $fixture = $this->create_bulk_import_fixture();
        $csv = "item_id,object_externalid,kp_externalid,evidence_strength,notes\n" .
            "CM3-Q1,CM3-BULK-OBJECT,CM3-BULK-KP,guided_performance,Bulk CM3 route\n";
        $beforemaps = $DB->count_records('flwcupkp_object_map');

        $preview = \local_flwcupkp\local\coverage_bulk_governance_manager::preview_bulk_import(
            $csv,
            'csv',
            'quiz_kp_mappings',
            'cm3.csv'
        );

        $this->assertTrue($preview['valid'], json_encode($preview['errors']));
        $this->assertFalse($preview['would_write']);
        $this->assertFalse($preview['duplicate']);
        $this->assertSame(1, $preview['counts']['rows']);
        $this->assertSame($beforemaps, $DB->count_records('flwcupkp_object_map'));

        $apply = \local_flwcupkp\local\coverage_bulk_governance_manager::apply_bulk_import(
            $csv,
            'csv',
            'quiz_kp_mappings',
            'cm3.csv'
        );

        $this->assertTrue($apply['applied']);
        $this->assertSame('imported', $apply['result']['status']);
        $this->assertTrue($DB->record_exists('flwcupkp_object_map', [
            'objectid' => $fixture['objectid'],
            'targettype' => 'kp',
            'targetid' => $fixture['kpid'],
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'cm3_bulk_import_applied',
            'targettype' => 'import',
            'targetid' => $apply['result']['importid'],
        ]));

        $duplicate = \local_flwcupkp\local\coverage_bulk_governance_manager::preview_bulk_import(
            $csv,
            'csv',
            'quiz_kp_mappings',
            'cm3.csv'
        );
        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame($apply['result']['importid'], $duplicate['existing_importid']);

        $rollbackpreview = \local_flwcupkp\local\coverage_bulk_governance_manager::rollback_preview(
            $apply['result']['importid']
        );
        $this->assertFalse($rollbackpreview['would_write']);
        $this->assertFalse($rollbackpreview['physical_rollback_available']);

        $rollback = \local_flwcupkp\local\coverage_bulk_governance_manager::request_rollback(
            $apply['result']['importid'],
            'PHPUnit rollback request'
        );
        $this->assertTrue($rollback['requested']);
        $this->assertSame('rollback_requested', $DB->get_field('flwcupkp_import', 'rollbackstatus', [
            'id' => $apply['result']['importid'],
        ]));
        $this->assertTrue($DB->record_exists('flwcupkp_audit', [
            'action' => 'cm3_import_rollback_requested',
            'targettype' => 'import',
            'targetid' => $apply['result']['importid'],
        ]));
    }

    public function test_governance_dashboard_and_export_are_read_only_surfaces(): void {
        $this->resetAfterTest(true);
        $fixture = $this->create_coverage_fixture();

        $dashboard = \local_flwcupkp\local\coverage_bulk_governance_manager::governance_dashboard(
            $fixture['frameworkid'],
            $fixture['courseid'],
            'UCM3',
            100
        );
        $this->assertSame('CupkpCm3GovernanceDashboard', $dashboard['type']);
        $this->assertSame('E2', $dashboard['next_allowed_gate']);
        $this->assertArrayHasKey('lifecycle_counts', $dashboard);
        $this->assertArrayHasKey('replacement_edges', $dashboard);
        $this->assertFalse($dashboard['state_changes_allowed']);

        $export = \local_flwcupkp\local\coverage_bulk_governance_manager::export_bulk_package($fixture['frameworkid']);
        $this->assertSame('CupkpCm3BulkExportPackage', $export['type']);
        $this->assertSame(64, strlen($export['checksum']));
        $this->assertStringContainsString('CM3-FW', $export['json']);
        $this->assertFalse($export['state_changes_allowed']);
    }

    /**
     * Create a coverage fixture with intentional governance gaps.
     *
     * @return array
     */
    private function create_coverage_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $frameworkid = $this->create_framework('CM3-FW', 'CM3 Framework', $course->id);
        $compid = $this->create_comp($frameworkid, 'CM3-COMP', 'draft');
        $upid = $this->create_up($frameworkid, 'CM3-UP-INTERACTION', 'draft');
        $orphanupid = $this->create_up($frameworkid, 'CM3-UP-ORPHAN', 'draft');
        $teachkpid = $this->create_kp($frameworkid, 'CM3-KP-TEACH', 'draft');
        $assesskpid = $this->create_kp($frameworkid, 'CM3-KP-ASSESS', 'draft');
        $deprecatedkpid = $this->create_kp($frameworkid, 'CM3-KP-DEPRECATED', 'deprecated');
        $this->create_kp($frameworkid, 'CM3-KP-ORPHAN', 'draft');

        $DB->insert_record('flwcupkp_comp_up', (object)[
            'competencyid' => $compid,
            'upid' => $upid,
            'role' => 'required',
            'weight' => 1,
            'sortorder' => 1,
        ]);
        foreach ([$teachkpid, $assesskpid, $deprecatedkpid] as $idx => $kpid) {
            $DB->insert_record('flwcupkp_up_kp', (object)[
                'upid' => $upid,
                'kpid' => $kpid,
                'role' => 'required',
                'weight' => 1,
                'sortorder' => $idx + 1,
            ]);
        }

        $teachobject = $this->create_object($frameworkid, $course->id, 'CM3-TEACH-OBJECT', 'lesson', 'lesson', 'guided');
        $assessobject = $this->create_object($frameworkid, $course->id, 'CM3-ASSESS-OBJECT', 'assessment', 'quiz',
            'independent_performance');
        $recognitionobject = $this->create_object($frameworkid, $course->id, 'CM3-RECOGNITION-OBJECT', 'assessment',
            'quiz', 'recognition');
        $deprecatedobject = $this->create_object($frameworkid, $course->id, 'CM3-DEPRECATED-OBJECT', 'assessment',
            'checkpoint', 'guided_performance');
        $this->create_object($frameworkid, $course->id, 'CM3-ORPHAN-OBJECT', 'practice', 'lesson', 'guided');

        $this->create_object_map($teachobject, 'kp', $teachkpid, 'lesson', 'guided');
        $this->create_object_map($assessobject, 'kp', $assesskpid, 'assessment', 'independent_performance');
        $this->create_object_map($recognitionobject, 'up', $upid, 'assessment', 'recognition');
        $this->create_object_map($deprecatedobject, 'kp', $deprecatedkpid, 'assessment', 'guided_performance');

        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'unitcode' => 'UCM3',
            'objectid' => $recognitionobject,
            'sourceattempt' => 'cm3-recognition',
            'evidencetype' => 'quiz_attempt',
            'targettype' => 'up',
            'targetid' => $upid,
            'rawscore' => 0.7,
            'normalizedscore' => 0.7,
            'rubricjson' => json_encode(['evidence_ceiling' => 'recognition']),
            'assessortype' => 'system',
            'confidence' => 0.8,
            'evidencestrength' => 'recognition',
            'provenance' => 'phpunit',
            'timecreated' => $now,
        ]);
        $DB->insert_record('flwcupkp_evidence', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'unitcode' => 'UCM3',
            'objectid' => $assessobject,
            'sourceattempt' => 'cm3-quality',
            'evidencetype' => 'quiz_attempt',
            'targettype' => 'kp',
            'targetid' => $assesskpid,
            'rawscore' => 0.9,
            'normalizedscore' => 0.9,
            'rubricjson' => json_encode(['quality' => ['validity' => 1], 'evidence_policy_version' => 'test']),
            'assessortype' => 'system',
            'confidence' => 0.95,
            'evidencestrength' => 'independent_performance',
            'provenance' => 'phpunit',
            'timecreated' => $now,
        ]);

        return [
            'courseid' => (int)$course->id,
            'frameworkid' => $frameworkid,
            'compid' => $compid,
            'upid' => $upid,
            'orphanupid' => $orphanupid,
            'teachkpid' => $teachkpid,
            'assesskpid' => $assesskpid,
            'deprecatedkpid' => $deprecatedkpid,
        ];
    }

    /**
     * Create a minimal CSV import fixture.
     *
     * @return array
     */
    private function create_bulk_import_fixture(): array {
        $course = $this->getDataGenerator()->create_course();
        $frameworkid = $this->create_framework('CM3-BULK-FW', 'CM3 Bulk Framework', $course->id);
        $objectid = $this->create_object($frameworkid, $course->id, 'CM3-BULK-OBJECT', 'assessment', 'quiz',
            'guided_performance');
        $kpid = $this->create_kp($frameworkid, 'CM3-BULK-KP', 'draft');

        return [
            'courseid' => (int)$course->id,
            'frameworkid' => $frameworkid,
            'objectid' => $objectid,
            'kpid' => $kpid,
        ];
    }

    /**
     * Create a framework row.
     *
     * @param string $externalid
     * @param string $name
     * @param int $courseid
     * @return int
     */
    private function create_framework(string $externalid, string $name, int $courseid): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => $externalid,
            'name' => $name,
            'courseid' => $courseid,
            'coursecode' => $externalid,
            'language' => 'en',
            'cefrrange' => 'B1',
            'version' => '1.0',
            'status' => 'draft',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Create a competency row.
     *
     * @param int $frameworkid
     * @param string $externalid
     * @param string $status
     * @return int
     */
    private function create_comp(int $frameworkid, string $externalid, string $status): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_comp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'cando' => 'Can complete CM3 work.',
            'cefr' => 'B1',
            'stage' => 'FLW-STAGE-03',
            'domain' => 'speaking',
            'status' => $status,
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Create a UP row.
     *
     * @param int $frameworkid
     * @param string $externalid
     * @param string $status
     * @return int
     */
    private function create_up(int $frameworkid, string $externalid, string $status): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_up', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'actionstatement' => 'Use the CM3 language in a pair exchange.',
            'cefr' => 'B1',
            'languagemode' => 'speaking',
            'interactiontype' => 'pair',
            'status' => $status,
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Create a KP row.
     *
     * @param int $frameworkid
     * @param string $externalid
     * @param string $status
     * @return int
     */
    private function create_kp(int $frameworkid, string $externalid, string $status): int {
        global $DB;

        $now = time();
        return (int)$DB->insert_record('flwcupkp_kp', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'title' => $externalid,
            'description' => 'CM3 knowledge point.',
            'language' => 'en',
            'cefr' => 'B1',
            'domain' => 'FUNC',
            'status' => $status,
            'version' => '1.0',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Create a learning object row.
     *
     * @param int $frameworkid
     * @param int $courseid
     * @param string $externalid
     * @param string $role
     * @param string $objecttype
     * @param string $strength
     * @return int
     */
    private function create_object(int $frameworkid, int $courseid, string $externalid, string $role,
            string $objecttype, string $strength): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_object', (object)[
            'frameworkid' => $frameworkid,
            'externalid' => $externalid,
            'courseid' => $courseid,
            'unitcode' => 'UCM3',
            'lesson' => '1',
            'objecttype' => $objecttype,
            'title' => $externalid,
            'cmid' => 300,
            'sourceid' => $externalid,
            'purpose' => $role,
            'evidencestrength' => $strength,
            'difficulty' => 0.5,
            'role' => $role,
            'metadatajson' => json_encode([
                'program1_identity' => [
                    'activityid' => $externalid,
                    'questionid' => $externalid . '-Q',
                ],
            ]),
        ]);
    }

    /**
     * Create an object-map row.
     *
     * @param int $objectid
     * @param string $targettype
     * @param int $targetid
     * @param string $role
     * @param string $strength
     * @return int
     */
    private function create_object_map(int $objectid, string $targettype, int $targetid, string $role,
            string $strength): int {
        global $DB;

        return (int)$DB->insert_record('flwcupkp_object_map', (object)[
            'objectid' => $objectid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'role' => $role,
            'evidencestrength' => $strength,
        ]);
    }
}
