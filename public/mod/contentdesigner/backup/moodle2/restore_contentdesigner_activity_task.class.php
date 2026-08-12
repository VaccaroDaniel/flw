<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Definition restore activity task.
 *
 * @package   mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('No direct access!');

require_once($CFG->dirroot . '/mod/contentdesigner/backup/moodle2/restore_contentdesigner_stepslib.php');

/**
 * Pulse restore task that provides all the settings and steps to perform one. complete restore of the activity
 */
class restore_contentdesigner_activity_task extends restore_activity_task {
    /**
     * Define particular settings for this activity.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define restore structure steps to restore to database from contentdesigner.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_contentdesigner_activity_structure_step('contentdesigner_structure', 'contentdesigner.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('contentdesigner', ['intro'], 'contentdesigner');
        $contents[] = new \restore_decode_content('cdelement_richtext', ['content'], 'richtext_instanceid');
        $contents[] = new \restore_decode_content('cdelement_outro', ['outrocontent'], 'outro_instanceid');
        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        return [];
    }
}
