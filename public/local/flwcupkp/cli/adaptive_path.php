<?php
// CLI for Program 3 Gate A5 Continuous Adaptive Path Engine.

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
    'reason' => 'Controlled Program 3 A5 CLI refresh.',
    'confirm' => false,
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
    echo "Program 3 Gate A5 Continuous Adaptive Path Engine\n\n";
    echo "Options:\n";
    echo "  --action=status|preview|apply|class-summary|apply-class\n";
    echo "  --courseid=ID --unitcode=CODE --frameworkid=ID --userid=ID --limit=N\n";
    echo "  --reason=TEXT --confirm   Required for apply/apply-class writes\n";
    exit(0);
}

$action = strtolower(trim((string)$options['action']));
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = max(1, min(300, (int)$options['limit']));
$reason = trim((string)$options['reason']);
$confirm = !empty($options['confirm']);

try {
    switch ($action) {
        case 'status':
            $result = \local_flwcupkp\local\adaptive_path_engine_service::status(
                $courseid, $unitcode, $frameworkid, $limit
            );
            break;
        case 'preview':
            if ($userid <= 0) {
                cli_error('--userid is required for preview.');
            }
            $result = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
                $userid, $courseid, $unitcode, $frameworkid, $limit
            );
            break;
        case 'apply':
            if ($userid <= 0) {
                cli_error('--userid is required for apply.');
            }
            if (!$confirm) {
                cli_error('--confirm is required for controlled A5 recommendation writes.');
            }
            $result = \local_flwcupkp\local\adaptive_path_engine_service::apply_learner_path(
                $userid, $courseid, $unitcode, $frameworkid, $limit, $reason
            );
            break;
        case 'class-summary':
            if ($courseid <= 0) {
                cli_error('--courseid is required for class-summary.');
            }
            $result = \local_flwcupkp\local\adaptive_path_engine_service::class_summary(
                $courseid, $unitcode, $frameworkid, $limit
            );
            break;
        case 'apply-class':
            if ($courseid <= 0) {
                cli_error('--courseid is required for apply-class.');
            }
            if (!$confirm) {
                cli_error('--confirm is required for controlled A5 recommendation writes.');
            }
            $result = \local_flwcupkp\local\adaptive_path_engine_service::apply_class_paths(
                $courseid, $unitcode, $frameworkid, $limit, $reason
            );
            break;
        default:
            cli_error('Unsupported action: ' . $action);
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
