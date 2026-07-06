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
