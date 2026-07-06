<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwplacement\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy metadata provider for FLW Placement.
 *
 * @package    local_flwplacement
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored user data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_flwplacement', [
            'userid' => 'privacy:metadata:local_flwplacement:userid',
            'courseid' => 'privacy:metadata:local_flwplacement:courseid',
            'resultjson' => 'privacy:metadata:local_flwplacement:resultjson',
            'attemptjson' => 'privacy:metadata:local_flwplacement:attemptjson',
        ], 'privacy:metadata:local_flwplacement');

        return $collection;
    }

    /**
     * Get contexts containing placement data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $records = $DB->get_records('local_flwplacement', ['userid' => $userid], '', 'courseid');
        foreach ($records as $record) {
            if ((int)$record->courseid === SITEID) {
                $contextlist->add_system_context();
            } else {
                $context = \context_course::instance($record->courseid, IGNORE_MISSING);
                if ($context) {
                    $contextlist->add_context($context);
                }
            }
        }

        return $contextlist;
    }

    /**
     * Export placement data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $records = array_values($DB->get_records('local_flwplacement', [
                    'userid' => $userid,
                    'courseid' => SITEID,
                ], 'timecreated ASC'));
            } else if ($context->contextlevel === CONTEXT_COURSE) {
                $records = array_values($DB->get_records('local_flwplacement', [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ], 'timecreated ASC'));
            } else {
                $records = [];
            }

            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_flwplacement')],
                    (object) ['results' => $records]
                );
            }
        }
    }

    /**
     * Delete all placement data in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_flwplacement', ['courseid' => SITEID]);
        } else if ($context->contextlevel === CONTEXT_COURSE) {
            $DB->delete_records('local_flwplacement', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete placement data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('local_flwplacement', [
                    'userid' => $userid,
                    'courseid' => SITEID,
                ]);
            } else if ($context->contextlevel === CONTEXT_COURSE) {
                $DB->delete_records('local_flwplacement', [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ]);
            }
        }
    }
}
