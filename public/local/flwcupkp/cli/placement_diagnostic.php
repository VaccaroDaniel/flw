<?php
// CLI for Program 3 Gate A2 Placement + Diagnostic + Cold Start.

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
    echo "C-UP-KP placement diagnostic and cold-start service\n\n";
    echo "Options:\n";
    echo "  --action=status|current|class|preview|apply|history\n";
    echo "  --courseid=ID                 Course scope for class/preview/apply\n";
    echo "  --unitcode=U038               Optional unit scope\n";
    echo "  --frameworkid=ID              Optional framework scope\n";
    echo "  --userid=ID                   Learner scope for current/preview/apply\n";
    echo "  --limit=100\n";
    echo "  --offset=0                    History V1 placement offset for preview/apply\n";
    echo "  --reason=\"operator note\"      Stored in apply audit details\n";
    echo "  --confirm=1                   Required for apply\n\n";
    echo "Examples:\n";
    echo "  php local/flwcupkp/cli/placement_diagnostic.php --action=status --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/placement_diagnostic.php --action=preview --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/placement_diagnostic.php --action=apply --courseid=124 --unitcode=U038 --confirm=1\n";
    exit(0);
}

$action = strtolower((string)$options['action']);
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = (int)$options['limit'];
$offset = (int)$options['offset'];
$reason = (string)$options['reason'];

try {
    if ($action === 'status') {
        $result = \local_flwcupkp\local\placement_diagnostic_service::status(
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } else if ($action === 'current') {
        if ($userid <= 0) {
            cli_error('Current action requires --userid=ID.');
        }
        $result = \local_flwcupkp\local\placement_diagnostic_service::current_placement(
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
        $result = \local_flwcupkp\local\placement_diagnostic_service::class_summary(
            $courseid,
            $unitcode,
            $frameworkid,
            $limit
        );
    } else if ($action === 'preview') {
        $result = \local_flwcupkp\local\placement_diagnostic_service::preview_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $offset
        );
    } else if ($action === 'apply') {
        if (empty($options['confirm'])) {
            cli_error('Apply requires --confirm=1.');
        }
        $result = \local_flwcupkp\local\placement_diagnostic_service::apply_reprocess(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $offset,
            $reason
        );
    } else if ($action === 'history') {
        $result = \local_flwcupkp\local\placement_diagnostic_service::recent_reprocess_history(
            $courseid,
            $unitcode,
            $limit
        );
    } else {
        cli_error('Unsupported action. Use status, current, class, preview, apply, or history.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
