<?php
// Privacy provider for local_flwcupkp.

namespace local_flwcupkp\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /** @var array Tables whose rows are owned by the learner. */
    private const LEARNER_TABLES = [
        'flwcupkp_evidence',
        'flwcupkp_state',
        'flwcupkp_recommend',
    ];

    /** @var array Operational tables that store only the acting Moodle user ID. */
    private const OPERATIONAL_USER_TABLES = [
        'flwcupkp_import',
        'flwcupkp_calsnapshot',
        'flwcupkp_calproposal',
        'flwcupkp_calrecalc',
        'flwcupkp_audit',
    ];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('flwcupkp_evidence', [
            'userid' => 'privacy:metadata:userid',
            'usermodified' => 'privacy:metadata:usermodified',
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
        $collection->add_database_table('flwcupkp_import', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_import');
        $collection->add_database_table('flwcupkp_calsnapshot', [
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_calsnapshot');
        $collection->add_database_table('flwcupkp_calproposal', [
            'userid' => 'privacy:metadata:userid',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_calproposal');
        $collection->add_database_table('flwcupkp_calrecalc', [
            'userid' => 'privacy:metadata:userid',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_calrecalc');
        $collection->add_database_table('flwcupkp_audit', [
            'userid' => 'privacy:metadata:userid',
            'targettype' => 'privacy:metadata:targettype',
            'targetid' => 'privacy:metadata:targetid',
            'timecreated' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:flwcupkp_audit');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if (self::has_user_data($userid)) {
            $systemcontext = \context_system::instance(0, MUST_EXIST, false);
            $contextlist->add_from_sql(
                'SELECT id FROM {context} WHERE id = :contextid',
                ['contextid' => $systemcontext->id]
            );
        }
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
                'modified_evidence' => array_values($DB->get_records('flwcupkp_evidence',
                    ['usermodified' => $userid])),
                'states' => array_values($DB->get_records('flwcupkp_state', ['userid' => $userid])),
                'recommendations' => array_values($DB->get_records('flwcupkp_recommend', ['userid' => $userid])),
                'imports' => array_values($DB->get_records('flwcupkp_import', ['userid' => $userid])),
                'calibration_snapshots' => array_values($DB->get_records('flwcupkp_calsnapshot',
                    ['userid' => $userid])),
                'calibration_proposals' => array_values($DB->get_records('flwcupkp_calproposal',
                    ['userid' => $userid])),
                'calibration_recalculations' => array_values($DB->get_records('flwcupkp_calrecalc',
                    ['userid' => $userid])),
                'audit_entries' => array_values($DB->get_records('flwcupkp_audit', ['userid' => $userid])),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'local_flwcupkp')], (object)$data);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            foreach (self::LEARNER_TABLES as $table) {
                $DB->delete_records($table);
            }
            foreach (self::OPERATIONAL_USER_TABLES as $table) {
                $DB->set_field_select($table, 'userid', null, 'userid IS NOT NULL', []);
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::delete_user_records($userid);
            }
        }
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_evidence}', []);
        $userlist->add_from_sql('usermodified', 'SELECT usermodified FROM {flwcupkp_evidence}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_state}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {flwcupkp_recommend}', []);
        foreach (self::OPERATIONAL_USER_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid IS NOT NULL", []);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_records((int)$userid);
        }
    }

    /**
     * Determine whether this plugin stores data linked to a user.
     *
     * @param int $userid
     * @return bool
     */
    private static function has_user_data(int $userid): bool {
        global $DB;

        foreach (self::LEARNER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }
        if ($DB->record_exists('flwcupkp_evidence', ['usermodified' => $userid])) {
            return true;
        }
        foreach (self::OPERATIONAL_USER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete learner-owned rows and anonymize operational actor fields for a user.
     *
     * @param int $userid
     */
    private static function delete_user_records(int $userid): void {
        global $DB;

        foreach (self::LEARNER_TABLES as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
        $DB->set_field('flwcupkp_evidence', 'usermodified', null, ['usermodified' => $userid]);
        foreach (self::OPERATIONAL_USER_TABLES as $table) {
            $DB->set_field($table, 'userid', null, ['userid' => $userid]);
        }
    }
}
