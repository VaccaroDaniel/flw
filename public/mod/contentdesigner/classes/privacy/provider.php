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
 * Privacy implementation for contentdesigner module
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_contentdesigner\privacy;

use stdClass;
use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\manager;

/**
 * The contentdesigner module stores user completion and invitation notified details.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Get metadata about this plugin's data usage.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): collection {
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param  int         $userid    The user to search.
     * @return contextlist $contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        return $contextlist;
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

        // Handle the 'contentdesigner' subplugin.
        manager::plugintype_class_callback(
            'contentdesignerelements',
            contentdesignerelements_provider::class,
            'get_users_in_context',
            [$userlist]
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // Handle the 'contentdesigner' subplugin.
        manager::plugintype_class_callback(
            'contentdesignerelements',
            contentdesignerelements_provider::class,
            'delete_data_for_users',
            [$userlist]
        );
    }

    /**
     * Delete user completion data for multiple context.
     *
     * @param approved_contextlist $contextlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {

        if (empty($contextlist->count())) {
            return;
        }

        // Handle the 'contentdesigner' subplugin.
        manager::plugintype_class_callback(
            'contentdesignerelements',
            contentdesignerelements_provider::class,
            'delete_data_for_user',
            [$contextlist]
        );
    }

    /**
     * Delete all completion data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('contentdesigner', $context->instanceid);
        if (!$cm) {
            return;
        }
        // Handle the 'contentdesigner' subplugin.
        manager::plugintype_class_callback(
            'contentdesignerelements',
            contentdesignerelements_provider::class,
            'delete_subplugin_data_for_all_users_in_context',
            [$context]
        );
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }
        // Context user.
        $user = $contextlist->get_user();
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT cm.id AS cmid, c.id AS contextid,
            p.id AS pid, p.course AS pcourse
            FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {contentdesigner} p ON p.id = cm.instance
            WHERE c.id {$contextsql}
            ORDER BY cm.id";

        $params = [
            'modname' => 'contentdesigner',
            'contextlevel' => CONTEXT_MODULE,
        ];
        $instances = $DB->get_records_sql($sql, $params + $contextparams);

        $contentdesignerids = array_column((array) $instances, 'pid');
        $components = \core_component::get_plugin_list('cdelement');
        $exportparams = [
            $contentdesignerids,
            $user,
        ];
        foreach (array_keys($components) as $component) {
            $classname = manager::get_provider_classname_for_component("cdelement_$component");
            if (class_exists($classname) && is_subclass_of($classname, contentdesignerelements_provider::class)) {
                $results = component_class_callback($classname, 'export_element_user_data', $exportparams);
                $instances = self::group_by_property($results, 'contentdesignerid');

                foreach ($instances as $instanceid => $element) {
                    if (empty($element)) {
                        continue;
                    }
                    $cm = get_coursemodule_from_instance('contentdesigner', $element[0]->contentdesignerid);
                    $context = \context_module::instance($cm->id);
                    // Fetch the generic module data for the questionnaire.
                    $contextdata = helper::get_context_data($context, $user);
                    unset($element['contentdesignerid']);
                    $contextdata = (object)array_merge((array)$contextdata, $element);
                    writer::with_context($context)->export_data(
                        [get_string('privacy:' . $component, 'cdelement_' . $component)],
                        $contextdata
                    );
                }
            }
        }
    }

    /**
     * Helper function to group an array of stdClasses by a common property.
     *
     * @param array $classes An array of classes to group.
     * @param string $property A common property to group the classes by.
     * @return array list of element seperated by given property.
     */
    private static function group_by_property(array $classes, string $property): array {
        return array_reduce(
            $classes,
            function (array $classes, stdClass $class) use ($property): array {
                $classes[$class->{$property}][] = $class;
                return $classes;
            },
            []
        );
    }
}
