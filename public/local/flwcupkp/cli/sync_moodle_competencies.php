<?php
// Sync C-UP-KP mastery states into native Moodle competency ratings.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'execute' => false,
    'limit' => 0,
    'help' => false,
], [
    'e' => 'execute',
    'l' => 'limit',
    'h' => 'help',
]);

if ($options['help']) {
    echo "Sync C-UP-KP competency states to native Moodle competency ratings.\n";
    echo "Usage: php local/flwcupkp/cli/sync_moodle_competencies.php [--execute] [--limit=N]\n";
    echo "Without --execute, this runs as a dry-run.\n";
    exit(0);
}

$dryrun = empty($options['execute']);
$limit = max(0, (int)$options['limit']);

try {
    $result = \local_flwcupkp\local\moodle_competency_writer::sync_all($dryrun, $limit);
    \local_flwcupkp\local\repository::audit('competency_sync_cli_executed', null, null, [
        'dryrun' => $dryrun,
        'limit' => $limit,
        'writeresult' => $result,
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
    cli_error(get_class($e) . ': ' . $e->getMessage());
}
