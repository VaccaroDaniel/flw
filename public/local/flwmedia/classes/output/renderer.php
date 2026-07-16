<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

/**
 * Renderer for FLW Media.
 *
 * @package    local_flwmedia
 */
class renderer extends plugin_renderer_base {
    /**
     * Render a media hub.
     *
     * @param mediahub $hub Hub renderable.
     * @return string
     */
    public function render_mediahub(mediahub $hub): string {
        $this->page->requires->js_call_amd('local_flwmedia/mediahub', 'initAll');
        return $this->render_from_template('local_flwmedia/mediahub', $hub->export_for_template($this));
    }
}
