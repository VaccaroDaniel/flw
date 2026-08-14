<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mldict\local;

defined('MOODLE_INTERNAL') || die();

class dictionary {
    public const TABLE_ENTRY = 'local_mldict_entry';
    public const TABLE_TRANSLATION = 'local_mldict_translation';
    public const TABLE_EXAMPLE = 'local_mldict_example';
    private const DEFAULT_ENABLED_LANGUAGES = 'en,ru,zh,de,ja,fr,es';
    private const DEFAULT_STARTER_LIMIT = 12;
    private const DEFAULT_FILTER_LIMIT = 500;
    private const FILTER_LIMIT_MAX = 800;

    private static function cache_store(): ?\cache {
        static $store = [];
        if (array_key_exists('instance', $store)) {
            return $store['instance'];
        }

        try {
            $store['instance'] = \cache::make('local_mldict', 'dictionary_payload');
            return $store['instance'];
        } catch (\Throwable $exception) {
            $store['instance'] = null;
            return null;
        }
    }

    private static function clear_payload_cache(): void {
        static $invalidated = false;
        if ($invalidated) {
            return;
        }

        $store = self::cache_store();
        if ($store) {
            $store->purge();
            $invalidated = true;
        }
    }

    private static function table_exists(string $tablename): bool {
        static $tablecache = [];
        if (array_key_exists($tablename, $tablecache)) {
            return $tablecache[$tablename];
        }

        global $DB;
        $tablecache[$tablename] = $DB->get_manager()->table_exists($tablename);
        return $tablecache[$tablename];
    }

    private static function normalize_limit(int $value, int $default, int $max): int {
        if ($value <= 0) {
            return $default;
        }

        return max(1, min($value, $max));
    }

    private static function filter_limit(?int $limit = null): int {
        if ($limit === null) {
            $configured = (int)get_config('filter_mldict', 'maxterms');
            $limit = $configured > 0 ? $configured : self::DEFAULT_FILTER_LIMIT;
        }

        return self::normalize_limit($limit, self::DEFAULT_FILTER_LIMIT, self::FILTER_LIMIT_MAX);
    }

    /**
     * Pre-warms all startup payload caches used by dictionary homepage and filter output.
     *
     * This is intentionally lightweight and idempotent by relying on per-request cache keys.
     */
    public static function refresh_payload_cache(int $filterlimit = self::DEFAULT_FILTER_LIMIT): void {
        $filterlimit = self::filter_limit($filterlimit);
        self::get_payload_stats();

        foreach (array_keys(self::lang_options()) as $code) {
            self::get_startup_starter_words($code, self::DEFAULT_STARTER_LIMIT);
        }
        if (self::normalise_lang_code('zh_cn') === 'zh') {
            self::get_startup_starter_words('zh_cn', self::DEFAULT_STARTER_LIMIT);
        }

        self::get_filter_payload_cached($filterlimit, false);
        self::get_filter_payload_cached($filterlimit, true);
    }

    public static function get_payload_stats(): array {
        global $DB;

        $store = self::cache_store();
        $cachekey = 'payload_stats';
        if ($store) {
            $cached = $store->get($cachekey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $stats = [
            'counts' => [],
            'generated' => time(),
        ];
        if (!self::table_exists(self::TABLE_ENTRY)) {
            if ($store) {
                $store->set($cachekey, $stats);
            }
            return $stats;
        }

        $rows = $DB->get_records_sql(
            "SELECT sourcelang, COUNT(1) AS total
               FROM {" . self::TABLE_ENTRY . "}
              WHERE headword <> :empty
           GROUP BY sourcelang",
            ['empty' => '']
        );
        foreach ($rows as $row) {
            $stats['counts'][(string)$row->sourcelang] = (int)$row->total;
        }
        if (isset($stats['counts']['zh_cn']) && empty($stats['counts']['zh'])) {
            $stats['counts']['zh'] = (int)$stats['counts']['zh_cn'];
        }

        if ($store) {
            $store->set($cachekey, $stats);
        }
        return $stats;
    }

    public static function get_language_counts(): array {
        $stats = self::get_payload_stats();
        return $stats['counts'] ?? [];
    }

    public static function get_startup_starter_words(string $lang, int $limit = self::DEFAULT_STARTER_LIMIT): array {
        $limit = self::normalize_limit($limit, self::DEFAULT_STARTER_LIMIT, 120);
        $store = self::cache_store();
        $normalized = self::normalise_lang_code($lang);
        $storekeylang = $normalized !== '' ? $normalized : 'all';
        $cachekey = 'startup_words_' . $storekeylang . '_' . $limit;
        if ($store) {
            $cached = $store->get($cachekey);
            if ($cached !== false) {
                $result = [];
                if (is_array($cached)) {
                    foreach ($cached as $record) {
                        $item = (object)$record;
                        $result[] = $item;
                    }
                }
                return $result;
            }
        }

        $params = ['empty' => ''];
        $where = 'headword <> :empty';
        if ($normalized !== '') {
            if ($normalized === 'zh' && $storekeylang !== 'all') {
                $where .= ' AND sourcelang = :sourcelang';
                $params['sourcelang'] = 'zh';
            } else if ($normalized !== '') {
                $where .= ' AND sourcelang = :sourcelang';
                $params['sourcelang'] = $normalized;
            }
        }

        global $DB;
        if (!self::table_exists(self::TABLE_ENTRY)) {
            if ($store) {
                $store->set($cachekey, []);
            }
            return [];
        }

        $sql = 'SELECT id, headword, sourcelang, partofspeech, cefrlevel
                  FROM {' . self::TABLE_ENTRY . '}
                 WHERE ' . $where . '
              ORDER BY CASE
                           WHEN cefrlevel = :a1 THEN 0
                           WHEN cefrlevel = :a2 THEN 1
                           WHEN cefrlevel = :emptycefr THEN 3
                           ELSE 2
                       END ASC,
                       ' . $DB->sql_length('headword') . ' DESC,
                       headword ASC';
        $params['a1'] = 'A1';
        $params['a2'] = 'A2';
        $params['emptycefr'] = '';

        $rows = $DB->get_records_sql($sql, $params, 0, $limit);
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->id,
                'headword' => (string)$row->headword,
                'sourcelang' => (string)$row->sourcelang,
                'partofspeech' => (string)$row->partofspeech,
                'cefrlevel' => (string)$row->cefrlevel,
            ];
        }

        if (empty($items) && $normalized === 'zh') {
            $fallbackwhere = 'headword <> :empty_fallback AND sourcelang = :fallbacklang';
            $fallbackrows = $DB->get_records_sql(
                'SELECT id, headword, sourcelang, partofspeech, cefrlevel
                   FROM {' . self::TABLE_ENTRY . '}
                  WHERE ' . $fallbackwhere . '
               ORDER BY CASE
                            WHEN cefrlevel = :a1b THEN 0
                            WHEN cefrlevel = :a2b THEN 1
                            WHEN cefrlevel = :emptycefrob THEN 3
                            ELSE 2
                        END ASC,
                        ' . $DB->sql_length('headword') . ' DESC,
                        headword ASC',
                [
                    'empty_fallback' => '',
                    'fallbacklang' => 'zh_cn',
                    'a1b' => 'A1',
                    'a2b' => 'A2',
                    'emptycefrob' => '',
                ],
                0,
                $limit
            );
            foreach ($fallbackrows as $row) {
                $items[] = [
                    'id' => (int)$row->id,
                    'headword' => (string)$row->headword,
                    'sourcelang' => (string)$row->sourcelang,
                    'partofspeech' => (string)$row->partofspeech,
                    'cefrlevel' => (string)$row->cefrlevel,
                ];
            }
        }

        $payload = array_slice($items, 0, $limit);
        if ($store) {
            $store->set($cachekey, $payload);
        }
        $result = [];
        foreach ($payload as $record) {
            $result[] = (object)$record;
        }
        return $result;
    }

    public static function get_filter_terms_cached(int $limit = 500): array {
        $limit = self::filter_limit($limit);
        $store = self::cache_store();
        $cachekey = 'filter_terms_' . $limit;
        if ($store) {
            $cached = $store->get($cachekey);
            if ($cached !== false) {
                return array_map(static function($record): \stdClass {
                    return (object)$record;
                }, (array)$cached);
            }
        }

        $rows = self::get_filter_terms_raw($limit);
        $payload = [];
        foreach ($rows as $row) {
            $payload[] = [
                'id' => (int)$row->id,
                'headword' => (string)$row->headword,
                'sourcelang' => (string)$row->sourcelang,
                'partofspeech' => (string)$row->partofspeech,
                'cefrlevel' => (string)$row->cefrlevel,
            ];
        }
        if ($store) {
            $store->set($cachekey, $payload);
        }
        return array_map(static function($record): \stdClass {
            return (object)$record;
        }, $payload);
    }

    /**
     * Return cached compiled filter payload (regex + URLs + title metadata) to avoid
     * rebuilding heavy matching tables on each request.
     *
     * @param int $limit
     * @param bool $casesensitive
     * @return array<int, array<string, string>>
     */
    public static function get_filter_payload_cached(int $limit = self::DEFAULT_FILTER_LIMIT, bool $casesensitive = false): array {
        $limit = self::filter_limit($limit);
        $store = self::cache_store();
        $cachekey = 'filter_payload_' . $limit . '_' . ($casesensitive ? 'cs' : 'ic');

        if ($store) {
            $cached = $store->get($cachekey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $terms = self::get_filter_terms_cached($limit);
        $payload = [];
        foreach ($terms as $term) {
            $word = trim((string)($term->headword ?? ''));
            if ($word === '' || \core_text::strlen($word) < 2) {
                continue;
            }

            $payload[] = [
                'pattern' => '/(?<![\\p{L}\\p{N}_])(' . preg_quote($word, '/') . ')(?![\\p{L}\\p{N}_])/' . ($casesensitive ? 'u' : 'iu'),
                'url' => (new \moodle_url('/local/mldict/view.php', ['id' => (int)$term->id]))->out(false),
                'title' => implode(' · ', array_filter([
                    (string)($term->sourcelang ?? ''),
                    (string)($term->partofspeech ?? ''),
                    (string)($term->cefrlevel ?? ''),
                ])),
            ];
        }

        if ($store) {
            $store->set($cachekey, $payload);
        }

        return $payload;
    }

    private static function get_filter_terms_raw(int $limit = 500): array {
        global $DB;

        if (!self::table_exists(self::TABLE_ENTRY)) {
            return [];
        }

        $sql = 'SELECT id, headword, sourcelang, partofspeech, cefrlevel
                  FROM {' . self::TABLE_ENTRY . '}
                 WHERE headword <> :empty
              ORDER BY ' . $DB->sql_length('headword') . ' DESC, headword ASC';
        return $DB->get_records_sql($sql, ['empty' => ''], 0, $limit);
    }

    public static function lang_options(): array {
        $languages = self::all_lang_options();
        $enabled = get_config('local_mldict', 'enabledlanguages') ?: self::DEFAULT_ENABLED_LANGUAGES;
        $enabledcodes = array_flip(array_filter(preg_split('/\s*,\s*/', trim($enabled))));
        $options = [];
        foreach ($languages as $code => $label) {
            if (isset($enabledcodes[$code])) {
                $options[$code] = $languages[$code];
            }
        }

        return $options ?: $languages;
    }

    public static function lang_label(string $code): string {
        $languages = self::all_lang_options();
        return $languages[$code] ?? $code;
    }

    public static function normalise_lang_code(string $code): string {
        $code = strtolower(trim($code));
        $code = str_replace('-', '_', $code);
        if ($code === 'zh_cn') {
            $code = 'zh';
        }

        return isset(self::lang_options()[$code]) ? $code : '';
    }

    public static function preferred_learning_language(): string {
        return self::normalise_lang_code($_COOKIE['flw_learning_language'] ?? '');
    }

    private static function all_lang_options(): array {
        return [
            'en' => get_string('english', 'local_mldict'),
            'ru' => get_string('russian', 'local_mldict'),
            'zh' => get_string('chinese', 'local_mldict'),
            'de' => get_string('german', 'local_mldict'),
            'ja' => get_string('japanese', 'local_mldict'),
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

    public static function count_distinct_headwords(string $lang): int {
        $counts = self::get_language_counts();
        $count = (int)($counts[$lang] ?? 0);

        if ($count > 0) {
            return $count;
        }

        if ($lang === 'zh' && isset($counts['zh_cn'])) {
            return (int)$counts['zh_cn'];
        }

        return 0;
    }

    public static function starter_entries(string $lang, int $limit = 12): array {
        return self::get_startup_starter_words($lang, $limit);
    }

    public static function display_text(string $text, ?string $language = null): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $language = self::normalise_lang_code($language ?? '') ?: (self::preferred_learning_language() ?: 'en');
        $entries = [];
        if (preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $entries[] = ['lang' => $match[1], 'text' => $match[2]];
            }
        }
        if (empty($entries) && preg_match_all('/<span\b(?=[^>]*\bclass=(["\'])(?:(?!\1).)*\bmultilang\b(?:(?!\1).)*\1)(?=[^>]*\blang=(["\'])([^"\']+)\2)[^>]*>(.*?)<\/span>/is', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $entries[] = ['lang' => $match[3], 'text' => $match[4]];
            }
        }

        if (!$entries) {
            return $text;
        }

        foreach ([$language, 'en'] as $wanted) {
            foreach ($entries as $entry) {
                $entrylangs = preg_split('/[\s,;|]+/', $entry['lang'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($entrylangs as $entrylang) {
                    if (self::normalise_lang_code($entrylang) === $wanted) {
                        return trim($entry['text']);
                    }
                }
            }
        }

        return trim($entries[0]['text']);
    }

    public static function display_plain_text(string $text, ?string $language = null): string {
        return format_text(self::display_text($text, $language), FORMAT_PLAIN);
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
        return self::get_filter_terms_cached($limit);
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
        self::clear_payload_cache();

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
        self::clear_payload_cache();
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
        $meta = [];
        if (!empty($entry->sourcelang)) {
            $meta[] = \html_writer::span(s(self::lang_label($entry->sourcelang)), 'local-mldict-chip');
        }
        if (!empty($entry->partofspeech)) {
            $meta[] = \html_writer::span(s($entry->partofspeech), 'local-mldict-chip local-mldict-chip-muted');
        }
        if (!empty($entry->cefrlevel)) {
            $meta[] = \html_writer::span(s($entry->cefrlevel), 'local-mldict-chip local-mldict-chip-level');
        }
        $language = self::normalise_lang_code((string)($entry->sourcelang ?? '')) ?: (self::preferred_learning_language() ?: 'en');
        $header = \html_writer::tag('h2', format_string(self::display_text($entry->headword, $language)), ['class' => 'local-mldict-headword']);
        if ($meta) {
            $header .= \html_writer::div(implode('', $meta), 'local-mldict-meta');
        }

        $out = \html_writer::div($header, 'local-mldict-entry-header');

        $pronunciation = [];
        if (!empty($entry->pronunciation) || !empty($entry->phonetic)) {
            if (!empty($entry->pronunciation)) {
                $pronunciation[] = \html_writer::span(s($entry->pronunciation), 'local-mldict-pron-value');
            }
            if (!empty($entry->phonetic)) {
                $pronunciation[] = \html_writer::span(s($entry->phonetic), 'local-mldict-ipa');
            }
            $out .= \html_writer::div(
                \html_writer::span(get_string('pronunciation', 'local_mldict'), 'local-mldict-section-label') .
                implode('', $pronunciation),
                'local-mldict-pronunciation'
            );
        }

        if (!empty($entry->definition)) {
            $out .= \html_writer::div(
                \html_writer::span(get_string('definition', 'local_mldict'), 'local-mldict-section-label') .
                \html_writer::div(self::display_plain_text($entry->definition, $language), 'local-mldict-definition-text'),
                'local-mldict-definition'
            );
        }

        if (!empty($entry->translations)) {
            $items = '';
            foreach ($entry->translations as $translation) {
                $items .= \html_writer::tag('li',
                    \html_writer::span(s(self::lang_label($translation->targetlang)), 'local-mldict-translation-lang') .
                    \html_writer::span(self::display_plain_text($translation->translation, $translation->targetlang), 'local-mldict-translation-text')
                );
            }
            $out .= \html_writer::div(
                \html_writer::tag('h3', get_string('translations', 'local_mldict')) .
                \html_writer::tag('ul', $items, ['class' => 'local-mldict-translation-grid']),
                'local-mldict-section'
            );
        }

        if (!empty($entry->examples)) {
            $items = '';
            foreach ($entry->examples as $example) {
                $text = \html_writer::span(s(self::lang_label($example->examplelang)), 'local-mldict-example-lang') .
                    \html_writer::div(self::display_plain_text($example->sentence, $example->examplelang), 'local-mldict-example-sentence');
                if (!empty($example->translation)) {
                    $text .= \html_writer::div(self::display_plain_text($example->translation, $language), 'local-mldict-example-translation');
                }
                $items .= \html_writer::tag('li', $text);
            }
            $out .= \html_writer::div(
                \html_writer::tag('h3', get_string('examples', 'local_mldict')) .
                \html_writer::tag('ul', $items, ['class' => 'local-mldict-example-list']),
                'local-mldict-section'
            );
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
