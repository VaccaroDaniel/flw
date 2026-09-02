<?php
// CLI for Program 3 Gate A5B Trajectory Simulation and Invariant Testing.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'action' => 'status',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'userid' => 0,
    'seed' => 'flw-cupkp-a5b-v1',
    'trajectories' => 512,
    'steps' => 24,
    'scenario' => 'all',
    'samplelimit' => 8,
    'help' => false,
], [
    'a' => 'action',
    'c' => 'courseid',
    'u' => 'unitcode',
    'f' => 'frameworkid',
    's' => 'scenario',
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Program 3 Gate A5B Trajectory Simulation and Invariant Testing\n\n";
    echo "Options:\n";
    echo "  --action=status|simulate|scenario|learner|self-test\n";
    echo "  --courseid=ID --unitcode=CODE --frameworkid=ID --userid=ID\n";
    echo "  --seed=TEXT --trajectories=N --steps=N --scenario=NAME --samplelimit=N\n";
    echo "All actions are deterministic and read-only.\n";
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$seed = (string)$options['seed'];
$trajectorycount = max(1, min(2000, (int)$options['trajectories']));
$steps = max(4, min(100, (int)$options['steps']));
$scenario = strtolower(trim((string)$options['scenario']));
$samplelimit = max(1, min(20, (int)$options['samplelimit']));

try {
    switch ($action) {
        case 'status':
            $result = \local_flwcupkp\local\trajectory_invariant_service::status(
                $courseid, $unitcode, $frameworkid
            );
            break;
        case 'simulate':
            $scenarios = $scenario === '' || $scenario === 'all' ? [] : [$scenario];
            $result = \local_flwcupkp\local\trajectory_invariant_service::simulate_suite(
                $seed, $trajectorycount, $steps, $scenarios, $samplelimit
            );
            break;
        case 'scenario':
            if ($scenario === '' || $scenario === 'all') {
                cli_error('--scenario is required for a scenario simulation.');
            }
            $trajectory = \local_flwcupkp\local\trajectory_invariant_service::simulate_scenario(
                $scenario, $seed, $steps
            );
            $result = [
                'trajectory' => $trajectory,
                'invariants' => \local_flwcupkp\local\trajectory_invariant_service::evaluate_trajectory(
                    $trajectory, $trajectory
                ),
            ];
            break;
        case 'learner':
            if ($userid <= 0) {
                cli_error('--userid is required for learner projection.');
            }
            $result = \local_flwcupkp\local\trajectory_invariant_service::learner_projection(
                $userid, $courseid, $unitcode, $frameworkid, $seed, $steps
            );
            break;
        case 'self-test':
            $result = \local_flwcupkp\local\trajectory_invariant_service::detector_self_test();
            break;
        default:
            cli_error('Unsupported action: ' . $action);
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
