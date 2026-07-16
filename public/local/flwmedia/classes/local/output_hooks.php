<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Output hook callbacks for FLW media assets.
 */
final class output_hooks {
    /**
     * Adds the FLW media stylesheet while the document head is still open.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook,
    ): void {
        $hook->add_html(\html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => (new \moodle_url('/local/flwmedia/styles.css'))->out(false),
        ]));
    }

    /**
     * Queues the lightweight hub auto-initializer before footer JS is finalized.
     *
     * This lets teacher-pasted .flwmedia-hub containers work in normal Moodle
     * content areas without theme changes. The AMD module exits immediately if
     * no hub is present on the page.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook,
    ): void {
        global $PAGE;

        $PAGE->requires->js_call_amd('local_flwmedia/mediahub', 'initAll');
    }
}
