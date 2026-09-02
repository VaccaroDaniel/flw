<?php
// Read-only CLI for Program 3 Gate F1 production validation.

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'action' => 'validate',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'userid' => 0,
    'limit' => 100,
    'performance' => true,
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
    cli_writeln('Program 3 Gate F1 full integrated production validation (read-only)');
    cli_writeln('');
    cli_writeln('--action=validate|discover|contract');
    cli_writeln('--courseid=ID --unitcode=CODE --frameworkid=ID --userid=ID --limit=N');
    cli_writeln('--performance=1|0');
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
if ($action === 'discover') {
    $result = \local_flwcupkp\local\production_validation_service::discover_scopes();
} else if ($action === 'contract') {
    $result = \local_flwcupkp\local\production_validation_service::contract();
} else if ($action === 'validate') {
    $courseid = max(0, (int)$options['courseid']);
    if ($courseid <= 0) {
        cli_error('--courseid is required for validate action.');
    }
    $performance = filter_var($options['performance'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $result = \local_flwcupkp\local\production_validation_service::validate_scope(
        $courseid,
        (string)$options['unitcode'],
        max(0, (int)$options['frameworkid']),
        max(0, (int)$options['userid']),
        max(10, min(500, (int)$options['limit'])),
        $performance !== false
    );
} else {
    cli_error('Unsupported action. Use validate, discover, or contract.');
}

cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
