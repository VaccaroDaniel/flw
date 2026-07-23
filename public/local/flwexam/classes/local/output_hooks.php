<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwexam\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks for FLW exam assets.
 */
final class output_hooks {
    /**
     * Adds the FLW exam stylesheet while the document head is still open.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        global $CFG;

        $stylesheet = $CFG->dirroot . '/local/flwexam/styles.css';
        $version = is_readable($stylesheet) ? filemtime($stylesheet) : time();
        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => (new \moodle_url('/local/flwexam/styles.css', ['v' => $version]))->out(false),
        ]));
    }
}
