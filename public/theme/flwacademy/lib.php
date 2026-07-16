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

    $flwclean = __DIR__ . '/scss/flwclean.scss';
    if (is_readable($flwclean)) {
        $scss .= "\n\n" . file_get_contents($flwclean);
    }

    return $scss;
}

function theme_flwacademy_get_pre_scss($theme): string {
    $emerald = get_config('theme_flwacademy', 'emerald') ?: '#0a4be8';
    $orange = get_config('theme_flwacademy', 'orange') ?: '#f2b84b';
    $purple = get_config('theme_flwacademy', 'purple') ?: '#3278bd';
    $pink = get_config('theme_flwacademy', 'pink') ?: '#e85d4f';
    $cream = get_config('theme_flwacademy', 'cream') ?: '#eef0f3';
    $radius = get_config('theme_flwacademy', 'radius') ?: '.5rem';

    return "
\$font-family-sans-serif: Arial, Helvetica, sans-serif;
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
 * Returns whether the current page should use FLW Clean Theme v3 student mode.
 *
 * Clean mode is intentionally limited to non-editing course pages where the
 * current user cannot update the course, so teacher/admin workflows keep Moodle
 * controls, blocks, reports, settings, and navigation.
 *
 * @return bool
 */
function theme_flwacademy_is_clean_mode(): bool {
    global $PAGE;

    if (empty($PAGE->course->id) || (int)$PAGE->course->id === SITEID) {
        return false;
    }

    if (strpos($PAGE->pagetype, 'course-view') !== 0) {
        return false;
    }

    if ($PAGE->user_is_editing()) {
        return false;
    }

    $context = context_course::instance((int)$PAGE->course->id, IGNORE_MISSING);
    if (!$context) {
        return false;
    }

    return !has_capability('moodle/course:update', $context);
}

/**
 * Returns whether a primary navigation link should be marked active.
 *
 * @param string $key Link key.
 * @param string $url Link URL.
 * @param string $currenturl Current page URL.
 * @return bool
 */
function theme_flwacademy_primary_navigation_is_active(string $key, string $url, string $currenturl): bool {
    global $DB, $PAGE;

    $currentparts = parse_url($currenturl);
    $currentpath = $currentparts['path'] ?? '/';
    $currentquery = $currentparts['query'] ?? '';
    $currentwithoutfragment = $currentpath . ($currentquery !== '' ? '?' . $currentquery : '');

    $linkwithoutfragment = strtok($url, '#');
    $linkparts = parse_url($linkwithoutfragment);
    $linkpath = $linkparts['path'] ?? '/';
    $linkquery = $linkparts['query'] ?? '';
    $linkwithoutfragment = $linkpath . ($linkquery !== '' ? '?' . $linkquery : '');

    if ($key === 'home') {
        return in_array(rtrim($currentpath, '/') ?: '/', ['/', '/index.php'], true);
    }

    if ($key === 'myhome') {
        return in_array(rtrim($currentpath, '/') ?: '/', ['/my', '/my/index.php'], true);
    }

    if ($key === 'administrationsite') {
        return strpos($currentpath, '/admin/') === 0 || $currentpath === '/admin/index.php';
    }

    if ($key === 'flw-dictionary') {
        return strpos($currentpath, '/local/mldict/') === 0
            || $currentpath === '/local/mldict/index.php';
    }

    if ($key === 'flw-practice' && strpos($currentpath, '/local/flwmedia/') === 0) {
        return true;
    }

    if ($key === 'flw-exam' && strpos($currentpath, '/local/flwexam/') === 0) {
        return true;
    }

    if ($key === 'flw-selfstudy' && strpos($currentpath, '/local/flwplacement/') === 0) {
        return true;
    }

    if (in_array($key, ['flw-demo', 'flw-school', 'flw-selfstudy', 'flw-practice', 'flw-exam'], true)
            && strpos($currentpath, '/course/index.php') !== false) {
        parse_str($currentquery, $queryparams);
        $categoryid = isset($queryparams['categoryid']) ? (int)$queryparams['categoryid'] : 0;
        if ($categoryid > 0) {
            $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            if ($category) {
                if ($key === 'flw-demo') {
                    return theme_flwacademy_category_navigation_is_active($key, $category);
                }
                if ($key === 'flw-school') {
                    return theme_flwacademy_is_school_category($category);
                }
                if ($key === 'flw-selfstudy') {
                    return theme_flwacademy_is_selfstudy_category($category);
                }
                $activity = theme_flwacademy_resolve_activity_category($category);
                if ($key === 'flw-practice') {
                    return $activity && $activity['area'] === 'practice';
                }
                if ($key === 'flw-exam') {
                    return $activity && $activity['area'] === 'exam';
                }
            }
        }
    }

    if (in_array($key, ['flw-demo', 'flw-school', 'flw-selfstudy', 'flw-practice', 'flw-exam'], true)) {
        $courseid = 0;
        if (strpos($currentpath, '/course/view.php') !== false) {
            parse_str($currentquery, $queryparams);
            $courseid = isset($queryparams['id']) ? (int)$queryparams['id'] : 0;
        }
        if ($courseid <= 0 && !empty($PAGE->course->id) && (int)$PAGE->course->id !== SITEID) {
            $courseid = (int)$PAGE->course->id;
        }
        if ($courseid > 0) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id,category', IGNORE_MISSING);
            if ($course) {
                $category = $DB->get_record('course_categories', ['id' => $course->category], 'id,name,parent', IGNORE_MISSING);
                if ($category && theme_flwacademy_category_navigation_is_active($key, $category)) {
                    return true;
                }
            }
        }
    }

    if ($currentwithoutfragment === $linkwithoutfragment) {
        return true;
    }

    return strpos($currentwithoutfragment, $linkwithoutfragment . '&') === 0;
}

/**
 * Returns whether a FLW navigation item matches a category or one of its parents.
 *
 * @param string $key Navigation key.
 * @param stdClass $category Course category.
 * @return bool
 */
function theme_flwacademy_category_navigation_is_active(string $key, stdClass $category): bool {
    foreach (theme_flwacademy_get_category_lineage($category) as $lineagecategory) {
        if ($key === 'flw-demo' && core_text::strtolower(trim((string)$lineagecategory->name)) === 'demo') {
            return true;
        }
        if ($key === 'flw-school' && theme_flwacademy_is_school_category($lineagecategory)) {
            return true;
        }
        if ($key === 'flw-selfstudy' && theme_flwacademy_is_selfstudy_category($lineagecategory)) {
            return true;
        }
        if ($key === 'flw-practice' || $key === 'flw-exam') {
            $activity = theme_flwacademy_resolve_activity_category($lineagecategory);
            if ($activity && (($key === 'flw-practice' && $activity['area'] === 'practice')
                    || ($key === 'flw-exam' && $activity['area'] === 'exam'))) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Gets a category and its parents, starting with the given category.
 *
 * @param stdClass $category Course category.
 * @return stdClass[]
 */
function theme_flwacademy_get_category_lineage(stdClass $category): array {
    global $DB;

    $lineage = [];
    $seen = [];
    while (!empty($category->id) && empty($seen[(int)$category->id])) {
        $lineage[] = $category;
        $seen[(int)$category->id] = true;
        if (empty($category->parent)) {
            break;
        }
        $category = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name,parent', IGNORE_MISSING);
        if (!$category) {
            break;
        }
    }

    return $lineage;
}

/**
 * Returns the Demo category URL when the category exists.
 *
 * @return string
 */
function theme_flwacademy_get_demo_category_url(): string {
    global $DB;

    $categories = $DB->get_records('course_categories', ['name' => 'Demo'], 'parent ASC, sortorder ASC', 'id,visible');
    foreach ($categories as $category) {
        if (!property_exists($category, 'visible') || (int)$category->visible === 1) {
            return (new moodle_url('/course/index.php', ['categoryid' => (int)$category->id]))->out(false);
        }
    }

    return '';
}

/**
 * Build native Boost menu items for the FLW primary navigation.
 *
 * @param array $primarymenu Boost primary navigation export data.
 * @return array
 */
function theme_flwacademy_prepare_primary_navigation(array $primarymenu): array {
    global $CFG;

    $primarymenu['lang'] = theme_flwacademy_sort_ui_language_menu($primarymenu['lang'] ?? []);
    $primarymenu['user'] = theme_flwacademy_sort_user_language_submenus($primarymenu['user'] ?? []);

    $moremenu = $primarymenu['moremenu'] ?? [];
    $moremenu['nodearray'] = [];

    $languages = theme_flwacademy_export_learning_languages();
    $defaultlanguageurl = $languages[0]['categoryurl'] ?? (new moodle_url('/course/index.php'))->out(false);
    $dashboardurl = (new moodle_url('/my/'))->out(false);
    $demourl = theme_flwacademy_get_demo_category_url() ?: (new moodle_url('/course/index.php'))->out(false);
    $dictionaryurl = is_readable($CFG->dirroot . '/local/mldict/index.php')
        ? (new moodle_url('/local/mldict/index.php'))->out(false)
        : $dashboardurl . '#flw-dictionary';

    $links = [
        'home' => [
            'text' => get_string('home'),
            'url' => (new moodle_url('/', ['redirect' => 0]))->out(false),
        ],
        'myhome' => [
            'text' => get_string('myhome'),
            'url' => $dashboardurl,
        ],
        'flw-school' => [
            'text' => 'K-12',
            'url' => $languages[0]['schoolcategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-selfstudy' => [
            'text' => 'Self Study',
            'url' => $languages[0]['selfstudycategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-practice' => [
            'text' => 'Practice',
            'url' => $languages[0]['practicecategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-dictionary' => [
            'text' => 'Dictionary',
            'url' => $dictionaryurl,
        ],
        'flw-exam' => [
            'text' => 'Exam',
            'url' => $languages[0]['examcategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-demo' => [
            'text' => 'Demo',
            'url' => $demourl,
        ],
        /*
        'flw-teacher' => [
            'text' => 'Teacher',
            'url' => $dashboardurl . '#flw-teacher',
        ],
        'flw-collaboration' => [
            'text' => 'Collaboration',
            'url' => $dashboardurl . '#flw-collaboration',
        ],
        */
    ];

    if (is_siteadmin() || has_capability('moodle/site:config', context_system::instance())) {
        $links['administrationsite'] = [
            'text' => get_string('administrationsite'),
            'url' => (new moodle_url('/admin/search.php'))->out(false),
        ];
    }

    $currenturl = qualified_me();
    $moremenuid = $moremenu['moremenuid'] ?? 'flw-primary';
    foreach ($links as $key => $link) {
        $moremenu['nodearray'][] = [
            'key' => $key,
            'text' => $link['text'],
            'title' => $link['text'],
            'url' => $link['url'],
            'isactive' => theme_flwacademy_primary_navigation_is_active($key, $link['url'], $currenturl),
            'children' => [],
            'haschildren' => false,
            'moremenuid' => $moremenuid,
        ];
    }

    $primarymenu['moremenu'] = $moremenu;
    $primarymenu['mobileprimarynav'] = array_map(static function(array $link, string $key) use ($currenturl): array {
        return [
            'key' => $key,
            'text' => $link['text'],
            'url' => $link['url'],
            'isactive' => theme_flwacademy_primary_navigation_is_active($key, $link['url'], $currenturl),
        ];
    }, $links, array_keys($links));

    return $primarymenu;
}

/**
 * Exports the shared FLW top navigation context.
 *
 * @param core_renderer $output Page renderer.
 * @param array $primarymenu Prepared Boost primary navigation export data.
 * @param array $options Optional overrides.
 * @return array
 */
function theme_flwacademy_export_topnav_context(core_renderer $output, array $primarymenu, array $options = []): array {
    global $USER;

    $activekey = (string)($options['activekey'] ?? '');
    $source = $primarymenu['mobileprimarynav'] ?? ($primarymenu['moremenu']['nodearray'] ?? []);
    $navitems = [];
    $navicons = [
        'home' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8v10h-6v-6H9v6H3Z"></path></svg>',
        'myhome' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5"></rect><rect x="13" y="4" width="7" height="7" rx="1.5"></rect><rect x="4" y="13" width="7" height="7" rx="1.5"></rect><rect x="13" y="13" width="7" height="7" rx="1.5"></rect></svg>',
        'flw-school' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10 12 5l8 5-8 5Z"></path><path d="M6 12v5c1.8 1.3 3.8 2 6 2s4.2-.7 6-2v-5"></path></svg>',
        'flw-selfstudy' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5Z"></path><path d="M4 5.5v16"></path></svg>',
        'flw-practice' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v4"></path><path d="M12 17v4"></path><path d="M4.8 7.2 7.6 10"></path><path d="m16.4 14 2.8 2.8"></path><circle cx="12" cy="12" r="5"></circle></svg>',
        'flw-dictionary' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h6a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 1Z"></path><path d="M19 4h-5a3 3 0 0 0-3 3v13h5a3 3 0 0 1 3 1Z"></path><path d="M8 8h3M8 12h3M15 8h2"></path></svg>',
        'flw-exam' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10v3H7zM5 6h14v15H5z"></path><path d="M8 10h8M8 14h8M8 18h5"></path></svg>',
        'flw-demo' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v10H5Z"></path><path d="M9 20h6"></path><path d="M12 14v6"></path><path d="m10 8 5 3-5 3Z"></path></svg>',
        'administrationsite' => '<svg class="flw-topnav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.1 2.1-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V20h-3v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-2.1-2.1.1-.1A1.7 1.7 0 0 0 5 14.6a1.7 1.7 0 0 0-1.6-1H3v-3h.4A1.7 1.7 0 0 0 5 9.6a1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.1-2.1.1.1A1.7 1.7 0 0 0 8.7 6a1.7 1.7 0 0 0 1-1.6V4h3v.4a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.1 2.1-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.4v3H19a1.7 1.7 0 0 0-1.6 1Z"></path></svg>',
    ];

    foreach ($source as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = (string)($item['key'] ?? '');
        $url = (string)($item['url'] ?? '');
        if ($key === '' || $url === '') {
            continue;
        }
        $text = (string)($item['text'] ?? '');
        if ($key === 'myhome') {
            $text = 'Dashboard';
        }
        $isactive = !empty($item['isactive']);
        if ($activekey !== '') {
            $isactive = $key === $activekey;
        }
        $navitems[] = [
            'key' => $key,
            'text' => $text,
            'url' => $url,
            'isactive' => $isactive,
            'iconhtml' => $navicons[$key] ?? '',
        ];
    }

    $isloggedinuser = isloggedin() && !isguestuser();
    $userpicture = '';
    $userfullname = '';
    if ($isloggedinuser) {
        $userfullname = fullname($USER);
        $userpicture = $output->user_picture($USER, [
            'size' => 38,
            'link' => false,
            'class' => 'flw-topnav-avatar',
        ]);
    }

    return [
        'brandurl' => (new moodle_url('/', ['redirect' => 0]))->out(false),
        'navitems' => $navitems,
        'hasnavitems' => !empty($navitems),
        'usermenu' => $primarymenu['user'] ?? [],
        'isloggedinuser' => $isloggedinuser,
        'userfullname' => $userfullname,
        'userpicturehtml' => $userpicture,
        'profileurl' => $isloggedinuser ? (new moodle_url('/user/profile.php', ['id' => $USER->id]))->out(false) : '',
        'logouturl' => $isloggedinuser ? (new moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false) : '',
        'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    ];
}

/**
 * Sorts Moodle UI language menu items into the FLW product order.
 *
 * @param array $langmenu Boost language menu export data.
 * @return array
 */
function theme_flwacademy_sort_ui_language_menu(array $langmenu): array {
    if (empty($langmenu['items']) || !is_array($langmenu['items'])) {
        return $langmenu;
    }

    $langmenu['items'] = theme_flwacademy_sort_language_selector_items($langmenu['items']);
    return $langmenu;
}

/**
 * Sorts Moodle user-menu language selector submenus into the FLW product order.
 *
 * @param array $usermenu Boost user menu export data.
 * @return array
 */
function theme_flwacademy_sort_user_language_submenus(array $usermenu): array {
    if (empty($usermenu['submenus']) || !is_array($usermenu['submenus'])) {
        return $usermenu;
    }

    $languageselectortitle = get_string('languageselector');
    foreach ($usermenu['submenus'] as $index => $submenu) {
        $title = is_object($submenu) ? ($submenu->title ?? '') : ($submenu['title'] ?? '');
        if ($title !== $languageselectortitle && core_text::strtolower((string)$title) !== 'language selector') {
            continue;
        }
        $items = is_object($submenu) ? ($submenu->items ?? []) : ($submenu['items'] ?? []);
        if (!is_array($items)) {
            continue;
        }
        $items = theme_flwacademy_sort_language_selector_items($items);
        if (is_object($submenu)) {
            $submenu->items = $items;
            $usermenu['submenus'][$index] = $submenu;
        } else {
            $usermenu['submenus'][$index]['items'] = $items;
        }
    }

    return $usermenu;
}

/**
 * Sorts language selector items into the FLW product order.
 *
 * @param array $items Language menu items.
 * @return array
 */
function theme_flwacademy_sort_language_selector_items(array $items): array {
    $order = [
        'en' => 0,
        'ru' => 1,
        'zh' => 2,
        'zh_cn' => 2,
        'zh-cn' => 2,
        'de' => 3,
        'ja' => 4,
        'fr' => 5,
        'es' => 6,
    ];

    $codefromitem = static function($item): string {
        $itemarray = is_object($item) ? (array)$item : (array)$item;
        $link = $itemarray['link'] ?? $itemarray;
        $link = is_object($link) ? (array)$link : (array)$link;
        foreach (($link['attributes'] ?? []) as $attribute) {
            $attribute = is_object($attribute) ? (array)$attribute : (array)$attribute;
            if (($attribute['key'] ?? '') === 'lang' && !empty($attribute['value'])) {
                return strtolower(str_replace('-', '_', $attribute['value']));
            }
        }
        if (!empty($link['url'])) {
            $query = parse_url($link['url'], PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                if (!empty($params['lang'])) {
                    return strtolower(str_replace('-', '_', (string)$params['lang']));
                }
            }
        }
        $title = (string)($link['title'] ?? $link['text'] ?? '');
        if (preg_match('/\((en|ru|zh(?:[_-]cn)?|de|ja|fr|es)\)/i', $title, $matches)) {
            return strtolower(str_replace('-', '_', $matches[1]));
        }

        return '';
    };

    $indexed = [];
    foreach (array_values($items) as $index => $item) {
        $code = $codefromitem($item);
        $indexed[] = [
            'index' => $index,
            'sort' => $order[$code] ?? 100 + $index,
            'item' => $item,
        ];
    }
    usort($indexed, static function(array $left, array $right): int {
        return $left['sort'] <=> $right['sort'] ?: $left['index'] <=> $right['index'];
    });

    return array_column($indexed, 'item');
}

/**
 * Returns the FLW learning language list in the product order.
 *
 * @return array
 */
function theme_flwacademy_get_learning_language_definitions(): array {
    return [
        ['code' => 'en', 'label' => 'English', 'aliases' => ['English'], 'nav' => [
            'school' => 'K-12', 'selfstudy' => 'Self Study', 'practice' => 'Practice',
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
        ['code' => 'de', 'label' => 'German', 'aliases' => ['German'], 'nav' => [
            'school' => 'Schule', 'selfstudy' => 'Selbststudium', 'practice' => 'Übung',
            'dictionary' => 'Wörterbuch', 'exam' => 'Prüfung', 'teacher' => 'Lehrer',
            'collaboration' => 'Zusammenarbeit',
        ]],
        ['code' => 'ja', 'label' => 'Japanese', 'aliases' => ['Japanese'], 'nav' => [
            'school' => '学校', 'selfstudy' => '自習', 'practice' => '練習',
            'dictionary' => '辞書', 'exam' => '試験', 'teacher' => '教師',
            'collaboration' => 'コラボレーション',
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
 * Finds the first visible course in a category branch.
 *
 * @param stdClass|null $category Category record.
 * @return int
 */
function theme_flwacademy_get_first_visible_course_in_category(?stdClass $category): int {
    global $DB;

    if (!$category) {
        return 0;
    }

    $fullcategory = $DB->get_record('course_categories', ['id' => (int)$category->id], 'id,path', IGNORE_MISSING);
    if (!$fullcategory) {
        return 0;
    }

    $sql = "SELECT c.id
              FROM {course} c
              JOIN {course_categories} cc ON cc.id = c.category
             WHERE c.visible = 1
               AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathmatch', false) . ")
          ORDER BY cc.depth ASC, cc.sortorder ASC, c.sortorder ASC, c.fullname ASC";
    $course = $DB->get_record_sql($sql, [
        'categoryid' => (int)$fullcategory->id,
        'pathmatch' => $fullcategory->path . '/%',
    ], IGNORE_MULTIPLE);

    return $course ? (int)$course->id : 0;
}

/**
 * Builds a language-level Practice plugin URL.
 *
 * @param string $languagecode FLW language code.
 * @param string $mode Practice mode.
 * @return string
 */
function theme_flwacademy_get_practice_page_url(string $languagecode, string $mode = 'watch'): string {
    return (new moodle_url('/local/flwmedia/index.php', [
        'language' => clean_param($languagecode, PARAM_ALPHANUMEXT),
        'mode' => clean_param($mode, PARAM_ALPHA),
    ]))->out(false);
}

/**
 * Returns the official Exam module URL.
 *
 * @return string
 */
function theme_flwacademy_get_exam_page_url(): string {
    return (new moodle_url('/local/flwexam/take.php'))->out(false);
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
        $practicepageurl = theme_flwacademy_get_practice_page_url($language['code'], 'watch');
        foreach (theme_flwacademy_get_practice_menu_items() as $key => $item) {
            $practicesubmenu[$key . 'url'] = theme_flwacademy_get_practice_page_url($language['code'], $key);
        }

        $examsubmenu = [];
        $examparent = $matchedsubcategories['exam'] ?? null;
        $exampageurl = theme_flwacademy_get_exam_page_url();
        $examlevels = theme_flwacademy_get_exam_levels_for_language($language['code']);
        for ($levelindex = 0; $levelindex < 6; $levelindex++) {
            $level = $examlevels[$levelindex] ?? '';
            $slot = $levelindex + 1;
            $examsubmenu['exam' . $slot . 'label'] = $level;
            $examsubmenu['exam' . $slot . 'url'] = $exampageurl;
        }

        $languages[] = [
            'code' => $language['code'],
            'label' => $language['label'],
            'categoryurl' => $url->out(false),
            'schoolcategoryurl' => $categoryurls['school'],
            'selfstudycategoryurl' => $categoryurls['selfstudy'],
            'placementtesturl' => (new moodle_url('/local/flwplacement/index.php', [
                'language' => $language['code'],
            ]))->out(false),
            'practicecategoryurl' => $practicepageurl,
            'examcategoryurl' => $exampageurl,
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
 * Adds the reusable FLW floating tools context to a layout/template context.
 *
 * @param array $context Existing template context.
 * @return array
 */
function theme_flwacademy_extend_tools_context(array $context): array {
    global $CFG, $PAGE;

    $learninglanguages = $context['learninglanguages'] ?? theme_flwacademy_export_learning_languages();
    $currentlanguagecode = clean_param($context['currentlanguagecode'] ?? ($_COOKIE['flw_learning_language'] ?? ''), PARAM_ALPHANUMEXT);
    if ($currentlanguagecode !== '') {
        $learninglanguages = array_map(static function(array $language) use ($currentlanguagecode): array {
            $language['isdefault'] = ($language['code'] ?? '') === $currentlanguagecode;
            return $language;
        }, $learninglanguages);
    }

    $currentlanguagelabel = $learninglanguages[0]['label'] ?? 'English';
    foreach ($learninglanguages as $language) {
        if (!empty($language['isdefault'])) {
            $currentlanguagelabel = $language['label'] ?? $currentlanguagelabel;
            break;
        }
    }

    $dictionaryurl = is_readable($CFG->dirroot . '/local/mldict/index.php')
        ? (new moodle_url('/local/mldict/index.php'))->out(false)
        : (new moodle_url('/my/'))->out(false) . '#flw-dictionary';
    $isscormpage = strpos($PAGE->pagetype, 'mod-scorm-') === 0;
    $iscoursepage = strpos($PAGE->pagetype, 'course-view-') === 0 || $PAGE->pagetype === 'course-view';
    $isdashboardpage = $PAGE->pagetype === 'my-index';
    $currentlocalurl = $PAGE->url ? $PAGE->url->out_as_local_url(false) : '';
    $isadminpage = strpos($currentlocalurl, '/admin/') === 0 || $currentlocalurl === '/admin/index.php';
    $isflwexampage = strpos($PAGE->pagetype, 'local-flwexam-') === 0
        || strpos($currentlocalurl, '/local/flwexam/') === 0;

    $context['hasflwtools'] = true;
    $context['hasflwdictionary'] = $dictionaryurl !== '' && !$isflwexampage && !$isdashboardpage;
    $context['flwdictionaryurl'] = $dictionaryurl;
    $context['haslearninglanguages'] = !empty($learninglanguages);
    $context['learninglanguages'] = $learninglanguages;
    $context['currentlanguagecode'] = $currentlanguagecode;
    $context['currentlanguagelabel'] = $currentlanguagelabel;
    $context['flwtoolsshowlanguage'] = !empty($learninglanguages) && !$isscormpage && !$iscoursepage;
    $context['flwtoolsshowdone'] = !$isscormpage && !$isdashboardpage && !$isadminpage;
    $context['flwtoolsshowcourseindex'] = $context['flwtoolsshowcourseindex']
        ?? (!empty($context['courseindex']) && !$isscormpage);
    $context['flwtoolsshowscormtoc'] = $PAGE->pagetype === 'mod-scorm-player' && !$PAGE->user_is_editing();
    $context['flwtoolsshowscormdone'] = $PAGE->pagetype === 'mod-scorm-player' && !$PAGE->user_is_editing();
    $context['flwscormcmid'] = !empty($PAGE->cm->id) ? (int)$PAGE->cm->id : 0;
    $context['flwscormcourseid'] = !empty($PAGE->course->id) ? (int)$PAGE->course->id : 0;
    $context['currentcategorytype'] = $context['currentcategorytype'] ?? '';

    return $context;
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
 * Returns true when the category is the FLW Demo category or inside it.
 *
 * @param stdClass $category
 * @return bool
 */
function theme_flwacademy_is_demo_category(stdClass $category): bool {
    foreach (theme_flwacademy_get_category_lineage($category) as $lineagecategory) {
        if (core_text::strtolower(trim((string)$lineagecategory->name)) === 'demo') {
            return true;
        }
    }

    return false;
}

/**
 * Gets the first course overview image URL, with a Self Study fallback.
 *
 * @param int $courseid
 * @param core_renderer $output
 * @return string
 */
function theme_flwacademy_get_course_cover_url(int $courseid, core_renderer $output): string {
    $context = context_course::instance($courseid);
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'course',
        'overviewfiles',
        false,
        'sortorder ASC, id ASC',
        false
    );

    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
    }

    return $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false);
}

/**
 * Returns a FLW redesign asset URL.
 *
 * @param core_renderer $output
 * @param string $name
 * @return string
 */
function theme_flwacademy_redesign_asset_url(core_renderer $output, string $name): string {
    return $output->image_url('redesign/' . $name, 'theme_flwacademy')->out(false);
}

/**
 * Returns the static prototype crest asset for a FLW language world.
 *
 * @param string $languagecode
 * @param string $hint
 * @return string
 */
function theme_flwacademy_get_world_crest_asset(string $languagecode, string $hint = ''): string {
    $hint = core_text::strtolower($hint);
    if ($languagecode === 'en' && strpos($hint, 'adventure') !== false) {
        return 'crest-adventure';
    }

    $assets = [
        'en' => 'crest-real',
        'ru' => 'crest-russian',
        'zh' => 'crest-chinese',
        'de' => 'crest-german',
        'ja' => 'crest-japanese',
        'fr' => 'crest-french',
        'es' => 'crest-spanish',
    ];

    return $assets[$languagecode] ?? 'crest-real';
}

/**
 * Returns the learner-facing language world label used by the prototype UI.
 *
 * @param array $language
 * @param bool $includeversion
 * @return string
 */
function theme_flwacademy_get_world_label(array $language, bool $includeversion = false): string {
    $label = trim((string)($language['label'] ?? 'English'));
    $worldlabel = $label . ' World';
    return $includeversion ? $worldlabel . ' V2' : $worldlabel;
}

/**
 * Extracts a compact unit label from course/section text.
 *
 * @param string $text
 * @return string
 */
function theme_flwacademy_extract_unit_label(string $text): string {
    if (preg_match('/\bunit\s*0*(\d+)\b/i', $text, $matches)) {
        return 'Unit ' . (int)$matches[1];
    }
    if (preg_match('/\bu0*(\d+)\b/i', $text, $matches)) {
        return 'Unit ' . (int)$matches[1];
    }

    return '';
}

/**
 * Returns a CSS-safe percentage.
 *
 * @param int|float $value
 * @return string
 */
function theme_flwacademy_percent_width($value): string {
    $percent = max(0, min(100, (int)round((float)$value)));
    return $percent . '%';
}

/**
 * Returns user activity completion progress for a course.
 *
 * @param stdClass $course
 * @param int $userid
 * @return array
 */
function theme_flwacademy_get_course_progress_summary(stdClass $course, int $userid): array {
    global $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    $total = 0;
    $completed = 0;
    if ($userid > 0 && !isguestuser()) {
        try {
            $completion = new completion_info($course);
            $modinfo = get_fast_modinfo($course, $userid);
            foreach ($modinfo->cms as $cm) {
                if (!$cm->uservisible || (int)$cm->completion === COMPLETION_TRACKING_NONE) {
                    continue;
                }
                $total++;
                $data = $completion->get_data($cm, false, $userid);
                if (in_array((int)$data->completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                    COMPLETION_COMPLETE_FAIL,
                ], true)) {
                    $completed++;
                }
            }
        } catch (Throwable $exception) {
            $total = 0;
            $completed = 0;
        }
    }

    $percent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
    return [
        'completed' => $completed,
        'total' => $total,
        'percent' => $percent,
        'progress' => theme_flwacademy_percent_width($percent),
        'meta' => $total > 0 ? $completed . ' / ' . $total . ' activities' : 'Open course',
        'label' => $total > 0 ? $percent . '% complete' : 'Ready to start',
    ];
}

/**
 * Returns enrolled courses for the current Moodle user.
 *
 * @param int $limit
 * @return array
 */
function theme_flwacademy_get_user_courses(int $limit = 12): array {
    global $CFG;

    if (!isloggedin() || isguestuser()) {
        return [];
    }

    require_once($CFG->libdir . '/enrollib.php');
    return array_values(enrol_get_my_courses(
        'format, summary, summaryformat',
        'ul.timeaccess DESC, c.sortorder ASC',
        $limit
    ));
}

/**
 * Counts visible courses inside a category branch.
 *
 * @param int $categoryid
 * @return int
 */
function theme_flwacademy_count_visible_courses_in_category(int $categoryid): int {
    global $DB;

    if ($categoryid <= 0) {
        return 0;
    }
    $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id,path', IGNORE_MISSING);
    if (!$category) {
        return 0;
    }

    return (int)$DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
          WHERE c.id <> :siteid
            AND c.visible = 1
            AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathmatch', false) . ")",
        [
            'siteid' => SITEID,
            'categoryid' => $category->id,
            'pathmatch' => $category->path . '/%',
        ]
    );
}

/**
 * Finds the first visible course matching a name fragment in a category branch.
 *
 * @param stdClass|null $category
 * @param string $needle
 * @return stdClass|null
 */
function theme_flwacademy_find_visible_course_by_name(?stdClass $category, string $needle): ?stdClass {
    global $DB;

    $needle = trim(core_text::strtolower($needle));
    if (!$category || $needle === '') {
        return null;
    }

    $category = $DB->get_record('course_categories', ['id' => (int)$category->id], 'id,path', IGNORE_MISSING);
    if (!$category) {
        return null;
    }

    $sql = "SELECT c.*
              FROM {course} c
              JOIN {course_categories} cc ON cc.id = c.category
             WHERE c.id <> :siteid
               AND c.visible = 1
               AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathmatch', false) . ")
               AND LOWER(c.fullname) LIKE :namepattern
          ORDER BY cc.depth ASC, cc.sortorder ASC, c.sortorder ASC, c.fullname ASC";
    return $DB->get_record_sql($sql, [
        'siteid' => SITEID,
        'categoryid' => (int)$category->id,
        'pathmatch' => $category->path . '/%',
        'namepattern' => '%' . $DB->sql_like_escape($needle) . '%',
    ], IGNORE_MULTIPLE) ?: null;
}

/**
 * Resolves the currently selected FLW learning language.
 *
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_get_selected_learning_language(array $learninglanguages): array {
    $selectedcode = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
    foreach ($learninglanguages as $language) {
        if (($language['code'] ?? '') === $selectedcode) {
            return $language;
        }
    }

    return $learninglanguages[0] ?? [
        'code' => 'en',
        'label' => 'English',
        'categoryurl' => (new moodle_url('/course/index.php'))->out(false),
    ];
}

/**
 * Returns home-page course cards using Moodle categories/courses/progress.
 *
 * @param core_renderer $output
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_export_home_course_cards(core_renderer $output, array $learninglanguages): array {
    global $DB, $USER;

    $ranges = [
        'ru' => 'A1 to C1',
        'zh' => 'A1 to C1',
        'de' => 'A1 to C2',
        'ja' => 'A1 to C1',
        'fr' => 'A1 to B2',
        'es' => 'A1 to B2',
    ];
    $cards = [];
    foreach ($learninglanguages as $language) {
        $code = $language['code'] ?? 'en';
        $categoryid = 0;
        if (!empty($language['categoryurl'])) {
            $query = parse_url($language['categoryurl'], PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                $categoryid = (int)($params['categoryid'] ?? 0);
            }
        }
        $category = $categoryid > 0
            ? $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING)
            : null;
        $courseid = $category
            ? theme_flwacademy_get_first_visible_course_in_category($category)
            : 0;
        $course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
        $coursecount = $categoryid > 0 ? theme_flwacademy_count_visible_courses_in_category($categoryid) : 0;
        $carddefs = $code === 'en' ? [
            [
                'name' => 'Adventure English World',
                'range' => 'Pre-A1 to B1',
                'asset' => 'crest-adventure',
                'needle' => 'adventure english',
            ],
            [
                'name' => 'Real English World',
                'range' => 'A1 to C1',
                'asset' => 'crest-real',
                'needle' => 'real english',
            ],
        ] : [
            [
                'name' => theme_flwacademy_get_world_label($language),
                'range' => $ranges[$code] ?? 'Language pathway',
                'asset' => theme_flwacademy_get_world_crest_asset($code),
                'needle' => '',
            ],
        ];

        foreach ($carddefs as $carddef) {
            $cardcourse = $carddef['needle'] !== ''
                ? theme_flwacademy_find_visible_course_by_name($category, $carddef['needle'])
                : $course;
            $cardcourse = $cardcourse ?: $course;
            $progress = $cardcourse ? theme_flwacademy_get_course_progress_summary($cardcourse, (int)($USER->id ?? 0)) : null;
            $cards[] = [
                'name' => $carddef['name'],
                'range' => $carddef['range'],
                'status' => $progress && $progress['total'] > 0
                    ? $progress['label']
                    : ($coursecount > 0 ? $coursecount . ' courses' : 'Ready to learn'),
                'progress' => $progress ? $progress['progress'] : '0%',
                'cresturl' => theme_flwacademy_redesign_asset_url($output, $carddef['asset']),
                'alt' => $carddef['name'] . ' crest',
                'url' => $cardcourse
                    ? (new moodle_url('/course/view.php', ['id' => (int)$cardcourse->id]))->out(false)
                    : ($language['selfstudycategoryurl'] ?? $language['categoryurl'] ?? (new moodle_url('/course/index.php'))->out(false)),
            ];
        }
    }

    return $cards;
}

/**
 * Returns dashboard data for the current Moodle learner.
 *
 * @param core_renderer $output
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_export_dashboard_data(core_renderer $output, array $learninglanguages): array {
    global $CFG, $DB, $USER;

    require_once($CFG->libdir . '/completionlib.php');

    $selectedlanguage = theme_flwacademy_get_selected_learning_language($learninglanguages);
    $selectedcode = $selectedlanguage['code'] ?? 'en';
    $selectedlabel = $selectedlanguage['label'] ?? 'English';
    $selectedselfstudyid = 0;
    if (!empty($selectedlanguage['selfstudycategoryurl'])) {
        $query = parse_url($selectedlanguage['selfstudycategoryurl'], PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            $selectedselfstudyid = (int)($params['categoryid'] ?? 0);
        }
    }

    $courses = theme_flwacademy_get_user_courses(20);
    $course = null;
    $selectedcategory = $selectedselfstudyid > 0
        ? $DB->get_record('course_categories', ['id' => $selectedselfstudyid], '*', IGNORE_MISSING)
        : null;
    if ($selectedcategory) {
        foreach ($courses as $enrolledcourse) {
            $coursecategoryid = (int)($enrolledcourse->category ?? 0);
            if ($coursecategoryid <= 0) {
                continue;
            }
            $coursecategory = $DB->get_record('course_categories', ['id' => $coursecategoryid], 'id,path', IGNORE_MISSING);
            if ($coursecategory && ((int)$coursecategory->id === (int)$selectedcategory->id ||
                    strpos($coursecategory->path, $selectedcategory->path . '/') === 0)) {
                $course = $enrolledcourse;
                break;
            }
        }
        if (!$course) {
            $courseid = theme_flwacademy_get_first_visible_course_in_category($selectedcategory);
            $course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
        }
    }
    if (!$course) {
        $course = $courses[0] ?? null;
    }

    $placement = theme_flwacademy_export_selfstudy_placement_profile((int)($USER->id ?? 0), $selectedcode, $selectedselfstudyid);
    $progress = $course ? theme_flwacademy_get_course_progress_summary($course, (int)($USER->id ?? 0)) : [
        'completed' => 0,
        'total' => 0,
        'percent' => 0,
        'progress' => '0%',
        'meta' => 'No activities yet',
        'label' => 'Ready to start',
    ];

    $courseurl = $course
        ? (new moodle_url('/course/view.php', ['id' => (int)$course->id]))->out(false)
        : ($selectedlanguage['selfstudycategoryurl'] ?? (new moodle_url('/course/index.php'))->out(false));
    $coursename = $course ? format_string($course->fullname) : $selectedlabel . ' World';
    $worldlabel = theme_flwacademy_get_world_label($selectedlanguage, true);
    $worldcresturl = theme_flwacademy_redesign_asset_url(
        $output,
        theme_flwacademy_get_world_crest_asset($selectedcode, $coursename)
    );
    $unitlabel = theme_flwacademy_extract_unit_label($coursename);
    if ($unitlabel === '' && $placement && !empty($placement['recommendedstartunit'])) {
        $unitlabel = 'Unit ' . (int)$placement['recommendedstartunit'];
    }
    if ($unitlabel === '') {
        $unitlabel = 'Course path';
    }

    $todayitems = [];
    $unitnodes = [];
    if ($course) {
        $modinfo = get_fast_modinfo($course, (int)($USER->id ?? 0));
        $completion = new completion_info($course);
        $sectionstatus = [];
        foreach ($modinfo->cms as $cm) {
            if (!$cm->uservisible || !$cm->url) {
                continue;
            }
            $complete = false;
            if ((int)$cm->completion !== COMPLETION_TRACKING_NONE) {
                $data = $completion->get_data($cm, false, (int)($USER->id ?? 0));
                $complete = in_array((int)$data->completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                    COMPLETION_COMPLETE_FAIL,
                ], true);
            }
            $sectionstatus[$cm->sectionnum]['total'] = ($sectionstatus[$cm->sectionnum]['total'] ?? 0) + 1;
            $sectionstatus[$cm->sectionnum]['complete'] = ($sectionstatus[$cm->sectionnum]['complete'] ?? 0) + ($complete ? 1 : 0);
            if (!$complete && count($todayitems) < 3) {
                $todayitems[] = [
                    'class' => ['blue', 'green', 'purple'][count($todayitems)] ?? 'blue',
                    'title' => format_string($cm->name),
                    'meta' => $unitlabel . ' · ' . get_string('pluginname', 'mod_' . $cm->modname),
                    'time' => 'Open',
                    'url' => $cm->url->out(false),
                ];
            }
        }

        $firstactive = false;
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int)$section->section === 0 || !$section->uservisible) {
                continue;
            }
            $status = $sectionstatus[$section->section] ?? ['total' => 0, 'complete' => 0];
            $complete = $status['total'] > 0 && $status['complete'] >= $status['total'];
            $active = !$complete && !$firstactive;
            if ($active) {
                $firstactive = true;
            }
            $unitnodes[] = [
                'class' => $complete ? 'complete' : ($active ? 'active' : ''),
                'title' => get_section_name($course, $section),
                'status' => $complete ? 'Complete' : ($active ? 'In progress' : 'Next'),
                'symbol' => $complete ? '●' : ($active ? '●' : '○'),
            ];
            if (count($unitnodes) >= 10) {
                break;
            }
        }
    }

    if (!$todayitems) {
        $todayitems[] = [
            'class' => 'blue',
            'title' => 'Start learning',
            'meta' => $coursename,
            'time' => 'Open',
            'url' => $courseurl,
        ];
    }
    if (!$unitnodes) {
        $unitnodes[] = [
            'class' => 'active',
            'title' => $unitlabel,
            'status' => 'Ready',
            'symbol' => '●',
        ];
    }

    $skillrows = [];
    $skillclassmap = [
        'Listening' => ['listen', 'blue'],
        'Speaking' => ['speak', 'green'],
        'Reading' => ['read', 'purple'],
        'Writing' => ['write', 'orange'],
        'Grammar' => ['dictate', 'cyan'],
        'Vocabulary' => ['dictate', 'cyan'],
    ];
    foreach (($placement['skillitems'] ?? []) as $skillitem) {
        $score = (int)preg_replace('/\D+/', '', (string)($skillitem['score'] ?? '0'));
        $label = $skillitem['label'] ?? 'Skill';
        $classes = $skillclassmap[$label] ?? ['dictate', 'cyan'];
        $skillrows[] = [
            'class' => $classes[0],
            'iconclass' => $classes[1],
            'label' => $label,
            'percent' => $score > 0 ? $score . '%' : '0%',
            'width' => theme_flwacademy_percent_width($score),
            'islisten' => $classes[0] === 'listen',
            'isspeak' => $classes[0] === 'speak',
            'isread' => $classes[0] === 'read',
            'iswrite' => $classes[0] === 'write',
            'isdictate' => $classes[0] === 'dictate',
        ];
    }
    if (!$skillrows) {
        $fallbackskills = [
            ['Listening', 0, 'listen'],
            ['Speaking', 0, 'speak'],
            ['Reading', 0, 'read'],
            ['Writing', 0, 'write'],
            ['Dictation', 0, 'dictate'],
        ];
        foreach ($fallbackskills as $skill) {
            $skillrows[] = [
                'label' => $skill[0],
                'percent' => $skill[1] . '%',
                'width' => theme_flwacademy_percent_width($skill[1]),
                'class' => $skill[2],
                'iconclass' => ['listen' => 'blue', 'speak' => 'green', 'read' => 'purple', 'write' => 'orange'][$skill[2]] ?? 'cyan',
                'islisten' => $skill[2] === 'listen',
                'isspeak' => $skill[2] === 'speak',
                'isread' => $skill[2] === 'read',
                'iswrite' => $skill[2] === 'write',
                'isdictate' => $skill[2] === 'dictate',
            ];
        }
    }

    $dictcount = $DB->get_manager()->table_exists('local_mldict_entry')
        ? (int)$DB->count_records('local_mldict_entry', ['sourcelang' => $selectedcode])
        : 0;
    if ($dictcount === 0 && $selectedcode === 'zh' && $DB->get_manager()->table_exists('local_mldict_entry')) {
        $dictcount = (int)$DB->count_records('local_mldict_entry', ['sourcelang' => 'zh_cn']);
    }

    $completedcourses = 0;
    foreach ($courses as $enrolledcourse) {
        $summary = theme_flwacademy_get_course_progress_summary($enrolledcourse, (int)($USER->id ?? 0));
        if ($summary['total'] > 0 && $summary['completed'] >= $summary['total']) {
            $completedcourses++;
        }
    }

    return [
        'currentlanguagecode' => $selectedcode,
        'currentlanguagelabel' => $selectedlabel,
        'currentworldname' => $worldlabel,
        'currentworldcresturl' => $worldcresturl,
        'currentcourse' => [
            'name' => $coursename,
            'subtitle' => $unitlabel,
            'summary' => $course && !empty($course->summary)
                ? format_string(strip_tags(format_text($course->summary, $course->summaryformat ?? FORMAT_HTML)))
                : ($placement['studyrecommendation'] ?? 'Continue from your next useful learning step.'),
            'url' => $courseurl,
            'imageurl' => $worldcresturl,
            'progress' => $progress['progress'],
            'progresslabel' => $progress['label'],
            'progressmeta' => $progress['meta'],
        ],
        'todayitems' => $todayitems,
        'unitnodes' => $unitnodes,
        'journey' => [
            'level' => $placement['overallcefr'] ?? '-',
            'small' => $placement ? 'Placement' : 'Not placed',
            'progresslabel' => $placement ? (($placement['confidencepercent'] ?? '0%') . ' confidence') : 'Take placement test',
            'reporturl' => $placement['reporturl'] ?? ($selectedlanguage['placementtesturl'] ?? (new moodle_url('/local/flwplacement/index.php'))->out(false)),
        ],
        'skillrows' => $skillrows,
        'vocab' => [
            'total' => $dictcount,
            'strong' => max(0, (int)round($dictcount * 0.7)),
            'good' => max(0, (int)round($dictcount * 0.2)),
            'review' => max(0, $dictcount - (int)round($dictcount * 0.9)),
            'strongwidth' => theme_flwacademy_percent_width(70),
            'goodwidth' => theme_flwacademy_percent_width(20),
            'reviewwidth' => theme_flwacademy_percent_width(10),
            'url' => (new moodle_url('/local/mldict/index.php', ['lang' => $selectedcode]))->out(false),
        ],
        'checkpoint' => [
            'title' => $placement && !empty($placement['nextcheckpointunit']) ? 'Unit ' . (int)$placement['nextcheckpointunit'] . ' Checkpoint' : 'Placement checkpoint',
            'meta' => $placement ? 'Recommended from your latest placement profile' : 'Take placement to create your path',
            'url' => $placement['checkpointurl'] ?? ($selectedlanguage['placementtesturl'] ?? '#'),
        ],
        'portfolio' => [
            'projects' => $completedcourses,
            'certificates' => 0,
            'artifacts' => count($courses),
            'url' => (new moodle_url('/my/'))->out(false),
        ],
        'rank' => [
            'title' => count($courses) > 0 ? 'You are enrolled in ' . count($courses) . ' courses' : 'Your learning path is ready',
            'text' => $progress['total'] > 0 ? $progress['meta'] : 'Open a course or take placement to begin.',
        ],
    ];
}

/**
 * Finds the visible Self Study course for a numbered unit.
 *
 * @param int $categoryid
 * @param int $unit
 * @return string
 */
function theme_flwacademy_get_selfstudy_unit_url(int $categoryid, int $unit): string {
    global $DB;

    if ($categoryid <= 0 || $unit <= 0) {
        return '';
    }
    $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id, path', IGNORE_MISSING);
    if (!$category) {
        return '';
    }

    $courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
          WHERE c.id <> :siteid
            AND c.visible = 1
            AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathprefix', false) . ")
          ORDER BY cc.sortorder ASC, c.sortorder ASC",
        [
            'siteid' => SITEID,
            'categoryid' => $category->id,
            'pathprefix' => $category->path . '/%',
        ]
    );

    $unitpattern = '/(?:\bunit\s*0*' . preg_quote((string)$unit, '/') . '\b|\bu0*' . preg_quote((string)$unit, '/') . '\b)/i';
    foreach ($courses as $course) {
        $haystack = $course->fullname . ' ' . $course->shortname;
        if (preg_match($unitpattern, $haystack)) {
            return (new moodle_url('/course/view.php', ['id' => (int)$course->id]))->out(false);
        }
    }

    return '';
}

/**
 * Exports the current learner's latest placement profile for a Self Study language.
 *
 * @param int $userid
 * @param string $languagecode
 * @param int $selfstudycategoryid
 * @return array|null
 */
function theme_flwacademy_export_selfstudy_placement_profile(int $userid, string $languagecode, int $selfstudycategoryid = 0): ?array {
    global $DB;

    if ($userid <= 0 || isguestuser() || !$DB->get_manager()->table_exists('local_flwplacement_profile')) {
        return null;
    }

    $languagevalues = [
        'en' => 'english',
        'ru' => 'russian',
        'zh' => 'chinese',
        'ja' => 'japanese',
        'de' => 'german',
        'fr' => 'french',
        'es' => 'spanish',
    ];
    $languagevalue = $languagevalues[$languagecode] ?? 'english';
    $coursekeyprefix = 'FLW_' . strtoupper($languagevalue) . '_';
    $profiles = $DB->get_records_select(
        'local_flwplacement_profile',
        'userid = :userid AND ' . $DB->sql_like('coursekey', ':coursekey', false),
        [
            'userid' => $userid,
            'coursekey' => $coursekeyprefix . '%',
        ],
        'timemodified DESC',
        '*',
        0,
        1
    );
    if (!$profiles) {
        return null;
    }

    $profile = reset($profiles);
    $skilllevels = json_decode($profile->skilllevelsjson ?? '[]', true) ?: [];
    $supportflags = json_decode($profile->supportflagsjson ?? '[]', true) ?: [];
    $learningpath = json_decode($profile->learningpathjson ?? '[]', true) ?: [];
    $profilejson = json_decode($profile->profilejson ?? '[]', true) ?: [];
    $skillpercentages = $profilejson['skill_percentages'] ?? [];

    $skillorder = ['listening', 'speaking', 'reading', 'writing', 'grammar', 'vocabulary'];
    $skillitems = [];
    foreach ($skillorder as $skill) {
        if (!array_key_exists($skill, $skilllevels) && !array_key_exists($skill, $skillpercentages)) {
            continue;
        }
        $score = isset($skillpercentages[$skill]) ? (int)round((float)$skillpercentages[$skill]) : null;
        $skillitems[] = [
            'label' => ucfirst($skill),
            'level' => $skilllevels[$skill] ?? '-',
            'score' => $score === null ? '' : $score . '%',
            'hasscore' => $score !== null,
        ];
    }

    $supportitems = [];
    foreach ($supportflags as $key => $enabled) {
        if (!$enabled || $key === 'teacher_review_recommended') {
            continue;
        }
        $label = ucfirst(str_replace('_', ' ', preg_replace('/^needs_|_support$|_repair$/', '', $key)));
        $supportitems[] = ['label' => $label];
    }

    $statuslabels = [
        'confirmed' => 'Confirmed',
        'provisional' => 'Provisional',
        'teacher_review_required' => 'Teacher review required',
    ];
    $pathlabels = [
        'teacher_review_first' => 'Teacher review first',
        'main_path_with_repair' => 'Main path with repair',
        'review_path' => 'Review path',
        'main_path' => 'Main path',
    ];
    $confidence = (int)round(((float)$profile->placementconfidence) * 100);
    $recommendedstartunit = (int)$profile->recommendedstartunit;
    $startuniturl = theme_flwacademy_get_selfstudy_unit_url($selfstudycategoryid, $recommendedstartunit);
    $hasexactstartuniturl = $startuniturl !== '';
    if (!$hasexactstartuniturl && $selfstudycategoryid > 0) {
        $startuniturl = (new moodle_url('/course/index.php', ['categoryid' => $selfstudycategoryid]))->out(false);
    }
    $nextcheckpointunit = (int)$profile->nextcheckpointunit;
    $checkpointurl = theme_flwacademy_get_selfstudy_unit_url($selfstudycategoryid, $nextcheckpointunit);
    $hasexactcheckpointurl = $checkpointurl !== '';
    if (!$hasexactcheckpointurl && $selfstudycategoryid > 0) {
        $checkpointurl = (new moodle_url('/course/index.php', ['categoryid' => $selfstudycategoryid]))->out(false);
    }
    $updateddate = new DateTime('@' . (int)$profile->timemodified);
    $updateddate->setTimezone(core_date::get_user_timezone_object());

    return [
        'overallcefr' => $profile->overallcefr ?: '-',
        'recommendedstartunit' => $recommendedstartunit,
        'startuniturl' => $startuniturl,
        'hasstartuniturl' => $startuniturl !== '',
        'startunitbuttonlabel' => $startuniturl === '' ? '' : ($hasexactstartuniturl ? 'Go to Unit ' . $recommendedstartunit : 'Find Unit ' . $recommendedstartunit),
        'nextcheckpointunit' => $nextcheckpointunit,
        'checkpointurl' => $checkpointurl,
        'hascheckpointurl' => $checkpointurl !== '',
        'checkpointbuttonlabel' => $checkpointurl === '' ? '' : ($hasexactcheckpointurl ? 'Go to Unit ' . $nextcheckpointunit : 'Find Unit ' . $nextcheckpointunit),
        'confidencepercent' => $confidence . '%',
        'placementstatus' => $statuslabels[$profile->placementstatus] ?? ucfirst(str_replace('_', ' ', $profile->placementstatus ?: 'pending')),
        'pathmode' => $pathlabels[$learningpath['start_mode'] ?? ''] ?? 'Main path',
        'skillitems' => $skillitems,
        'hasskillitems' => !empty($skillitems),
        'supportitems' => $supportitems,
        'hassupportitems' => !empty($supportitems),
        'teacherreview' => !empty($supportflags['teacher_review_recommended']),
        'studyrecommendation' => $profilejson['study_recommendation'] ?? 'Continue from the recommended unit and review any weak skill areas first.',
        'reporturl' => (new moodle_url('/local/flwplacement/view.php', ['id' => (int)$profile->latestresultid]))->out(false),
        'updatedlabel' => $updateddate->format('Y.m.d H:i:s'),
    ];
}

/**
 * Exports FLW Self Study category page data.
 *
 * @param int $categoryid
 * @param core_renderer $output
 * @return array
 */
function theme_flwacademy_export_selfstudy_category_page(int $categoryid, core_renderer $output): array {
    global $DB, $USER;

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
            'imageurl' => theme_flwacademy_get_course_cover_url((int)$course->id, $output),
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
    $placementprofile = theme_flwacademy_export_selfstudy_placement_profile((int)$USER->id, $languageCode, (int)$category->id);

    return [
        'language' => $languageLabel,
        'languagecode' => $languageCode,
        'title' => format_string($category->name),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $languagecategory->id]))->out(false),
        'placementtesturl' => (new moodle_url('/local/flwplacement/index.php', [
            'language' => $languageCode,
        ]))->out(false),
        'heroimageurl' => $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false),
        'languagetiles' => $languageTiles,
        'hasplacementprofile' => $placementprofile !== null,
        'placementprofile' => $placementprofile ?? [],
        'mapnodes' => $mapnodes,
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
    ];
}

/**
 * Exports FLW Demo category page data using the Self Study-style page treatment.
 *
 * @param int $categoryid
 * @param core_renderer $output
 * @return array
 */
function theme_flwacademy_export_demo_category_page(int $categoryid, core_renderer $output): array {
    global $DB;

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $description = '';
    if (!empty($category->description)) {
        $description = format_text($category->description, $category->descriptionformat, [
            'context' => context_coursecat::instance($category->id),
            'overflowdiv' => true,
            'filter' => false,
        ]);
    }

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
            'pathprefix' => $category->path . '/%',
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
            'imageurl' => theme_flwacademy_get_course_cover_url((int)$course->id, $output),
        ];
    }

    return [
        'language' => 'Demo',
        'languagecode' => '',
        'title' => format_string($category->name),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'heroimageurl' => $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false),
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
        'coursecount' => count($courseitems),
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
    $languageCode = $language['code'] ?? 'en';

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
        'languagecode' => $languageCode,
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
    $languageCode = $language['code'];
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

    $practiceMenuItems = theme_flwacademy_get_practice_menu_items();
    $currentpracticekey = '';
    if ($area === 'practice' && $itemcategory) {
        foreach ($practiceMenuItems as $key => $item) {
            if (core_text::strtolower($item['label']) === core_text::strtolower($itemcategory->name)) {
                $currentpracticekey = $key;
                break;
            }
        }
    }

    $practiceitems = [];
    foreach ($practiceMenuItems as $key => $item) {
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
            'isactive' => $area === 'practice' && $currentpracticekey === $key,
        ];
    }

    $currentpractice = null;
    if ($area === 'practice' && $itemcategory) {
        foreach ($practiceMenuItems as $key => $item) {
            if (core_text::strtolower($item['label']) === core_text::strtolower($itemcategory->name)) {
                $currentpractice = $item + ['key' => $key];
                break;
            }
        }
    }
    $practicehero = $currentpractice ?: $practiceMenuItems['watch'] + ['key' => 'watch'];

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
        'languagecode' => $languageCode,
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
        'examheroimageurl' => $output->image_url('dashboard2/exam-hero-clear', 'theme_flwacademy')->out(false),
        'examlevels' => $childitems,
        'hasexamlevels' => !empty($childitems),
        'examframework' => $framework,
        'selectedlevel' => $selectedlevel,
        'examsamples' => $samples,
        'watchcards' => $watchcards,
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $resolved['languagecategory']->id]))->out(false),
    ];
}
