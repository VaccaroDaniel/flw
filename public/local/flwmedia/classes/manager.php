<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access and validation for FLW media practice.
 *
 * @package    local_flwmedia
 */
class manager {
    /** @var string[] Supported practice modes. */
    public const MODES = ['watch', 'listen', 'speak', 'read', 'dictate'];

    /** @var array Default category labels used until teachers create their own. */
    public const DEFAULT_CATEGORIES = [
        'unit_watch' => 'Unit Watch',
        'model_dialogue' => 'Dialogue',
        'vocabulary' => 'Vocabulary',
        'model_sentence' => 'Sentence',
        'pronunciation' => 'Pronunciation',
        'story' => 'Story',
        'project' => 'Project',
        'dictation' => 'Dictation',
        'reading' => 'Reading',
        'review' => 'Review',
    ];

    /**
     * Return paginated media items for a language.
     *
     * @param array $params Validated request parameters.
     * @return array
     */
    public static function get_items(array $params): array {
        global $DB;

        self::validate_practice_view();

        $page = max(1, (int)$params['page']);
        $perpage = min(48, max(1, (int)$params['perpage']));
        $offset = ($page - 1) * $perpage;
        $language = self::normalize_language((string)($params['language'] ?? 'en'));
        $courseid = (int)($params['courseid'] ?? 0);

        $where = [
            'visible = 1',
            'lang = :lang',
        ];
        $sqlparams = [
            'lang' => $language,
        ];

        if ($courseid > 0) {
            $where[] = 'courseid = :courseid';
            $sqlparams['courseid'] = $courseid;
        }

        if (!empty($params['unitcode'])) {
            $where[] = 'unitcode = :unitcode';
            $sqlparams['unitcode'] = $params['unitcode'];
        }

        if (!empty($params['mode']) && in_array($params['mode'], self::MODES, true)) {
            $where[] = 'mode = :mode';
            $sqlparams['mode'] = $params['mode'];
        }

        if (!empty($params['category']) && $params['category'] !== 'all') {
            $where[] = 'category = :category';
            $sqlparams['category'] = $params['category'];
        }

        $search = trim((string)($params['search'] ?? ''));
        if ($search !== '') {
            $likes = [];
            foreach (['title', 'transcript', 'expectedtext', 'kptags'] as $field) {
                $name = 'search' . $field;
                $likes[] = $DB->sql_like($field, ':' . $name, false, false);
                $sqlparams[$name] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }

        $wheresql = implode(' AND ', $where);
        $total = (int)$DB->count_records_select('local_flwmedia_items', $wheresql, $sqlparams);
        $records = $DB->get_records_select(
            'local_flwmedia_items',
            $wheresql,
            $sqlparams,
            'sortorder ASC, id ASC',
            '*',
            $offset,
            $perpage
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_item($record);
        }

        return [
            'items' => $items,
            'categories' => self::get_categories($language, (string)($params['mode'] ?? '')),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'pages' => max(1, (int)ceil($total / $perpage)),
        ];
    }

    /**
     * Return visible categories for a language and optional mode.
     *
     * @param string $language Language code.
     * @param string $mode Optional practice mode.
     * @return array
     */
    public static function get_categories(string $language, string $mode = ''): array {
        global $DB;

        $language = self::normalize_language($language);
        $mode = in_array($mode, self::MODES, true) ? $mode : '';
        $categories = [];

        if ($DB->get_manager()->table_exists('local_flwmedia_categories')) {
            $where = ['lang = :lang', 'visible = 1'];
            $params = ['lang' => $language];
            if ($mode !== '') {
                $where[] = '(mode = :mode OR mode = :emptymode)';
                $params['mode'] = $mode;
                $params['emptymode'] = '';
            }
            $records = $DB->get_records_select(
                'local_flwmedia_categories',
                implode(' AND ', $where),
                $params,
                'sortorder ASC, name ASC',
                'categorykey, name, mode, sortorder'
            );
            foreach ($records as $record) {
                $categories[$record->categorykey] = [
                    'key' => $record->categorykey,
                    'label' => $record->name,
                    'mode' => $record->mode,
                ];
            }
        }

        foreach (self::DEFAULT_CATEGORIES as $key => $label) {
            if (!isset($categories[$key])) {
                $categories[$key] = [
                    'key' => $key,
                    'label' => self::default_category_label($key, $label),
                    'mode' => '',
                ];
            }
        }

        $where = ['lang = :lang', 'visible = 1', "category <> ''"];
        $params = ['lang' => $language];
        if ($mode !== '') {
            $where[] = 'mode = :mode';
            $params['mode'] = $mode;
        }
        $records = $DB->get_records_sql(
            'SELECT DISTINCT category
               FROM {local_flwmedia_items}
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY category ASC',
            $params
        );
        foreach ($records as $record) {
            if (!isset($categories[$record->category])) {
                $categories[$record->category] = [
                    'key' => $record->category,
                    'label' => self::label_from_key($record->category),
                    'mode' => $mode,
                ];
            }
        }

        return array_values($categories);
    }

    /**
     * Convert a category key into a readable label.
     *
     * @param string $key Category key.
     * @return string
     */
    public static function label_from_key(string $key): string {
        $label = str_replace(['_', '-'], ' ', trim($key));
        return $label === '' ? '' : \core_text::strtotitle($label);
    }

    /**
     * Translate a built-in category key.
     *
     * @param string $key Category key.
     * @param string $fallback Fallback label.
     * @return string
     */
    protected static function default_category_label(string $key, string $fallback): string {
        $stringkey = 'category' . str_replace('_', '', $key);
        return get_string_manager()->string_exists($stringkey, 'local_flwmedia')
            ? get_string($stringkey, 'local_flwmedia')
            : $fallback;
    }

    /**
     * Save progress for one media item.
     *
     * @param array $params Validated request parameters.
     * @return array
     */
    public static function save_progress(array $params): array {
        global $DB, $USER;

        $item = self::require_item(
            (int)$params['itemid'],
            $params['mode'],
            (int)($params['courseid'] ?? 0),
            (string)($params['language'] ?? '')
        );
        self::validate_practice_view();

        $now = time();
        $existing = $DB->get_record('local_flwmedia_progress', [
            'userid' => $USER->id,
            'itemid' => $item->id,
        ]);

        $data = (object)[
            'userid' => $USER->id,
            'courseid' => $item->courseid,
            'itemid' => $item->id,
            'mode' => $item->mode,
            'secondsdone' => max(0, (int)$params['secondsdone']),
            'percentdone' => min(100, max(0, (int)$params['percentdone'])),
            'completed' => !empty($params['completed']) ? 1 : 0,
            'score' => self::nullable_float($params['score'] ?? null),
            'attemptjson' => self::clean_json_string($params['attemptjson'] ?? ''),
            'timemodified' => $now,
        ];

        if ($existing) {
            $data->id = $existing->id;
            $data->timecreated = $existing->timecreated;
            $DB->update_record('local_flwmedia_progress', $data);
            $id = (int)$existing->id;
        } else {
            $data->timecreated = $now;
            $id = (int)$DB->insert_record('local_flwmedia_progress', $data);
        }

        return [
            'success' => true,
            'progressid' => $id,
            'completed' => (int)$data->completed,
        ];
    }

    /**
     * Save speaking, reading, or dictation attempts.
     *
     * @param array $params Validated request parameters.
     * @param string $mode Expected mode.
     * @return array
     */
    public static function save_attempt(array $params, string $mode): array {
        global $DB, $USER;

        $item = self::require_item(
            (int)$params['itemid'],
            $mode,
            (int)($params['courseid'] ?? 0),
            (string)($params['language'] ?? '')
        );
        self::validate_practice_view();

        $score = self::nullable_float($params['score'] ?? null);
        $feedback = (string)($params['feedback'] ?? '');
        if ($mode === 'dictate') {
            $check = self::score_dictation((string)($params['response'] ?? ''), (string)$item->expectedtext);
            if ($score === null) {
                $score = $check['score'];
            }
            if ($feedback === '') {
                $feedback = json_encode($check);
            }
        }

        $now = time();
        $attempt = (object)[
            'userid' => $USER->id,
            'courseid' => $item->courseid,
            'itemid' => $item->id,
            'mode' => $mode,
            'response' => (string)($params['response'] ?? ''),
            'transcript' => (string)($params['transcript'] ?? ''),
            'score' => $score,
            'feedback' => $feedback,
            'audiofileurl' => (string)($params['audiofileurl'] ?? ''),
            'attemptjson' => self::clean_json_string($params['attemptjson'] ?? ''),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $attemptid = (int)$DB->insert_record('local_flwmedia_attempts', $attempt);

        $progress = self::save_progress([
            'courseid' => $item->courseid,
            'itemid' => $item->id,
            'mode' => $mode,
            'percentdone' => 100,
            'secondsdone' => 0,
            'completed' => 1,
            'score' => $score,
            'attemptjson' => $attempt->attemptjson,
        ]);

        return [
            'success' => true,
            'attemptid' => $attemptid,
            'progressid' => $progress['progressid'],
            'score' => $score ?? 0,
            'feedback' => $feedback,
        ];
    }

    /**
     * Validate view access for language-level Practice.
     *
     * @return \context_system
     */
    public static function validate_practice_view(): \context_system {
        $context = \context_system::instance();
        require_login();
        if (isloggedin() && !isguestuser()) {
            return $context;
        }
        require_capability('local/flwmedia:view', $context);
        return $context;
    }

    /**
     * Legacy course validation wrapper.
     *
     * @param int $courseid Course id.
     * @return \context_system
     */
    public static function validate_course_view(int $courseid): \context_system {
        return self::validate_practice_view();
    }

    /**
     * Require a media item in the expected language and mode.
     *
     * @param int $itemid Item id.
     * @param string $mode Expected mode.
     * @param int $courseid Optional legacy course filter.
     * @param string $language Optional language filter.
     * @return \stdClass
     */
    public static function require_item(int $itemid, string $mode, int $courseid = 0, string $language = ''): \stdClass {
        global $DB;

        if (!in_array($mode, self::MODES, true)) {
            throw new \invalid_parameter_exception(get_string('unsupportedmode', 'local_flwmedia'));
        }

        $conditions = [
            'id' => $itemid,
            'mode' => $mode,
        ];
        if ($courseid > 0) {
            $conditions['courseid'] = $courseid;
        }
        if (trim($language) !== '') {
            $conditions['lang'] = self::normalize_language($language);
        }

        $item = $DB->get_record('local_flwmedia_items', $conditions, '*', MUST_EXIST);

        if (empty($item->visible)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/flwmedia:view',
                'nopermissions',
                ''
            );
        }

        return $item;
    }

    /**
     * Normalize language codes used by FLW media.
     *
     * @param string $language Raw language code.
     * @return string
     */
    public static function normalize_language(string $language): string {
        $language = \core_text::strtolower(trim($language));
        $language = str_replace('-', '_', $language);
        if (strpos($language, '_') !== false) {
            $parts = explode('_', $language);
            $language = $parts[0];
        }
        return $language !== '' ? $language : 'en';
    }

    /**
     * Normalize and score dictation text.
     *
     * @param string $response Learner response.
     * @param string $expected Expected answer.
     * @return array
     */
    public static function score_dictation(string $response, string $expected): array {
        $normalizedresponse = self::normalize_text($response);
        $normalizedexpected = self::normalize_text($expected);

        if ($normalizedexpected === '') {
            return [
                'score' => 0.0,
                'exact' => false,
                'normalizedmatch' => false,
                'wordoverlap' => 0.0,
                'expected' => $expected,
            ];
        }

        if ($normalizedresponse === $normalizedexpected) {
            return [
                'score' => 100.0,
                'exact' => trim($response) === trim($expected),
                'normalizedmatch' => true,
                'wordoverlap' => 100.0,
                'expected' => $expected,
            ];
        }

        $expectedwords = array_filter(explode(' ', $normalizedexpected));
        $responsewords = array_filter(explode(' ', $normalizedresponse));
        $matches = count(array_intersect($expectedwords, $responsewords));
        $overlap = count($expectedwords) > 0 ? ($matches / count($expectedwords)) * 100 : 0;

        return [
            'score' => round($overlap, 2),
            'exact' => false,
            'normalizedmatch' => false,
            'wordoverlap' => round($overlap, 2),
            'expected' => $expected,
        ];
    }

    /**
     * Normalize text for dictation checks.
     *
     * @param string $text Raw text.
     * @return string
     */
    public static function normalize_text(string $text): string {
        $text = \core_text::strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text ?? '');
    }

    /**
     * Format a DB record for external return and templates.
     *
     * @param \stdClass $record DB record.
     * @return array
     */
    private static function format_item(\stdClass $record): array {
        $data = [];
        foreach ([
            'id', 'courseid', 'unitcode', 'lessoncode', 'mode', 'category', 'title', 'description',
            'mediaurl', 'posterurl', 'subtitleurl', 'transcript', 'readtext', 'expectedtext',
            'duration', 'lang', 'cefr', 'kptags', 'sortorder', 'visible',
        ] as $field) {
            $data[$field] = $record->{$field} ?? '';
        }

        $data['id'] = (int)$data['id'];
        $data['courseid'] = (int)$data['courseid'];
        $data['duration'] = (int)$data['duration'];
        $data['sortorder'] = (int)$data['sortorder'];
        $data['visible'] = (int)$data['visible'];
        $data['hasmediaurl'] = $data['mediaurl'] !== '' ? 1 : 0;
        $data['hasposterurl'] = $data['posterurl'] !== '' ? 1 : 0;
        $data['hassubtitleurl'] = $data['subtitleurl'] !== '' ? 1 : 0;
        $data['hastranscript'] = $data['transcript'] !== '' ? 1 : 0;
        $data['hasreadtext'] = $data['readtext'] !== '' ? 1 : 0;
        $data['hasexpectedtext'] = $data['expectedtext'] !== '' ? 1 : 0;

        return $data;
    }

    /**
     * Clean JSON text without requiring it to be valid yet.
     *
     * @param mixed $json JSON string.
     * @return string
     */
    private static function clean_json_string($json): string {
        if ($json === null || $json === '') {
            return '';
        }

        return (string)$json;
    }

    /**
     * Normalize optional score values.
     *
     * @param mixed $value Score value.
     * @return float|null
     */
    private static function nullable_float($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }
}
