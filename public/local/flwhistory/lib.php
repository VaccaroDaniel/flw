<?php
// Library hooks for local_flwhistory.

defined('MOODLE_INTERNAL') || die();

/**
 * Add the learner history dashboard link to course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_flwhistory_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    if (!has_capability('local/flwhistory:viewown', $context)
            && !has_capability('local/flwhistory:viewcourse', $context)
            && !has_capability('local/flwhistory:viewall', context_system::instance())) {
        return;
    }

    $navigation->add(
        get_string('dashboardnav', 'local_flwhistory'),
        new moodle_url('/local/flwhistory/dashboard.php', ['courseid' => (int)$course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_flwhistory_dashboard'
    );

    if (has_capability('local/flwhistory:viewcourse', $context)
            || has_capability('local/flwhistory:viewall', context_system::instance())) {
        $navigation->add(
            get_string('teacheranalyticsnav', 'local_flwhistory'),
            new moodle_url('/local/flwhistory/teacher.php', ['courseid' => (int)$course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_flwhistory_teacher'
        );
    }
}
