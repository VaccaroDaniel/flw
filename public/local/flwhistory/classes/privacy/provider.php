<?php
// Privacy provider for local_flwhistory.

namespace local_flwhistory\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for FLW Learning and Grade History.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /** Learner-owned history tables keyed by userid. */
    private const LEARNER_TABLES = [
        'flwhist_source_event',
        'flwhist_attempt',
        'flwhist_question_attempt',
        'flwhist_grade_version',
        'flwhist_grade_summary',
        'flwhist_completion',
        'flwhist_placement',
        'flwhist_coverage',
    ];

    /** Actor fields to anonymize when the actor is deleted. */
    private const ACTOR_FIELDS = [
        'flwhist_source_event' => ['usermodified'],
        'flwhist_coverage' => ['usermodified'],
        'flwhist_grade_version' => ['graderid'],
        'flwhist_completion' => ['overrideby'],
        'flwhist_reconcile_run' => ['userid'],
        'flwhist_correction' => ['userid'],
    ];

    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('flwhist_source_event', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'sourcesystem' => 'privacy:metadata:sourcesystem',
            'sourcetype' => 'privacy:metadata:sourcetype',
            'sourceid' => 'privacy:metadata:sourceid',
            'sourceversion' => 'privacy:metadata:sourceversion',
            'eventtype' => 'privacy:metadata:eventtype',
            'eventtime' => 'privacy:metadata:eventtime',
            'summaryjson' => 'privacy:metadata:summaryjson',
            'usermodified' => 'privacy:metadata:usermodified',
        ], 'privacy:metadata:flwhist_source_event');

        $collection->add_database_table('flwhist_attempt', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'sourceattemptid' => 'privacy:metadata:sourceid',
            'attemptstate' => 'privacy:metadata:eventtype',
            'rawscore' => 'privacy:metadata:score',
            'scaledscore' => 'privacy:metadata:score',
            'summaryjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_attempt');

        $collection->add_database_table('flwhist_question_attempt', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'questionid' => 'privacy:metadata:questionid',
            'resultstate' => 'privacy:metadata:eventtype',
            'rawmark' => 'privacy:metadata:score',
            'fraction' => 'privacy:metadata:score',
            'summaryjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_question_attempt');

        $collection->add_database_table('flwhist_grade_version', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'gradeitemid' => 'privacy:metadata:gradeitemid',
            'rawgrade' => 'privacy:metadata:score',
            'finalgrade' => 'privacy:metadata:score',
            'previousgrade' => 'privacy:metadata:score',
            'graderid' => 'privacy:metadata:usermodified',
            'summaryjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_grade_version');

        $collection->add_database_table('flwhist_grade_summary', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'gradeitemid' => 'privacy:metadata:gradeitemid',
            'latestattemptscore' => 'privacy:metadata:score',
            'bestattemptscore' => 'privacy:metadata:score',
            'officialrawgrade' => 'privacy:metadata:score',
            'officialfinalgrade' => 'privacy:metadata:score',
            'summaryjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_grade_summary');

        $collection->add_database_table('flwhist_completion', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:cmid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'completionstate' => 'privacy:metadata:eventtype',
            'overrideby' => 'privacy:metadata:usermodified',
            'detailsjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_completion');

        $collection->add_database_table('flwhist_placement', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'previouslevel' => 'privacy:metadata:eventtype',
            'currentlevel' => 'privacy:metadata:eventtype',
            'score' => 'privacy:metadata:score',
            'confidence' => 'privacy:metadata:score',
            'profilejson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_placement');

        $collection->add_database_table('flwhist_coverage', [
            'userid' => 'privacy:metadata:userid',
            'courseid' => 'privacy:metadata:courseid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'sourcefamily' => 'privacy:metadata:sourcesystem',
            'coveragestatus' => 'privacy:metadata:eventtype',
            'eventavailability' => 'privacy:metadata:eventtype',
            'eventcount' => 'privacy:metadata:score',
            'normpolicyversion' => 'privacy:metadata:normpolicyversion',
            'detailsjson' => 'privacy:metadata:summaryjson',
            'usermodified' => 'privacy:metadata:usermodified',
        ], 'privacy:metadata:flwhist_coverage');

        $collection->add_database_table('flwhist_reconcile_run', [
            'userid' => 'privacy:metadata:usermodified',
            'courseid' => 'privacy:metadata:courseid',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'runtype' => 'privacy:metadata:eventtype',
            'scopejson' => 'privacy:metadata:summaryjson',
            'errorjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_reconcile_run');

        $collection->add_database_table('flwhist_correction', [
            'userid' => 'privacy:metadata:usermodified',
            'sourcekey' => 'privacy:metadata:sourcekey',
            'recordtable' => 'privacy:metadata:eventtype',
            'recordid' => 'privacy:metadata:sourceid',
            'reason' => 'privacy:metadata:summaryjson',
            'summaryjson' => 'privacy:metadata:summaryjson',
        ], 'privacy:metadata:flwhist_correction');

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
        $courseids = [];

        foreach (self::LEARNER_TABLES as $table) {
            foreach ($DB->get_records($table, ['userid' => $userid], '', 'courseid') as $record) {
                if (!empty($record->courseid)) {
                    $courseids[(int)$record->courseid] = true;
                } else {
                    $contextlist->add_system_context();
                }
            }
        }

        foreach (self::ACTOR_FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                if (!$DB->record_exists_select($table, "{$field} = :userid", ['userid' => $userid])) {
                    continue;
                }
                if (self::table_has_courseid($table)) {
                    foreach ($DB->get_records($table, [$field => $userid], '', 'courseid') as $record) {
                        if (!empty($record->courseid)) {
                            $courseids[(int)$record->courseid] = true;
                        } else {
                            $contextlist->add_system_context();
                        }
                    }
                } else {
                    $contextlist->add_system_context();
                }
            }
        }

        foreach (array_keys($courseids) as $courseid) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if ($context) {
                $contextlist->add_context($context);
            }
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
        foreach ($contextlist->get_contexts() as $context) {
            $courseid = $context->contextlevel === CONTEXT_COURSE ? (int)$context->instanceid : 0;
            $data = [
                'source_events' => self::get_user_records_for_context('flwhist_source_event', $userid, $courseid),
                'attempts' => self::get_user_records_for_context('flwhist_attempt', $userid, $courseid),
                'question_attempts' => self::get_user_records_for_context('flwhist_question_attempt', $userid, $courseid),
                'grade_versions' => self::get_user_records_for_context('flwhist_grade_version', $userid, $courseid),
                'grade_summaries' => self::get_user_records_for_context('flwhist_grade_summary', $userid, $courseid),
                'completions' => self::get_user_records_for_context('flwhist_completion', $userid, $courseid),
                'placements' => self::get_user_records_for_context('flwhist_placement', $userid, $courseid),
                'coverage' => self::get_user_records_for_context('flwhist_coverage', $userid, $courseid),
            ];

            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $data['reconciliation_runs'] = array_values($DB->get_records('flwhist_reconcile_run',
                    ['userid' => $userid], 'timestarted ASC'));
                $data['corrections'] = array_values($DB->get_records('flwhist_correction',
                    ['userid' => $userid], 'timecreated ASC'));
            }

            writer::with_context($context)->export_data([get_string('pluginname', 'local_flwhistory')], (object)$data);
        }
    }

    /**
     * Delete data for all users in a context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach (self::LEARNER_TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $context->instanceid]);
            }
            self::anonymize_actor_fields(['courseid' => $context->instanceid]);
            return;
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            foreach (self::LEARNER_TABLES as $table) {
                $DB->delete_records($table);
            }
            self::anonymize_actor_fields();
        }
    }

    /**
     * Delete data for a user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_context_data((int)$userid, $context);
        }
    }

    /**
     * Add users present in a context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE && $context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $params = [];
        $coursesql = '';
        if ($context->contextlevel === CONTEXT_COURSE) {
            $coursesql = ' AND courseid = :courseid';
            $params['courseid'] = $context->instanceid;
        }

        foreach (self::LEARNER_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {{$table}} WHERE userid IS NOT NULL{$coursesql}", $params);
        }

        foreach (self::ACTOR_FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                $actorsql = '';
                $actorparams = [];
                if ($context->contextlevel === CONTEXT_COURSE && self::table_has_courseid($table)) {
                    $actorsql = ' AND courseid = :courseid';
                    $actorparams['courseid'] = $context->instanceid;
                } else if ($context->contextlevel === CONTEXT_COURSE) {
                    continue;
                }
                $userlist->add_from_sql($field, "SELECT {$field} FROM {{$table}} WHERE {$field} IS NOT NULL{$actorsql}",
                    $actorparams);
            }
        }
    }

    /**
     * Delete data for approved users.
     *
     * @param approved_userlist $userlist Approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_context_data((int)$userid, $userlist->get_context());
        }
    }

    /**
     * Fetch user records for a privacy export context.
     *
     * @param string $table Table.
     * @param int $userid User id.
     * @param int $courseid Course id or 0 for system rows.
     * @return array
     */
    private static function get_user_records_for_context(string $table, int $userid, int $courseid): array {
        global $DB;

        $conditions = ['userid' => $userid];
        if ($courseid > 0) {
            $conditions['courseid'] = $courseid;
        } else {
            return array_values($DB->get_records_select($table,
                'userid = :userid AND (courseid IS NULL OR courseid = 0)',
                ['userid' => $userid], 'timecreated ASC'));
        }
        return array_values($DB->get_records($table, $conditions, 'timecreated ASC'));
    }

    /**
     * Delete learner-owned rows and anonymize actor fields for a user/context.
     *
     * @param int $userid User id.
     * @param \context $context Context.
     */
    private static function delete_user_context_data(int $userid, \context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            foreach (self::LEARNER_TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid, 'courseid' => $context->instanceid]);
            }
            self::anonymize_actor_fields(['courseid' => $context->instanceid], $userid);
            return;
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            foreach (self::LEARNER_TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
            self::anonymize_actor_fields([], $userid);
        }
    }

    /**
     * Null actor fields.
     *
     * @param array $scope Optional scope conditions.
     * @param int|null $userid Optional user id filter.
     */
    private static function anonymize_actor_fields(array $scope = [], ?int $userid = null): void {
        global $DB;

        foreach (self::ACTOR_FIELDS as $table => $fields) {
            if (array_key_exists('courseid', $scope) && !self::table_has_courseid($table)) {
                continue;
            }
            foreach ($fields as $field) {
                $conditions = $scope;
                if ($userid !== null) {
                    $conditions[$field] = $userid;
                }
                if (!$conditions) {
                    $DB->set_field_select($table, $field, null, "{$field} IS NOT NULL", []);
                } else {
                    $DB->set_field($table, $field, null, $conditions);
                }
            }
        }
    }

    /**
     * Whether a table has a courseid field.
     *
     * @param string $table Table.
     * @return bool
     */
    private static function table_has_courseid(string $table): bool {
        return in_array($table, [
            'flwhist_source_event',
            'flwhist_attempt',
            'flwhist_question_attempt',
            'flwhist_grade_version',
            'flwhist_completion',
            'flwhist_placement',
            'flwhist_coverage',
            'flwhist_reconcile_run',
        ], true);
    }
}
