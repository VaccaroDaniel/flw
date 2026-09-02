<?php
// CLI for Program 3 Gate A5C Progress and Goal Readiness Contract.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'action' => 'status',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'userid' => 0,
    'limit' => 100,
    'help' => false,
], [
    'a' => 'action',
    'c' => 'courseid',
    'u' => 'unitcode',
    'f' => 'frameworkid',
    'l' => 'limit',
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Program 3 Gate A5C Progress and Goal Readiness Contract\n\n";
    echo "Options:\n";
    echo "  --action=status|learner|class\n";
    echo "  --courseid=ID --unitcode=CODE --frameworkid=ID --userid=ID --limit=N\n";
    echo "All actions are read-only.\n";
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = max(1, min(300, (int)$options['limit']));

try {
    switch ($action) {
        case 'status':
            $result = \local_flwcupkp\local\progress_goal_readiness_service::status(
                $courseid, $unitcode, $frameworkid
            );
            break;
        case 'learner':
            if ($userid <= 0) {
                cli_error('--userid is required for learner progress.');
            }
            $result = \local_flwcupkp\local\progress_goal_readiness_service::learner_progress(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            );
            break;
        case 'class':
            if ($courseid <= 0) {
                cli_error('--courseid is required for class progress.');
            }
            $result = \local_flwcupkp\local\progress_goal_readiness_service::class_summary(
                $courseid, $unitcode, $frameworkid, $limit
            );
            break;
        default:
            cli_error('Unsupported action: ' . $action);
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
