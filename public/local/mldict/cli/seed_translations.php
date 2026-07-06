<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'file' => '',
    'help' => false,
], [
    'f' => 'file',
    'h' => 'help',
]);

if ($options['help'] || empty($options['file'])) {
    echo "Seed local_mldict translations from a UTF-8 CSV file.\n\n";
    echo "Required columns: headword,sourcelang,targetlang,translation\n";
    echo "Usage: php local/mldict/cli/seed_translations.php --file=/path/to/translations.csv\n";
    exit($options['help'] ? 0 : 1);
}

$csvpath = $options['file'];
$handle = fopen($csvpath, 'r');
if (!$handle) {
    cli_error("Unable to open translation CSV: {$csvpath}");
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    cli_error("Translation CSV is empty: {$csvpath}");
}

$now = time();
$inserted = 0;
$skipped = 0;
$missing = 0;
$entrycache = [];

while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);
    if ($data === false) {
        $skipped++;
        continue;
    }

    $headword = trim($data['headword'] ?? '');
    $sourcelang = trim($data['sourcelang'] ?? '');
    $targetlang = trim($data['targetlang'] ?? '');
    $translation = trim($data['translation'] ?? '');

    if ($headword === '' || $sourcelang === '' || $targetlang === '' || $translation === '') {
        $skipped++;
        continue;
    }

    $cachekey = $sourcelang . "\n" . $headword;
    if (!array_key_exists($cachekey, $entrycache)) {
        $entry = $DB->get_record('local_mldict_entry', [
            'headword' => $headword,
            'sourcelang' => $sourcelang,
        ], 'id', IGNORE_MULTIPLE);
        $entrycache[$cachekey] = $entry ? (int)$entry->id : 0;
    }

    $entryid = $entrycache[$cachekey];
    if (!$entryid) {
        $missing++;
        continue;
    }

    if ($DB->record_exists('local_mldict_translation', ['entryid' => $entryid, 'targetlang' => $targetlang])) {
        $skipped++;
        continue;
    }

    $record = (object)[
        'entryid' => $entryid,
        'targetlang' => $targetlang,
        'translation' => $translation,
        'definition' => 'Machine translated learner-dictionary seed.',
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $DB->insert_record('local_mldict_translation', $record);
    $inserted++;
}

fclose($handle);

echo "Inserted: {$inserted}\n";
echo "Skipped: {$skipped}\n";
echo "Missing entries: {$missing}\n";
echo 'Total translations: ' . $DB->count_records('local_mldict_translation') . "\n";
