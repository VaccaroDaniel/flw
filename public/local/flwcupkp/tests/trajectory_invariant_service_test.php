<?php
// PHPUnit tests for Program 3 Gate A5B trajectory simulation and invariants.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * A5B deterministic trajectory and global invariant tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\trajectory_invariant_service::class)]
class trajectory_invariant_service_test extends \advanced_testcase {
    public function test_contract_and_status_freeze_read_only_a5b_boundary(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $before = $this->mutation_counts();

        $contract = \local_flwcupkp\local\trajectory_invariant_service::contract();
        $status = \local_flwcupkp\local\trajectory_invariant_service::status((int)$course->id, 'UA5B', 0);

        $this->assertSame('P3_A5B', $contract['gate']);
        $this->assertSame('FLW_CUPKP_TRAJECTORY_SIMULATION_INVARIANTS_V1', $contract['version']);
        $this->assertSame('cupkp-trajectory-invariants-v1', $contract['simulation_policy_version']);
        $this->assertSame(\local_flwcupkp\local\trajectory_invariant_service::SCENARIOS,
            $contract['scenarios']);
        $this->assertSame(\local_flwcupkp\local\trajectory_invariant_service::DETECTORS,
            $contract['detectors']);
        $this->assertTrue($contract['read_only']);
        $this->assertSame([], $contract['write_boundary']);
        $this->assertSame('A5C', $contract['next_allowed_gate']);

        $this->assertSame('CupkpTrajectorySimulationInvariantStatus', $status['type']);
        $this->assertSame('ready', $status['status'], json_encode($status['findings']));
        $this->assertSame(0, $status['criteria_summary']['failed'], json_encode($status['criteria']));
        $this->assertTrue($status['detector_self_test']['pass']);
        $this->assertSame(9, $status['detector_self_test']['passed']);
        $this->assertTrue($status['determinism_smoke']['pass']);
        $this->assertTrue($status['read_only']);
        $this->assertFalse($status['state_changes_allowed']);
        $this->assertSame([], $status['write_boundary']);
        $this->assertSame('A5C', $status['next_allowed_gate']);
        $this->assertTrue($status['files']['present']['trajectory_simulation.php']);
        $this->assertTrue($status['files']['present']['cli/trajectory_simulation.php']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_all_required_scenarios_are_deterministic_and_invariant_clean(): void {
        $this->resetAfterTest(true);
        $before = $this->mutation_counts();

        foreach (\local_flwcupkp\local\trajectory_invariant_service::SCENARIOS as $scenario) {
            $first = \local_flwcupkp\local\trajectory_invariant_service::simulate_scenario(
                $scenario, 'phpunit-a5b-scenarios', 24, 7
            );
            $replay = \local_flwcupkp\local\trajectory_invariant_service::simulate_scenario(
                $scenario, 'phpunit-a5b-scenarios', 24, 7
            );
            $report = \local_flwcupkp\local\trajectory_invariant_service::evaluate_trajectory($first, $replay);

            $this->assertSame($first['trajectory_hash'], $replay['trajectory_hash'], $scenario);
            $this->assertSame($first['steps'], $replay['steps'], $scenario);
            $this->assertTrue($report['pass'], $scenario . ': ' . json_encode($report['failed_detectors']));
            $this->assertSame(24, $report['step_count']);
        }

        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_large_suite_replays_exactly_and_exercises_every_detector(): void {
        $this->resetAfterTest(true);
        $before = $this->mutation_counts();

        $suite = \local_flwcupkp\local\trajectory_invariant_service::simulate_suite(
            'phpunit-a5b-suite', 512, 24, [], 8
        );

        $this->assertSame('passed', $suite['status'], json_encode($suite['summary']['violations']));
        $this->assertTrue($suite['global_invariants_passed']);
        $this->assertTrue($suite['deterministic']);
        $this->assertSame($suite['suite_hash'], $suite['replay_hash']);
        $this->assertSame(512, $suite['summary']['trajectories']);
        $this->assertSame(12288, $suite['summary']['simulated_steps']);
        $this->assertSame(512, $suite['summary']['passed']);
        $this->assertSame(0, $suite['summary']['failed']);
        $this->assertCount(8, $suite['summary']['scenarios']);
        $this->assertTrue($suite['detector_self_test']['pass']);
        $this->assertSame(9, $suite['detector_self_test']['passed']);
        $this->assertSame([], $suite['write_boundary']);
        $this->assertFalse($suite['state_changes_allowed']);
        $this->assertSame('A5C', $suite['next_allowed_gate']);
        $this->assertSame($before, $this->mutation_counts());
    }

    public function test_adversarial_self_test_proves_every_failure_detector(): void {
        $this->resetAfterTest(true);

        $result = \local_flwcupkp\local\trajectory_invariant_service::detector_self_test();

        $this->assertTrue($result['pass']);
        $this->assertSame(9, $result['total']);
        $this->assertSame(9, $result['passed']);
        $this->assertSame(0, $result['failed']);
        foreach (\local_flwcupkp\local\trajectory_invariant_service::DETECTORS as $detector) {
            $this->assertArrayHasKey($detector, $result['detectors']);
            $this->assertTrue($result['detectors'][$detector]['pass'], $detector);
            $this->assertNotEmpty($result['detectors'][$detector]['incidents'], $detector);
        }
    }

    /**
     * Counts tables that A5B must never mutate.
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
            'placement' => $DB->count_records('flwcupkp_placement_state'),
        ];
    }
}
