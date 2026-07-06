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
    echo "Rebuild local_mldict from a PanLex concept CSV.\n\n";
    echo "Required columns: meaning,en,ru,zh,ja,de,fr,es. Optional columns: cefr,cefrsource\n";
    echo "Usage: php local/mldict/cli/rebuild_panlex_seed.php --file=/path/to/concepts.csv\n";
    exit($options['help'] ? 0 : 1);
}

$csvpath = $options['file'];
$handle = fopen($csvpath, 'r');
if (!$handle) {
    cli_error("Unable to open PanLex concept CSV: {$csvpath}");
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    cli_error("PanLex concept CSV is empty: {$csvpath}");
}

$languages = ['en', 'ru', 'zh', 'ja', 'de', 'fr', 'es'];
$now = time();
$entryids = [];
$entrycount = 0;
$translationcount = 0;

$transaction = $DB->start_delegated_transaction();

$DB->delete_records('local_mldict_example');
$DB->delete_records('local_mldict_translation');
$DB->delete_records('local_mldict_entry');

set_config('enabledlanguages', implode(',', $languages), 'local_mldict');
set_config('defaultsourcelang', 'en', 'local_mldict');

while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);
    if ($data === false) {
        continue;
    }

    $meaning = trim($data['meaning'] ?? '');
    if ($meaning === '') {
        continue;
    }

    $terms = [];
    foreach ($languages as $lang) {
        $term = trim($data[$lang] ?? '');
        if ($term === '') {
            continue 2;
        }
        $terms[$lang] = $term;
    }
    $cefr = trim($data['cefr'] ?? '');
    if (!in_array($cefr, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true)) {
        $cefr = '';
    }
    $cefrsource = trim($data['cefrsource'] ?? '');
    $cefrnote = $cefrsource !== '' ? '; CEFR source ' . $cefrsource : '';

    foreach ($languages as $lang) {
        $record = (object)[
            'headword' => $terms[$lang],
            'sourcelang' => $lang,
            'partofspeech' => 'other',
            'cefrlevel' => $cefr,
            'pronunciation' => '',
            'phonetic' => '',
            'definition' => '',
            'notes' => 'PanLex meaning ' . $meaning . '; source cointegrated/panlex-meanings' . $cefrnote . '.',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $entryids[$meaning][$lang] = $DB->insert_record('local_mldict_entry', $record);
        $entrycount++;
    }

    foreach ($languages as $sourcelang) {
        foreach ($languages as $targetlang) {
            if ($targetlang === $sourcelang) {
                continue;
            }
            $record = (object)[
                'entryid' => $entryids[$meaning][$sourcelang],
                'targetlang' => $targetlang,
                'translation' => $terms[$targetlang],
                'definition' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_mldict_translation', $record);
            $translationcount++;
        }
    }

    unset($entryids[$meaning]);
}

fclose($handle);
$transaction->allow_commit();

echo "Entries inserted: {$entrycount}\n";
echo "Translations inserted: {$translationcount}\n";
echo 'Total entries: ' . $DB->count_records('local_mldict_entry') . "\n";
echo 'Total translations: ' . $DB->count_records('local_mldict_translation') . "\n";
