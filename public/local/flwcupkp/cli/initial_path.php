<?php
// CLI for Program 3 Gate A4 Goal-Gap + Initial Personalized Path.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'action' => 'status',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'userid' => 0,
    'limit' => 100,
], [
    'h' => 'help',
    'a' => 'action',
    'c' => 'courseid',
    'u' => 'unitcode',
    'f' => 'frameworkid',
    'l' => 'limit',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unknown option(s):\n  {$unrecognized}");
}

if (!empty($options['help'])) {
    echo "C-UP-KP goal-gap initial path service\n\n";
    echo "Options:\n";
    echo "  --action=status|policy|learner|class\n";
    echo "  --courseid=ID                 Course scope for learner/class\n";
    echo "  --unitcode=U038               Optional unit scope\n";
    echo "  --frameworkid=ID              Optional framework scope\n";
    echo "  --userid=ID                   Learner scope for initial path\n";
    echo "  --limit=100\n\n";
    echo "Examples:\n";
    echo "  php local/flwcupkp/cli/initial_path.php --action=status --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/initial_path.php --action=learner --userid=5 --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/initial_path.php --action=class --courseid=124 --unitcode=U038\n";
    exit(0);
}

$action = strtolower((string)$options['action']);
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = (int)$options['limit'];

try {
    if ($action === 'status') {
        $result = \local_flwcupkp\local\goal_gap_path_service::status(
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } else if ($action === 'policy') {
        $result = \local_flwcupkp\local\goal_gap_path_service::policy();
    } else if ($action === 'learner') {
        if ($userid <= 0) {
            cli_error('Learner action requires --userid=ID.');
        }
        $result = \local_flwcupkp\local\goal_gap_path_service::learner_path(
            $userid,
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } else if ($action === 'class') {
        if ($courseid <= 0) {
            cli_error('Class action requires --courseid=ID.');
        }
        $result = \local_flwcupkp\local\goal_gap_path_service::class_summary(
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } else {
        cli_error('Unsupported action. Use status, policy, learner, or class.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
