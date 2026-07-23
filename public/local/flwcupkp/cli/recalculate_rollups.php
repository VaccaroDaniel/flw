<?php
// Recalculate C-UP-KP parent states from KP/UP topology.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'userid' => 0,
    'limit' => 0,
    'no-sync' => false,
    'help' => false,
], [
    'u' => 'userid',
    'h' => 'help',
]);

if ($options['help']) {
    echo "Recalculate C-UP-KP UP and competency roll-up states.\n";
    echo "Usage: php local/flwcupkp/cli/recalculate_rollups.php [--userid=5] [--limit=100] [--no-sync]\n";
    echo "--userid recalculates one Moodle user only.\n";
    echo "--limit caps the number of users when recalculating all users.\n";
    echo "--no-sync updates C-UP-KP parent states without writing native Moodle competency ratings.\n";
    exit(0);
}

$userid = (int)$options['userid'];
$limit = (int)$options['limit'];
$syncmoodle = empty($options['no-sync']);

$result = \local_flwcupkp\local\rollup_engine::recalculate_all(
    $userid > 0 ? $userid : null,
    $syncmoodle,
    $limit
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
