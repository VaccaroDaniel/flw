<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Get supported FLW media practice modes.
 *
 * @return string[]
 */
function local_flwmedia_get_modes(): array {
    return ['watch', 'listen', 'speak', 'read', 'dictate'];
}

/**
 * Get supported FLW media categories.
 *
 * @return string[]
 */
function local_flwmedia_get_categories(): array {
    return [
        'unit_watch',
        'model_dialogue',
        'vocabulary',
        'model_sentence',
        'pronunciation',
        'story',
        'project',
        'dictation',
        'reading',
        'review',
    ];
}

/**
 * Require language-level access to FLW media.
 *
 * @param string $capability Required capability.
 * @return context_system
 */
function local_flwmedia_require_practice_access(string $capability = 'local/flwmedia:view'): context_system {
    $context = context_system::instance();
    require_login();
    if ($capability === 'local/flwmedia:view' && isloggedin() && !isguestuser()) {
        return $context;
    }
    require_capability($capability, $context);

    return $context;
}

/**
 * Legacy wrapper for old course-specific calls.
 *
 * @param int $courseid Ignored legacy course id.
 * @param string $capability Required capability.
 * @return context_system
 */
function local_flwmedia_require_course_access(int $courseid, string $capability = 'local/flwmedia:view'): context_system {
    return local_flwmedia_require_practice_access($capability);
}
