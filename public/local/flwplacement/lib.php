<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add the FLW placement link to course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_flwplacement_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!has_capability('local/flwplacement:take', $context) && (!isloggedin() || isguestuser())) {
        return;
    }

    $url = new moodle_url('/local/flwplacement/index.php');
    $navigation->add(
        get_string('courseplacementlink', 'local_flwplacement'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_flwplacement_take'
    );
}
