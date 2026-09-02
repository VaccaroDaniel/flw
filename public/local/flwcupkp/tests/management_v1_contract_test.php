<?php
// PHPUnit tests for Program 3 Gate CM4 Management V1 freeze.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * CM4 Management V1 freeze tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\management_v1_contract::class)]
class management_v1_contract_test extends \advanced_testcase {
    public function test_contract_freezes_management_surface_without_adaptive_logic(): void {
        $contract = \local_flwcupkp\local\management_v1_contract::contract();

        $this->assertSame('P3_CM4', $contract['gate']);
        $this->assertSame('FLW_CUPKP_MANAGEMENT_V1', $contract['version']);
        $this->assertContains(\local_flwcupkp\local\foundation_v1_contract::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\core_curriculum_manager::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\relationship_where_used_manager::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains(\local_flwcupkp\local\coverage_bulk_governance_manager::CONTRACT_VERSION,
            $contract['depends_on']);
        $this->assertContains('ontology_frozen', $contract['pass_criteria']);
        $this->assertContains('program2_history_contract_ready', $contract['pass_criteria']);
        $this->assertContains('management_v1_contract::consumer_snapshot', $contract['allowed_read_apis']);
        $this->assertContains('curriculum_manager::save_entity', $contract['allowed_write_surfaces']);
        $this->assertContains('adaptive_path_selection', $contract['does_not_do']);
        $this->assertContains('raw_moodle_log_scraping', $contract['does_not_do']);
        $this->assertFalse($contract['state_changes_allowed']);
        $this->assertSame('E2', $contract['consumer_contract']['next_allowed_gate']);
    }

    public function test_management_status_is_frozen_read_only_and_points_to_e2_after_e1(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $status = \local_flwcupkp\local\management_v1_contract::management_status(0, '', 0, 20);

        $this->assertSame('CupkpManagementV1Status', $status['type']);
        $this->assertSame('frozen', $status['status'], json_encode($status['findings']));
        $this->assertSame('E2', $status['next_allowed_gate']);
        $this->assertSame(10, $status['criteria_summary']['total']);
        $this->assertSame(10, $status['criteria_summary']['passed'], json_encode($status['criteria']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertSame('pass', $status['criteria']['management_crud_works']['status']);
        $this->assertSame('pass', $status['criteria']['permissions_work']['status']);
        $this->assertSame('pass', $status['criteria']['coverage_validation_works']['status']);
        $this->assertTrue($status['files']['present']['management.php']);
        $this->assertTrue($status['permissions']['valid']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_consumer_snapshot_exposes_stable_read_model(): void {
        global $DB;

        $this->resetAfterTest(true);
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforestate = $DB->count_records('flwcupkp_state');
        $beforerecommend = $DB->count_records('flwcupkp_recommend');

        $snapshot = \local_flwcupkp\local\management_v1_contract::consumer_snapshot(0, '', 0, 20);

        $this->assertSame('CupkpManagementV1ConsumerSnapshot', $snapshot['type']);
        $this->assertSame(\local_flwcupkp\local\management_v1_contract::CONTRACT_VERSION, $snapshot['contract']);
        $this->assertSame('frozen', $snapshot['management_status'], json_encode($snapshot['findings']));
        $this->assertSame('E2', $snapshot['handoff']['next_allowed_gate']);
        $this->assertSame(\local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $snapshot['normal_source_history_input']);
        $this->assertContains('management_v1_contract::consumer_snapshot', $snapshot['allowed_read_apis']);
        $this->assertContains('adaptive_path_selection', $snapshot['forbidden_until_e1_or_later']);
        $this->assertArrayHasKey('categories', $snapshot['coverage']);
        $this->assertCount(6, $snapshot['coverage']['categories']);
        $this->assertArrayHasKey('summary', $snapshot['governance']);
        $this->assertTrue($snapshot['read_only_for_consumers']);
        $this->assertFalse($snapshot['state_changes_allowed']);

        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));
        $this->assertSame($beforerecommend, $DB->count_records('flwcupkp_recommend'));
    }

    public function test_management_status_blocks_when_frozen_dependencies_are_broken(): void {
        global $DB;

        $this->resetAfterTest(true);
        $now = time();
        $DB->insert_record('flwcupkp_framework', (object)[
            'externalid' => 'CM4-BAD-FW',
            'name' => 'CM4 Bad Framework',
            'version' => '',
            'status' => 'published',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $status = \local_flwcupkp\local\management_v1_contract::management_status(0, '', 0, 20);

        $this->assertSame('blocked', $status['status']);
        $this->assertGreaterThan(0, $status['criteria_summary']['failed']);
        $codes = array_column($status['findings'], 'code');
        $this->assertNotEmpty(array_filter($codes, static function(string $code): bool {
            return str_ends_with($code, '_failed');
        }));
    }
}
