<?php
// Navigation callbacks for local_flwtextbookimport.

defined('MOODLE_INTERNAL') || die();

/**
 * Add a teacher/admin review link on FLW textbook-generated courses.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_flwtextbookimport_extend_navigation_course(navigation_node $navigation, stdClass $course,
        context_course $context): void {
    global $DB;

    if (!has_capability('moodle/site:config', context_system::instance())) {
        return;
    }
    if (!$DB->record_exists('flwtbi_review', ['courseid' => $course->id])) {
        return;
    }

    $navigation->add(
        get_string('reviewnav', 'local_flwtextbookimport'),
        new moodle_url('/local/flwtextbookimport/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_flwtextbookimport_review'
    );
}
