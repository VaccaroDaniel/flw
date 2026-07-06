<?php
// This file is part of Moodle - http://moodle.org/

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

use local_mldict\local\dictionary;

require_login(null, false);
$context = context_system::instance();
require_capability('local/mldict:view', $context);

$q = required_param('q', PARAM_TEXT);
$lang = optional_param('lang', '', PARAM_ALPHANUMEXT);

$entries = dictionary::search_entries($q, $lang, 10);
$result = [];
foreach ($entries as $entry) {
    $full = dictionary::get_full_entry((int)$entry->id);
    $translations = [];
    foreach ($full->translations as $translation) {
        $translations[] = [
            'lang' => dictionary::lang_label($translation->targetlang),
            'translation' => $translation->translation,
        ];
    }
    $result[] = [
        'id' => $full->id,
        'headword' => $full->headword,
        'sourceLang' => dictionary::lang_label($full->sourcelang),
        'partOfSpeech' => $full->partofspeech,
        'cefrLevel' => $full->cefrlevel,
        'definition' => $full->definition,
        'translations' => $translations,
        'url' => (new moodle_url('/local/mldict/view.php', ['id' => $full->id]))->out(false),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
