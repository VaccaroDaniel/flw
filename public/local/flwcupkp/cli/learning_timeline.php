<?php
// Read-only Program 3 UX1 timeline CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'action' => 'status',
    'userid' => 0,
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'limit' => 10,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unrecognized options:\n  {$unrecognized}");
}
if (!empty($options['help'])) {
    echo "Program 3 UX1 Past, Present, and Future dashboard\n\n";
    echo "Options:\n";
    echo "  --action=status|learner\n";
    echo "  --userid=ID\n";
    echo "  --courseid=ID\n";
    echo "  --unitcode=CODE\n";
    echo "  --frameworkid=ID\n";
    echo "  --limit=N\n";
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$userid = (int)$options['userid'];
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$limit = (int)$options['limit'];

if ($action === 'status') {
    $result = \local_flwcupkp\local\student_learning_timeline_view_service::status(
        $courseid, $unitcode, $frameworkid
    );
} else if ($action === 'learner') {
    if ($userid <= 0 || $courseid <= 0) {
        cli_error('--userid and --courseid are required for learner action.');
    }
    $result = \local_flwcupkp\local\student_learning_timeline_view_service::learner_timeline(
        $userid, $courseid, $unitcode, $frameworkid, $limit
    );
} else {
    cli_error('Unknown action. Use status or learner.');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
