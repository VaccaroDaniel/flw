<?php
// CLI for Program 3 Gate A1 competency-centered learning goals.

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
    'query' => '',
    'datajson' => '',
    'source' => 'STUDENT',
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
    echo "C-UP-KP competency-centered learning goal service\n\n";
    echo "Options:\n";
    echo "  --action=status|current|class|options|save\n";
    echo "  --courseid=ID                 Optional course scope\n";
    echo "  --unitcode=U038               Optional unit scope\n";
    echo "  --frameworkid=ID              Optional framework scope\n";
    echo "  --userid=ID                   Learner scope for current/save\n";
    echo "  --limit=100\n";
    echo "  --query=\"text\"                Target option search\n";
    echo "  --datajson='{}'               Goal payload for save\n";
    echo "  --source=STUDENT|TEACHER|INSTITUTION\n";
    echo "  --reason=\"operator note\"      Stored in goal version audit details\n";
    echo "  --confirm=1                   Required for save\n\n";
    echo "Examples:\n";
    echo "  php local/flwcupkp/cli/learning_goal.php --action=status --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/learning_goal.php --action=current --userid=5 --courseid=124 --unitcode=U038\n";
    echo "  php local/flwcupkp/cli/learning_goal.php --action=save --userid=5 --courseid=124 --unitcode=U038 --datajson='{\"desiredprofile\":\"Read B1 articles independently\"}' --confirm=1\n";
    exit(0);
}

$action = strtolower((string)$options['action']);
$courseid = (int)$options['courseid'];
$unitcode = (string)$options['unitcode'];
$frameworkid = (int)$options['frameworkid'];
$userid = (int)$options['userid'];
$limit = (int)$options['limit'];
$query = (string)$options['query'];
$source = (string)$options['source'];
$reason = (string)$options['reason'];

try {
    if ($action === 'status') {
        $result = \local_flwcupkp\local\learning_goal_service::status($courseid, $unitcode, $frameworkid, $limit);
    } else if ($action === 'current') {
        if ($userid <= 0) {
            cli_error('Current action requires --userid=ID.');
        }
        $result = \local_flwcupkp\local\learning_goal_service::current_goal($userid, $courseid, $unitcode,
            $frameworkid, $limit);
    } else if ($action === 'class') {
        if ($courseid <= 0) {
            cli_error('Class action requires --courseid=ID.');
        }
        $result = \local_flwcupkp\local\learning_goal_service::class_summary($courseid, $unitcode, $frameworkid,
            $limit);
    } else if ($action === 'options') {
        $result = \local_flwcupkp\local\learning_goal_service::goal_options($courseid, $unitcode, $frameworkid,
            $query, $limit);
    } else if ($action === 'save') {
        if (empty($options['confirm'])) {
            cli_error('Save requires --confirm=1.');
        }
        if ($userid <= 0) {
            cli_error('Save action requires --userid=ID.');
        }
        $data = json_decode((string)$options['datajson'], true);
        if (!is_array($data)) {
            cli_error('Save action requires --datajson as a JSON object.');
        }
        $data['courseid'] = $courseid ?: (int)($data['courseid'] ?? 0);
        $data['unitcode'] = $unitcode !== '' ? $unitcode : (string)($data['unitcode'] ?? '');
        $data['frameworkid'] = $frameworkid ?: (int)($data['frameworkid'] ?? 0);
        $result = \local_flwcupkp\local\learning_goal_service::save_goal($userid, $data, $source, $reason);
    } else {
        cli_error('Unsupported action. Use status, current, class, options, or save.');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    cli_error($e->getMessage());
}
