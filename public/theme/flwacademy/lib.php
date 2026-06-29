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
        ['code' => 'en', 'label' => 'English', 'aliases' => ['English']],
        ['code' => 'ru', 'label' => 'Russian', 'aliases' => ['Russian']],
        ['code' => 'zh', 'label' => 'Chinese', 'aliases' => ['Chinese', 'Chinese Language', 'Han Chinese', '汉语']],
        ['code' => 'ja', 'label' => 'Japanese', 'aliases' => ['Japanese']],
        ['code' => 'de', 'label' => 'German', 'aliases' => ['German']],
        ['code' => 'fr', 'label' => 'French', 'aliases' => ['French']],
        ['code' => 'es', 'label' => 'Spanish', 'aliases' => ['Spanish']],
    ];
}

/**
 * Exports learning languages with matching Moodle course category URLs.
 *
 * @return array
 */
function theme_flwacademy_export_learning_languages(): array {
    global $DB;

    $records = $DB->get_records('course_categories', ['parent' => 0], 'sortorder ASC', 'id,name');
    $categoriesbyname = [];
    foreach ($records as $record) {
        $categoriesbyname[core_text::strtolower(trim($record->name))] = $record;
    }

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

        $languages[] = [
            'code' => $language['code'],
            'label' => $language['label'],
            'categoryurl' => $url->out(false),
            'categoryid' => $category ? $category->id : 0,
            'isdefault' => $index === 0,
        ];
    }

    return $languages;
}
