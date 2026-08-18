<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for FLW VR Room.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe stored user data.
     *
     * @param \core_privacy\local\metadata\collection $collection
     * @return \core_privacy\local\metadata\collection
     */
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('flwvrroom_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'score' => 'privacy:metadata:attempts:score',
            'completedobjects' => 'privacy:metadata:attempts:completedobjects',
            'kpcodes' => 'privacy:metadata:attempts:kpcodes',
            'speakingtext' => 'privacy:metadata:attempts:speakingtext',
            'aifeedback' => 'privacy:metadata:attempts:aifeedback',
            'hotspotsjson' => 'privacy:metadata:attempts:hotspotsjson',
            'roleturnsjson' => 'privacy:metadata:attempts:roleturnsjson',
            'speakingjson' => 'privacy:metadata:attempts:speakingjson',
            'taskcomplete' => 'privacy:metadata:attempts:taskcomplete',
            'durationseconds' => 'privacy:metadata:attempts:durationseconds',
            'timecreated' => 'privacy:metadata:attempts:timecreated',
        ], 'privacy:metadata:attempts');

        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {flwvrroom} f ON f.id = cm.instance
                  JOIN {flwvrroom_attempts} a ON a.flwvrroomid = f.id
                 WHERE a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'flwvrroom',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('flwvrroom', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $attempts = $DB->get_records('flwvrroom_attempts', ['flwvrroomid' => $cm->instance, 'userid' => $userid]);
            \core_privacy\local\request\writer::with_context($context)->export_data([get_string('modulename', 'flwvrroom')], (object) [
                'attempts' => array_values($attempts),
            ]);
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('flwvrroom', $context->instanceid);
        if ($cm) {
            $DB->delete_records('flwvrroom_attempts', ['flwvrroomid' => $cm->instance]);
        }
    }

    /**
     * Delete user data in approved contexts.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('flwvrroom', $context->instanceid);
            if ($cm) {
                $DB->delete_records('flwvrroom_attempts', ['flwvrroomid' => $cm->instance, 'userid' => $userid]);
            }
        }
    }
}
