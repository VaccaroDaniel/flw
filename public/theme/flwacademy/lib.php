<?php
defined('MOODLE_INTERNAL') || die();

function theme_flwacademy_get_main_scss_content($theme): string {
    global $CFG;

    $scss = '';

    $boostdefault = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    if (!is_readable($boostdefault)) {
        $boostdefault = $CFG->dirroot . '/public/theme/boost/scss/preset/default.scss';
    }
    if (is_readable($boostdefault)) {
        $scss .= file_get_contents($boostdefault);
    }

    $post = __DIR__ . '/scss/post.scss';
    if (is_readable($post)) {
        $scss .= "\n\n" . file_get_contents($post);
    }

    return $scss;
}

function theme_flwacademy_get_pre_scss($theme): string {
    $emerald = get_config('theme_flwacademy', 'emerald') ?: '#087f8c';
    $orange = get_config('theme_flwacademy', 'orange') ?: '#f2b84b';
    $purple = get_config('theme_flwacademy', 'purple') ?: '#3278bd';
    $pink = get_config('theme_flwacademy', 'pink') ?: '#e85d4f';
    $cream = get_config('theme_flwacademy', 'cream') ?: '#f7faf8';
    $radius = get_config('theme_flwacademy', 'radius') ?: '.5rem';

    return "
\$font-family-sans-serif: Inter, \"Segoe UI\", Arial, sans-serif;
\$font-family-base: \$font-family-sans-serif;
\$headings-font-family: \$font-family-sans-serif;
\$input-btn-font-family: \$font-family-sans-serif;
\$primary: {$emerald};
\$secondary: {$purple};
\$success: {$emerald};
\$info: {$purple};
\$warning: {$orange};
\$danger: {$pink};
\$body-bg: {$cream};
\$link-color: {$emerald};
\$border-radius: {$radius};
\$border-radius-lg: calc({$radius} + .25rem);
\$btn-border-radius: 999px;
\$card-border-radius: calc({$radius} + .2rem);
";
}

function theme_flwacademy_get_extra_scss($theme): string {
    return get_config('theme_flwacademy', 'extrascss') ?: '';
}

/**
 * Returns the FLW learning language list in the product order.
 *
 * @return array
 */
function theme_flwacademy_get_learning_language_definitions(): array {
    return [
        ['code' => 'en', 'label' => 'English', 'aliases' => ['English'], 'nav' => [
            'school' => 'School', 'selfstudy' => 'Self Study', 'practice' => 'Practice',
            'dictionary' => 'Dictionary', 'exam' => 'Exam', 'teacher' => 'Teacher',
            'collaboration' => 'Collaboration',
        ]],
        ['code' => 'ru', 'label' => 'Russian', 'aliases' => ['Russian'], 'nav' => [
            'school' => 'Школа', 'selfstudy' => 'Самообучение', 'practice' => 'Практика',
            'dictionary' => 'Словарь', 'exam' => 'Экзамен', 'teacher' => 'Учитель',
            'collaboration' => 'Сотрудничество',
        ]],
        ['code' => 'zh', 'label' => 'Chinese', 'aliases' => ['Chinese', 'Chinese Language', 'Han Chinese', '汉语'], 'nav' => [
            'school' => '学校', 'selfstudy' => '自学', 'practice' => '练习',
            'dictionary' => '词典', 'exam' => '考试', 'teacher' => '教师',
            'collaboration' => '协作',
        ]],
        ['code' => 'ja', 'label' => 'Japanese', 'aliases' => ['Japanese'], 'nav' => [
            'school' => '学校', 'selfstudy' => '自習', 'practice' => '練習',
            'dictionary' => '辞書', 'exam' => '試験', 'teacher' => '教師',
            'collaboration' => 'コラボレーション',
        ]],
        ['code' => 'de', 'label' => 'German', 'aliases' => ['German'], 'nav' => [
            'school' => 'Schule', 'selfstudy' => 'Selbststudium', 'practice' => 'Übung',
            'dictionary' => 'Wörterbuch', 'exam' => 'Prüfung', 'teacher' => 'Lehrer',
            'collaboration' => 'Zusammenarbeit',
        ]],
        ['code' => 'fr', 'label' => 'French', 'aliases' => ['French'], 'nav' => [
            'school' => 'École', 'selfstudy' => 'Auto-apprentissage', 'practice' => 'Pratique',
            'dictionary' => 'Dictionnaire', 'exam' => 'Examen', 'teacher' => 'Enseignant',
            'collaboration' => 'Collaboration',
        ]],
        ['code' => 'es', 'label' => 'Spanish', 'aliases' => ['Spanish'], 'nav' => [
            'school' => 'Escuela', 'selfstudy' => 'Autoestudio', 'practice' => 'Práctica',
            'dictionary' => 'Diccionario', 'exam' => 'Examen', 'teacher' => 'Profesor',
            'collaboration' => 'Colaboración',
        ]],
    ];
}

/**
 * FLW Practice submenu metadata from the prototype.
 *
 * @return array
 */
function theme_flwacademy_get_practice_menu_items(): array {
    return [
        'watch' => [
            'label' => 'Watch',
            'title' => 'Watch practice',
            'text' => 'Video practice by language and level: Easy, Medium, or Hard.',
            'image' => 'dashboard/watch',
            'accent' => 'accent-teal',
        ],
        'listen' => [
            'label' => 'Listen',
            'title' => 'Listen practice',
            'text' => 'Audio and audio-plus-book materials by CEFR, HSK, JLPT, or TORFL level.',
            'image' => 'dashboard/listen-merged',
            'accent' => 'accent-coral',
        ],
        'speak' => [
            'label' => 'Speak',
            'title' => 'Speak practice',
            'text' => 'Choose a language, level, and topic for guided AI speaking practice.',
            'image' => 'dashboard/speak-merged',
            'accent' => 'accent-green',
        ],
        'read' => [
            'label' => 'Read',
            'title' => 'Read practice',
            'text' => 'Open books, PDF readers, and HTML reading materials for free reading.',
            'image' => 'dashboard/read-merged',
            'accent' => 'accent-yellow',
        ],
        'dictate' => [
            'label' => 'Dictate',
            'title' => 'Dictate practice',
            'text' => 'Listen, type what you hear, and save the dictation score to your profile.',
            'image' => 'dashboard/dictate',
            'accent' => 'accent-blue',
        ],
    ];
}

/**
 * FLW Exam level list from the prototype.
 *
 * @param string $languagecode
 * @return array
 */
function theme_flwacademy_get_exam_levels_for_language(string $languagecode): array {
    if ($languagecode === 'zh') {
        return ['HSK 1', 'HSK 2', 'HSK 3', 'HSK 4', 'HSK 5', 'HSK 6'];
    }
    if ($languagecode === 'ja') {
        return ['JLPT N5', 'JLPT N4', 'JLPT N3', 'JLPT N2', 'JLPT N1'];
    }
    if ($languagecode === 'ru') {
        return ['TORFL A1', 'TORFL A2', 'TORFL B1', 'TORFL B2', 'TORFL C1', 'TORFL C2'];
    }
    return ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
}

/**
 * Exports learning languages with matching Moodle course category URLs.
 *
 * @return array
 */
function theme_flwacademy_export_learning_languages(): array {
    global $DB;

    $records = $DB->get_records('course_categories', null, 'parent ASC, sortorder ASC', 'id,parent,name');
    $categoriesbyname = [];
    $childrenbyparent = [];
    foreach ($records as $record) {
        $key = core_text::strtolower(trim($record->name));
        if ((int)$record->parent === 0) {
            $categoriesbyname[$key] = $record;
        } else {
            $childrenbyparent[(int)$record->parent][$key] = $record;
        }
    }

    $subcategoryaliases = [
        'school' => ['School', 'School Course Category', 'School Courses'],
        'selfstudy' => ['Self Study', 'Self-Study', 'Self Study Course Category', 'Self-Study Course Category'],
        'practice' => ['Practice', 'Practice Course Category'],
        'exam' => ['Exam', 'Exam Course Category', 'Exam Preparation'],
    ];

    $languages = [];
    foreach (theme_flwacademy_get_learning_language_definitions() as $index => $language) {
        $category = null;
        foreach ($language['aliases'] as $alias) {
            $key = core_text::strtolower(trim($alias));
            if (isset($categoriesbyname[$key])) {
                $category = $categoriesbyname[$key];
                break;
            }
        }

        $url = $category
            ? new moodle_url('/course/index.php', ['categoryid' => $category->id])
            : new moodle_url('/course/index.php');

        $categoryurls = [];
        $matchedsubcategories = [];
        foreach ($subcategoryaliases as $type => $aliases) {
            $subcategory = null;
            if ($category) {
                foreach ($aliases as $alias) {
                    $key = core_text::strtolower(trim($alias));
                    if (isset($childrenbyparent[(int)$category->id][$key])) {
                        $subcategory = $childrenbyparent[(int)$category->id][$key];
                        break;
                    }
                }
            }
            $matchedsubcategories[$type] = $subcategory;
            $categoryurls[$type] = $subcategory
                ? (new moodle_url('/course/index.php', ['categoryid' => $subcategory->id]))->out(false)
                : $url->out(false);
        }

        $practicesubmenu = [];
        $practiceparent = $matchedsubcategories['practice'] ?? null;
        foreach (theme_flwacademy_get_practice_menu_items() as $key => $item) {
            $child = null;
            if ($practiceparent) {
                $childkey = core_text::strtolower($item['label']);
                $child = $childrenbyparent[(int)$practiceparent->id][$childkey] ?? null;
            }
            $practicesubmenu[$key . 'url'] = $child
                ? (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false)
                : $categoryurls['practice'];
        }

        $examsubmenu = [];
        $examparent = $matchedsubcategories['exam'] ?? null;
        $examlevels = theme_flwacademy_get_exam_levels_for_language($language['code']);
        for ($levelindex = 0; $levelindex < 6; $levelindex++) {
            $level = $examlevels[$levelindex] ?? '';
            $child = null;
            if ($examparent && $level !== '') {
                $childkey = core_text::strtolower($level);
                $child = $childrenbyparent[(int)$examparent->id][$childkey] ?? null;
            }
            $slot = $levelindex + 1;
            $examsubmenu['exam' . $slot . 'label'] = $level;
            $examsubmenu['exam' . $slot . 'url'] = $child
                ? (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false)
                : $categoryurls['exam'];
        }

        $languages[] = [
            'code' => $language['code'],
            'label' => $language['label'],
            'categoryurl' => $url->out(false),
            'schoolcategoryurl' => $categoryurls['school'],
            'selfstudycategoryurl' => $categoryurls['selfstudy'],
            'practicecategoryurl' => $categoryurls['practice'],
            'examcategoryurl' => $categoryurls['exam'],
            'practicewatchurl' => $practicesubmenu['watchurl'],
            'practicelistenurl' => $practicesubmenu['listenurl'],
            'practicespeakurl' => $practicesubmenu['speakurl'],
            'practicereadurl' => $practicesubmenu['readurl'],
            'practicedictateurl' => $practicesubmenu['dictateurl'],
            'exam1label' => $examsubmenu['exam1label'],
            'exam1url' => $examsubmenu['exam1url'],
            'exam2label' => $examsubmenu['exam2label'],
            'exam2url' => $examsubmenu['exam2url'],
            'exam3label' => $examsubmenu['exam3label'],
            'exam3url' => $examsubmenu['exam3url'],
            'exam4label' => $examsubmenu['exam4label'],
            'exam4url' => $examsubmenu['exam4url'],
            'exam5label' => $examsubmenu['exam5label'],
            'exam5url' => $examsubmenu['exam5url'],
            'exam6label' => $examsubmenu['exam6label'],
            'exam6url' => $examsubmenu['exam6url'],
            'navschool' => $language['nav']['school'],
            'navselfstudy' => $language['nav']['selfstudy'],
            'navpractice' => $language['nav']['practice'],
            'navdictionary' => $language['nav']['dictionary'],
            'navexam' => $language['nav']['exam'],
            'navteacher' => $language['nav']['teacher'],
            'navcollaboration' => $language['nav']['collaboration'],
            'categoryid' => $category ? $category->id : 0,
            'isdefault' => $index === 0,
        ];
    }

    return $languages;
}

/**
 * Finds a FLW learning language definition for a top-level course category.
 *
 * @param stdClass $category
 * @return array|null
 */
function theme_flwacademy_match_learning_language_category(stdClass $category): ?array {
    foreach (theme_flwacademy_get_learning_language_definitions() as $language) {
        foreach ($language['aliases'] as $alias) {
            if (core_text::strtolower(trim($category->name)) === core_text::strtolower(trim($alias))) {
                return $language;
            }
        }
    }
    return null;
}

/**
 * Returns true when the category is a FLW language School category.
 *
 * @param stdClass $category
 * @return bool
 */
function theme_flwacademy_is_school_category(stdClass $category): bool {
    global $DB;

    if (core_text::strtolower(trim($category->name)) !== 'school' || empty($category->parent)) {
        return false;
    }

    $parent = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name', IGNORE_MISSING);
    return $parent && theme_flwacademy_match_learning_language_category($parent) !== null;
}

/**
 * Returns true when the category is a FLW language Self Study category.
 *
 * @param stdClass $category
 * @return bool
 */
function theme_flwacademy_is_selfstudy_category(stdClass $category): bool {
    global $DB;

    $name = core_text::strtolower(trim($category->name));
    if (($name !== 'self study' && $name !== 'self-study') || empty($category->parent)) {
        return false;
    }

    $parent = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name', IGNORE_MISSING);
    return $parent && theme_flwacademy_match_learning_language_category($parent) !== null;
}

/**
 * Exports FLW Self Study category page data.
 *
 * @param int $categoryid
 * @param core_renderer $output
 * @return array
 */
function theme_flwacademy_export_selfstudy_category_page(int $categoryid, core_renderer $output): array {
    global $DB;

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $languagecategory = $DB->get_record('course_categories', ['id' => $category->parent], '*', MUST_EXIST);
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    $languageLabel = $language['label'] ?? format_string($languagecategory->name);
    $languageCode = $language['code'] ?? 'en';

    $description = '';
    if (!empty($category->description)) {
        $description = format_text($category->description, $category->descriptionformat, [
            'context' => context_coursecat::instance($category->id),
            'overflowdiv' => true,
            'filter' => false,
        ]);
    }

    $nativeNames = [
        'en' => 'English',
        'ru' => 'Русский',
        'zh' => '中文',
        'ja' => '日本語',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
    ];
    $imageKeys = [
        'en' => 'english',
        'ru' => 'russian',
        'zh' => 'chinese',
        'ja' => 'japanese',
        'de' => 'german',
        'fr' => 'french',
        'es' => 'spanish',
    ];

    $languageTiles = [];
    foreach (theme_flwacademy_export_learning_languages() as $item) {
        $code = $item['code'];
        $languageTiles[] = [
            'label' => $item['label'],
            'native' => $nativeNames[$code] ?? $item['label'],
            'url' => $item['selfstudycategoryurl'],
            'imageurl' => $output->image_url('languages/' . ($imageKeys[$code] ?? 'english'), 'theme_flwacademy')->out(false),
            'isactive' => $code === $languageCode,
        ];
    }

    $pathprefix = $category->path . '/';
    $courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat, cc.name AS categoryname
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
          WHERE c.id <> :siteid
            AND c.visible = 1
            AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")
          ORDER BY cc.sortorder ASC, c.sortorder ASC",
        [
            'siteid' => SITEID,
            'categoryid' => $category->id,
            'pathprefix' => $pathprefix . '%',
        ]
    );
    $courseitems = [];
    foreach ($courses as $course) {
        $summary = '';
        if (!empty($course->summary)) {
            $summary = shorten_text(trim(strip_tags(format_text($course->summary, $course->summaryformat))), 150);
        }
        $courseitems[] = [
            'name' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'categoryname' => format_string($course->categoryname),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    $mapnodes = [
        ['unit' => 'Unit 1', 'title' => 'Hello and first steps', 'level' => 'A1', 'mission' => 'Start with greetings, names, and simple classroom language.'],
        ['unit' => 'Unit 2', 'title' => 'People and places', 'level' => 'A1', 'mission' => 'Build everyday words and short question-answer routines.'],
        ['unit' => 'Unit 3', 'title' => 'Daily life', 'level' => 'A1-A2', 'mission' => 'Connect vocabulary, grammar, listening, and speaking practice.'],
        ['unit' => 'Unit 4', 'title' => 'Stories and tasks', 'level' => 'A2', 'mission' => 'Read short texts and complete guided writing or speaking tasks.'],
        ['unit' => 'Unit 5', 'title' => 'Projects', 'level' => 'A2-B1', 'mission' => 'Create evidence for your learning profile.'],
        ['unit' => 'Unit 6', 'title' => 'Checkpoint', 'level' => 'B1', 'mission' => 'Review, test, and choose the next recommended step.'],
    ];

    return [
        'language' => $languageLabel,
        'languagecode' => $languageCode,
        'title' => format_string($category->name),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $languagecategory->id]))->out(false),
        'heroimageurl' => $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false),
        'languagetiles' => $languageTiles,
        'mapnodes' => $mapnodes,
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
    ];
}

/**
 * Exports FLW School category page data.
 *
 * @param int $categoryid
 * @param core_renderer $output
 * @return array
 */
function theme_flwacademy_export_school_category_page(int $categoryid, core_renderer $output): array {
    global $DB;

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $languagecategory = $DB->get_record('course_categories', ['id' => $category->parent], '*', MUST_EXIST);
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    $languageLabel = $language['label'] ?? format_string($languagecategory->name);

    $description = '';
    if (!empty($category->description)) {
        $description = format_text($category->description, $category->descriptionformat, [
            'context' => context_coursecat::instance($category->id),
            'overflowdiv' => true,
            'filter' => false,
        ]);
    }
    $hasdescription = trim(strip_tags($description)) !== '';

    $children = $DB->get_records('course_categories', ['parent' => $category->id], 'sortorder ASC', 'id,name,description,descriptionformat,coursecount');
    $groups = [
        'primary' => [],
        'secondary' => [],
        'university' => [],
    ];

    foreach ($children as $child) {
        $namekey = core_text::strtolower($child->name);
        $item = [
            'name' => format_string($child->name),
            'url' => (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
            'coursecount' => (int)$child->coursecount,
            'description' => '',
            'hasdescription' => false,
        ];
        if (!empty($child->description)) {
            $item['description'] = format_text($child->description, $child->descriptionformat, [
                'context' => context_coursecat::instance($child->id),
                'overflowdiv' => true,
                'filter' => false,
            ]);
            $item['hasdescription'] = trim(strip_tags($item['description'])) !== '';
        }
        if (strpos($namekey, 'university') !== false) {
            $groups['university'][] = $item;
        } else if (strpos($namekey, 'secondary') !== false) {
            $groups['secondary'][] = $item;
        } else {
            $groups['primary'][] = $item;
        }
    }

    $pathprefix = $category->path . '/';
    $courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat, cc.name AS categoryname
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
          WHERE c.id <> :siteid
            AND c.visible = 1
            AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")
          ORDER BY cc.sortorder ASC, c.sortorder ASC",
        [
            'siteid' => SITEID,
            'categoryid' => $category->id,
            'pathprefix' => $pathprefix . '%',
        ]
    );

    $courseitems = [];
    foreach ($courses as $course) {
        $summary = '';
        if (!empty($course->summary)) {
            $summary = shorten_text(trim(strip_tags(format_text($course->summary, $course->summaryformat))), 150);
        }
        $courseitems[] = [
            'name' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'categoryname' => format_string($course->categoryname),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    return [
        'language' => $languageLabel,
        'title' => format_string($category->name),
        'description' => $description,
        'hasdescription' => $hasdescription,
        'primarycategories' => $groups['primary'],
        'hasprimarycategories' => !empty($groups['primary']),
        'secondarycategories' => $groups['secondary'],
        'hassecondarycategories' => !empty($groups['secondary']),
        'hasprimarysecondarycategories' => !empty($groups['primary']) || !empty($groups['secondary']),
        'universitycategories' => $groups['university'],
        'hasuniversitycategories' => !empty($groups['university']),
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $languagecategory->id]))->out(false),
        'heroimageurl' => $output->image_url('dashboard/school-fit', 'theme_flwacademy')->out(false),
    ];
}

/**
 * Resolves a FLW Practice or Exam category.
 *
 * @param stdClass $category
 * @return array|null
 */
function theme_flwacademy_resolve_activity_category(stdClass $category): ?array {
    global $DB;

    if (empty($category->parent)) {
        return null;
    }

    $parent = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name,parent', IGNORE_MISSING);
    if (!$parent) {
        return null;
    }

    $categoryname = core_text::strtolower(trim($category->name));
    if (($categoryname === 'practice' || $categoryname === 'exam') && !empty($parent->id)) {
        $language = theme_flwacademy_match_learning_language_category($parent);
        if ($language) {
            return [
                'area' => $categoryname,
                'languagecategory' => $parent,
                'areacategory' => $category,
                'itemcategory' => null,
                'language' => $language,
            ];
        }
    }

    $parentname = core_text::strtolower(trim($parent->name));
    if ($parentname !== 'practice' && $parentname !== 'exam') {
        return null;
    }

    $languagecategory = $DB->get_record('course_categories', ['id' => $parent->parent], 'id,name,parent', IGNORE_MISSING);
    if (!$languagecategory) {
        return null;
    }
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    if (!$language) {
        return null;
    }

    return [
        'area' => $parentname,
        'languagecategory' => $languagecategory,
        'areacategory' => $parent,
        'itemcategory' => $category,
        'language' => $language,
    ];
}

/**
 * Exports prototype-like Practice or Exam category page data.
 *
 * @param int $categoryid
 * @param core_renderer $output
 * @return array
 */
function theme_flwacademy_export_activity_category_page(int $categoryid, core_renderer $output): array {
    global $DB;

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $resolved = theme_flwacademy_resolve_activity_category($category);
    if (!$resolved) {
        return [];
    }

    $language = $resolved['language'];
    $languageLabel = $language['label'];
    $area = $resolved['area'];
    $itemcategory = $resolved['itemcategory'];
    $areacategory = $resolved['areacategory'];
    $displaycategory = $itemcategory ?: $areacategory;

    $description = '';
    if (!empty($displaycategory->description)) {
        $description = format_text($displaycategory->description, $displaycategory->descriptionformat, [
            'context' => context_coursecat::instance($displaycategory->id),
            'overflowdiv' => true,
            'filter' => false,
        ]);
    }

    $childrenparent = $itemcategory ? (int)$areacategory->id : (int)$displaycategory->id;
    $children = $DB->get_records('course_categories', ['parent' => $childrenparent], 'sortorder ASC', 'id,name,description,descriptionformat,coursecount');
    $childitems = [];
    foreach ($children as $child) {
        $childdescription = '';
        if (!empty($child->description)) {
            $childdescription = format_text($child->description, $child->descriptionformat, [
                'context' => context_coursecat::instance($child->id),
                'overflowdiv' => true,
                'filter' => false,
            ]);
        }
        $childitems[] = [
            'name' => format_string($child->name),
            'url' => (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
            'description' => $childdescription,
            'hasdescription' => trim(strip_tags($childdescription)) !== '',
            'coursecount' => (int)$child->coursecount,
        ];
    }

    $pathprefix = $displaycategory->path . '/';
    $courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname, c.summary, c.summaryformat, cc.name AS categoryname
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
          WHERE c.id <> :siteid
            AND c.visible = 1
            AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")
          ORDER BY cc.sortorder ASC, c.sortorder ASC",
        [
            'siteid' => SITEID,
            'categoryid' => $displaycategory->id,
            'pathprefix' => $pathprefix . '%',
        ]
    );

    $courseitems = [];
    foreach ($courses as $course) {
        $summary = '';
        if (!empty($course->summary)) {
            $summary = shorten_text(trim(strip_tags(format_text($course->summary, $course->summaryformat))), 150);
        }
        $courseitems[] = [
            'name' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'categoryname' => format_string($course->categoryname),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    $practiceitems = [];
    foreach (theme_flwacademy_get_practice_menu_items() as $key => $item) {
        $matched = null;
        foreach ($childitems as $childitem) {
            if (core_text::strtolower($childitem['name']) === core_text::strtolower($item['label'])) {
                $matched = $childitem;
                break;
            }
        }
        $practiceitems[] = [
            'key' => $key,
            'label' => $item['label'],
            'title' => $item['title'],
            'text' => $item['text'],
            'url' => $matched['url'] ?? (new moodle_url('/course/index.php', ['categoryid' => $areacategory->id]))->out(false),
            'accent' => $item['accent'],
            'imageurl' => $output->image_url($item['image'], 'theme_flwacademy')->out(false),
        ];
    }

    $currentpractice = null;
    if ($area === 'practice' && $itemcategory) {
        foreach (theme_flwacademy_get_practice_menu_items() as $key => $item) {
            if (core_text::strtolower($item['label']) === core_text::strtolower($itemcategory->name)) {
                $currentpractice = $item + ['key' => $key];
                break;
            }
        }
    }
    $practicehero = $currentpractice ?: theme_flwacademy_get_practice_menu_items()['watch'] + ['key' => 'watch'];

    $examlevels = theme_flwacademy_get_exam_levels_for_language($language['code']);
    $framework = 'CEFR';
    $selectedlevel = $itemcategory ? format_string($itemcategory->name) : ($examlevels[0] ?? 'A1');
    if (strpos($selectedlevel, 'HSK') === 0) {
        $framework = 'HSK';
    } else if (strpos($selectedlevel, 'JLPT') === 0) {
        $framework = 'JLPT';
    } else if (strpos($selectedlevel, 'TORFL') === 0) {
        $framework = 'TORFL';
    }
    $samples = [
        ['index' => 1, 'title' => $framework . ' Listening Sample', 'prompt' => 'Listen for the speaker\'s purpose in a ' . $languageLabel . ' ' . $selectedlevel . ' dialogue.', 'task' => 'Choose the best summary after listening twice.'],
        ['index' => 2, 'title' => $framework . ' Reading Sample', 'prompt' => 'Read a short ' . $languageLabel . ' text at ' . $selectedlevel . ' and identify the main claim.', 'task' => 'Select the sentence that best matches the writer\'s intention.'],
        ['index' => 3, 'title' => $framework . ' Use of Language', 'prompt' => 'Complete a ' . $selectedlevel . ' grammar and vocabulary item for ' . $languageLabel . '.', 'task' => 'Choose the answer that fits the context and register.'],
        ['index' => 4, 'title' => $framework . ' Speaking or Writing', 'prompt' => 'Produce a short response for a ' . $languageLabel . ' ' . $selectedlevel . ' situation.', 'task' => 'Record or draft a response, then compare it with the checklist.'],
    ];
    $watchcards = [
        ['title' => 'Pronunciation', 'text' => 'Breath, stress, rhythm, and clear spoken models.', 'imageurl' => $output->image_url('practice/Pronuciation', 'theme_flwacademy')->out(false)],
        ['title' => 'Vocabulary', 'text' => 'Words and phrases in short visual lessons.', 'imageurl' => $output->image_url('practice/Vocabulary', 'theme_flwacademy')->out(false)],
        ['title' => 'Courses', 'text' => 'Course videos connected to lessons and levels.', 'imageurl' => $output->image_url('practice/Courses', 'theme_flwacademy')->out(false)],
        ['title' => 'Skill', 'text' => 'Focused practice for listening, speaking, reading, and writing.', 'imageurl' => $output->image_url('practice/Skills', 'theme_flwacademy')->out(false)],
        ['title' => 'Work English', 'text' => 'Workplace language for meetings, email, and interviews.', 'imageurl' => $output->image_url('practice/work-english', 'theme_flwacademy')->out(false)],
    ];

    return [
        'language' => $languageLabel,
        'area' => $area,
        'ispractice' => $area === 'practice',
        'isexam' => $area === 'exam',
        'title' => format_string($displaycategory->name),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'children' => $childitems,
        'haschildren' => !empty($childitems),
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
        'practiceitems' => $practiceitems,
        'practiceherotitle' => $practicehero['title'],
        'practiceherotext' => $practicehero['text'],
        'practiceheroimageurl' => $output->image_url($practicehero['image'], 'theme_flwacademy')->out(false),
        'examheroimageurl' => $output->image_url('dashboard2/exam-banner', 'theme_flwacademy')->out(false),
        'examlevels' => $childitems,
        'hasexamlevels' => !empty($childitems),
        'examframework' => $framework,
        'selectedlevel' => $selectedlevel,
        'examsamples' => $samples,
        'watchcards' => $watchcards,
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $resolved['languagecategory']->id]))->out(false),
    ];
}
