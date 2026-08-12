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
 * @package   cdelement_h5p
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_h5p;

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
    public static function store_result_data_parameters() {
        return new \external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'instanceid' => new external_value(PARAM_INT, 'H5P element instance id'),
                'result' => new \external_single_structure(
                    [
                        'completion' => new external_value(PARAM_BOOL, 'Attempt userid'),
                        'success' => new external_value(PARAM_BOOL, 'Attempt userid'),
                        'response' => new external_value(PARAM_TEXT, 'Response of the user attempt', VALUE_OPTIONAL),
                        'duration' => new external_value(PARAM_TEXT, 'Duration of the user attempt', VALUE_OPTIONAL),
                        'score' => new \external_single_structure(
                            [
                                'max' => new external_value(PARAM_FLOAT, 'Max number of score'),
                                'min' => new external_value(PARAM_FLOAT, 'Max number of score'),
                                'scaled' => new external_value(PARAM_FLOAT, 'Max number of score'),
                                'raw' => new external_value(PARAM_ALPHANUMEXT, 'Max number of score'),
                            ]
                        ),
                    ]
                ),
            ]
        );
    }

    /**
     * Store the user response for the H5P.
     *
     * @param int $cmid Course module id.
     * @param int $instanceid Instance id of the H5P.
     * @param array $result Result object form the H5P data statement.
     * @return bool
     */
    public static function store_result_data($cmid, $instanceid, $result) {
        global $DB, $USER;

        $vaildparams = self::validate_parameters(self::store_result_data_parameters(), [
            'cmid' => $cmid,
            'instanceid' => $instanceid,
            'result' => $result,
        ]);

        self::validate_context(\context_module::instance($cmid));

        $instanceid = $vaildparams['instanceid'];
        $result = $vaildparams['result'];

        if ($record = $DB->get_record('cdelement_h5p_completion', ['instance' => $instanceid, 'userid' => $USER->id])) {
            // Store only highest scored results only.
            if ($record->score > $result['score']['raw']) {
                return true;
            }
        }

        $data = new \stdclass();
        $data->instance = $instanceid;
        $data->userid = $USER->id;
        $data->completion = $result['completion'];
        $data->success = $result['success'];
        $data->duration = $result['duration'] ?: '';
        $data->score = $result['score']['raw'];
        $data->scoredata = json_encode($result['score']);
        $data->response = $result['response'];
        $data->timemodified = time();
        if (isset($record->id)) {
            $data->id = $record->id;
            $DB->update_record('cdelement_h5p_completion', $data);
        } else {
            $data->timecreated = time();
            if (!$DB->insert_record('cdelement_h5p_completion', $data)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the updated result of store data.
     *
     * @return external_value True if data updated otherwise  returns false.
     */
    public static function store_result_data_returns() {
        return new external_value(PARAM_BOOL, 'Result of stored user response');
    }
}
