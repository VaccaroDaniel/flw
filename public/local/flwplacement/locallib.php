<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Require access for taking a placement test.
 *
 * The dashboard links to the site-level placement test, where ordinary
 * authenticated learners may not hold an explicit course role yet.
 *
 * @param context $context Current page context.
 */
function local_flwplacement_require_take_access(context $context): void {
    if (has_capability('local/flwplacement:take', $context)) {
        return;
    }

    if (isloggedin() && !isguestuser()) {
        return;
    }

    require_capability('local/flwplacement:take', $context);
}

/**
 * Return a stable FLW learning-language profile value.
 *
 * @param string $language Language code or profile value.
 * @return string
 */
function local_flwplacement_language_profile_value(string $language): string {
    $map = [
        'en' => 'english',
        'english' => 'english',
        'ru' => 'russian',
        'russian' => 'russian',
        'zh' => 'chinese',
        'zh_cn' => 'chinese',
        'chinese' => 'chinese',
        'de' => 'german',
        'german' => 'german',
        'ja' => 'japanese',
        'japanese' => 'japanese',
        'fr' => 'french',
        'french' => 'french',
        'es' => 'spanish',
        'spanish' => 'spanish',
    ];

    $language = core_text::strtolower(clean_param($language, PARAM_ALPHANUMEXT));
    return $map[$language] ?? 'english';
}

/**
 * Return the configured Moodle Quiz id for a placement language.
 *
 * @param string $language Language code or profile value.
 * @return int
 */
function local_flwplacement_get_quiz_id_for_language(string $language): int {
    $profilevalue = local_flwplacement_language_profile_value($language);
    $map = [
        'english' => 'en',
        'russian' => 'ru',
        'chinese' => 'zh',
        'german' => 'de',
        'japanese' => 'ja',
        'french' => 'fr',
        'spanish' => 'es',
    ];

    return (int)get_config('local_flwplacement', 'quizid_' . ($map[$profilevalue] ?? 'en'));
}

/**
 * Return the FLW learning language linked to a placement Moodle Quiz.
 *
 * @param int $quizid Quiz instance id.
 * @return array|null Language metadata.
 */
function local_flwplacement_get_quiz_language_for_quiz_id(int $quizid): ?array {
    if ($quizid <= 0) {
        return null;
    }

    $languages = [
        'en' => 'English',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'de' => 'German',
        'ja' => 'Japanese',
        'fr' => 'French',
        'es' => 'Spanish',
    ];
    foreach ($languages as $code => $label) {
        if ((int)get_config('local_flwplacement', 'quizid_' . $code) !== $quizid) {
            continue;
        }

        return [
            'code' => $code,
            'label' => $label,
            'profilevalue' => local_flwplacement_language_profile_value($code),
        ];
    }

    return null;
}

/**
 * Return linked Moodle Quiz display metadata.
 *
 * @param int $quizid Quiz instance id.
 * @return array|null
 */
function local_flwplacement_get_quiz_info(int $quizid): ?array {
    global $CFG, $DB;

    if ($quizid <= 0 || !$DB->get_manager()->table_exists('quiz')) {
        return null;
    }

    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING);
    if (!$quiz) {
        return null;
    }

    require_once($CFG->dirroot . '/course/lib.php');
    $cm = get_coursemodule_from_instance('quiz', $quizid, (int)$quiz->course, false, IGNORE_MISSING);
    if (!$cm) {
        return null;
    }

    return [
        'id' => (int)$quiz->id,
        'name' => format_string($quiz->name),
        'courseid' => (int)$quiz->course,
        'cmid' => (int)$cm->id,
        'url' => new moodle_url('/mod/quiz/view.php', ['id' => (int)$cm->id]),
        'questioncount' => local_flwplacement_count_quiz_attempt_questions((int)$quiz->id),
        'sourcequestioncount' => local_flwplacement_count_quiz_source_questions((int)$quiz->id),
        'grade' => (float)$quiz->grade,
        'sumgrades' => (float)$quiz->sumgrades,
    ];
}

/**
 * Remove unfinished Moodle Quiz attempts whose saved layout no longer matches the quiz slots.
 *
 * Placement quizzes can be rebuilt from large question banks. If a learner had
 * an unfinished attempt before the rebuild, Moodle may try to load stale slot
 * numbers and crash before the placement page can recover.
 *
 * @param int $quizid Quiz instance id.
 * @param int $userid Learner id.
 * @return int Number of stale attempts removed.
 */
function local_flwplacement_cleanup_stale_quiz_attempts(int $quizid, int $userid): int {
    global $CFG, $DB;

    if ($quizid <= 0 || $userid <= 0 || !$DB->get_manager()->table_exists('quiz_attempts')) {
        return 0;
    }

    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING);
    if (!$quiz) {
        return 0;
    }

    $slotrecords = $DB->get_records('quiz_slots', ['quizid' => $quizid], '', 'id, slot');
    if (!$slotrecords) {
        return 0;
    }

    $validslots = [];
    foreach ($slotrecords as $slotrecord) {
        $validslots[(int)$slotrecord->slot] = true;
    }

    [$statesql, $stateparams] = $DB->get_in_or_equal(['inprogress', 'overdue'], SQL_PARAMS_NAMED, 'state');
    $params = [
        'quizid' => $quizid,
        'userid' => $userid,
    ] + $stateparams;

    $attempts = $DB->get_records_sql(
        "SELECT *
           FROM {quiz_attempts}
          WHERE quiz = :quizid
            AND userid = :userid
            AND state {$statesql}
       ORDER BY id",
        $params
    );
    if (!$attempts) {
        return 0;
    }

    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    $removed = 0;
    foreach ($attempts as $attempt) {
        if (!local_flwplacement_quiz_attempt_layout_is_stale((string)$attempt->layout, $validslots)) {
            continue;
        }

        quiz_delete_attempt($attempt, $quiz);
        $removed++;
    }

    return $removed;
}

/**
 * Check whether an attempt layout references slots that the quiz no longer has.
 *
 * @param string $layout Attempt layout string.
 * @param array $validslots Slot-number lookup keyed by slot number.
 * @return bool
 */
function local_flwplacement_quiz_attempt_layout_is_stale(string $layout, array $validslots): bool {
    foreach (preg_split('/,/', $layout, -1, PREG_SPLIT_NO_EMPTY) as $item) {
        $slot = (int)trim($item);
        if ($slot > 0 && !isset($validslots[$slot])) {
            return true;
        }
    }

    return false;
}

/**
 * Count questions shown in a Moodle Quiz attempt.
 *
 * @param int $quizid Quiz instance id.
 * @return int
 */
function local_flwplacement_count_quiz_attempt_questions(int $quizid): int {
    global $DB;

    if ($quizid <= 0) {
        return 0;
    }

    return (int)$DB->count_records('quiz_slots', ['quizid' => $quizid]);
}

/**
 * Count Moodle Quiz source questions.
 *
 * Random-slot placement quizzes keep a large source bank while showing a
 * smaller sampled attempt to the learner.
 *
 * @param int $quizid Quiz instance id.
 * @return int
 */
function local_flwplacement_count_quiz_source_questions(int $quizid): int {
    global $DB;

    if ($quizid <= 0) {
        return 0;
    }

    $entryids = [];
    $directentries = $DB->get_records_sql(
        "SELECT DISTINCT qr.questionbankentryid AS id
           FROM {quiz_slots} qs
           JOIN {question_references} qr
             ON qr.itemid = qs.id
            AND qr.component = :component
            AND qr.questionarea = :questionarea
          WHERE qs.quizid = :quizid",
        [
            'component' => 'mod_quiz',
            'questionarea' => 'slot',
            'quizid' => $quizid,
        ]
    );
    foreach ($directentries as $entry) {
        $entryids[(int)$entry->id] = true;
    }

    $categoryids = local_flwplacement_get_quiz_random_source_category_ids($quizid);
    if ($categoryids) {
        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
        $params['status'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $randomentries = $DB->get_records_sql(
            "SELECT DISTINCT qbe.id
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               JOIN {question} q ON q.id = qv.questionid
              WHERE qbe.questioncategoryid {$insql}
                AND qv.status = :status
                AND q.parent = 0",
            $params
        );
        foreach ($randomentries as $entry) {
            $entryids[(int)$entry->id] = true;
        }
    }

    return $entryids ? count($entryids) : (int)$DB->count_records('quiz_slots', ['quizid' => $quizid]);
}

/**
 * Get question category ids used by random slots in a Moodle Quiz.
 *
 * @param int $quizid Quiz instance id.
 * @return array
 */
function local_flwplacement_get_quiz_random_source_category_ids(int $quizid): array {
    global $DB;

    $refs = $DB->get_records_sql(
        "SELECT qsr.id, qsr.filtercondition
           FROM {quiz_slots} qs
           JOIN {question_set_references} qsr
             ON qsr.itemid = qs.id
            AND qsr.component = :component
            AND qsr.questionarea = :questionarea
          WHERE qs.quizid = :quizid",
        [
            'component' => 'mod_quiz',
            'questionarea' => 'slot',
            'quizid' => $quizid,
        ]
    );

    $categoryids = [];
    foreach ($refs as $ref) {
        $filter = json_decode($ref->filtercondition ?? '[]', true) ?: [];
        $categoryfilter = $filter['filter']['category'] ?? [];
        $values = array_map('intval', $categoryfilter['values'] ?? []);
        if (!empty($categoryfilter['filteroptions']['includesubcategories'])) {
            $values = local_flwplacement_expand_question_category_ids($values);
        }
        foreach ($values as $value) {
            if ($value > 0) {
                $categoryids[$value] = $value;
            }
        }
    }

    return array_values($categoryids);
}

/**
 * Include descendants for a list of question category ids.
 *
 * @param array $categoryids Question category ids.
 * @return array
 */
function local_flwplacement_expand_question_category_ids(array $categoryids): array {
    global $DB;

    $expanded = [];
    $queue = array_values(array_filter(array_map('intval', $categoryids)));
    while ($queue) {
        $id = array_shift($queue);
        if (isset($expanded[$id])) {
            continue;
        }
        $expanded[$id] = $id;
        $children = $DB->get_records('question_categories', ['parent' => $id], '', 'id');
        foreach ($children as $child) {
            $queue[] = (int)$child->id;
        }
    }

    return array_values($expanded);
}

/**
 * Save the learner's latest finished Moodle Quiz attempt as an FLW Placement profile.
 *
 * @param int $quizid Quiz instance id.
 * @param int $userid Learner id.
 * @param string $language Language code.
 * @param string $languagelabel Human language label.
 * @return int Placement result id.
 */
function local_flwplacement_save_quiz_result(int $quizid, int $userid, string $language, string $languagelabel): int {
    global $DB;

    if ($quizid <= 0 || !$DB->record_exists('quiz', ['id' => $quizid])) {
        throw new moodle_exception('linkedquiznotavailable', 'local_flwplacement');
    }

    $attempts = $DB->get_records('quiz_attempts', [
        'quiz' => $quizid,
        'userid' => $userid,
        'state' => 'finished',
        'preview' => 0,
    ], 'timefinish DESC, id DESC', '*', 0, 1);
    if (!$attempts) {
        throw new moodle_exception('noquizattempttosync', 'local_flwplacement');
    }

    $quizattempt = reset($attempts);
    return local_flwplacement_save_quiz_attempt_result(
        $quizid,
        $userid,
        (int)$quizattempt->id,
        $language,
        $languagelabel
    );
}

/**
 * Save a specific finished Moodle Quiz attempt as an FLW Placement profile.
 *
 * @param int $quizid Quiz instance id.
 * @param int $userid Learner id.
 * @param int $quizattemptid Quiz attempt id.
 * @param string $language Language code.
 * @param string $languagelabel Human language label.
 * @return int Placement result id.
 */
function local_flwplacement_save_quiz_attempt_result(
    int $quizid,
    int $userid,
    int $quizattemptid,
    string $language,
    string $languagelabel
): int {
    global $CFG, $DB;

    if ($quizid <= 0 || !$DB->record_exists('quiz', ['id' => $quizid])) {
        throw new moodle_exception('linkedquiznotavailable', 'local_flwplacement');
    }

    $quizattempt = $DB->get_record('quiz_attempts', [
        'id' => $quizattemptid,
        'quiz' => $quizid,
        'userid' => $userid,
        'state' => 'finished',
        'preview' => 0,
    ], '*', IGNORE_MISSING);
    if (!$quizattempt || $quizattempt->sumgrades === null) {
        throw new moodle_exception('noquizattempttosync', 'local_flwplacement');
    }

    $existingid = local_flwplacement_find_existing_quiz_result($userid, (int)$quizattempt->id);
    if ($existingid > 0) {
        return $existingid;
    }

    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    $percent = local_flwplacement_quiz_attempt_percent($quiz, $quizattempt);
    $cefr = local_flwplacement_cefr_from_percent($percent);
    $startunit = local_flwplacement_start_unit_from_percent($percent);
    $nextcheckpoint = min(108, max($startunit + 6, (int)(ceil($startunit / 12) * 12)));
    $profilevalue = local_flwplacement_language_profile_value($language);
    $coursekey = 'FLW_' . strtoupper($profilevalue) . '_SELFSTUDY';
    $recommendedcourse = $languagelabel . ' Self Study';
    $skills = ['listening', 'speaking', 'reading', 'writing'];
    $skilllevels = [];
    $skillpercentages = [];
    foreach ($skills as $skill) {
        $skilllevels[$skill] = $cefr;
        $skillpercentages[$skill] = $percent;
    }

    $result = [
        'placement_date' => gmdate('c', (int)$quizattempt->timefinish),
        'course' => $coursekey,
        'overall_cefr' => $cefr,
        'recommended_start_unit' => $startunit,
        'next_checkpoint_unit' => $nextcheckpoint,
        'placement_confidence' => 0.82,
        'placement_status' => 'confirmed',
        'skill_levels' => $skilllevels,
        'kp_mastery' => [],
        'support_flags' => [
            'foundation_review' => $percent < 55,
            'checkpoint_ready' => $percent >= 70,
        ],
        'speaking_profile' => [
            'source' => 'moodle_quiz',
            'needs_voice_repair' => $percent < 55,
        ],
        'learning_path' => [
            'source' => 'moodle_quiz',
            'start_mode' => $percent < 55 ? 'main_path_with_repair' : 'main_path',
            'required_repair_units' => $percent < 55 ? [max(1, $startunit - 1)] : [],
            'optional_review_units' => $percent >= 55 && $percent < 70 ? [max(1, $startunit - 1)] : [],
            'locked_until_checkpoint' => false,
        ],
        'audit' => [
            'source' => 'moodle_quiz_attempt',
            'quizid' => (int)$quiz->id,
            'quiz_attempt_id' => (int)$quizattempt->id,
            'raw_score' => round((float)$quizattempt->sumgrades, 2),
            'max_score' => round((float)$quiz->sumgrades, 2),
        ],
        'cefr_level' => $cefr,
        'skill_profile' => $skilllevels,
        'recommended_course' => $recommendedcourse,
        'starting_unit' => $startunit,
        'confidence_score' => 82,
        'weighted_score' => $percent,
        'skill_percentages' => $skillpercentages,
        'weak_skill_warnings' => $percent < 55 ? $skills : [],
        'strong_areas' => $percent >= 75 ? $skills : [],
        'repair_areas' => $percent < 55 ? ['foundation_review'] : [],
        'study_recommendation' => $percent < 55
            ? 'Begin with foundation review before continuing the main path.'
            : 'Begin at the recommended unit and continue to the next checkpoint.',
    ];

    $attempt = [
        'source' => 'moodle_quiz',
        'quizid' => (int)$quiz->id,
        'quiz_attempt_id' => (int)$quizattempt->id,
        'quiz_courseid' => (int)$quiz->course,
        'quiz_sumgrades' => (float)$quiz->sumgrades,
        'quiz_grade' => (float)$quiz->grade,
        'attempt_sumgrades' => (float)$quizattempt->sumgrades,
        'percent' => $percent,
        'time_started' => (int)$quizattempt->timestart,
        'time_finished' => (int)$quizattempt->timefinish,
    ];

    return \local_flwplacement\service\result_repository::save_result($userid, SITEID, $result, $attempt);
}

/**
 * Return an existing placement result for a synced Moodle Quiz attempt.
 *
 * @param int $userid User id.
 * @param int $quizattemptid Quiz attempt id.
 * @return int Existing placement id or 0.
 */
function local_flwplacement_find_existing_quiz_result(int $userid, int $quizattemptid): int {
    global $DB;

    if (!$DB->get_manager()->table_exists('local_flwplacement')) {
        return 0;
    }

    $needle = '%' . $DB->sql_like_escape('"quiz_attempt_id":' . $quizattemptid) . '%';
    $id = $DB->get_field_select(
        'local_flwplacement',
        'id',
        'userid = :userid AND courseid = :courseid AND ' . $DB->sql_like('attemptjson', ':needle', false),
        [
            'userid' => $userid,
            'courseid' => SITEID,
            'needle' => $needle,
        ],
        IGNORE_MULTIPLE
    );

    return $id ? (int)$id : 0;
}

/**
 * Return the FLW placement report URL for a finished Moodle Quiz review.
 *
 * If the attempt has not been synced yet, this tries to sync it before
 * returning the report URL. Non-placement quizzes return null.
 *
 * @param int $quizid Quiz instance id.
 * @param int $userid Learner id.
 * @param int $quizattemptid Quiz attempt id.
 * @return moodle_url|null Placement report URL, or null for normal Moodle flow.
 */
function local_flwplacement_get_quiz_review_report_url(int $quizid, int $userid, int $quizattemptid): ?moodle_url {
    if ($quizid <= 0 || $userid <= 0 || $quizattemptid <= 0) {
        return null;
    }

    $language = local_flwplacement_get_quiz_language_for_quiz_id($quizid);
    if (!$language) {
        return null;
    }

    $resultid = local_flwplacement_find_existing_quiz_result($userid, $quizattemptid);
    if ($resultid <= 0) {
        try {
            $resultid = local_flwplacement_save_quiz_attempt_result(
                $quizid,
                $userid,
                $quizattemptid,
                $language['code'],
                $language['label']
            );
        } catch (Throwable $e) {
            debugging(
                'local_flwplacement could not prepare placement report URL for quiz attempt ' .
                    $quizattemptid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    return $resultid > 0 ? new moodle_url('/local/flwplacement/view.php', ['id' => $resultid]) : null;
}

/**
 * Convert a Moodle Quiz attempt grade into a percentage.
 *
 * @param object $quiz Quiz record.
 * @param object $attempt Quiz attempt record.
 * @return float
 */
function local_flwplacement_quiz_attempt_percent(object $quiz, object $attempt): float {
    $rawgrade = (float)($attempt->sumgrades ?? 0);
    $quizgrade = (float)($quiz->grade ?? 0);
    $sumgrades = (float)($quiz->sumgrades ?? 0);

    if ($quizgrade > 0 && function_exists('quiz_rescale_grade')) {
        $scaledgrade = (float)quiz_rescale_grade($rawgrade, $quiz, false);
        return round(max(0, min(100, ($scaledgrade / $quizgrade) * 100)), 2);
    }

    if ($sumgrades > 0) {
        return round(max(0, min(100, ($rawgrade / $sumgrades) * 100)), 2);
    }

    return 0.0;
}

/**
 * Convert a percentage to a CEFR placement band.
 *
 * @param float $percent
 * @return string
 */
function local_flwplacement_cefr_from_percent(float $percent): string {
    if ($percent >= 90) {
        return 'C2';
    }
    if ($percent >= 75) {
        return 'C1';
    }
    if ($percent >= 58) {
        return 'B2';
    }
    if ($percent >= 38) {
        return 'B1';
    }
    if ($percent >= 20) {
        return 'A2';
    }
    return 'A1';
}

/**
 * Convert a percentage to a conservative 1-108 unit starting point.
 *
 * @param float $percent
 * @return int
 */
function local_flwplacement_start_unit_from_percent(float $percent): int {
    return max(1, min(108, (int)ceil(($percent / 100) * 108)));
}
