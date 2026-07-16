<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;

/**
 * Renderable FLW media hub placeholder.
 *
 * @package    local_flwmedia
 */
class mediahub implements renderable, templatable {
    /** @var string Language code. */
    private string $language;

    /** @var int Optional legacy course id. */
    private int $courseid;

    /** @var string Unit code. */
    private string $unitcode;

    /** @var string Default mode. */
    private string $defaultmode;

    /**
     * Constructor.
     *
     * @param string $language Language code.
     * @param string $unitcode Unit code.
     * @param string $defaultmode Default mode.
     * @param int $courseid Optional legacy course filter.
     */
    public function __construct(string $language, string $unitcode, string $defaultmode = 'watch', int $courseid = 0) {
        $this->language = $language;
        $this->courseid = $courseid;
        $this->unitcode = $unitcode;
        $this->defaultmode = $defaultmode;
    }

    /**
     * Export template data.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'language' => $this->language,
            'courseid' => $this->courseid,
            'unitcode' => $this->unitcode,
            'defaultmode' => $this->defaultmode,
        ];
    }
}
