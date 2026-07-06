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
    echo "Seed local_mldict entries from a UTF-8 CSV file.\n\n";
    echo "Required columns: headword,sourcelang,partofspeech,cefrlevel,definition\n";
    echo "Usage: php local/mldict/cli/seed_words.php --file=/path/to/seed.csv\n";
    exit($options['help'] ? 0 : 1);
}

$csvpath = $options['file'];
$handle = fopen($csvpath, 'r');
if (!$handle) {
    cli_error("Unable to open seed CSV: {$csvpath}");
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    cli_error("Seed CSV is empty: {$csvpath}");
}

$now = time();
$inserted = 0;
$skipped = 0;
$bylang = [];

set_config('enabledlanguages', 'en,ru,zh,ja,de,fr,es', 'local_mldict');
set_config('defaultsourcelang', 'en', 'local_mldict');

while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);
    if ($data === false) {
        $skipped++;
        continue;
    }

    $headword = trim($data['headword'] ?? '');
    $lang = trim($data['sourcelang'] ?? '');
    if ($headword === '' || $lang === '') {
        $skipped++;
        continue;
    }

    if ($DB->record_exists('local_mldict_entry', ['headword' => $headword, 'sourcelang' => $lang])) {
        $skipped++;
        continue;
    }

    $record = (object)[
        'headword' => $headword,
        'sourcelang' => $lang,
        'partofspeech' => trim($data['partofspeech'] ?? 'other') ?: 'other',
        'cefrlevel' => trim($data['cefrlevel'] ?? ''),
        'pronunciation' => '',
        'phonetic' => '',
        'definition' => trim($data['definition'] ?? ''),
        'notes' => 'Seeded from frequency-ranked FLW language word lists.',
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    $DB->insert_record('local_mldict_entry', $record);
    $inserted++;
    $bylang[$lang] = ($bylang[$lang] ?? 0) + 1;
}

fclose($handle);

ksort($bylang);
echo "Inserted: {$inserted}\n";
echo "Skipped: {$skipped}\n";
foreach ($bylang as $lang => $count) {
    echo "{$lang}: {$count}\n";
}
echo 'Total entries: ' . $DB->count_records('local_mldict_entry') . "\n";
