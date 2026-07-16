<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Require the plugin database tables.
 */
function local_flwexam_require_installed(): void {
    global $DB;

    if (!$DB->get_manager()->table_exists('local_flwexam_results')) {
        throw new moodle_exception('pluginnotinstalled', 'local_flwexam');
    }
}

/**
 * Format a score for display.
 *
 * @param float $score
 * @return string
 */
function local_flwexam_format_score(float $score): string {
    return format_float($score, 1) . '%';
}

/**
 * Render the FLW Academy-style page hero used across exam pages.
 *
 * @param string $kicker Small uppercase page label.
 * @param string $title Main page title.
 * @param string $intro Supporting introduction text.
 * @param array $actions List of rendered action links/buttons.
 * @param array $stats Optional summary cards as label => value.
 * @return string
 */
function local_flwexam_render_hero(
    string $kicker,
    string $title,
    string $intro,
    array $actions = [],
    array $stats = [],
): string {
    $content = html_writer::start_div('flwexam-hero-copy');
    $content .= html_writer::tag('p', s($kicker), ['class' => 'flwexam-kicker']);
    $content .= html_writer::tag('h2', s($title));
    if ($intro !== '') {
        $content .= html_writer::tag('p', s($intro), ['class' => 'flwexam-lead']);
    }
    if ($actions) {
        $content .= html_writer::div(implode('', $actions), 'flwexam-hero-actions');
    }
    $content .= html_writer::end_div();

    if ($stats) {
        $content .= html_writer::start_div('flwexam-hero-stats');
        foreach ($stats as $label => $value) {
            $content .= html_writer::div(
                html_writer::span(s($label)) .
                html_writer::tag('strong', s($value)),
                'flwexam-stat-card'
            );
        }
        $content .= html_writer::end_div();
    }

    return html_writer::tag('section', $content, ['class' => 'flwexam-hero']);
}
