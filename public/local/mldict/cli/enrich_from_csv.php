<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'file' => '',
    'replace' => false,
    'help' => false,
], [
    'f' => 'file',
    'r' => 'replace',
    'h' => 'help',
]);

if ($options['help'] || empty($options['file'])) {
    echo "Enrich local_mldict entries from a source-backed CSV.\n\n";
    echo "Columns: sourcelang,headword,partofspeech,pronunciation,phonetic,definition,example,exampletranslation,source\n";
    echo "Usage: php local/mldict/cli/enrich_from_csv.php --file=/path/enrichment.csv [--replace]\n";
    exit($options['help'] ? 0 : 1);
}

$csvpath = $options['file'];
$handle = fopen($csvpath, 'r');
if (!$handle) {
    cli_error("Unable to open enrichment CSV: {$csvpath}");
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    cli_error("Enrichment CSV is empty: {$csvpath}");
}

$replace = !empty($options['replace']);
$now = time();
$rowsread = 0;
$entriesmatched = 0;
$entriesupdated = 0;
$examplesinserted = 0;

$transaction = $DB->start_delegated_transaction();

while (($row = fgetcsv($handle)) !== false) {
    $rowsread++;
    $data = array_combine($header, $row);
    if ($data === false) {
        continue;
    }

    $sourcelang = trim($data['sourcelang'] ?? '');
    $headword = trim($data['headword'] ?? '');
    if ($sourcelang === '' || $headword === '') {
        continue;
    }

    $records = $DB->get_records('local_mldict_entry', [
        'sourcelang' => $sourcelang,
        'headword' => $headword,
    ], '', 'id, partofspeech, pronunciation, phonetic, definition, notes');

    if (!$records) {
        continue;
    }

    $partofspeech = trim($data['partofspeech'] ?? '');
    $pronunciation = trim($data['pronunciation'] ?? '');
    $phonetic = trim($data['phonetic'] ?? '');
    $definition = trim($data['definition'] ?? '');
    $example = trim($data['example'] ?? '');
    $exampletranslation = trim($data['exampletranslation'] ?? '');
    $source = trim($data['source'] ?? '');
    $sourcenote = $source !== '' ? "Enrichment source: {$source}." : '';

    foreach ($records as $entry) {
        $entriesmatched++;
        $update = (object)['id' => $entry->id];
        $changed = false;

        if ($partofspeech !== '' && ($replace || empty($entry->partofspeech))) {
            $update->partofspeech = $partofspeech;
            $changed = true;
        }
        if ($pronunciation !== '' && ($replace || empty($entry->pronunciation))) {
            $update->pronunciation = core_text::substr($pronunciation, 0, 255);
            $changed = true;
        }
        if ($phonetic !== '' && ($replace || empty($entry->phonetic))) {
            $update->phonetic = core_text::substr($phonetic, 0, 255);
            $changed = true;
        }
        if ($definition !== '' && ($replace || empty($entry->definition))) {
            $update->definition = $definition;
            $changed = true;
        }
        if ($sourcenote !== '' && strpos((string)$entry->notes, $sourcenote) === false) {
            $update->notes = trim((string)$entry->notes . "\n" . $sourcenote);
            $changed = true;
        }

        if ($changed) {
            $update->timemodified = $now;
            $DB->update_record('local_mldict_entry', $update);
            $entriesupdated++;
        }

        if ($example !== '') {
            $exists = $DB->record_exists('local_mldict_example', [
                'entryid' => $entry->id,
                'examplelang' => $sourcelang,
                'sentence' => $example,
            ]);
            if (!$exists) {
                $DB->insert_record('local_mldict_example', (object)[
                    'entryid' => $entry->id,
                    'examplelang' => $sourcelang,
                    'sentence' => $example,
                    'translation' => $exampletranslation,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $examplesinserted++;
            }
        }
    }
}

fclose($handle);
$transaction->allow_commit();

echo "Rows read: {$rowsread}\n";
echo "Entries matched: {$entriesmatched}\n";
echo "Entries updated: {$entriesupdated}\n";
echo "Examples inserted: {$examplesinserted}\n";
