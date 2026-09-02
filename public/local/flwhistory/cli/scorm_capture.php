<?php
// Controlled SCORM History V1 repair CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'action' => 'repair',
    'attemptid' => 0,
    'confirm' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}
if (!empty($options['help'])) {
    cli_writeln('FLW History V1 SCORM capture and controlled repair');
    cli_writeln('');
    cli_writeln('--action=repair --attemptid=ID --confirm=1');
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$attemptid = (int)$options['attemptid'];
if ($action !== 'repair') {
    cli_error('Unsupported action. Use repair.');
}
if ($attemptid <= 0) {
    cli_error('--attemptid is required.');
}
if (!filter_var($options['confirm'], FILTER_VALIDATE_BOOLEAN)) {
    cli_error('Controlled repair requires --confirm=1.');
}

$result = \local_flwhistory\local\capture_service::repair_scorm_attempt($attemptid);
cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
