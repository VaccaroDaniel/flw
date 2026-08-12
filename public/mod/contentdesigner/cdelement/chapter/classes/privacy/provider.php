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
 * Privacy class for requesting user data.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace cdelement_chapter\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\approved_contextlist;

/**
 * Privacy class for requesting user data.
 *
 * @package   cdelement_chapter
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \mod_contentdesigner\privacy\contentdesignerelements_provider {
    /**
     * List of used data fields summary meta key.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        // Module Completion table fields meta summary.
        $completionmetadata = [
            'instance' => 'privacy:metadata:completion:chapterid',
            'userid' => 'privacy:metadata:completion:userid',
            'completion' => 'privacy:metadata:completion:completion',
            'timecreated' => 'privacy:metadata:completion:timecreated',
        ];
        $collection->add_database_table('cdelement_chapter_completion', $completionmetadata, 'privacy:metadata:chaptercompletion');
        return $collection;
    }

    /**
     * Helper function to export completions.
     *
     * The array of "completions" is actually the result returned by the SQL in export_user_data.
     * It is more of a list of sessions. Which is why it needs to be grouped by context id.
     *
     * @param array $contentdesignerids Array of completions to export the logs for.
     * @param stdclass $user User record object.
     */
    public static function export_element_user_data(array $contentdesignerids, \stdclass $user) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($contentdesignerids, SQL_PARAMS_NAMED);
        $sql = "SELECT ec.*, ecc.userid AS userid, ecc.completion AS completion, ecc.timecreated AS timecompleted
            FROM {cdelement_chapter} ec
            INNER JOIN {cdelement_chapter_completion} ecc ON ecc.instance = ec.id AND ecc.userid = :userid
            WHERE ec.contentdesignerid {$insql}
            ORDER BY ec.id";

        $params = [
            'userid' => $user->id,
        ];
        $completions = $DB->get_records_sql($sql, $params + $inparams);
        foreach ($completions as $chapterid => $completion) {
            $data[$chapterid] = (object) [
                'completed' => (($completion->completion == 1) ? get_string('yes') : get_string('no')),
                'completedtime' => $completion->timecreated ? transform::datetime($completion->timecreated) : '-',
                'title' => $completion->title,
                'contentdesignerid' => $completion->contentdesignerid,
            ];
        }
        return $data;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
        ];

        // Discussion authors.
        $sql = "SELECT ecc.userid
        FROM {cdelement_chapter} ec
        JOIN {cdelement_chapter_completion} ecc ON ecc.instance = ec.id
        WHERE ec.contentdesignerid = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $contentdesigner = $DB->get_record('contentdesigner', ['id' => $cm->instance]);

        [$userinsql, $userinparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'usr');
        $list = $DB->get_records('cdelement_chapter', ['contentdesignerid' => $contentdesigner->id]);
        $ids = array_column($list, 'id');
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ch');
        $DB->delete_records_select('cdelement_chapter_completion',
        "userid {$userinsql} AND instance $insql", $userinparams + $inparams);
    }

    /**
     * Delete user completion data for multiple context.
     *
     * @param approved_contextlist $contextlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $list = $DB->get_records('cdelement_chapter', ['contentdesignerid' => $instanceid]);
            $ids = array_column($list, 'id');
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ch');
            $DB->delete_records_select('cdelement_chapter_completion', "userid=:userid AND instance $insql", [
            'userid' => $userid] + $inparams);
        }
    }

    /**
     * Delete all completion data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('contentdesigner', $context->instanceid);
        if (!$cm) {
            return;
        }
        $list = $DB->get_records('cdelement_chapter', ['contentdesignerid' => $cm->instance]);
        $ids = array_column($list, 'id');
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ch');
        $DB->delete_records_select('cdelement_chapter_completion', "instance $insql", $inparams);
    }
}
