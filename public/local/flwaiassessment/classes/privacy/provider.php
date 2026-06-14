<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwaiassessment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy metadata for FLW AI assessment.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_flwai_results', [
            'userid' => 'privacy:metadata:local_flwai_results:userid',
            'rawtext' => 'privacy:metadata:local_flwai_results:rawtext',
            'transcript' => 'privacy:metadata:local_flwai_results:transcript',
            'cefrlevel' => 'privacy:metadata:local_flwai_results:cefrlevel',
            'teachernote' => 'privacy:metadata:local_flwai_results:teachernote',
        ], 'privacy:metadata:local_flwai_results');

        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('local_flwai_results', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            $records = array_values($DB->get_records('local_flwai_results', ['userid' => $userid], 'timecreated ASC'));
            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_flwaiassessment')],
                    (object) ['results' => $records]
                );
            }
        }
    }

    /**
     * Delete all plugin data in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_flwai_results');
        }
    }

    /**
     * Delete user data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        foreach ($contextlist as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('local_flwai_results', ['userid' => $contextlist->get_user()->id]);
            }
        }
    }
}
