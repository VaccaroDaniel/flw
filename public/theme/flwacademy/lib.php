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

    $tokens = __DIR__ . '/scss/tokens.scss';
    if (is_readable($tokens)) {
        $scss .= "\n\n" . file_get_contents($tokens);
    }

    $system = __DIR__ . '/scss/system.scss';
    if (is_readable($system)) {
        $scss .= "\n\n" . file_get_contents($system);
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
 * Gets a theme cache store by name with safe fallback.
 *
 * @param string $name Cache definition name.
 * @return \cache|null
 */
function theme_flwacademy_get_cache_store(string $name): ?\cache {
    static $stores = [];

    if (array_key_exists($name, $stores)) {
        return $stores[$name];
    }

    try {
        $stores[$name] = \cache::make('theme_flwacademy', $name);
        return $stores[$name];
    } catch (\Throwable $exception) {
        $stores[$name] = null;
        return null;
    }
}

/**
 * Builds a stable cache key for FLW page exports.
 *
 * @param string $scope
 * @param array $parts
 * @return string
 */
function theme_flwacademy_export_cache_key(string $scope, array $parts): string {
    return $scope . '|' . sha1(json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Cached DB table existence check to avoid repeated manager metadata queries.
 *
 * @param string $table
 * @return bool
 */
function theme_flwacademy_db_table_exists(string $table): bool {
    global $DB;
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $cache[$table] = $DB->get_manager()->table_exists($table);
    return $cache[$table];
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

    static $activecache = [];
    $cachekey = $key . '|' . $url . '|' . $currenturl;
    if (array_key_exists($cachekey, $activecache)) {
        return $activecache[$cachekey];
    }

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
        if (strpos($currentpath, '/local/flwplacement/') === 0) {
            $activecache[$cachekey] = true;
            return true;
        }
        $active = in_array(rtrim($currentpath, '/') ?: '/', ['/', '/index.php'], true);
        $activecache[$cachekey] = $active;
        return $active;
    }

    if ($key === 'myhome') {
        $active = in_array(rtrim($currentpath, '/') ?: '/', ['/my', '/my/index.php'], true);
        $activecache[$cachekey] = $active;
        return $active;
    }

    if ($key === 'administrationsite') {
        $active = strpos($currentpath, '/admin/') === 0 || $currentpath === '/admin/index.php';
        $activecache[$cachekey] = $active;
        return $active;
    }

    if ($key === 'flw-dictionary') {
        $active = strpos($currentpath, '/local/mldict/') === 0
            || $currentpath === '/local/mldict/index.php';
        $activecache[$cachekey] = $active;
        return $active;
    }

    if ($key === 'flw-practice' && strpos($currentpath, '/local/flwmedia/') === 0) {
        $activecache[$cachekey] = true;
        return true;
    }

    if ($key === 'flw-exam' && strpos($currentpath, '/local/flwexam/') === 0) {
        $activecache[$cachekey] = true;
        return true;
    }

    if ($key === 'flw-selfstudy' && strpos($currentpath, '/local/flwplacement/') === 0) {
        $activecache[$cachekey] = false;
        return false;
    }

    if (in_array($key, ['flw-demo', 'flw-school', 'flw-selfstudy', 'flw-practice', 'flw-exam'], true)
            && strpos($currentpath, '/course/index.php') !== false) {
        parse_str($currentquery, $queryparams);
        $categoryid = isset($queryparams['categoryid']) ? (int)$queryparams['categoryid'] : 0;
        if ($categoryid > 0) {
            $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING);
            if ($category) {
                if ($key === 'flw-demo') {
                    $active = theme_flwacademy_category_navigation_is_active($key, $category);
                    $activecache[$cachekey] = $active;
                    return $active;
                }
                if ($key === 'flw-school') {
                    $active = theme_flwacademy_is_school_category($category);
                    $activecache[$cachekey] = $active;
                    return $active;
                }
                if ($key === 'flw-selfstudy') {
                    $active = theme_flwacademy_is_selfstudy_category($category);
                    $activecache[$cachekey] = $active;
                    return $active;
                }
                $activity = theme_flwacademy_resolve_activity_category($category);
                if ($key === 'flw-practice') {
                    $active = !empty($activity) && $activity['area'] === 'practice';
                    $activecache[$cachekey] = $active;
                    return $active;
                }
                if ($key === 'flw-exam') {
                    $active = !empty($activity) && $activity['area'] === 'exam';
                    $activecache[$cachekey] = $active;
                    return $active;
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
                    $activecache[$cachekey] = true;
                    return true;
                }
            }
        }
    }

    if ($currentwithoutfragment === $linkwithoutfragment) {
        $activecache[$cachekey] = true;
        return true;
    }

    $active = strpos($currentwithoutfragment, $linkwithoutfragment . '&') === 0;
    $activecache[$cachekey] = $active;
    return $active;
}

/**
 * Returns whether a FLW navigation item matches a category or one of its parents.
 *
 * @param string $key Navigation key.
 * @param stdClass $category Course category.
 * @return bool
 */
function theme_flwacademy_category_navigation_is_active(string $key, stdClass $category): bool {
    static $lineagecache = [];
    $cachekey = $key . '|' . (int)$category->id;
    if (array_key_exists($cachekey, $lineagecache)) {
        return $lineagecache[$cachekey];
    }

    foreach (theme_flwacademy_get_category_lineage($category) as $lineagecategory) {
        if ($key === 'flw-demo' && core_text::strtolower(trim((string)$lineagecategory->name)) === 'demo') {
            $lineagecache[$cachekey] = true;
            return true;
        }
        if ($key === 'flw-school' && theme_flwacademy_is_school_category($lineagecategory)) {
            $lineagecache[$cachekey] = true;
            return true;
        }
        if ($key === 'flw-selfstudy' && theme_flwacademy_is_selfstudy_category($lineagecategory)) {
            $lineagecache[$cachekey] = true;
            return true;
        }
        if ($key === 'flw-practice' || $key === 'flw-exam') {
            $activity = theme_flwacademy_resolve_activity_category($lineagecategory);
            if ($activity && (($key === 'flw-practice' && $activity['area'] === 'practice')
                    || ($key === 'flw-exam' && $activity['area'] === 'exam'))) {
                $lineagecache[$cachekey] = true;
                return true;
            }
        }
    }

    $lineagecache[$cachekey] = false;
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

    $categoryid = (int)($category->id ?? 0);
    static $lineagecache = [];
    if ($categoryid <= 0) {
        return [];
    }
    if (array_key_exists($categoryid, $lineagecache)) {
        return $lineagecache[$categoryid];
    }

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

    $lineagecache[$categoryid] = $lineage;
    return $lineage;
}

/**
 * Returns the Demo category URL when the category exists.
 *
 * @return string
 */
function theme_flwacademy_get_demo_category_url(): string {
    global $DB;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $categories = $DB->get_records('course_categories', ['name' => 'Demo'], 'parent ASC, sortorder ASC', 'id,visible');
    foreach ($categories as $category) {
        if (!property_exists($category, 'visible') || (int)$category->visible === 1) {
            $cache = (new moodle_url('/course/index.php', ['categoryid' => (int)$category->id]))->out(false);
            return $cache;
        }
    }

    $cache = '';
    return $cache;
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
            'text' => get_string('k12', 'theme_flwacademy'),
            'url' => $languages[0]['schoolcategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-selfstudy' => [
            'text' => get_string('selfstudy', 'theme_flwacademy'),
            'url' => $languages[0]['selfstudycategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-practice' => [
            'text' => get_string('practice', 'theme_flwacademy'),
            'url' => $languages[0]['practicecategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-dictionary' => [
            'text' => get_string('dictionary', 'theme_flwacademy'),
            'url' => $dictionaryurl,
        ],
        'flw-exam' => [
            'text' => get_string('exam', 'theme_flwacademy'),
            'url' => $languages[0]['examcategoryurl'] ?? $defaultlanguageurl,
        ],
        'flw-demo' => [
            'text' => get_string('demo', 'theme_flwacademy'),
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
            $text = get_string('dashboard', 'theme_flwacademy');
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
        ['code' => 'en', 'label' => get_string('languageenglish', 'theme_flwacademy'), 'aliases' => ['English'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'ru', 'label' => get_string('languagerussian', 'theme_flwacademy'), 'aliases' => ['Russian'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'zh', 'label' => get_string('languagechinese', 'theme_flwacademy'), 'aliases' => ['Chinese', 'Chinese Language', 'Han Chinese', '汉语'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'de', 'label' => get_string('languagegerman', 'theme_flwacademy'), 'aliases' => ['German'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'ja', 'label' => get_string('languagejapanese', 'theme_flwacademy'), 'aliases' => ['Japanese'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'fr', 'label' => get_string('languagefrench', 'theme_flwacademy'), 'aliases' => ['French'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
        ]],
        ['code' => 'es', 'label' => get_string('languagespanish', 'theme_flwacademy'), 'aliases' => ['Spanish'], 'nav' => [
            'school' => get_string('k12', 'theme_flwacademy'), 'selfstudy' => get_string('selfstudy', 'theme_flwacademy'), 'practice' => get_string('practice', 'theme_flwacademy'),
            'dictionary' => get_string('dictionary', 'theme_flwacademy'), 'exam' => get_string('exam', 'theme_flwacademy'), 'teacher' => get_string('teacher', 'theme_flwacademy'),
            'collaboration' => get_string('collaboration', 'theme_flwacademy'),
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
            'label' => get_string('watch', 'theme_flwacademy'),
            'title' => get_string('watchpractice', 'theme_flwacademy'),
            'text' => get_string('watchpracticetext', 'theme_flwacademy'),
            'image' => 'dashboard/watch',
            'accent' => 'accent-teal',
        ],
        'listen' => [
            'label' => get_string('listen', 'theme_flwacademy'),
            'title' => get_string('listenpractice', 'theme_flwacademy'),
            'text' => get_string('listenpracticetext', 'theme_flwacademy'),
            'image' => 'dashboard/listen-merged',
            'accent' => 'accent-coral',
        ],
        'speak' => [
            'label' => get_string('speak', 'theme_flwacademy'),
            'title' => get_string('speakpractice', 'theme_flwacademy'),
            'text' => get_string('speakpracticetext', 'theme_flwacademy'),
            'image' => 'dashboard/speak-merged',
            'accent' => 'accent-green',
        ],
        'read' => [
            'label' => get_string('read', 'theme_flwacademy'),
            'title' => get_string('readpractice', 'theme_flwacademy'),
            'text' => get_string('readpracticetext', 'theme_flwacademy'),
            'image' => 'dashboard/read-merged',
            'accent' => 'accent-yellow',
        ],
        'dictate' => [
            'label' => get_string('dictate', 'theme_flwacademy'),
            'title' => get_string('dictatepractice', 'theme_flwacademy'),
            'text' => get_string('dictatepracticetext', 'theme_flwacademy'),
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
    return (new moodle_url('/local/flwexam/index.php', ['view' => 'available']))->out(false);
}

/**
 * Returns the Moodle Quiz id for the current mod_quiz page, if any.
 *
 * @return int
 */
function theme_flwacademy_get_current_quiz_id(): int {
    global $DB, $PAGE;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $quizid = 0;
    $pagecm = null;
    try {
        $pagecm = $PAGE->cm;
    } catch (Throwable $exception) {
        $pagecm = null;
    }
    if ($pagecm && ($pagecm->modname ?? '') === 'quiz') {
        $quizid = (int)$pagecm->instance;
    }

    if ($quizid <= 0) {
        $cmid = optional_param('cmid', 0, PARAM_INT);
        if ($cmid <= 0) {
            $cmid = optional_param('id', 0, PARAM_INT);
        }
        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, IGNORE_MISSING);
            if ($cm) {
                $quizid = (int)$cm->instance;
            }
        }
    }

    if ($quizid <= 0) {
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if ($attemptid > 0) {
            $quizid = (int)$DB->get_field('quiz_attempts', 'quiz', ['id' => $attemptid], IGNORE_MISSING);
        }
    }

    if ($quizid <= 0) {
        $cache = 0;
        return 0;
    }

    $cache = (int)$quizid;
    return $cache;
}

/**
 * Finds the linked FLW Exam for the current Moodle Quiz page, if any.
 *
 * @return stdClass|null
 */
function theme_flwacademy_get_current_flw_exam_quiz(): ?stdClass {
    global $DB;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!theme_flwacademy_db_table_exists('local_flwexam_exams')) {
        $cache = null;
        return null;
    }

    $quizid = theme_flwacademy_get_current_quiz_id();
    if ($quizid <= 0) {
        $cache = null;
        return null;
    }

    $cache = $DB->get_record('local_flwexam_exams', [
        'quizid' => $quizid,
        'visible' => 1,
    ], 'id, name, quizid, visible', IGNORE_MISSING) ?: null;
    return $cache;
}

/**
 * Finds the linked FLW Placement test for the current Moodle Quiz page, if any.
 *
 * @return stdClass|null
 */
function theme_flwacademy_get_current_flw_placement_quiz(): ?stdClass {
    global $DB, $CFG;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $quizid = theme_flwacademy_get_current_quiz_id();
    if (!theme_flwacademy_db_table_exists('quiz')) {
        $cache = null;
        return null;
    }

    if ($quizid <= 0) {
        $cache = null;
        return null;
    }

    $hasplacementparams = optional_param('flwplacement', false, PARAM_BOOL)
        || optional_param('flwskippreflight', false, PARAM_BOOL)
        || optional_param('flwautostart', false, PARAM_BOOL)
        || optional_param('autostart', false, PARAM_BOOL);
    if (!$hasplacementparams) {
        if (!is_readable($CFG->dirroot . '/local/flwplacement/locallib.php')) {
            $cache = null;
            return null;
        }
        require_once($CFG->dirroot . '/local/flwplacement/locallib.php');
        $isplacementquiz = function_exists('local_flwplacement_get_quiz_language_for_quiz_id')
            && !empty(local_flwplacement_get_quiz_language_for_quiz_id((int)$quizid));
        if (!$isplacementquiz) {
            $cache = null;
            return null;
        }
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

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, course', IGNORE_MISSING);
        if (!$quiz) {
            $cache = null;
            return null;
        }

        $cache = (object)[
            'id' => 0,
            'name' => $quiz->name,
            'quizid' => $quizid,
            'courseid' => (int)$quiz->course,
            'languagecode' => $code,
            'languagelabel' => $label,
            'visible' => 1,
        ];
        return $cache;
    }

    if ($hasplacementparams) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, course', IGNORE_MISSING);
        if ($quiz) {
            $cache = (object)[
                'id' => 0,
                'name' => $quiz->name,
                'quizid' => $quizid,
                'courseid' => (int)$quiz->course,
                'languagecode' => '',
                'languagelabel' => '',
                'visible' => 1,
            ];
            return $cache;
        }
    }

    $cache = null;
    return null;
}

/**
 * Returns the Placement module URL, optionally for a specific learning language.
 *
 * @param stdClass|null $placementquiz Placement quiz metadata.
 * @return string
 */
function theme_flwacademy_get_placement_page_url(?stdClass $placementquiz = null): string {
    $params = [];
    if ($placementquiz && !empty($placementquiz->languagecode)) {
        $params['language'] = clean_param($placementquiz->languagecode, PARAM_ALPHANUMEXT);
    }
    $params['flwplacement'] = 1;
    $params['flwautostart'] = 1;
    $params['flwskippreflight'] = 1;
    $params['autostart'] = 1;

    return (new moodle_url('/local/flwplacement/index.php', $params))->out(false);
}

/**
 * Returns the direct placement attempt URL for a learning language.
 *
 * If the placement quiz and its course module are configured, returns a direct
 * start-attempt URL so users jump straight into the quiz. Falls back to the
 * landing page when quiz configuration is incomplete.
 *
 * @param string $languagecode Language code from the language selector.
 * @return string
 */
function theme_flwacademy_get_placement_quiz_start_url(string $languagecode): string {
    global $DB;

    $language = core_text::strtolower(clean_param($languagecode, PARAM_ALPHANUMEXT));
    $language = $language === 'zh_cn' ? 'zh' : $language;

    $placementlanguagecodes = ['en', 'ru', 'zh', 'de', 'ja', 'fr', 'es'];
    if ($language === '' || !in_array($language, $placementlanguagecodes, true)) {
        $language = 'en';
    }
    if (!theme_flwacademy_db_table_exists('quiz')) {
        return theme_flwacademy_get_placement_page_url((object)['languagecode' => $language]);
    }

    $quizid = (int)get_config('local_flwplacement', 'quizid_' . $language);
    if ($quizid <= 0) {
        foreach ($placementlanguagecodes as $fallback) {
            $quizid = (int)get_config('local_flwplacement', 'quizid_' . $fallback);
            if ($quizid > 0) {
                break;
            }
        }
    }

    if ($quizid <= 0) {
        return theme_flwacademy_get_placement_page_url((object)['languagecode' => $language]);
    }

    $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, course', IGNORE_MISSING);
    if (!$quiz) {
        return theme_flwacademy_get_placement_page_url((object)['languagecode' => $language]);
    }

    $cm = get_coursemodule_from_instance('quiz', $quizid, (int)$quiz->course, false, IGNORE_MISSING);
    if (!$cm) {
        return theme_flwacademy_get_placement_page_url((object)['languagecode' => $language]);
    }

    if ((int)$DB->count_records('quiz_slots', ['quizid' => $quizid]) !== 30) {
        return theme_flwacademy_get_placement_page_url((object)['languagecode' => $language]);
    }

    return (new moodle_url('/mod/quiz/startattempt.php', [
        'cmid' => (int)$cm->id,
        'sesskey' => sesskey(),
        'flwplacement' => 1,
        'flwautostart' => 1,
        'flwskippreflight' => 1,
        'autostart' => 1,
    ]))->out(false);
}

/**
 * Exports learning languages with matching Moodle course category URLs.
 *
 * @return array
 */
function theme_flwacademy_export_learning_languages(): array {
    global $DB;

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

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
            'placementtesturl' => theme_flwacademy_get_placement_quiz_start_url($language['code']),
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

    $cache = $languages;
    return $cache;
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
    $flwexamquiz = theme_flwacademy_get_current_flw_exam_quiz();
    $flwplacementquiz = theme_flwacademy_get_current_flw_placement_quiz();
    $isflwquizpage = !empty($flwexamquiz) || !empty($flwplacementquiz);

    $context['hasflwtools'] = true;
    $context['hasflwdictionary'] = $dictionaryurl !== '' && !$isflwexampage && !$isdashboardpage && !$isflwquizpage;
    $context['flwdictionaryurl'] = $dictionaryurl;
    if ($flwexamquiz) {
        $context['flwbackurl'] = theme_flwacademy_get_exam_page_url();
        $context['flwbacklabel'] = get_string('backtoexamcenter', 'local_flwexam');
    } else if ($flwplacementquiz) {
        $context['flwbackurl'] = theme_flwacademy_get_placement_page_url($flwplacementquiz);
        $context['flwbacklabel'] = get_string('backtoplacementtest', 'local_flwplacement');
    } else {
        $context['flwbackurl'] = $context['flwbackurl'] ?? '';
        $context['flwbacklabel'] = $context['flwbacklabel'] ?? get_string('back', 'core');
    }
    $context['haslearninglanguages'] = !empty($learninglanguages);
    $context['learninglanguages'] = $learninglanguages;
    $context['currentlanguagecode'] = $currentlanguagecode;
    $context['currentlanguagelabel'] = $currentlanguagelabel;
    $context['flwtoolsshowlanguage'] = !empty($learninglanguages) && !$isscormpage && !$iscoursepage && !$isflwquizpage;
    $context['flwtoolsshowdone'] = !$isscormpage && !$isdashboardpage && !$isadminpage && !$isflwquizpage;
    $context['flwtoolsshowcourseindex'] = $context['flwtoolsshowcourseindex']
        ?? (!empty($context['courseindex']) && !$isscormpage && !$isflwquizpage);
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

    $categoryid = (int)($category->id ?? 0);
    static $cache = [];
    if ($categoryid <= 0) {
        return false;
    }
    if (array_key_exists($categoryid, $cache)) {
        return $cache[$categoryid];
    }

    if (core_text::strtolower(trim($category->name)) !== 'school' || empty($category->parent)) {
        $cache[$categoryid] = false;
        return false;
    }

    $parent = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name', IGNORE_MISSING);
    $cache[$categoryid] = $parent && theme_flwacademy_match_learning_language_category($parent) !== null;
    return $cache[$categoryid];
}

/**
 * Returns true when the category is a FLW language Self Study category.
 *
 * @param stdClass $category
 * @return bool
 */
function theme_flwacademy_is_selfstudy_category(stdClass $category): bool {
    global $DB;

    $categoryid = (int)($category->id ?? 0);
    static $cache = [];
    if ($categoryid <= 0) {
        return false;
    }
    if (array_key_exists($categoryid, $cache)) {
        return $cache[$categoryid];
    }

    $name = core_text::strtolower(trim($category->name));
    if (($name !== 'self study' && $name !== 'self-study') || empty($category->parent)) {
        $cache[$categoryid] = false;
        return false;
    }

    $parent = $DB->get_record('course_categories', ['id' => $category->parent], 'id,name', IGNORE_MISSING);
    $cache[$categoryid] = $parent && theme_flwacademy_match_learning_language_category($parent) !== null;
    return $cache[$categoryid];
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
function theme_flwacademy_redesign_asset_url($output, string $name): string {
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
 * Formats a localized course count.
 *
 * @param int $count
 * @return string
 */
function theme_flwacademy_course_count_label(int $count): string {
    return get_string($count === 1 ? 'coursecountone' : 'coursecountmany', 'theme_flwacademy', $count);
}

/**
 * Formats a localized available-course count.
 *
 * @param int $count
 * @return string
 */
function theme_flwacademy_available_courses_label(int $count): string {
    return get_string($count === 1 ? 'availablecourseone' : 'availablecoursemany', 'theme_flwacademy', $count);
}

/**
 * Formats a localized activity progress count.
 *
 * @param int $completed
 * @param int $total
 * @return string
 */
function theme_flwacademy_activity_progress_label(int $completed, int $total): string {
    return get_string('activityprogress', 'theme_flwacademy', (object)[
        'completed' => $completed,
        'total' => $total,
    ]);
}

/**
 * Formats a localized percent-complete label.
 *
 * @param int $percent
 * @return string
 */
function theme_flwacademy_percent_complete_label(int $percent): string {
    return get_string('percentcomplete', 'theme_flwacademy', $percent);
}

/**
 * Returns a localized FLW skill label.
 *
 * @param string $skill
 * @return string
 */
function theme_flwacademy_skill_label(string $skill): string {
    $key = preg_replace('/[^a-z0-9]+/', '', core_text::strtolower($skill));
    $stringkey = 'skill' . $key;
    return get_string_manager()->string_exists($stringkey, 'theme_flwacademy')
        ? get_string($stringkey, 'theme_flwacademy')
        : ucfirst(str_replace('_', ' ', $skill));
}

/**
 * Returns a localized unit-map status label.
 *
 * @param bool $complete
 * @param bool $active
 * @return string
 */
function theme_flwacademy_unit_status_label(bool $complete, bool $active): string {
    if ($complete) {
        return get_string('unitstatuscomplete', 'theme_flwacademy');
    }
    if ($active) {
        return get_string('unitstatusinprogress', 'theme_flwacademy');
    }
    return get_string('unitstatusnext', 'theme_flwacademy');
}

/**
 * Returns a localized day-streak label.
 *
 * @param int $days
 * @return string
 */
function theme_flwacademy_day_streak_label(int $days): string {
    return get_string($days === 1 ? 'daystreakone' : 'daystreakmany', 'theme_flwacademy', $days);
}

/**
 * Returns a localized placement study recommendation for known stored messages.
 *
 * @param string $recommendation
 * @return string
 */
function theme_flwacademy_study_recommendation_label(string $recommendation): string {
    $normalised = trim(preg_replace('/\s+/', ' ', $recommendation));
    $map = [
        'Begin with foundation review before continuing the main path.' => 'studyrecfoundationreview',
        'Begin at the recommended unit and continue to the next checkpoint.' => 'studyrecmainpath',
        'Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.' => 'studyrecpronwritingrepair',
        'Continue from the recommended unit and review any weak skill areas first.' => 'studyrecweakskills',
        'Continue from your next useful learning step.' => 'studyrecnextusefulstep',
    ];
    return isset($map[$normalised]) ? get_string($map[$normalised], 'theme_flwacademy') : $recommendation;
}

/**
 * Normalises FLW learning language codes and language names.
 *
 * @param string $code
 * @return string
 */
function theme_flwacademy_normalise_learning_language_code(string $code): string {
    $code = core_text::strtolower(trim(str_replace('-', '_', $code)));
    $aliases = [
        'english' => 'en',
        'russian' => 'ru',
        'русский' => 'ru',
        'chinese' => 'zh',
        'han_chinese' => 'zh',
        '汉语' => 'zh',
        'zh_cn' => 'zh',
        'german' => 'de',
        'deutsch' => 'de',
        'japanese' => 'ja',
        '日本語' => 'ja',
        'french' => 'fr',
        'français' => 'fr',
        'spanish' => 'es',
        'español' => 'es',
    ];

    return $aliases[$code] ?? clean_param($code, PARAM_ALPHANUMEXT);
}

/**
 * Checks whether a multilingual content entry belongs to the selected FLW learning language.
 *
 * @param string $entrylang
 * @param string $languagecode
 * @return bool
 */
function theme_flwacademy_multilang_entry_matches(string $entrylang, string $languagecode): bool {
    $wanted = theme_flwacademy_normalise_learning_language_code($languagecode) ?: 'en';
    $entrycodes = preg_split('/[\s,;|]+/', $entrylang, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($entrycodes as $entrycode) {
        if (theme_flwacademy_normalise_learning_language_code($entrycode) === $wanted) {
            return true;
        }
    }

    return false;
}

/**
 * Returns a known K-12 hero description when legacy multilingual text has already been flattened.
 *
 * @param string $text
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_known_school_description(string $text, string $languagecode): string {
    $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (stripos($plain, 'School courses by institution level') === false ||
            stripos($plain, 'Schulkurse nach Bildungsstufe') === false) {
        return '';
    }

    $wanted = theme_flwacademy_normalise_learning_language_code($languagecode) ?: 'en';
    $descriptions = [
        'en' => 'School courses by institution level.',
        'ru' => 'Школьные курсы по уровню образования.',
        'zh' => '按教育阶段划分的学校课程。',
        'de' => 'Schulkurse nach Bildungsstufe.',
        'ja' => '教育段階別の学校コース。',
        'fr' => 'Cours scolaires par niveau d\'etablissement.',
        'es' => 'Cursos escolares por nivel educativo.',
    ];

    return $descriptions[$wanted] ?? $descriptions['en'];
}

/**
 * Extracts the selected FLW learning-language text from Moodle multilingual content.
 *
 * @param string $text
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_extract_learning_language_text(string $text, string $languagecode): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $entries = [];
    if (preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $entries[] = [
                'lang' => $match[1],
                'text' => $match[2],
            ];
        }
    }

    if (empty($entries) && preg_match_all('/<span\b(?=[^>]*\bclass=(["\'])(?:(?!\1).)*\bmultilang\b(?:(?!\1).)*\1)(?=[^>]*\blang=(["\'])([^"\']+)\2)[^>]*>(.*?)<\/span>/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $entries[] = [
                'lang' => $match[3],
                'text' => $match[4],
            ];
        }
    }

    if (!empty($entries)) {
        foreach ($entries as $entry) {
            if (theme_flwacademy_multilang_entry_matches($entry['lang'], $languagecode)) {
                return trim($entry['text']);
            }
        }
        foreach ($entries as $entry) {
            if (theme_flwacademy_multilang_entry_matches($entry['lang'], 'en')) {
                return trim($entry['text']);
            }
        }

        return trim($entries[0]['text']);
    }

    $knownschooldescription = theme_flwacademy_known_school_description($text, $languagecode);
    return $knownschooldescription !== '' ? $knownschooldescription : $text;
}

/**
 * Returns selected-language text using the active FLW learning language by default.
 *
 * This is the preferred entry point for FLW page output that may contain
 * Moodle multilang markup, flattened legacy multilang text, or plain text.
 *
 * @param string $text
 * @param string|null $languagecode
 * @return string
 */
function theme_flwacademy_selected_learning_language_text(string $text, ?string $languagecode = null): string {
    $languagecode = $languagecode !== null && $languagecode !== ''
        ? $languagecode
        : (theme_flwacademy_get_active_learning_language_code() ?: 'en');

    return theme_flwacademy_extract_learning_language_text($text, $languagecode);
}

/**
 * Formats multilingual text for the selected FLW learning language.
 *
 * @param string $text
 * @param int $format
 * @param context $context
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_format_learning_language_text(string $text, int $format, context $context, string $languagecode): string {
    $selectedtext = theme_flwacademy_selected_learning_language_text($text, $languagecode);
    return format_text($selectedtext, $format, [
        'context' => $context,
        'overflowdiv' => true,
        'filter' => true,
    ]);
}

/**
 * Formats a short Moodle string for the selected FLW learning language.
 *
 * Use this for names/titles that may contain Moodle multilang markup.
 *
 * @param string $text
 * @param string $languagecode
 * @param context|null $context
 * @return string
 */
function theme_flwacademy_format_learning_language_string(
    string $text,
    string $languagecode,
    ?context $context = null
): string {
    $selectedtext = theme_flwacademy_selected_learning_language_text($text, $languagecode);
    $options = ['filter' => true];
    if ($context) {
        $options['context'] = $context;
    }

    return format_string($selectedtext, true, $options);
}

/**
 * Returns selected-language plain text from Moodle formatted content.
 *
 * @param string $text
 * @param int $format
 * @param context $context
 * @param string $languagecode
 * @param int $limit Optional shorten_text limit.
 * @return string
 */
function theme_flwacademy_learning_language_plain_text(
    string $text,
    int $format,
    context $context,
    string $languagecode,
    int $limit = 0
): string {
    $html = theme_flwacademy_format_learning_language_text($text, $format, $context, $languagecode);
    $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return $limit > 0 ? shorten_text($plain, $limit) : $plain;
}

/**
 * Returns a selected-language category display name.
 *
 * @param stdClass $category
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_get_category_display_name(stdClass $category, string $languagecode): string {
    return theme_flwacademy_format_learning_language_string(
        (string)($category->name ?? ''),
        $languagecode,
        context_coursecat::instance((int)$category->id)
    );
}

/**
 * Returns a selected-language course display name.
 *
 * @param stdClass $course
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_get_course_display_name(stdClass $course, string $languagecode): string {
    $context = context_course::instance((int)$course->id, IGNORE_MISSING);
    return theme_flwacademy_format_learning_language_string(
        (string)($course->fullname ?? ''),
        $languagecode,
        $context ?: null
    );
}

/**
 * Gets the selected learning language from URL first, then the persistent cookie.
 *
 * @return string
 */
function theme_flwacademy_get_active_learning_language_code(): string {
    $requested = optional_param('flwlang', '', PARAM_ALPHANUMEXT);
    if ($requested === '') {
        $requested = optional_param('learninglanguage', '', PARAM_ALPHANUMEXT);
    }
    if ($requested === '') {
        $requested = clean_param($_COOKIE['flw_learning_language'] ?? '', PARAM_ALPHANUMEXT);
    }

    return theme_flwacademy_normalise_learning_language_code($requested);
}

/**
 * Builds a dashboard URL that selects a language world immediately.
 *
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_get_dashboard_url_for_language(string $languagecode): string {
    $languagecode = theme_flwacademy_normalise_learning_language_code($languagecode);
    return (new moodle_url('/my/', ['flwlang' => $languagecode ?: 'en']))->out(false);
}

/**
 * Extracts a category id from a Moodle URL string.
 *
 * @param string $url
 * @return int
 */
function theme_flwacademy_get_categoryid_from_url(string $url): int {
    $query = parse_url($url, PHP_URL_QUERY);
    if (!$query) {
        return 0;
    }
    parse_str($query, $params);
    return (int)($params['categoryid'] ?? 0);
}

/**
 * Returns visible courses in a category branch.
 *
 * @param int $categoryid
 * @param int $limit
 * @return stdClass[]
 */
function theme_flwacademy_get_courses_in_category(int $categoryid, int $limit = 0): array {
    global $DB;

    static $cache = [];
    $cachekey = $categoryid . '|' . $limit;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    if ($categoryid <= 0) {
        $cache[$cachekey] = [];
        return [];
    }
    $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id,path', IGNORE_MISSING);
    if (!$category) {
        $cache[$cachekey] = [];
        return [];
    }

    $sql = "SELECT c.*
              FROM {course} c
              JOIN {course_categories} cc ON cc.id = c.category
             WHERE c.id <> :siteid
               AND c.visible = 1
               AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':pathmatch', false) . ")
          ORDER BY cc.depth ASC, cc.sortorder ASC, c.sortorder ASC, c.fullname ASC";

    $cache[$cachekey] = array_values($DB->get_records_sql($sql, [
        'siteid' => SITEID,
        'categoryid' => $category->id,
        'pathmatch' => $category->path . '/%',
    ], 0, $limit));
    return $cache[$cachekey];
}

/**
 * Returns visible course ids in a category branch.
 *
 * @param int $categoryid
 * @return int[]
 */
function theme_flwacademy_get_course_ids_in_category(int $categoryid): array {
    return array_map(static function(stdClass $course): int {
        return (int)$course->id;
    }, theme_flwacademy_get_courses_in_category($categoryid));
}

/**
 * Returns aggregate learner progress for a category branch.
 *
 * @param int $categoryid
 * @param int $userid
 * @param int $limit
 * @return array
 */
function theme_flwacademy_get_category_progress_summary(int $categoryid, int $userid, int $limit = 0, bool $skipcompletion = false): array {
    static $cache = [];
    $store = theme_flwacademy_get_cache_store('category_progress');
    $cachekey = $categoryid . '|' . $userid . '|' . $limit . '|' . ($skipcompletion ? '1' : '0');
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $cache[$cachekey] = $stored;
            return $stored;
        }
    }

    $courses = theme_flwacademy_get_courses_in_category($categoryid, $limit);

    if ($skipcompletion) {
        $coursecount = count($courses);
        $result = [
            'coursecount' => $coursecount,
            'coursecountlabel' => theme_flwacademy_course_count_label($coursecount),
            'completedcourses' => 0,
            'completed' => 0,
            'total' => 0,
            'percent' => 0,
            'progress' => '0%',
            'label' => $coursecount > 0 ? get_string('browsecourses', 'theme_flwacademy') : get_string('readytostart', 'theme_flwacademy'),
            'meta' => $coursecount > 0 ? theme_flwacademy_available_courses_label($coursecount) : get_string('nocoursesyet', 'theme_flwacademy'),
        ];
        if ($store) {
            $store->set($cachekey, $result);
        }
        $cache[$cachekey] = $result;
        return $cache[$cachekey];
    }

    $totalactivities = 0;
    $completedactivities = 0;
    $completedcourses = 0;

    foreach ($courses as $course) {
        $summary = theme_flwacademy_get_course_progress_summary($course, $userid);
        $totalactivities += (int)$summary['total'];
        $completedactivities += (int)$summary['completed'];
        if ((int)$summary['total'] > 0 && (int)$summary['completed'] >= (int)$summary['total']) {
            $completedcourses++;
        }
    }

    $percent = $totalactivities > 0 ? (int)round(($completedactivities / $totalactivities) * 100) : 0;
    $coursecount = count($courses);
    $result = [
        'coursecount' => $coursecount,
        'coursecountlabel' => theme_flwacademy_course_count_label($coursecount),
        'completedcourses' => $completedcourses,
        'completed' => $completedactivities,
        'total' => $totalactivities,
        'percent' => $percent,
        'progress' => theme_flwacademy_percent_width($percent),
        'label' => $totalactivities > 0 ? theme_flwacademy_percent_complete_label($percent) : get_string('readytostart', 'theme_flwacademy'),
        'meta' => $totalactivities > 0
            ? theme_flwacademy_activity_progress_label($completedactivities, $totalactivities)
            : ($coursecount > 0 ? theme_flwacademy_available_courses_label($coursecount) : get_string('nocoursesyet', 'theme_flwacademy')),
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $cache[$cachekey] = $result;
    return $cache[$cachekey];
}

/**
 * Returns the FLW placement course-key language value.
 *
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_get_language_profile_value(string $languagecode): string {
    $values = [
        'en' => 'english',
        'ru' => 'russian',
        'zh' => 'chinese',
        'ja' => 'japanese',
        'de' => 'german',
        'fr' => 'french',
        'es' => 'spanish',
    ];
    $languagecode = theme_flwacademy_normalise_learning_language_code($languagecode);
    return $values[$languagecode] ?? 'english';
}

/**
 * Returns a course-key prefix for placement profile lookup.
 *
 * @param string $languagecode
 * @return string
 */
function theme_flwacademy_get_language_coursekey_prefix(string $languagecode): string {
    return 'FLW_' . strtoupper(theme_flwacademy_get_language_profile_value($languagecode)) . '_';
}

/**
 * Builds CEFR journey steps for the dashboard/report graph.
 *
 * @param string $level
 * @return array
 */
function theme_flwacademy_get_cefr_journey_context(string $level): array {
    $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
    $baselevel = 'A1';
    if (preg_match('/\b(A1|A2|B1|B2|C1|C2)\b/i', $level, $matches)) {
        $baselevel = strtoupper($matches[1]);
    }
    $currentindex = array_search($baselevel, $levels, true);
    if ($currentindex === false) {
        $currentindex = 0;
    }

    $steps = [];
    foreach ($levels as $index => $steplevel) {
        $class = $index < $currentindex ? 'complete' : ($index === $currentindex ? 'current' : 'upcoming');
        $steps[] = [
            'label' => $steplevel,
            'class' => $class,
            'checked' => $index === $currentindex,
        ];
    }

    return [
        'steps' => $steps,
        'fillwidth' => theme_flwacademy_percent_width(($currentindex / max(1, count($levels) - 1)) * 100),
    ];
}

/**
 * Converts a timestamp to the current user's date string.
 *
 * @param int $timestamp
 * @return string
 */
function theme_flwacademy_user_day_key(int $timestamp): string {
    $date = new DateTimeImmutable('@' . $timestamp);
    return $date->setTimezone(core_date::get_user_timezone_object())->format('Y-m-d');
}

/**
 * Returns rank and streak data for a selected language world.
 *
 * @param int $userid
 * @param string $languagecode
 * @param int $categoryid
 * @return array
 */
function theme_flwacademy_get_language_rank_and_streak(int $userid, string $languagecode, int $categoryid): array {
    global $DB;

    $store = theme_flwacademy_get_cache_store('language_rank');
    static $cache = [];
    $cachekey = $userid . '|' . $languagecode . '|' . $categoryid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $cache[$cachekey] = $stored;
            return $stored;
        }
    }

    if ($userid <= 0 || isguestuser()) {
        $worldlabel = theme_flwacademy_get_world_label([
            'label' => get_string('language' . theme_flwacademy_get_language_profile_value($languagecode), 'theme_flwacademy'),
        ]);
        $cache[$cachekey] = [
            'title' => get_string('ranktitle', 'theme_flwacademy', (object)['rank' => 1, 'world' => $worldlabel]),
            'text' => get_string('ranktextnoplace', 'theme_flwacademy', (object)[
                'streak' => theme_flwacademy_day_streak_label(0),
                'points' => 0,
            ]),
            'userscount' => 1,
            'score' => 0,
            'streak' => theme_flwacademy_get_language_streak_summary(0, $languagecode, $categoryid),
        ];
        return $cache[$cachekey];
    }

    $languagecode = theme_flwacademy_normalise_learning_language_code($languagecode) ?: 'en';
    $worldlabel = theme_flwacademy_get_world_label([
        'label' => get_string('language' . theme_flwacademy_get_language_profile_value($languagecode), 'theme_flwacademy'),
    ]);
    $scores = [];

    if (theme_flwacademy_db_table_exists('local_flwplacement_profile')) {
        $prefix = theme_flwacademy_get_language_coursekey_prefix($languagecode);
        $hasplacementtable = theme_flwacademy_db_table_exists('local_flwplacement');
        $placementjoin = $hasplacementtable
            ? ' LEFT JOIN {local_flwplacement} lp ON lp.id = p.latestresultid'
            : '';
        $placementselect = $hasplacementtable ? ', lp.weightedscore' : '';
        $profiles = $DB->get_records_sql(
            "SELECT p.userid, p.overallcefr, p.placementconfidence, p.timemodified" . $placementselect . "
               FROM {local_flwplacement_profile} p
               JOIN (
                     SELECT userid, MAX(timemodified) AS timemodified
                       FROM {local_flwplacement_profile}
                      WHERE " . $DB->sql_like('coursekey', ':coursekeysub', false) . "
                   GROUP BY userid
               ) latest ON latest.userid = p.userid AND latest.timemodified = p.timemodified" . $placementjoin . "
              WHERE " . $DB->sql_like('p.coursekey', ':coursekey', false) . "
           ORDER BY p.timemodified DESC",
            [
                'coursekeysub' => $prefix . '%',
                'coursekey' => $prefix . '%',
            ]
        );
        foreach ($profiles as $profile) {
            $weighted = (float)($profile->weightedscore ?? 0);
            $placementscore = $weighted > 0 ? $weighted : ((float)$profile->placementconfidence * 100);
            $scores[(int)$profile->userid]['placement'] = max($scores[(int)$profile->userid]['placement'] ?? 0, $placementscore);
            $scores[(int)$profile->userid]['level'] = $profile->overallcefr ?: '';
        }
    }

    if (theme_flwacademy_db_table_exists('local_flwexam_results')) {
        $examrows = $DB->get_records_sql(
            "SELECT userid, AVG(overallscore) AS score
               FROM {local_flwexam_results}
              WHERE language = :language
           GROUP BY userid",
            ['language' => $languagecode]
        );
        foreach ($examrows as $row) {
            $scores[(int)$row->userid]['exam'] = (float)$row->score;
        }
    }

    $courseids = theme_flwacademy_get_course_ids_in_category($categoryid);
    if ($courseids && theme_flwacademy_db_table_exists('course_modules_completion')) {
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $completionrows = $DB->get_records_sql(
            "SELECT cmc.userid, COUNT(1) AS completecount
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course {$insql}
                AND cmc.completionstate IN (1, 2, 3)
           GROUP BY cmc.userid",
            $inparams
        );
        foreach ($completionrows as $row) {
            $scores[(int)$row->userid]['activity'] = min(100, (int)$row->completecount * 5);
        }
    }

    if ($userid > 0 && empty($scores[$userid])) {
        $scores[$userid] = ['placement' => 0, 'exam' => 0, 'activity' => 0, 'level' => ''];
    }

    $rankrows = [];
    foreach ($scores as $scoreuserid => $parts) {
        $placement = (float)($parts['placement'] ?? 0);
        $exam = (float)($parts['exam'] ?? 0);
        $activity = (float)($parts['activity'] ?? 0);
        $rankrows[] = [
            'userid' => (int)$scoreuserid,
            'score' => round(($placement * 0.55) + ($exam * 0.35) + ($activity * 0.10), 2),
            'level' => $parts['level'] ?? '',
        ];
    }
    usort($rankrows, static function(array $left, array $right): int {
        return $right['score'] <=> $left['score'] ?: $left['userid'] <=> $right['userid'];
    });

    $rank = 1;
    $userscount = max(1, count($rankrows));
    $currentscore = 0.0;
    $currentlevel = '';
    foreach ($rankrows as $index => $row) {
        if ((int)$row['userid'] === $userid) {
            $rank = $index + 1;
            $currentscore = (float)$row['score'];
            $currentlevel = $row['level'];
            break;
        }
    }

    $streak = theme_flwacademy_get_language_streak_summary($userid, $languagecode, $categoryid);
    $ranktextdata = (object)[
        'level' => $currentlevel,
        'streak' => theme_flwacademy_day_streak_label((int)$streak['days']),
        'points' => (int)round($currentscore),
    ];
    $result = [
        'title' => get_string('ranktitle', 'theme_flwacademy', (object)['rank' => $rank, 'world' => $worldlabel]),
        'text' => $currentlevel !== ''
            ? get_string('ranktextwithplacement', 'theme_flwacademy', $ranktextdata)
            : get_string('ranktextnoplace', 'theme_flwacademy', $ranktextdata),
        'userscount' => $userscount,
        'score' => (int)round($currentscore),
        'streak' => $streak,
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $cache[$cachekey] = $result;
    return $cache[$cachekey];
}

/**
 * Returns continuous studied days for the selected language.
 *
 * @param int $userid
 * @param string $languagecode
 * @param int $categoryid
 * @return array
 */
function theme_flwacademy_get_language_streak_summary(int $userid, string $languagecode, int $categoryid): array {
    global $DB;

    $store = theme_flwacademy_get_cache_store('language_streak');
    static $cache = [];
    $cachekey = $userid . '|' . $languagecode . '|' . $categoryid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $cache[$cachekey] = $stored;
            return $stored;
        }
    }

    $dates = [];
    $since = time() - (86400 * 90);
    $courseids = theme_flwacademy_get_course_ids_in_category($categoryid);
    if ($userid > 0 && $courseids && theme_flwacademy_db_table_exists('logstore_standard_log')) {
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params = $inparams + [
            'userid' => $userid,
            'since' => $since,
        ];
        $logs = $DB->get_records_sql(
            "SELECT id, timecreated
               FROM {logstore_standard_log}
              WHERE userid = :userid
                AND courseid {$insql}
                AND timecreated >= :since
           ORDER BY timecreated DESC",
            $params,
            0,
            500
        );
        foreach ($logs as $log) {
            $dates[theme_flwacademy_user_day_key((int)$log->timecreated)] = true;
        }
    }

    if ($userid > 0 && theme_flwacademy_db_table_exists('local_flwplacement_profile')) {
        $prefix = theme_flwacademy_get_language_coursekey_prefix($languagecode);
        $profiles = $DB->get_records_select(
            'local_flwplacement_profile',
            'userid = :userid AND timemodified >= :since AND ' . $DB->sql_like('coursekey', ':coursekey', false),
            ['userid' => $userid, 'since' => $since, 'coursekey' => $prefix . '%'],
            'timemodified DESC',
            'id,timemodified'
        );
        foreach ($profiles as $profile) {
            $dates[theme_flwacademy_user_day_key((int)$profile->timemodified)] = true;
        }
    }

    if ($userid > 0 && theme_flwacademy_db_table_exists('local_flwexam_results')) {
        $results = $DB->get_records_select(
            'local_flwexam_results',
            'userid = :userid AND language = :language AND timecreated >= :since',
            ['userid' => $userid, 'language' => theme_flwacademy_normalise_learning_language_code($languagecode), 'since' => $since],
            'timecreated DESC',
            'id,timecreated'
        );
        foreach ($results as $result) {
            $dates[theme_flwacademy_user_day_key((int)$result->timecreated)] = true;
        }
    }

    $timezone = core_date::get_user_timezone_object();
    $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0);
    $start = !empty($dates[$today->format('Y-m-d')])
        ? $today
        : $today->sub(new DateInterval('P1D'));
    $days = 0;
    $cursor = $start;
    while (!empty($dates[$cursor->format('Y-m-d')])) {
        $days++;
        $cursor = $cursor->sub(new DateInterval('P1D'));
    }

    $week = [];
    $weekstart = $today->sub(new DateInterval('P' . max(0, ((int)$today->format('N')) - 1) . 'D'));
    for ($i = 0; $i < 7; $i++) {
        $day = $weekstart->add(new DateInterval('P' . $i . 'D'));
        $week[] = [
            'label' => substr($day->format('D'), 0, 1),
            'active' => !empty($dates[$day->format('Y-m-d')]),
        ];
    }

    $result = [
        'days' => $days,
        'displaydays' => (string)$days,
        'summary' => theme_flwacademy_day_streak_label($days),
        'label' => get_string('daystreaklabel', 'theme_flwacademy'),
        'weeklabel' => get_string('thisweek', 'theme_flwacademy'),
        'week' => $week,
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $cache[$cachekey] = $result;
    return $cache[$cachekey];
}

/**
 * Gets the latest module URL for a learner in a course.
 *
 * @param stdClass $course
 * @param int $userid
 * @return string
 */
function theme_flwacademy_get_latest_course_module_url(stdClass $course, int $userid): string {
    global $DB;

    static $cache = [];
    $cachekey = (int)($course->id ?? 0) . '|' . $userid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    if ($userid <= 0 || !theme_flwacademy_db_table_exists('logstore_standard_log')) {
        $cache[$cachekey] = '';
        return '';
    }
    $record = $DB->get_record_sql(
        "SELECT contextinstanceid
           FROM {logstore_standard_log}
          WHERE userid = :userid
            AND courseid = :courseid
            AND contextlevel = :contextlevel
            AND contextinstanceid > 0
       ORDER BY timecreated DESC",
        [
            'userid' => $userid,
            'courseid' => (int)$course->id,
            'contextlevel' => CONTEXT_MODULE,
        ],
        IGNORE_MULTIPLE
    );
    if (!$record) {
        return '';
    }

    try {
        $modinfo = get_fast_modinfo($course, $userid);
        $cm = $modinfo->get_cm((int)$record->contextinstanceid);
        if ($cm && $cm->uservisible && $cm->url) {
            return $cm->url->out(false);
        }
    } catch (Throwable $exception) {
        $cache[$cachekey] = '';
        return '';
    }

    $cache[$cachekey] = '';
    return '';
}

/**
 * Returns dashboard unit map and lesson URLs for a course.
 *
 * @param stdClass $course
 * @param int $userid
 * @param string $languagecode
 * @return array
 */
function theme_flwacademy_get_course_learning_map(stdClass $course, int $userid, string $languagecode = 'en'): array {
    global $DB, $CFG;

    $store = theme_flwacademy_get_cache_store('learning_map');
    static $cache = [];
    $languagecode = theme_flwacademy_normalise_learning_language_code($languagecode) ?: 'en';
    $cachekey = (int)$course->id . '|' . $userid . '|' . $languagecode;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $cache[$cachekey] = $stored;
            return $stored;
        }
    }

    require_once($CFG->libdir . '/completionlib.php');

    $courseurl = (new moodle_url('/course/view.php', ['id' => (int)$course->id]))->out(false);
    $firstcmurl = '';
    $firstincompleteurl = '';
    $unitnodes = [];
    $sectionstatus = [];
    $completion = new completion_info($course);
    $modinfo = get_fast_modinfo($course, $userid);

    foreach ($modinfo->cms as $cm) {
        if (!$cm->uservisible || !$cm->url) {
            continue;
        }
        if ($firstcmurl === '') {
            $firstcmurl = $cm->url->out(false);
        }
        $complete = false;
        if ((int)$cm->completion !== COMPLETION_TRACKING_NONE) {
            $data = $completion->get_data($cm, false, $userid);
            $complete = in_array((int)$data->completionstate, [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS,
                COMPLETION_COMPLETE_FAIL,
            ], true);
        }
        if (!$complete && $firstincompleteurl === '') {
            $firstincompleteurl = $cm->url->out(false);
        }
        $sectionstatus[$cm->sectionnum]['total'] = ($sectionstatus[$cm->sectionnum]['total'] ?? 0) + 1;
        $sectionstatus[$cm->sectionnum]['complete'] = ($sectionstatus[$cm->sectionnum]['complete'] ?? 0) + ($complete ? 1 : 0);
    }

    foreach ($modinfo->cms as $cm) {
        if ($cm->modname !== 'scorm' || !$cm->uservisible) {
            continue;
        }
        $scoes = $DB->get_records_select(
            'scorm_scoes',
            'scorm = :scorm AND launch <> :emptylaunch AND title <> :emptytitle',
            [
                'scorm' => (int)$cm->instance,
                'emptylaunch' => '',
                'emptytitle' => '',
            ],
            'sortorder ASC, id ASC',
            'id,title'
        );
        if (!$scoes) {
            continue;
        }

        $statuses = [];
        if ($userid > 0 && theme_flwacademy_db_table_exists('scorm_scoes_value')) {
            $tracks = $DB->get_records_sql(
                "SELECT sv.id, sv.scoid, sv.value, sv.timemodified
                   FROM {scorm_scoes_value} sv
                   JOIN {scorm_attempt} sa ON sa.id = sv.attemptid
                   JOIN {scorm_element} se ON se.id = sv.elementid
                  WHERE sa.userid = :userid
                    AND sa.scormid = :scormid
                    AND se.element IN ('cmi.core.lesson_status', 'cmi.completion_status')
               ORDER BY sv.timemodified DESC",
                [
                    'userid' => $userid,
                    'scormid' => (int)$cm->instance,
                ]
            );
            foreach ($tracks as $track) {
                if (!isset($statuses[(int)$track->scoid])) {
                    $statuses[(int)$track->scoid] = core_text::strtolower((string)$track->value);
                }
            }
        }

        $activefound = false;
        foreach ($scoes as $sco) {
            $statusvalue = $statuses[(int)$sco->id] ?? '';
            $complete = in_array($statusvalue, ['completed', 'passed'], true);
            $active = !$complete && !$activefound;
            if ($active) {
                $activefound = true;
            }
            $unitnodes[] = [
                'class' => $complete ? 'complete' : ($active ? 'active' : ''),
                'title' => theme_flwacademy_format_learning_language_string($sco->title, $languagecode),
                'status' => theme_flwacademy_unit_status_label($complete, $active),
                'symbol' => $complete ? '✓' : ($active ? '●' : '○'),
                'url' => $cm->url ? $cm->url->out(false) : $courseurl,
            ];
            if (count($unitnodes) >= 10) {
                break 2;
            }
        }
    }

    if (!$unitnodes) {
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
                'title' => theme_flwacademy_format_learning_language_string(get_section_name($course, $section), $languagecode),
                'status' => theme_flwacademy_unit_status_label($complete, $active),
                'symbol' => $complete ? '✓' : ($active ? '●' : '○'),
                'url' => (new moodle_url('/course/view.php', [
                    'id' => (int)$course->id,
                    'section' => (int)$section->section,
                ]))->out(false),
            ];
            if (count($unitnodes) >= 10) {
                break;
            }
        }
    }

    $latesturl = theme_flwacademy_get_latest_course_module_url($course, $userid);
    $result = [
        'continueurl' => $latesturl ?: ($firstincompleteurl ?: ($firstcmurl ?: $courseurl)),
        'overviewurl' => $courseurl,
        'restarturl' => $firstcmurl ?: $courseurl,
        'unitnodes' => $unitnodes,
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $cache[$cachekey] = $result;
    return $cache[$cachekey];
}

/**
 * Returns units/activities studied today in the selected language.
 *
 * @param int $userid
 * @param int[] $courseids
 * @param string $fallbacklabel
 * @param string $fallbackurl
 * @param string $languagecode
 * @return array
 */
function theme_flwacademy_get_today_learning_items(
    int $userid,
    array $courseids,
    string $fallbacklabel,
    string $fallbackurl,
    string $languagecode = 'en'
): array {
    global $DB;

    $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
    sort($courseids, SORT_NUMERIC);
    $languagecode = theme_flwacademy_normalise_learning_language_code($languagecode) ?: 'en';
    static $cache = [];
    $cachekey = $userid . '|' . implode(',', $courseids) . '|' . $fallbacklabel . '|' . $fallbackurl . '|' . $languagecode;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    $items = [];
    if ($userid > 0 && $courseids && theme_flwacademy_db_table_exists('logstore_standard_log')) {
        $timezone = core_date::get_user_timezone_object();
        $startofday = (new DateTimeImmutable('now', $timezone))->setTime(0, 0)->getTimestamp();
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $params = $inparams + [
            'userid' => $userid,
            'startofday' => $startofday,
            'contextlevel' => CONTEXT_MODULE,
        ];
        $logs = $DB->get_records_sql(
            "SELECT MAX(id) AS id, courseid, contextinstanceid, MAX(timecreated) AS lasttime
               FROM {logstore_standard_log}
              WHERE userid = :userid
                AND courseid {$insql}
                AND contextlevel = :contextlevel
                AND contextinstanceid > 0
                AND timecreated >= :startofday
           GROUP BY courseid, contextinstanceid
           ORDER BY MAX(timecreated) DESC",
            $params,
            0,
            6
        );
        foreach ($logs as $log) {
            if (count($items) >= 3) {
                break;
            }
            $course = $DB->get_record('course', ['id' => (int)$log->courseid], '*', IGNORE_MISSING);
            if (!$course) {
                continue;
            }
            try {
                $modinfo = get_fast_modinfo($course, $userid);
                $cm = $modinfo->get_cm((int)$log->contextinstanceid);
                if (!$cm || !$cm->uservisible || !$cm->url) {
                    continue;
                }
                $items[] = [
                    'class' => ['blue', 'green', 'purple'][count($items)] ?? 'blue',
                    'title' => theme_flwacademy_get_course_display_name($course, $languagecode),
                    'meta' => theme_flwacademy_format_learning_language_string($cm->name, $languagecode, context_module::instance((int)$cm->id)) .
                        ' · ' . get_string('pluginname', 'mod_' . $cm->modname),
                    'time' => userdate((int)$log->lasttime, get_string('strftimetime')),
                    'url' => $cm->url->out(false),
                ];
            } catch (Throwable $exception) {
                continue;
            }
        }
    }

    if (!$items) {
        $items[] = [
            'class' => 'blue',
            'title' => get_string('startlearningtoday', 'theme_flwacademy'),
            'meta' => $fallbacklabel,
            'time' => get_string('open', 'theme_flwacademy'),
            'url' => $fallbackurl,
        ];
    }

    $cache[$cachekey] = $items;
    return $cache[$cachekey];
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

    $store = theme_flwacademy_get_cache_store('course_progress');
    static $cache = [];
    $courseid = (int)($course->id ?? 0);
    $cachekey = $courseid . '|' . $userid;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $cache[$cachekey] = $stored;
            return $stored;
        }
    }
    if ($courseid <= 0) {
        $fallback = [
            'completed' => 0,
            'total' => 0,
            'percent' => 0,
            'progress' => '0%',
            'meta' => get_string('opencourse', 'theme_flwacademy'),
            'label' => get_string('readytostart', 'theme_flwacademy'),
        ];
        if ($store) {
            $store->set($cachekey, $fallback);
        }
        $cache[$cachekey] = $fallback;
        return $cache[$cachekey];
    }

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
    $result = [
        'completed' => $completed,
        'total' => $total,
        'percent' => $percent,
        'progress' => theme_flwacademy_percent_width($percent),
        'meta' => $total > 0 ? theme_flwacademy_activity_progress_label($completed, $total) : get_string('opencourse', 'theme_flwacademy'),
        'label' => $total > 0 ? theme_flwacademy_percent_complete_label($percent) : get_string('readytostart', 'theme_flwacademy'),
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $cache[$cachekey] = $result;
    return $cache[$cachekey];
}

/**
 * Returns enrolled courses for the current Moodle user.
 *
 * @param int $limit
 * @return array
 */
function theme_flwacademy_get_user_courses(int $limit = 12): array {
    global $CFG, $USER;

    static $cache = [];
    $userid = (int)($USER->id ?? 0);
    $cachekey = $userid . '|' . $limit;
    if (array_key_exists($cachekey, $cache)) {
        return $cache[$cachekey];
    }

    if (!isloggedin() || isguestuser() || $userid <= 0) {
        $cache[$cachekey] = [];
        return [];
    }

    $store = theme_flwacademy_get_cache_store('user_courses');
    if ($store) {
        $cached = $store->get($cachekey);
        if ($cached !== false) {
            $cache[$cachekey] = $cached;
            return $cached;
        }
    }

    require_once($CFG->libdir . '/enrollib.php');
    $courses = array_values(enrol_get_my_courses(
        'format, summary, summaryformat',
        'ul.timeaccess DESC, c.sortorder ASC',
        $limit
    ));
    if ($store) {
        $store->set($cachekey, $courses);
    }
    $cache[$cachekey] = $courses;
    return $cache[$cachekey];
}

/**
 * Returns count of fully completed courses for a user by comparing completion totals to completed count.
 *
 * @param stdClass[] $courses
 * @param int $userid
 * @return int
 */
function theme_flwacademy_count_user_completed_courses(array $courses, int $userid): int {
    global $DB;

    if ($userid <= 0 || empty($courses)) {
        return 0;
    }
    $courseids = [];
    foreach ($courses as $course) {
        $courseid = (int)($course->id ?? 0);
        if ($courseid > 0) {
            $courseids[$courseid] = $courseid;
        }
    }
    if (empty($courseids)) {
        return 0;
    }

    if (!theme_flwacademy_db_table_exists('course_modules_completion')) {
        return 0;
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_values($courseids), SQL_PARAMS_NAMED, 'cid');

    $totals = $DB->get_records_sql(
        "SELECT cm.course, COUNT(cm.id) AS total
           FROM {course_modules} cm
          WHERE cm.course {$insql}
            AND cm.completion > 0
            AND cm.visible = 1
       GROUP BY cm.course",
        $inparams
    );
    if (empty($totals)) {
        return 0;
    }

    $completed = $DB->get_records_sql(
        "SELECT cm.course, COUNT(1) AS complete
           FROM {course_modules_completion} cmc
           JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
          WHERE cm.course {$insql}
            AND cmc.userid = :userid
            AND cmc.completionstate IN (1, 2, 3)
       GROUP BY cm.course",
        $inparams + ['userid' => $userid]
    );

    $completedcourses = 0;
    foreach ($totals as $courseid => $totalsrow) {
        $total = (int)$totalsrow->total;
        if ($total <= 0) {
            continue;
        }
        $complete = (int)($completed[(int)$courseid]->complete ?? 0);
        if ($complete >= $total) {
            $completedcourses++;
        }
    }

    return $completedcourses;
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
    $selectedcode = theme_flwacademy_get_active_learning_language_code();
    foreach ($learninglanguages as $language) {
        if (theme_flwacademy_normalise_learning_language_code($language['code'] ?? '') === $selectedcode) {
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
 * Adds runtime-only dashboard fragments that should not be stored in MUC.
 *
 * @param array $data
 * @return array
 */
function theme_flwacademy_enrich_dashboard_runtime_data(array $data): array {
    global $USER;

    $data['cupkpcontrolcenterhtml'] = '';
    $data['hascupkpcontrolcenter'] = false;

    if (!isloggedin() || isguestuser()) {
        return $data;
    }

    if (!class_exists('\local_flwcupkp\local\output_hooks')) {
        return $data;
    }

    try {
        $html = \local_flwcupkp\local\output_hooks::dashboard_control_center_html((int)$USER->id);
        if ($html !== '') {
            $data['cupkpcontrolcenterhtml'] = $html;
            $data['hascupkpcontrolcenter'] = true;
        }
    } catch (\Throwable $exception) {
        debugging('theme_flwacademy C-UP-KP dashboard export failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
    }

    return $data;
}

/**
 * Returns home-page course cards using Moodle categories/courses/progress.
 *
 * @param core_renderer $output
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_export_home_course_cards($output, array $learninglanguages): array {
    global $DB, $USER;

    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('home_course_cards', [
        'userid' => (int)($USER->id ?? 0),
        'languages' => array_map(static function(array $language): array {
            return [
                'code' => $language['code'] ?? '',
                'categoryurl' => $language['categoryurl'] ?? '',
                'schoolcategoryurl' => $language['schoolcategoryurl'] ?? '',
            ];
        }, $learninglanguages),
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('home_course_cards');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

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
        $categoryid = theme_flwacademy_get_categoryid_from_url($language['categoryurl'] ?? '');
        $category = $categoryid > 0
            ? $DB->get_record('course_categories', ['id' => $categoryid], '*', IGNORE_MISSING)
            : null;
        $courseid = $category
            ? theme_flwacademy_get_first_visible_course_in_category($category)
            : 0;
        $course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING) : null;
        $categoryprogress = theme_flwacademy_get_category_progress_summary(
            $categoryid,
            (int)($USER->id ?? 0),
            0,
            true
        );
        $coursecount = (int)$categoryprogress['coursecount'];
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
                'status' => $coursecount > 0
                    ? theme_flwacademy_course_count_label($coursecount)
                    : get_string('readytolearn', 'theme_flwacademy'),
                'progresslabel' => $progress && $progress['total'] > 0 ? $progress['label'] : $categoryprogress['label'],
                'progress' => $progress && $progress['total'] > 0 ? $progress['progress'] : $categoryprogress['progress'],
                'cresturl' => theme_flwacademy_redesign_asset_url($output, $carddef['asset']),
                'alt' => $carddef['name'] . ' crest',
                'url' => theme_flwacademy_get_dashboard_url_for_language($code),
                'languagecode' => $code,
            ];
        }
    }

    if ($store) {
        $store->set($cachekey, $cards);
    }
    $runtimecache[$cachekey] = $cards;
    return $cards;
}

/**
 * Returns home-page K-12 cards for the first three language worlds.
 *
 * @param core_renderer $output
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_export_home_school_groups($output, array $learninglanguages): array {
    global $DB, $USER;

    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('home_school_groups', [
        'userid' => (int)($USER->id ?? 0),
        'languages' => array_map(static function(array $language): array {
            return [
                'code' => $language['code'] ?? '',
                'schoolcategoryurl' => $language['schoolcategoryurl'] ?? '',
            ];
        }, $learninglanguages),
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('home_school_groups');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

    $targetcodes = ['en', 'ru', 'zh'];
    $slotnames = [
        'Primary Year 1',
        'Primary Year 2',
        'Primary Year 3',
        'Secondary Year 1',
        'Secondary Year 2',
        'Secondary Year 3',
        'University Year 1',
        'University Year 2',
    ];
    $groups = [];
    foreach ($learninglanguages as $language) {
        $code = theme_flwacademy_normalise_learning_language_code($language['code'] ?? '');
        if (!in_array($code, $targetcodes, true)) {
            continue;
        }

        $schoolcategoryid = theme_flwacademy_get_categoryid_from_url($language['schoolcategoryurl'] ?? '');
        $schoolcategory = $schoolcategoryid > 0
            ? $DB->get_record('course_categories', ['id' => $schoolcategoryid], 'id,name,path', IGNORE_MISSING)
            : null;
        $childrenbyname = [];
        if ($schoolcategory) {
            $children = $DB->get_records('course_categories', [
                'parent' => $schoolcategory->id,
                'visible' => 1,
            ], 'sortorder ASC', 'id,name,coursecount,path');
            foreach ($children as $child) {
                $childrenbyname[core_text::strtolower(trim($child->name))] = $child;
            }
        }

        $cards = [];
        foreach ($slotnames as $index => $slotname) {
            $child = $childrenbyname[core_text::strtolower($slotname)] ?? null;
            $targetcategoryid = $child ? (int)$child->id : 0;
            $progress = $targetcategoryid > 0
                ? theme_flwacademy_get_category_progress_summary($targetcategoryid, (int)($USER->id ?? 0), 0, true)
                : [
                    'coursecount' => 0,
                    'coursecountlabel' => theme_flwacademy_course_count_label(0),
                    'progress' => '0%',
                    'label' => get_string('readytostart', 'theme_flwacademy'),
                    'meta' => get_string('nocoursesyet', 'theme_flwacademy'),
                ];
            $cards[] = [
                'name' => $slotname,
                'shortname' => preg_replace('/^(.+?)\s+Year\s+/', '$1 ', $slotname),
                'number' => $index + 1,
                'coursecountlabel' => $progress['coursecountlabel'],
                'progress' => $progress['progress'],
                'progresslabel' => $progress['label'],
                'url' => $targetcategoryid > 0
                    ? (new moodle_url('/course/index.php', ['categoryid' => $targetcategoryid]))->out(false)
                    : ($language['schoolcategoryurl'] ?? (new moodle_url('/course/index.php'))->out(false)),
            ];
        }

        $groups[] = [
            'languagecode' => $code,
            'name' => ($language['label'] ?? 'English') . ' K-12',
            'worldname' => theme_flwacademy_get_world_label($language),
            'cresturl' => theme_flwacademy_redesign_asset_url($output, theme_flwacademy_get_world_crest_asset($code)),
            'url' => $language['schoolcategoryurl'] ?? (new moodle_url('/course/index.php'))->out(false),
            'cards' => $cards,
        ];
    }

    if ($store) {
        $store->set($cachekey, $groups);
    }
    $runtimecache[$cachekey] = $groups;
    return $groups;
}

/**
 * Returns dashboard data for the current Moodle learner.
 *
 * @param core_renderer $output
 * @param array $learninglanguages
 * @return array
 */
function theme_flwacademy_export_dashboard_data($output, array $learninglanguages): array {
    global $CFG, $DB, $USER;

    require_once($CFG->libdir . '/completionlib.php');

    $dashboardcache = theme_flwacademy_get_cache_store('dashboard');
    $selectedlanguage = theme_flwacademy_get_selected_learning_language($learninglanguages);
    $selectedcode = $selectedlanguage['code'] ?? 'en';
    $selectedcode = theme_flwacademy_normalise_learning_language_code($selectedcode) ?: 'en';
    $selectedlabel = $selectedlanguage['label'] ?? 'English';
    $selectedlanguagecategoryid = theme_flwacademy_get_categoryid_from_url($selectedlanguage['categoryurl'] ?? '');
    $selectedselfstudyid = 0;
    if (!empty($selectedlanguage['selfstudycategoryurl'])) {
        $selectedselfstudyid = theme_flwacademy_get_categoryid_from_url($selectedlanguage['selfstudycategoryurl']);
    }
    $dashboardcachekey = (int)($USER->id ?? 0) . '|' . $selectedcode . '|' . $selectedlanguagecategoryid . '|' . $selectedselfstudyid;
    if ($dashboardcache) {
        $cached = $dashboardcache->get($dashboardcachekey);
        if ($cached !== false) {
            return theme_flwacademy_enrich_dashboard_runtime_data($cached);
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
        'meta' => get_string('noactivitiesyet', 'theme_flwacademy'),
        'label' => get_string('readytostart', 'theme_flwacademy'),
    ];

    $courseurl = $course
        ? (new moodle_url('/course/view.php', ['id' => (int)$course->id]))->out(false)
        : ($selectedlanguage['selfstudycategoryurl'] ?? (new moodle_url('/course/index.php'))->out(false));
    $coursename = $course ? theme_flwacademy_get_course_display_name($course, $selectedcode) : $selectedlabel . ' World';
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
        $unitlabel = get_string('coursepath', 'theme_flwacademy');
    }

    $learningmap = $course ? theme_flwacademy_get_course_learning_map($course, (int)($USER->id ?? 0), $selectedcode) : [
        'continueurl' => $courseurl,
        'overviewurl' => $courseurl,
        'restarturl' => $courseurl,
        'unitnodes' => [],
    ];
    $languagecourseids = theme_flwacademy_get_course_ids_in_category($selectedlanguagecategoryid ?: $selectedselfstudyid);
    $todayitems = theme_flwacademy_get_today_learning_items(
        (int)($USER->id ?? 0),
        $languagecourseids,
        $coursename,
        $learningmap['continueurl'] ?: $courseurl,
        $selectedcode
    );
    $unitnodes = $learningmap['unitnodes'];
    if (!$unitnodes) {
        $unitnodes[] = [
            'class' => 'active',
            'title' => $unitlabel,
            'status' => get_string('ready', 'theme_flwacademy'),
            'symbol' => '●',
            'url' => $courseurl,
        ];
    }

    $skillrows = [];
    $skillclassmap = [
        'listening' => ['listen', 'blue'],
        'speaking' => ['speak', 'green'],
        'reading' => ['read', 'purple'],
        'writing' => ['write', 'orange'],
    ];
    foreach (($placement['skillitems'] ?? []) as $skillitem) {
        $score = (int)preg_replace('/\D+/', '', (string)($skillitem['score'] ?? '0'));
        $key = preg_replace('/[^a-z0-9]+/', '', core_text::strtolower((string)($skillitem['key'] ?? $skillitem['label'] ?? '')));
        if (!isset($skillclassmap[$key])) {
            continue;
        }
        $classes = $skillclassmap[$key] ?? ['dictate', 'cyan'];
        $skillrows[] = [
            'class' => $classes[0],
            'iconclass' => $classes[1],
            'label' => theme_flwacademy_skill_label($key),
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
            ['listening', 0, 'listen'],
            ['speaking', 0, 'speak'],
            ['reading', 0, 'read'],
            ['writing', 0, 'write'],
        ];
        foreach ($fallbackskills as $skill) {
            $skillrows[] = [
                'label' => theme_flwacademy_skill_label($skill[0]),
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

    $dictcount = 0;
    if (theme_flwacademy_db_table_exists('local_mldict_entry') && class_exists('local_mldict\\local\\dictionary')) {
        $dictcount = (int)\local_mldict\local\dictionary::count_distinct_headwords($selectedcode);
    }

    $completedcourses = theme_flwacademy_count_user_completed_courses(
        $courses,
        (int)($USER->id ?? 0)
    );

    $journeycontext = theme_flwacademy_get_cefr_journey_context($placement['overallcefr'] ?? '');
    $rankcontext = theme_flwacademy_get_language_rank_and_streak(
        (int)($USER->id ?? 0),
        $selectedcode,
        $selectedlanguagecategoryid ?: $selectedselfstudyid
    );

    $result = [
        'currentlanguagecode' => $selectedcode,
        'currentlanguagelabel' => $selectedlabel,
        'currentworldname' => $worldlabel,
        'currentworldcresturl' => $worldcresturl,
        'currentcourse' => [
            'name' => $coursename,
            'subtitle' => $unitlabel,
            'summary' => $course && !empty($course->summary)
                ? theme_flwacademy_learning_language_plain_text(
                    $course->summary,
                    $course->summaryformat ?? FORMAT_HTML,
                    context_course::instance((int)$course->id),
                    $selectedcode,
                    180
                )
                : ($placement['studyrecommendation'] ?? get_string('studyrecnextusefulstep', 'theme_flwacademy')),
            'url' => $courseurl,
            'continueurl' => $learningmap['continueurl'] ?: $courseurl,
            'overviewurl' => $learningmap['overviewurl'] ?: $courseurl,
            'restarturl' => $learningmap['restarturl'] ?: $courseurl,
            'imageurl' => $worldcresturl,
            'progress' => $progress['progress'],
            'progresslabel' => $progress['label'],
            'progressmeta' => $progress['meta'],
        ],
        'todayitems' => $todayitems,
        'unitnodes' => $unitnodes,
        'journey' => [
            'level' => $placement['overallcefr'] ?? '-',
            'small' => $placement ? get_string('placement', 'theme_flwacademy') : get_string('notplaced', 'theme_flwacademy'),
            'progresslabel' => $placement
                ? get_string('confidencewithpercent', 'theme_flwacademy', $placement['confidencepercent'] ?? '0%')
                : get_string('takeplacementtestlower', 'theme_flwacademy'),
            'reporturl' => $placement['reporturl'] ?? ($selectedlanguage['placementtesturl'] ?? theme_flwacademy_get_placement_quiz_start_url($selectedcode)),
            'steps' => $journeycontext['steps'],
            'fillwidth' => $journeycontext['fillwidth'],
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
            'title' => $placement && !empty($placement['nextcheckpointunit'])
                ? get_string('unitcheckpoint', 'theme_flwacademy', (int)$placement['nextcheckpointunit'])
                : get_string('placementcheckpoint', 'theme_flwacademy'),
            'meta' => $placement
                ? get_string('recommendedfromplacement', 'theme_flwacademy')
                : get_string('takeplacementcreatepath', 'theme_flwacademy'),
            'url' => $placement['checkpointurl'] ?? ($selectedlanguage['placementtesturl'] ?? '#'),
        ],
        'portfolio' => [
            'projects' => $completedcourses,
            'certificates' => 0,
            'artifacts' => count($courses),
            'url' => (new moodle_url('/my/'))->out(false),
        ],
        'rank' => [
            'title' => $rankcontext['title'],
            'text' => $rankcontext['text'],
            'score' => $rankcontext['score'],
            'userscount' => $rankcontext['userscount'],
        ],
        'streak' => $rankcontext['streak'],
    ];
    if ($dashboardcache) {
        $dashboardcache->set($dashboardcachekey, $result);
    }
    return theme_flwacademy_enrich_dashboard_runtime_data($result);
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

    if ($userid <= 0 || isguestuser() || !theme_flwacademy_db_table_exists('local_flwplacement_profile')) {
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
            'key' => $skill,
            'label' => theme_flwacademy_skill_label($skill),
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
        $label = theme_flwacademy_skill_label(preg_replace('/^needs_|_support$|_repair$/', '', $key));
        $supportitems[] = ['label' => $label];
    }

    $statuslabels = [
        'confirmed' => get_string('placementstatusconfirmed', 'theme_flwacademy'),
        'provisional' => get_string('placementstatusprovisional', 'theme_flwacademy'),
        'teacher_review_required' => get_string('placementstatusteacherreviewrequired', 'theme_flwacademy'),
    ];
    $pathlabels = [
        'teacher_review_first' => get_string('pathmodeteacherreviewfirst', 'theme_flwacademy'),
        'main_path_with_repair' => get_string('pathmodemainpathwithrepair', 'theme_flwacademy'),
        'review_path' => get_string('pathmodereviewpath', 'theme_flwacademy'),
        'main_path' => get_string('pathmodemainpath', 'theme_flwacademy'),
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
        'startunitbuttonlabel' => $startuniturl === '' ? '' : ($hasexactstartuniturl
            ? get_string('gotounit', 'theme_flwacademy', $recommendedstartunit)
            : get_string('findunit', 'theme_flwacademy', $recommendedstartunit)),
        'nextcheckpointunit' => $nextcheckpointunit,
        'checkpointurl' => $checkpointurl,
        'hascheckpointurl' => $checkpointurl !== '',
        'checkpointbuttonlabel' => $checkpointurl === '' ? '' : ($hasexactcheckpointurl
            ? get_string('gotounit', 'theme_flwacademy', $nextcheckpointunit)
            : get_string('findunit', 'theme_flwacademy', $nextcheckpointunit)),
        'confidencepercent' => $confidence . '%',
        'placementstatus' => $statuslabels[$profile->placementstatus] ?? get_string('placementstatuspending', 'theme_flwacademy'),
        'pathmode' => $pathlabels[$learningpath['start_mode'] ?? ''] ?? get_string('pathmodemainpath', 'theme_flwacademy'),
        'skillitems' => $skillitems,
        'hasskillitems' => !empty($skillitems),
        'supportitems' => $supportitems,
        'hassupportitems' => !empty($supportitems),
        'teacherreview' => !empty($supportflags['teacher_review_recommended']),
        'studyrecommendation' => theme_flwacademy_study_recommendation_label($profilejson['study_recommendation'] ?? 'Continue from the recommended unit and review any weak skill areas first.'),
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

    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('selfstudy_category_page', [
        'categoryid' => $categoryid,
        'userid' => (int)($USER->id ?? 0),
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('category_page');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $languagecategory = $DB->get_record('course_categories', ['id' => $category->parent], '*', MUST_EXIST);
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    $languageLabel = $language['label'] ?? theme_flwacademy_get_category_display_name($languagecategory, 'en');
    $languageCode = $language['code'] ?? 'en';

    $description = '';
    if (!empty($category->description)) {
        $description = theme_flwacademy_format_learning_language_text(
            $category->description,
            $category->descriptionformat,
            context_coursecat::instance($category->id),
            $languageCode
        );
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
            $summary = theme_flwacademy_learning_language_plain_text(
                $course->summary,
                $course->summaryformat,
                context_course::instance((int)$course->id),
                $languageCode,
                150
            );
        }
        $coursecontext = context_course::instance((int)$course->id);
        $courseitems[] = [
            'name' => theme_flwacademy_get_course_display_name($course, $languageCode),
            'shortname' => theme_flwacademy_format_learning_language_string($course->shortname, $languageCode, $coursecontext),
            'categoryname' => theme_flwacademy_format_learning_language_string($course->categoryname, $languageCode),
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

    $result = [
        'language' => $languageLabel,
        'languagecode' => $languageCode,
        'title' => theme_flwacademy_get_category_display_name($category, $languageCode),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'languagecategoryurl' => (new moodle_url('/course/index.php', ['categoryid' => $languagecategory->id]))->out(false),
        'placementtesturl' => theme_flwacademy_get_placement_quiz_start_url($languageCode),
        'heroimageurl' => $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false),
        'languagetiles' => $languageTiles,
        'hasplacementprofile' => $placementprofile !== null,
        'placementprofile' => $placementprofile ?? [],
        'mapnodes' => $mapnodes,
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $runtimecache[$cachekey] = $result;
    return $result;
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

    $activecode = theme_flwacademy_get_active_learning_language_code() ?: 'en';
    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('demo_category_page', [
        'categoryid' => $categoryid,
        'languagecode' => $activecode,
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('category_page');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $languageCode = $activecode;
    $description = '';
    if (!empty($category->description)) {
        $description = theme_flwacademy_format_learning_language_text(
            $category->description,
            $category->descriptionformat,
            context_coursecat::instance($category->id),
            $languageCode
        );
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
            $summary = theme_flwacademy_learning_language_plain_text(
                $course->summary,
                $course->summaryformat,
                context_course::instance((int)$course->id),
                $languageCode,
                150
            );
        }
        $coursecontext = context_course::instance((int)$course->id);
        $courseitems[] = [
            'name' => theme_flwacademy_get_course_display_name($course, $languageCode),
            'shortname' => theme_flwacademy_format_learning_language_string($course->shortname, $languageCode, $coursecontext),
            'categoryname' => theme_flwacademy_format_learning_language_string($course->categoryname, $languageCode),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'imageurl' => theme_flwacademy_get_course_cover_url((int)$course->id, $output),
        ];
    }

    $result = [
        'language' => 'Demo',
        'languagecode' => $languageCode,
        'title' => theme_flwacademy_get_category_display_name($category, $languageCode),
        'description' => $description,
        'hasdescription' => trim(strip_tags($description)) !== '',
        'heroimageurl' => $output->image_url('dashboard/self-study', 'theme_flwacademy')->out(false),
        'courses' => $courseitems,
        'hascourses' => !empty($courseitems),
        'coursecount' => count($courseitems),
    ];
    if ($store) {
        $store->set($cachekey, $result);
    }
    $runtimecache[$cachekey] = $result;
    return $result;
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

    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('school_category_page', [
        'categoryid' => $categoryid,
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('category_page');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $languagecategory = $DB->get_record('course_categories', ['id' => $category->parent], '*', MUST_EXIST);
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    $languageLabel = $language['label'] ?? theme_flwacademy_get_category_display_name($languagecategory, 'en');
    $languageCode = $language['code'] ?? 'en';

    $description = '';
    if (!empty($category->description)) {
        $description = theme_flwacademy_format_learning_language_text($category->description, $category->descriptionformat,
            context_coursecat::instance($category->id), $languageCode);
    }
    $hasdescription = trim(strip_tags($description)) !== '';
    $herotitle = 'School courses by institution level.';
    $descriptiontext = trim(preg_replace('/\s+/', ' ', strip_tags($description)));
    if ($descriptiontext === $herotitle) {
        $description = '';
        $hasdescription = false;
    }

    $children = $DB->get_records('course_categories', ['parent' => $category->id], 'sortorder ASC', 'id,name,description,descriptionformat,coursecount');
    $groups = [
        'primary' => [],
        'secondary' => [],
        'university' => [],
    ];

    foreach ($children as $child) {
        $namekey = core_text::strtolower($child->name);
        $item = [
            'name' => theme_flwacademy_get_category_display_name($child, $languageCode),
            'url' => (new moodle_url('/course/index.php', ['categoryid' => $child->id]))->out(false),
            'coursecount' => (int)$child->coursecount,
            'description' => '',
            'hasdescription' => false,
        ];
        if (!empty($child->description)) {
            $item['description'] = theme_flwacademy_format_learning_language_text($child->description,
                $child->descriptionformat, context_coursecat::instance($child->id), $languageCode);
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
            $summarytext = theme_flwacademy_selected_learning_language_text($course->summary, $languageCode);
            $summary = shorten_text(trim(strip_tags(format_text($summarytext, $course->summaryformat, [
                'context' => context_course::instance($course->id),
                'filter' => true,
            ]))), 150);
        }
        $coursecontext = context_course::instance((int)$course->id);
        $courseitems[] = [
            'name' => theme_flwacademy_get_course_display_name($course, $languageCode),
            'shortname' => theme_flwacademy_format_learning_language_string($course->shortname, $languageCode, $coursecontext),
            'categoryname' => theme_flwacademy_format_learning_language_string($course->categoryname, $languageCode),
            'summary' => $summary,
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    $result = [
        'language' => $languageLabel,
        'languagecode' => $languageCode,
        'title' => $herotitle,
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
    if ($store) {
        $store->set($cachekey, $result);
    }
    $runtimecache[$cachekey] = $result;
    return $result;
}

/**
 * Resolves a FLW Practice or Exam category.
 *
 * @param stdClass $category
 * @return array|null
 */
function theme_flwacademy_resolve_activity_category(stdClass $category): ?array {
    global $DB;

    $categoryid = (int)($category->id ?? 0);
    static $cache = [];
    if ($categoryid <= 0) {
        return null;
    }
    if (array_key_exists($categoryid, $cache)) {
        return $cache[$categoryid];
    }

    if (empty($category->parent)) {
        $cache[$categoryid] = null;
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
            $cache[$categoryid] = [
                'area' => $categoryname,
                'languagecategory' => $parent,
                'areacategory' => $category,
                'itemcategory' => null,
                'language' => $language,
            ];
            return $cache[$categoryid];
        }
    }

    $parentname = core_text::strtolower(trim($parent->name));
    if ($parentname !== 'practice' && $parentname !== 'exam') {
        $cache[$categoryid] = null;
        return null;
    }

    $languagecategory = $DB->get_record('course_categories', ['id' => $parent->parent], 'id,name,parent', IGNORE_MISSING);
    if (!$languagecategory) {
        return null;
    }
    $language = theme_flwacademy_match_learning_language_category($languagecategory);
    if (!$language) {
        $cache[$categoryid] = null;
        return null;
    }

    $cache[$categoryid] = [
        'area' => $parentname,
        'languagecategory' => $languagecategory,
        'areacategory' => $parent,
        'itemcategory' => $category,
        'language' => $language,
    ];
    return $cache[$categoryid];
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

    static $runtimecache = [];
    $cachekey = theme_flwacademy_export_cache_key('activity_category_page', [
        'categoryid' => $categoryid,
    ]);
    if (array_key_exists($cachekey, $runtimecache)) {
        return $runtimecache[$cachekey];
    }
    $store = theme_flwacademy_get_cache_store('category_page');
    if ($store) {
        $stored = $store->get($cachekey);
        if ($stored !== false) {
            $runtimecache[$cachekey] = $stored;
            return $stored;
        }
    }

    $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
    $resolved = theme_flwacademy_resolve_activity_category($category);
    if (!$resolved) {
        if ($store) {
            $store->set($cachekey, []);
        }
        $runtimecache[$cachekey] = [];
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
        $description = theme_flwacademy_format_learning_language_text(
            $displaycategory->description,
            $displaycategory->descriptionformat,
            context_coursecat::instance($displaycategory->id),
            $languageCode
        );
    }

    $childrenparent = $itemcategory ? (int)$areacategory->id : (int)$displaycategory->id;
    $children = $DB->get_records('course_categories', ['parent' => $childrenparent], 'sortorder ASC', 'id,name,description,descriptionformat,coursecount');
    $childitems = [];
    foreach ($children as $child) {
        $childdescription = '';
        if (!empty($child->description)) {
            $childdescription = theme_flwacademy_format_learning_language_text(
                $child->description,
                $child->descriptionformat,
                context_coursecat::instance($child->id),
                $languageCode
            );
        }
        $childitems[] = [
            'name' => theme_flwacademy_get_category_display_name($child, $languageCode),
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
            $summary = theme_flwacademy_learning_language_plain_text(
                $course->summary,
                $course->summaryformat,
                context_course::instance((int)$course->id),
                $languageCode,
                150
            );
        }
        $coursecontext = context_course::instance((int)$course->id);
        $courseitems[] = [
            'name' => theme_flwacademy_get_course_display_name($course, $languageCode),
            'shortname' => theme_flwacademy_format_learning_language_string($course->shortname, $languageCode, $coursecontext),
            'categoryname' => theme_flwacademy_format_learning_language_string($course->categoryname, $languageCode),
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
    $selectedlevel = $itemcategory ? theme_flwacademy_get_category_display_name($itemcategory, $languageCode) : ($examlevels[0] ?? 'A1');
    if (strpos($selectedlevel, 'HSK') === 0) {
        $framework = 'HSK';
    } else if (strpos($selectedlevel, 'JLPT') === 0) {
        $framework = 'JLPT';
    } else if (strpos($selectedlevel, 'TORFL') === 0) {
        $framework = 'TORFL';
    }
    $samplecontext = (object)[
        'language' => $languageLabel,
        'level' => $selectedlevel,
    ];
    $samples = [
        [
            'index' => 1,
            'title' => get_string('practicesamplelisteningtitle', 'theme_flwacademy', $framework),
            'prompt' => get_string('practicesamplelisteningprompt', 'theme_flwacademy', $samplecontext),
            'task' => get_string('practicesamplelisteningtask', 'theme_flwacademy'),
        ],
        [
            'index' => 2,
            'title' => get_string('practicesamplereadingtitle', 'theme_flwacademy', $framework),
            'prompt' => get_string('practicesamplereadingprompt', 'theme_flwacademy', $samplecontext),
            'task' => get_string('practicesamplereadingtask', 'theme_flwacademy'),
        ],
        [
            'index' => 3,
            'title' => get_string('practicesamplelanguagetitle', 'theme_flwacademy', $framework),
            'prompt' => get_string('practicesamplelanguageprompt', 'theme_flwacademy', $samplecontext),
            'task' => get_string('practicesamplelanguagetask', 'theme_flwacademy'),
        ],
        [
            'index' => 4,
            'title' => get_string('practicesamplespeakingwritingtitle', 'theme_flwacademy', $framework),
            'prompt' => get_string('practicesamplespeakingwritingprompt', 'theme_flwacademy', $samplecontext),
            'task' => get_string('practicesamplespeakingwritingtask', 'theme_flwacademy'),
        ],
    ];
    $watchcards = [
        ['title' => get_string('pronunciation', 'theme_flwacademy'), 'text' => get_string('practicecardpronunciationtext', 'theme_flwacademy'), 'imageurl' => $output->image_url('practice/Pronuciation', 'theme_flwacademy')->out(false)],
        ['title' => get_string('vocabulary', 'theme_flwacademy'), 'text' => get_string('practicecardvocabularytext', 'theme_flwacademy'), 'imageurl' => $output->image_url('practice/Vocabulary', 'theme_flwacademy')->out(false)],
        ['title' => get_string('courses', 'theme_flwacademy'), 'text' => get_string('practicecardcoursestext', 'theme_flwacademy'), 'imageurl' => $output->image_url('practice/Courses', 'theme_flwacademy')->out(false)],
        ['title' => get_string('practicecardskill', 'theme_flwacademy'), 'text' => get_string('practicecardskilltext', 'theme_flwacademy'), 'imageurl' => $output->image_url('practice/Skills', 'theme_flwacademy')->out(false)],
        ['title' => get_string('practicecardworkenglish', 'theme_flwacademy'), 'text' => get_string('practicecardworkenglishtext', 'theme_flwacademy'), 'imageurl' => $output->image_url('practice/work-english', 'theme_flwacademy')->out(false)],
    ];

    $result = [
        'language' => $languageLabel,
        'languagecode' => $languageCode,
        'area' => $area,
        'ispractice' => $area === 'practice',
        'isexam' => $area === 'exam',
        'title' => theme_flwacademy_get_category_display_name($displaycategory, $languageCode),
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
    if ($store) {
        $store->set($cachekey, $result);
    }
    $runtimecache[$cachekey] = $result;
    return $result;
}
