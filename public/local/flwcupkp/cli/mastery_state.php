<?php
// CLI for Program 3 Gate E2 Mastery + Confidence + Current Learner State.

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
    echo "C-UP-KP mastery/confidence/current-state service\n\n";
    echo "Options:\n";
    echo "  --action=status|learner|class|preview|apply\n";
    echo "  --courseid=ID                 Course scope for class/preview/apply\n";
    echo "  --unitcode=U038               Optional unit scope\n";
    echo "  --frameworkid=ID              Optional framework scope\n";
    echo "  --userid=ID                   Learner scope for learner/preview/apply\n";
    echo "  --limit=100\n";
    echo "  --reason=\"operator note\"      Stored in apply audit details\n";
    echo "  --confirm=1                   Required for apply\n\n";
    echo "Examples:\n";
    echo "  php local/flwcupkp/cli/mastery_state.php --action=status --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/mastery_state.php --action=preview --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/mastery_state.php --action=apply --courseid=124 --unitcode=U038 --confirm=1\n";
    exit(0);
}

$action = strtolower((string)$options['action']);
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = (int)$options['limit'];
$reason = (string)$options['reason'];

try {
    if ($action === 'status') {
        $result = \local_flwcupkp\local\mastery_state_service::status($courseid, $unitcode, $frameworkid, $limit);
    } else if ($action === 'learner') {
        if ($userid <= 0) {
            cli_error('Learner action requires --userid=ID.');
        }
        $result = \local_flwcupkp\local\mastery_state_service::current_learner_state(
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
        $result = \local_flwcupkp\local\mastery_state_service::class_summary($courseid, $unitcode, $frameworkid,
            $limit);
    } else if ($action === 'preview') {
        $result = \local_flwcupkp\local\mastery_state_service::preview_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit
        );
    } else if ($action === 'apply') {
        if (empty($options['confirm'])) {
            cli_error('Apply requires --confirm=1.');
        }
        $result = \local_flwcupkp\local\mastery_state_service::apply_rebuild(
            $courseid,
            $unitcode,
            $frameworkid,
            $userid,
            $limit,
            $reason
        );
    } else {
        cli_error('Unsupported action. Use status, learner, class, preview, or apply.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
