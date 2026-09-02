<?php
// PHPUnit tests for the Program 3 History V1 consumer contract.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Program 3 History V1 consumer contract tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\history_v1_consumer_contract::class)]
class history_v1_consumer_contract_test extends \advanced_testcase {
    public function test_planned_paths_use_history_adapter_and_keep_raw_logs_out_of_normal_flow(): void {
        $paths = \local_flwcupkp\local\history_v1_consumer_contract::planned_consumption_paths();
        $boundary = \local_flwcupkp\local\history_v1_consumer_contract::normal_source_boundary();

        $this->assertArrayHasKey('attempts', $paths);
        $this->assertStringContainsString('local_flwhistory', $paths['attempts']['source']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $boundary['normal_rule']
        );
        $this->assertSame('diagnostic_only', $boundary['raw_moodle_log_access']);
        $this->assertFalse($boundary['state_changes_allowed_in_gate']);
    }

    public function test_contract_status_reports_required_history_v1_contract(): void {
        $status = \local_flwcupkp\local\history_v1_consumer_contract::contract_status();

        $this->assertSame('Program3HistoryV1ConsumptionStatus', $status['type']);
        $this->assertSame('P3_A0', $status['gate']);
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
            $status['requiredcontract']
        );
        $this->assertSame(
            \local_flwcupkp\local\history_v1_consumer_contract::CONSUMPTION_RULE,
            $status['normal_source_rule']
        );
        $this->assertContains('raw_moodle_log_scraping', $status['outofscope']);

        if ($status['historypluginavailable']) {
            $this->assertTrue($status['contractavailable']);
            $this->assertNotSame('blocked', $status['status']);
            $this->assertSame(
                \local_flwcupkp\local\history_v1_consumer_contract::REQUIRED_CONTRACT,
                $status['contract']['version']
            );
            $this->assertContains('attempts', $status['contract']['facttypes']);
            $this->assertContains('content_identities', $status['contract']['facttypes']);
            $this->assertNotEmpty($status['contract']['normpolicyversion']);
        } else {
            $this->assertSame('blocked', $status['status']);
        }
    }

    public function test_course_sampling_is_bounded_and_does_not_write_cupkp_rows(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $beforeevidence = $DB->count_records('flwcupkp_evidence');
        $beforeaudit = $DB->count_records('flwcupkp_audit');
        $beforestate = $DB->count_records('flwcupkp_state');

        $status = \local_flwcupkp\local\history_v1_consumer_contract::contract_status((int)$course->id, 999);

        $this->assertSame($beforeevidence, $DB->count_records('flwcupkp_evidence'));
        $this->assertSame($beforeaudit, $DB->count_records('flwcupkp_audit'));
        $this->assertSame($beforestate, $DB->count_records('flwcupkp_state'));

        if (!$status['historypluginavailable']) {
            $this->assertSame([], $status['sample']);
            return;
        }

        $this->assertArrayHasKey('attempts', $status['sample']);
        foreach ($status['sample'] as $sample) {
            if (empty($sample['pagination'])) {
                continue;
            }
            $this->assertLessThanOrEqual(500, $sample['pagination']['limit']);
        }
    }
}
