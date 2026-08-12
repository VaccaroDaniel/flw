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
            'stringsjson' => json_encode(self::hub_strings(), JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Return localized strings used by the JavaScript Practice hub.
     *
     * @return array
     */
    private static function hub_strings(): array {
        return [
            'all' => get_string('all', 'local_flwmedia'),
            'audio' => get_string('audio', 'local_flwmedia'),
            'correct' => get_string('correct', 'local_flwmedia'),
            'dictate' => get_string('modedictate', 'local_flwmedia'),
            'flwpractice' => get_string('flwpractice', 'local_flwmedia'),
            'item' => get_string('item', 'local_flwmedia'),
            'items' => get_string('items', 'local_flwmedia'),
            'listen' => get_string('modelisten', 'local_flwmedia'),
            'loaderror' => get_string('loaderror', 'local_flwmedia'),
            'loadingpractice' => get_string('loadingpractice', 'local_flwmedia'),
            'nopracticemedia' => get_string('nopracticemedia', 'local_flwmedia'),
            'practicepage' => get_string('practicepage', 'local_flwmedia'),
            'record' => get_string('record', 'local_flwmedia'),
            'recording' => get_string('recording', 'local_flwmedia'),
            'recordingnotsupported' => get_string('recordingnotsupported', 'local_flwmedia'),
            'recordingsaved' => get_string('recordingsaved', 'local_flwmedia'),
            'read' => get_string('moderead', 'local_flwmedia'),
            'readingcompleted' => get_string('readingcompleted', 'local_flwmedia'),
            'searcharia' => get_string('searcharia', 'local_flwmedia'),
            'searchpractice' => get_string('searchpractice', 'local_flwmedia'),
            'scoreprefix' => get_string('scoreprefix', 'local_flwmedia'),
            'speak' => get_string('modespeak', 'local_flwmedia'),
            'text' => get_string('text', 'local_flwmedia'),
            'tryanotherfilter' => get_string('tryanotherfilter', 'local_flwmedia'),
            'type' => get_string('type', 'local_flwmedia'),
            'video' => get_string('video', 'local_flwmedia'),
            'watch' => get_string('modewatch', 'local_flwmedia'),
            'wordoverlapsuffix' => get_string('wordoverlapsuffix', 'local_flwmedia'),
            'microphonenotgranted' => get_string('microphonenotgranted', 'local_flwmedia'),
        ];
    }
}
