<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for FLW Media.
 *
 * @package    local_flwmedia
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe user data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_flwmedia_progress', [
            'userid' => 'privacy:metadata:local_flwmedia_progress:userid',
            'courseid' => 'privacy:metadata:local_flwmedia_progress:courseid',
            'itemid' => 'privacy:metadata:local_flwmedia_progress:itemid',
            'mode' => 'privacy:metadata:local_flwmedia_progress:mode',
            'secondsdone' => 'privacy:metadata:local_flwmedia_progress:secondsdone',
            'percentdone' => 'privacy:metadata:local_flwmedia_progress:percentdone',
            'completed' => 'privacy:metadata:local_flwmedia_progress:completed',
            'score' => 'privacy:metadata:local_flwmedia_progress:score',
            'attemptjson' => 'privacy:metadata:local_flwmedia_progress:attemptjson',
        ], 'privacy:metadata:local_flwmedia_progress');

        $collection->add_database_table('local_flwmedia_attempts', [
            'userid' => 'privacy:metadata:local_flwmedia_attempts:userid',
            'courseid' => 'privacy:metadata:local_flwmedia_attempts:courseid',
            'itemid' => 'privacy:metadata:local_flwmedia_attempts:itemid',
            'mode' => 'privacy:metadata:local_flwmedia_attempts:mode',
            'response' => 'privacy:metadata:local_flwmedia_attempts:response',
            'transcript' => 'privacy:metadata:local_flwmedia_attempts:transcript',
            'score' => 'privacy:metadata:local_flwmedia_attempts:score',
            'feedback' => 'privacy:metadata:local_flwmedia_attempts:feedback',
            'audiofileurl' => 'privacy:metadata:local_flwmedia_attempts:audiofileurl',
            'attemptjson' => 'privacy:metadata:local_flwmedia_attempts:attemptjson',
        ], 'privacy:metadata:local_flwmedia_attempts');

        return $collection;
    }

    /**
     * Get contexts where a user has data.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        $courseids = [];

        foreach ($DB->get_records('local_flwmedia_progress', ['userid' => $userid], '', 'courseid') as $record) {
            $courseids[(int)$record->courseid] = true;
        }
        foreach ($DB->get_records('local_flwmedia_attempts', ['userid' => $userid], '', 'courseid') as $record) {
            $courseids[(int)$record->courseid] = true;
        }

        foreach (array_keys($courseids) as $courseid) {
            if ((int)$courseid === SITEID) {
                $contextlist->add_system_context();
            } else {
                $context = \context_course::instance($courseid, IGNORE_MISSING);
                if ($context) {
                    $contextlist->add_context($context);
                }
            }
        }

        return $contextlist;
    }

    /**
     * Export approved user data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            $courseid = $context->contextlevel === CONTEXT_SYSTEM ? SITEID : $context->instanceid;

            $progress = array_values($DB->get_records('local_flwmedia_progress', [
                'userid' => $userid,
                'courseid' => $courseid,
            ], 'timemodified ASC'));

            $attempts = array_values($DB->get_records('local_flwmedia_attempts', [
                'userid' => $userid,
                'courseid' => $courseid,
            ], 'timecreated ASC'));

            if ($progress || $attempts) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_flwmedia')],
                    (object)[
                        'progress' => $progress,
                        'attempts' => $attempts,
                    ]
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

        if ($context->contextlevel !== CONTEXT_COURSE && $context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $courseid = $context->contextlevel === CONTEXT_SYSTEM ? SITEID : $context->instanceid;
        $DB->delete_records('local_flwmedia_progress', ['courseid' => $courseid]);
        $DB->delete_records('local_flwmedia_attempts', ['courseid' => $courseid]);
    }

    /**
     * Delete data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE && $context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $courseid = $context->contextlevel === CONTEXT_SYSTEM ? SITEID : $context->instanceid;

            $DB->delete_records('local_flwmedia_progress', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
            $DB->delete_records('local_flwmedia_attempts', [
                'userid' => $userid,
                'courseid' => $courseid,
            ]);
        }
    }
}
