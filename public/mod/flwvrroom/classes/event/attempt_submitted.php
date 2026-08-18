<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a learner submits an FLW VR Room attempt.
 */
class attempt_submitted extends \core\event\base {
    /**
     * Initialise event data.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'flwvrroom_attempts';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventattemptsubmitted', 'flwvrroom');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' submitted FLW VR Room attempt '{$this->objectid}'.";
    }

    /**
     * Related activity URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/flwvrroom/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Object ID mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'flwvrroom_attempts', 'restore' => 'flwvrroom_attempt'];
    }
}
