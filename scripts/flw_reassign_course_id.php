<?php
// This file is part of FLW local maintenance tooling.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'old' => 0,
    'new' => 0,
    'execute' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || $options['old'] <= 0 || $options['new'] <= 0) {
    echo "Reassign a Moodle course id and common course references.\n\n";
    echo "Usage:\n";
    echo "  php scripts/flw_reassign_course_id.php --old=112 --new=35 --execute\n";
    echo "  php scripts/flw_reassign_course_id.php --old=112 --new=35\n\n";
    echo "Without --execute this script performs a dry run.\n";
    exit($options['help'] ? 0 : 1);
}

global $DB, $CFG;

$oldid = (int)$options['old'];
$newid = (int)$options['new'];
$execute = (bool)$options['execute'];

if (!$DB->record_exists('course', ['id' => $oldid])) {
    cli_error("Course {$oldid} does not exist.");
}
if ($DB->record_exists('course', ['id' => $newid])) {
    cli_error("Target course id {$newid} already exists.");
}

$records = $DB->get_records_sql(
    "SELECT table_name, column_name
       FROM information_schema.columns
      WHERE table_schema = current_schema()
        AND table_name LIKE ?
        AND column_name IN ('course', 'courseid')
      ORDER BY table_name, column_name",
    [$CFG->prefix . '%']
);

$updates = [];
foreach ($records as $record) {
    $table = substr($record->table_name, strlen($CFG->prefix));
    $column = $record->column_name;
    if (!preg_match('/^[a-z0-9_]+$/', $table) || !preg_match('/^[a-z0-9_]+$/', $column)) {
        cli_error('Unsafe table or column name detected.');
    }
    $count = $DB->count_records($table, [$column => $oldid]);
    if ($count > 0) {
        $updates[] = [$table, $column, $count];
    }
}

$contextcount = $DB->count_records('context', [
    'contextlevel' => CONTEXT_COURSE,
    'instanceid' => $oldid,
]);

echo ($execute ? "Executing" : "Dry run") . " course id reassignment {$oldid} -> {$newid}\n";
foreach ($updates as [$table, $column, $count]) {
    echo "  {$table}.{$column}: {$count}\n";
}
if ($contextcount > 0) {
    echo "  context.instanceid: {$contextcount}\n";
}
echo "  course.id: 1\n";

if (!$execute) {
    exit(0);
}

$transaction = $DB->start_delegated_transaction();
try {
    foreach ($updates as [$table, $column]) {
        $DB->execute("UPDATE {{$table}} SET {$column} = ? WHERE {$column} = ?", [$newid, $oldid]);
    }

    if ($contextcount > 0) {
        $DB->execute(
            'UPDATE {context} SET instanceid = ? WHERE contextlevel = ? AND instanceid = ?',
            [$newid, CONTEXT_COURSE, $oldid]
        );
    }

    $DB->execute('UPDATE {course} SET id = ? WHERE id = ?', [$newid, $oldid]);
    $transaction->allow_commit();
    echo "Done.\n";
} catch (Throwable $e) {
    $transaction->rollback($e);
}
