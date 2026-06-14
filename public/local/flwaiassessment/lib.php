<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add the FLW AI assessment link to course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_flwaiassessment_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!has_capability('local/flwaiassessment:manage', $context)) {
        return;
    }

    $url = new moodle_url('/local/flwaiassessment/submit.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('courseassessmentlink', 'local_flwaiassessment'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_flwaiassessment_submit'
    );
}
