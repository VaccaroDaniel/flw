<?php
// This file is part of Moodle - http://moodle.org/

namespace local_mldict\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks for the floating dictionary.
 */
final class floating_dictionary {
    /**
     * Adds the floating dictionary stylesheet while the page head is still open.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_system::instance();
        if (!has_capability('local/mldict:view', $context)) {
            return;
        }

        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => (new \moodle_url('/local/mldict/styles.css'))->out(false),
        ]));
    }

    /**
     * Adds the floating dictionary after the main page region.
     *
     * @param \core\hook\output\after_standard_main_region_html_generation $hook
     */
    public static function after_standard_main_region_html_generation(
        \core\hook\output\after_standard_main_region_html_generation $hook,
    ): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/mldict/lib.php');
        $hook->add_html(local_mldict_render_floating_dictionary());
    }
}
