<?php
// CLI for Program 3 Gate E1 History V1 evidence reprocessing.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'action' => 'status',
    'courseid' => 0,
    'unitcode' => '',
    'frameworkid' => 0,
    'facttypes' => '',
    'limit' => 100,
    'offset' => 0,
    'reason' => '',
    'confirm' => false,
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
    echo "History V1 to C-UP-KP evidence adapter\n\n";
    echo "Options:\n";
    echo "  --action=status|preview|apply\n";
    echo "  --courseid=ID                 Required for preview/apply\n";
    echo "  --unitcode=U038               Optional unit scope\n";
    echo "  --frameworkid=ID              Optional framework scope\n";
    echo "  --facttypes=attempts,completion\n";
    echo "  --limit=100 --offset=0\n";
    echo "  --reason=\"operator note\"      Stored in apply audit details\n";
    echo "  --confirm=1                   Required for apply\n\n";
    echo "Examples:\n";
    echo "  php local/flwcupkp/cli/history_evidence.php --action=preview --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/history_evidence.php --action=apply --courseid=124 --unitcode=U038 --confirm=1\n";
    exit(0);
}

$action = strtolower((string)$options['action']);
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$limit = (int)$options['limit'];
$offset = (int)$options['offset'];
$reason = (string)$options['reason'];
$facttypes = array_filter(array_map('trim', explode(',', (string)$options['facttypes'])));

try {
    if ($action === 'status') {
        $result = \local_flwcupkp\local\history_evidence_adapter::status($courseid, $unitcode, $frameworkid, $limit);
    } else if ($action === 'preview') {
        $result = \local_flwcupkp\local\history_evidence_adapter::preview_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $facttypes,
            $limit,
            $offset
        );
    } else if ($action === 'apply') {
        if (empty($options['confirm'])) {
            cli_error('Apply requires --confirm=1.');
        }
        $result = \local_flwcupkp\local\history_evidence_adapter::apply_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $facttypes,
            $limit,
            $offset,
            $reason
        );
    } else {
        cli_error('Unsupported action. Use status, preview, or apply.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
