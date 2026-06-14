<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwaispeaking\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for FLW AI Speaking.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Return metadata.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('flwaispeaking_submissions', [
            'userid' => 'privacy:metadata:flwaispeaking_submissions:userid',
            'transcript' => 'privacy:metadata:flwaispeaking_submissions:transcript',
            'assessmentid' => 'privacy:metadata:flwaispeaking_submissions:assessmentid',
        ], 'privacy:metadata:flwaispeaking_submissions');

        return $collection;
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {flwaispeaking} fs ON fs.id = cm.instance
                  JOIN {flwaispeaking_submissions} sub ON sub.flwaispeakingid = fs.id
                 WHERE sub.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'flwaispeaking',
            'userid' => $userid,
        ]);

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
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('flwaispeaking', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $records = $DB->get_records('flwaispeaking_submissions', [
                'flwaispeakingid' => $cm->instance,
                'userid' => $userid,
            ], 'timecreated ASC');

            if ($records) {
                writer::with_context($context)->export_data(
                    [get_string('modulename', 'flwaispeaking')],
                    (object) ['submissions' => array_values($records)]
                );
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('flwaispeaking', $context->instanceid);
        if ($cm) {
            $DB->delete_records('flwaispeaking_submissions', ['flwaispeakingid' => $cm->instance]);
        }
    }

    /**
     * Delete user data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('flwaispeaking', $context->instanceid);
            if ($cm) {
                $DB->delete_records('flwaispeaking_submissions', [
                    'flwaispeakingid' => $cm->instance,
                    'userid' => $userid,
                ]);
            }
        }
    }
}
