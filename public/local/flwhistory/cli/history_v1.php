<?php
// History V1 migration, reconciliation, freeze, and downstream contract CLI.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'action' => 'freeze',
    'courseid' => 0,
    'userid' => 0,
    'limit' => 100,
    'cursorjson' => '',
    'source' => 'h7_cli',
    'sources' => '',
    'execute' => false,
    'idempotency' => '',
], [
    'h' => 'help',
]);

if (!empty($unrecognized)) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unknown option(s):\n  {$unrecognized}");
}

if (!empty($options['help'])) {
    cli_writeln("FLW History V1 H7 CLI\n");
    cli_writeln("Actions:");
    cli_writeln("  --action=backfill     Backfill recoverable Moodle/FLW facts. Dry-run unless --execute is supplied.");
    cli_writeln("  --action=reconcile    Reconcile History V1 summaries/state against source systems. Dry-run unless --execute is supplied.");
    cli_writeln("  --action=performance  Measure core learner/class history read paths.");
    cli_writeln("  --action=freeze       Report History V1 freeze readiness.");
    cli_writeln("  --action=contract     Print the Program 3 downstream evidence contract.");
    cli_writeln("\nOptions:");
    cli_writeln("  --courseid=N          Moodle course id for course-scoped actions.");
    cli_writeln("  --userid=N            Optional learner id for performance probes.");
    cli_writeln("  --limit=N             Batch/page size. Backfill is capped by the service.");
    cli_writeln("  --sources=a,b         Optional backfill sources: quiz_attempts, completion, grade_history, grade_current, placement.");
    cli_writeln("  --cursorjson='{}'     Resume cursors returned from a previous dry-run or execute call.");
    cli_writeln("  --source=LABEL        Source label recorded in backfill summaries.");
    cli_writeln("  --execute             Write changes for backfill/reconcile. Omitted means dry-run.");
    cli_writeln("  --idempotency=KEY     Optional run key suffix for repeated controlled operations.");
    exit(0);
}

$action = trim((string)$options['action']);
$courseid = (int)$options['courseid'];
$userid = (int)$options['userid'];
$limit = (int)$options['limit'];
$dryrun = empty($options['execute']);
$cursors = [];

if ((string)$options['cursorjson'] !== '') {
    $decoded = json_decode((string)$options['cursorjson'], true);
    if (!is_array($decoded)) {
        cli_error('Invalid --cursorjson value. Expected a JSON object.');
    }
    $cursors = $decoded;
}

if (in_array($action, ['backfill', 'reconcile', 'performance'], true) && $courseid <= 0) {
    cli_error('--courseid is required for this action.');
}

switch ($action) {
    case 'backfill':
        $sources = array_filter(array_map('trim', explode(',', (string)$options['sources'])));
        $result = \local_flwhistory\local\history_v1_service::backfill_course($courseid, [
            'dryrun' => $dryrun,
            'batchsize' => $limit,
            'cursors' => $cursors,
            'sources' => $sources,
            'sourcelabel' => (string)$options['source'],
            'idempotencykey' => (string)$options['idempotency'],
        ]);
        break;

    case 'reconcile':
        $result = \local_flwhistory\local\history_v1_service::reconcile_course($courseid, [
            'dryrun' => $dryrun,
            'batchsize' => $limit,
            'idempotencykey' => (string)$options['idempotency'],
        ]);
        break;

    case 'performance':
        $result = \local_flwhistory\local\history_v1_service::performance_snapshot($courseid, $userid, [
            'limit' => $limit,
        ]);
        break;

    case 'freeze':
        $result = \local_flwhistory\local\history_v1_service::freeze_status($courseid, [
            'userid' => $userid,
            'limit' => $limit,
        ]);
        break;

    case 'contract':
        $result = \local_flwhistory\local\history_v1_service::downstream_contract();
        break;

    default:
        cli_error('Unknown --action. Use --help for supported actions.');
}

cli_writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
