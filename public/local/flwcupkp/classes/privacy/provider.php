<?php
// Privacy provider for local_flwcupkp.

namespace local_flwcupkp\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('flwcupkp_evidence', [
            'userid' => 'privacy:metadata:userid',
            'targettype' => 'privacy:metadata:targettype',
            'targetid' => 'privacy:metadata:targetid',
            'normalizedscore' => 'privacy:metadata:score',
            'timecreated' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_evidence');
        $collection->add_database_table('flwcupkp_state', [
            'userid' => 'privacy:metadata:userid',
            'targettype' => 'privacy:metadata:targettype',
            'targetid' => 'privacy:metadata:targetid',
            'masteryscore' => 'privacy:metadata:score',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_state');
        $collection->add_database_table('flwcupkp_recommend', [
            'userid' => 'privacy:metadata:userid',
            'targettype' => 'privacy:metadata:targettype',
            'targetid' => 'privacy:metadata:targetid',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_recommend');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $data = [
                'evidence' => array_values($DB->get_records('flwcupkp_evidence', ['userid' => $userid])),
                'states' => array_values($DB->get_records('flwcupkp_state', ['userid' => $userid])),
                'recommendations' => array_values($DB->get_records('flwcupkp_recommend', ['userid' => $userid])),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'local_flwcupkp')], (object)$data);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->delete_records('flwcupkp_evidence');
            $DB->delete_records('flwcupkp_state');
            $DB->delete_records('flwcupkp_recommend');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->delete_records('flwcupkp_evidence', ['userid' => $userid]);
                $DB->delete_records('flwcupkp_state', ['userid' => $userid]);
                $DB->delete_records('flwcupkp_recommend', ['userid' => $userid]);
            }
        }
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_evidence}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_state}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_recommend}', []);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('flwcupkp_evidence', ['userid' => $userid]);
            $DB->delete_records('flwcupkp_state', ['userid' => $userid]);
            $DB->delete_records('flwcupkp_recommend', ['userid' => $userid]);
        }
    }
}
