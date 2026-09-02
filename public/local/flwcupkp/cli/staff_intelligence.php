<?php
// Read-only CLI for Program 3 Gate UX3 staff intelligence.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'action' => 'status',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'userid' => 0,
    'limit' => 50,
], [
    'h' => 'help',
    'a' => 'action',
    'c' => 'courseid',
    'u' => 'unitcode',
    'f' => 'frameworkid',
    'l' => 'userid',
]);

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}
if (!empty($options['help'])) {
    cli_writeln('Program 3 Gate UX3 staff intelligence (read-only)');
    cli_writeln('');
    cli_writeln('--action=status|learner|history');
    cli_writeln('--courseid=ID --unitcode=CODE --frameworkid=ID --userid=ID --limit=N');
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$courseid = max(0, (int)$options['courseid']);
$unitcode = (string)$options['unitcode'];
$frameworkid = max(0, (int)$options['frameworkid']);
$userid = max(0, (int)$options['userid']);
$limit = max(1, min(200, (int)$options['limit']));

if ($action === 'status') {
    $result = \local_flwcupkp\local\staff_intelligence_service::status(
        $courseid, $unitcode, $frameworkid
    );
} else if ($action === 'learner') {
    if ($userid <= 0 || $courseid <= 0) {
        cli_error('--userid and --courseid are required for learner action.');
    }
    $result = \local_flwcupkp\local\staff_intelligence_service::learner_intelligence(
        $userid, $courseid, $unitcode, $frameworkid, $limit
    );
} else if ($action === 'history') {
    if ($userid <= 0 || $courseid <= 0) {
        cli_error('--userid and --courseid are required for history action.');
    }
    $result = \local_flwcupkp\local\staff_intelligence_service::intervention_history(
        $userid, $courseid, $unitcode, $limit
    );
} else {
    cli_error('Unsupported action. Use status, learner, or history.');
}

cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
