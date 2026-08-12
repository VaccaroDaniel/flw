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
 * Chapter element external werbservice deifintion to manage the chapter completion.
 *
 * @package    cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_chapter;

defined('MOODLE_INTERNAL') || die('No direct access');

use external_value;
require_once($CFG->libdir . '/externallib.php');

/**
 * Chapter external service methods.
 */
class external extends \external_api {
    /**
     * Paramters definition for the methos update chapter progress of user.
     *
     * @return external_function_parameters
     */
    public static function update_completion_parameters() {
        return new \external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'chapter' => new external_value(PARAM_INT, 'Chapter element instance id'),
            ]
        );
    }

    /**
     * Update the content designer chapter progress for the current logged in user.
     *
     * @param int $cmid Coursemodule id.
     * @param int $chapter Content designer module instance id.
     * @return bool true if everything updated fine, false if not.
     */
    public static function update_completion($cmid, $chapter) {
        global $DB, $USER;

        $vaildparams = self::validate_parameters(self::update_completion_parameters(), ['cmid' => $cmid, 'chapter' => $chapter]);
        self::validate_context(\context_module::instance($cmid));
        $chapter = $vaildparams['chapter'];
        $record = $DB->get_record('cdelement_chapter_completion', ['instance' => $chapter, 'userid' => $USER->id]);
        $data = new \stdclass();
        $data->instance = $chapter;
        $data->userid = $USER->id;
        $data->completion = true;
        $data->timemodified = time();
        if (isset($record->id)) {
            $data->id = $record->id;
            return $DB->update_record('cdelement_chapter_completion', $data);
        } else {
            $data->timecreated = time();
            if (!$DB->insert_record('cdelement_chapter_completion', $data)) {
                return false;
            }
        }
        return false;
    }

    /**
     * Returns the updated result of module completion.
     *
     * @return external_value True if state updated otherwise  returns false.s
     */
    public static function update_completion_returns() {
        return new external_value(PARAM_BOOL, 'Result of stored user response');
    }
}
