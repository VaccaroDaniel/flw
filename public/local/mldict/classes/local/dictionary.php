<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mldict\local;

defined('MOODLE_INTERNAL') || die();

class dictionary {
    public const TABLE_ENTRY = 'local_mldict_entry';
    public const TABLE_TRANSLATION = 'local_mldict_translation';
    public const TABLE_EXAMPLE = 'local_mldict_example';
    private const DEFAULT_ENABLED_LANGUAGES = 'en,ru,zh,ja,de,fr,es';

    public static function lang_options(): array {
        $languages = self::all_lang_options();
        $enabled = get_config('local_mldict', 'enabledlanguages') ?: self::DEFAULT_ENABLED_LANGUAGES;
        $options = [];
        foreach (preg_split('/\s*,\s*/', trim($enabled)) as $code) {
            if ($code !== '' && isset($languages[$code])) {
                $options[$code] = $languages[$code];
            }
        }

        return $options ?: $languages;
    }

    public static function lang_label(string $code): string {
        $languages = self::all_lang_options();
        return $languages[$code] ?? $code;
    }

    private static function all_lang_options(): array {
        return [
            'en' => get_string('english', 'local_mldict'),
            'ru' => get_string('russian', 'local_mldict'),
            'zh' => get_string('chinese', 'local_mldict'),
            'ja' => get_string('japanese', 'local_mldict'),
            'de' => get_string('german', 'local_mldict'),
            'fr' => get_string('french', 'local_mldict'),
            'es' => get_string('spanish', 'local_mldict'),
        ];
    }

    public static function pos_options(): array {
        return [
            '' => get_string('choosedots'),
            'noun' => get_string('noun', 'local_mldict'),
            'verb' => get_string('verb', 'local_mldict'),
            'adjective' => get_string('adjective', 'local_mldict'),
            'adverb' => get_string('adverb', 'local_mldict'),
            'phrase' => get_string('phrase', 'local_mldict'),
            'other' => get_string('other', 'local_mldict'),
        ];
    }

    public static function cefr_options(): array {
        return [
            '' => get_string('choosedots'),
            'A1' => 'A1',
            'A2' => 'A2',
            'B1' => 'B1',
            'B2' => 'B2',
            'C1' => 'C1',
            'C2' => 'C2',
        ];
    }

    public static function search_entries(string $query = '', string $lang = '', int $limit = 100, int $offset = 0): array {
        global $DB;

        [$wheresql, $params] = self::search_filter($query, $lang);
        $sort = self::search_sort($query, $params);
        return $DB->get_records_select(self::TABLE_ENTRY, $wheresql, $params, $sort, '*', $offset, $limit);
    }

    public static function count_entries(string $query = '', string $lang = ''): int {
        global $DB;

        [$wheresql, $params] = self::search_filter($query, $lang);
        return $DB->count_records_select(self::TABLE_ENTRY, $wheresql, $params);
    }

    public static function get_entry(int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE_ENTRY, ['id' => $id], '*', MUST_EXIST);
    }

    public static function get_full_entry(int $id): \stdClass {
        global $DB;
        $entry = self::get_entry($id);
        $entry->translations = $DB->get_records(self::TABLE_TRANSLATION, ['entryid' => $id], 'targetlang ASC');
        $entry->examples = $DB->get_records(self::TABLE_EXAMPLE, ['entryid' => $id], 'examplelang ASC, id ASC');
        return $entry;
    }

    public static function get_filter_terms(int $limit = 500): array {
        global $DB;
        $sql = 'SELECT id, headword, sourcelang, partofspeech, cefrlevel
                  FROM {' . self::TABLE_ENTRY . '}
                 WHERE headword <> :empty
              ORDER BY ' . $DB->sql_length('headword') . ' DESC, headword ASC';
        return $DB->get_records_sql($sql, ['empty' => ''], 0, $limit);
    }

    public static function save_form_data(\stdClass $data): int {
        global $DB;

        $now = time();
        $record = (object)[
            'headword' => trim($data->headword),
            'sourcelang' => trim($data->sourcelang),
            'partofspeech' => $data->partofspeech ?? '',
            'cefrlevel' => $data->cefrlevel ?? '',
            'pronunciation' => $data->pronunciation ?? '',
            'phonetic' => $data->phonetic ?? '',
            'definition' => $data->definition ?? '',
            'notes' => $data->notes ?? '',
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record(self::TABLE_ENTRY, $record);
            $entryid = $record->id;
            $DB->delete_records(self::TABLE_TRANSLATION, ['entryid' => $entryid]);
            $DB->delete_records(self::TABLE_EXAMPLE, ['entryid' => $entryid]);
        } else {
            $record->timecreated = $now;
            $entryid = $DB->insert_record(self::TABLE_ENTRY, $record);
        }

        self::save_translations($entryid, $data->translations ?? '');
        self::save_examples($entryid, $data->examples ?? '');

        return $entryid;
    }

    public static function form_data_from_entry(\stdClass $entry): \stdClass {
        $full = self::get_full_entry((int)$entry->id);
        $data = clone $full;
        $translationlines = [];
        foreach ($full->translations as $translation) {
            $translationlines[] = $translation->targetlang . '=' . $translation->translation;
        }
        $examplelines = [];
        foreach ($full->examples as $example) {
            $line = $example->examplelang . '=' . $example->sentence;
            if (!empty($example->translation)) {
                $line .= '|' . $example->translation;
            }
            $examplelines[] = $line;
        }
        $data->translations = implode("\n", $translationlines);
        $data->examples = implode("\n", $examplelines);
        return $data;
    }

    public static function delete_entry(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE_EXAMPLE, ['entryid' => $id]);
        $DB->delete_records(self::TABLE_TRANSLATION, ['entryid' => $id]);
        $DB->delete_records(self::TABLE_ENTRY, ['id' => $id]);
    }

    public static function import_csv_text(string $csv): int {
        $count = 0;
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (!$lines) {
            return 0;
        }

        $first = true;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            if ($first) {
                $first = false;
                if (isset($row[0]) && strtolower(trim($row[0])) === 'headword') {
                    continue;
                }
            }
            $headword = trim($row[0] ?? '');
            if ($headword === '') {
                continue;
            }
            $data = (object)[
                'headword' => $headword,
                'sourcelang' => trim($row[1] ?? 'en') ?: 'en',
                'partofspeech' => trim($row[2] ?? ''),
                'cefrlevel' => trim($row[3] ?? ''),
                'definition' => trim($row[4] ?? ''),
                'translations' => self::pipe_pairs_to_lines($row[5] ?? ''),
                'examples' => self::pipe_pairs_to_lines($row[6] ?? ''),
                'pronunciation' => '',
                'phonetic' => '',
                'notes' => '',
            ];
            self::save_form_data($data);
            $count++;
        }
        return $count;
    }

    public static function render_entry_html(\stdClass $entry): string {
        $entry = isset($entry->translations) ? $entry : self::get_full_entry((int)$entry->id);
        $out = \html_writer::tag('h3', format_string($entry->headword));
        $meta = [];
        if (!empty($entry->sourcelang)) {
            $meta[] = s(self::lang_label($entry->sourcelang));
        }
        if (!empty($entry->partofspeech)) {
            $meta[] = s($entry->partofspeech);
        }
        if (!empty($entry->cefrlevel)) {
            $meta[] = s($entry->cefrlevel);
        }
        if ($meta) {
            $out .= \html_writer::div(implode(' · ', $meta), 'local-mldict-meta');
        }
        if (!empty($entry->pronunciation) || !empty($entry->phonetic)) {
            $out .= \html_writer::div(s(trim($entry->pronunciation . ' ' . $entry->phonetic)), 'local-mldict-pronunciation');
        }
        if (!empty($entry->definition)) {
            $out .= \html_writer::tag('p', format_text($entry->definition, FORMAT_PLAIN));
        }
        if (!empty($entry->translations)) {
            $items = '';
            foreach ($entry->translations as $translation) {
                $items .= \html_writer::tag('li', \html_writer::tag('strong', s(self::lang_label($translation->targetlang))) . ': ' . format_text($translation->translation, FORMAT_PLAIN));
            }
            $out .= \html_writer::tag('h4', get_string('translations', 'local_mldict')) . \html_writer::tag('ul', $items);
        }
        if (!empty($entry->examples)) {
            $items = '';
            foreach ($entry->examples as $example) {
                $text = \html_writer::tag('strong', s(self::lang_label($example->examplelang))) . ': ' . format_text($example->sentence, FORMAT_PLAIN);
                if (!empty($example->translation)) {
                    $text .= \html_writer::div(format_text($example->translation, FORMAT_PLAIN), 'local-mldict-example-translation');
                }
                $items .= \html_writer::tag('li', $text);
            }
            $out .= \html_writer::tag('h4', get_string('examples', 'local_mldict')) . \html_writer::tag('ul', $items);
        }
        return \html_writer::div($out, 'local-mldict-entry');
    }

    private static function save_translations(int $entryid, string $text): void {
        global $DB;
        $now = time();
        foreach (self::parse_key_value_lines($text) as $lang => $translation) {
            $record = (object)[
                'entryid' => $entryid,
                'targetlang' => $lang,
                'translation' => $translation,
                'definition' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(self::TABLE_TRANSLATION, $record);
        }
    }

    private static function save_examples(int $entryid, string $text): void {
        global $DB;
        $now = time();
        foreach (self::parse_example_lines($text) as $example) {
            $record = (object)[
                'entryid' => $entryid,
                'examplelang' => $example['lang'],
                'sentence' => $example['sentence'],
                'translation' => $example['translation'],
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(self::TABLE_EXAMPLE, $record);
        }
    }

    private static function parse_key_value_lines(string $text): array {
        $items = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key !== '' && $value !== '') {
                $items[$key] = $value;
            }
        }
        return $items;
    }

    private static function search_filter(string $query = '', string $lang = ''): array {
        global $DB;

        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = $DB->sql_like('headword', ':query', false, false);
            $params['query'] = '%' . $DB->sql_like_escape($query) . '%';
        }
        if ($lang !== '') {
            $where[] = 'sourcelang = :sourcelang';
            $params['sourcelang'] = $lang;
        }

        return [$where ? implode(' AND ', $where) : '1=1', $params];
    }

    private static function search_sort(string $query, array &$params): string {
        global $DB;

        if ($query === '') {
            return 'headword ASC, sourcelang ASC';
        }

        $params['queryexact'] = $query;
        $params['querystart'] = $DB->sql_like_escape($query) . '%';
        $params['queryanywhere'] = '%' . $DB->sql_like_escape($query) . '%';
        $params['queryposition'] = $query;
        $position = $DB->sql_position('LOWER(:queryposition)', 'LOWER(headword)');

        return "CASE
                    WHEN " . $DB->sql_equal('headword', ':queryexact', false) . " THEN 0
                    WHEN " . $DB->sql_like('headword', ':querystart', false, false) . " THEN 1
                    WHEN " . $DB->sql_like('headword', ':queryanywhere', false, false) . " THEN 2
                    ELSE 3
                END ASC, {$position} ASC, " . $DB->sql_length('headword') . " ASC, headword ASC, sourcelang ASC";
    }

    private static function parse_example_lines(string $text): array {
        $items = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$lang, $rest] = explode('=', $line, 2);
            $translation = '';
            if (strpos($rest, '|') !== false) {
                [$sentence, $translation] = explode('|', $rest, 2);
            } else {
                $sentence = $rest;
            }
            $lang = trim($lang);
            $sentence = trim($sentence);
            if ($lang !== '' && $sentence !== '') {
                $items[] = [
                    'lang' => $lang,
                    'sentence' => $sentence,
                    'translation' => trim($translation),
                ];
            }
        }
        return $items;
    }

    private static function pipe_pairs_to_lines(string $text): string {
        $parts = array_filter(array_map('trim', explode('|', $text)));
        return implode("\n", $parts);
    }
}
