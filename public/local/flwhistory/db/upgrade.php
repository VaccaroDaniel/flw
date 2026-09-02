<?php
// Upgrade steps for local_flwhistory.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_flwhistory.
 *
 * @param int $oldversion Previous version.
 * @return bool
 */
function xmldb_local_flwhistory_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082702) {
        $addfield = function(
            string $tablename,
            string $fieldname,
            string $type,
            ?string $precision,
            bool $notnull = false,
            $default = null,
            ?string $previous = null
        ) use ($dbman): void {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field($fieldname, $type, $precision, null,
                $notnull ? XMLDB_NOTNULL : null, null, $default, $previous);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        };

        $addindex = function(string $tablename, string $indexname, array $fields) use ($dbman): void {
            $table = new xmldb_table($tablename);
            $index = new xmldb_index($indexname, XMLDB_INDEX_NOTUNIQUE, $fields);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        };

        $normalisedtables = [
            'flwhist_source_event' => ['sourcefamily' => 'unknown', 'familyindex' => ['sourcefamily', 'courseid']],
            'flwhist_attempt' => ['sourcefamily' => 'unknown', 'familyindex' => ['sourcefamily', 'courseid']],
            'flwhist_placement' => ['sourcefamily' => 'placement', 'familyindex' => null],
            'flwhist_question_attempt' => ['sourcefamily' => 'quiz', 'familyindex' => ['sourcefamily', 'courseid']],
            'flwhist_grade_version' => ['sourcefamily' => 'gradebook', 'familyindex' => ['sourcefamily', 'courseid']],
            'flwhist_completion' => ['sourcefamily' => 'completion', 'familyindex' => ['sourcefamily', 'courseid']],
        ];

        foreach ($normalisedtables as $tablename => $config) {
            $addfield($tablename, 'sourcefactkey', XMLDB_TYPE_CHAR, '191', false, null, 'sourcekey');
            $addfield($tablename, 'sourcefamily', XMLDB_TYPE_CHAR, '80', true, $config['sourcefamily'], 'sourcefactkey');
            $addfield($tablename, 'normpolicyversion', XMLDB_TYPE_CHAR, '40', true, 'H1B-20260827.1', null);
            $DB->execute("UPDATE {{$tablename}} SET sourcefactkey = sourcekey WHERE sourcefactkey IS NULL");
            $addindex($tablename, 'sourcefact_ix', ['sourcefactkey']);
            if ($config['familyindex'] !== null) {
                $addindex($tablename, 'family_course_ix', $config['familyindex']);
            }
        }

        $addfield('flwhist_reconcile_run', 'normpolicyversion', XMLDB_TYPE_CHAR, '40', true,
            'H1B-20260827.1', 'runtype');

        $table = new xmldb_table('flwhist_coverage');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('sourcekey', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, null);
            $table->add_field('scopelevel', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'course');
            $table->add_field('sourcefamily', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('worldid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('stageid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('unitid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('timerangestart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timerangeend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('coveragestatus', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'UNKNOWN');
            $table->add_field('eventavailability', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'NO_EVENT_AVAILABLE');
            $table->add_field('capturestartedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('backfillstartedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('backfillcompletedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('earliestreliableeventat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('latestreconciledat', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('sourceavailable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('eventcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('reasoncode', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('normpolicyversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'H1B-20260827.1');
            $table->add_field('detailsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('sourcekey_uix', XMLDB_INDEX_UNIQUE, ['sourcekey']);
            $table->add_index('family_course_ix', XMLDB_INDEX_NOTUNIQUE, ['sourcefamily', 'courseid']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['coveragestatus']);
            $table->add_index('range_ix', XMLDB_INDEX_NOTUNIQUE, ['timerangestart', 'timerangeend']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082702, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082703) {
        upgrade_plugin_savepoint(true, 2026082703, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082801) {
        $table = new xmldb_table('flwhist_grade_summary');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('sourcekey', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sourcefamily', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, 'gradebook');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('gradeitemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('gradegradeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('itemmodule', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('iteminstance', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('itemnumber', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('latestattemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('latestattemptsourceid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('latestattemptscore', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('latestattempttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('bestattemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('bestattemptsourceid', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('bestattemptscore', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('bestattempttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('officialgradegradeid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('officialrawgrade', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('officialfinalgrade', XMLDB_TYPE_NUMBER, '12, 5', null, null, null, null);
            $table->add_field('officialgradetime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('latestgradeversionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('reconciliationstatus', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'current');
            $table->add_field('normpolicyversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'H1B-20260827.1');
            $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('sourcekey_uix', XMLDB_INDEX_UNIQUE, ['sourcekey']);
            $table->add_index('user_grade_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'gradeitemid']);
            $table->add_index('course_grade_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'gradeitemid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['reconciliationstatus']);
            $table->add_index('latest_grade_ix', XMLDB_INDEX_NOTUNIQUE, ['latestgradeversionid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082801, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082802) {
        // H4 adds secure external API definitions and summary services only.
        upgrade_plugin_savepoint(true, 2026082802, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082803) {
        // H5 adds the learner history dashboard page and composition service only.
        upgrade_plugin_savepoint(true, 2026082803, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082804) {
        // H6 adds history-specific teacher analytics without adaptive-policy ownership.
        upgrade_plugin_savepoint(true, 2026082804, 'local', 'flwhistory');
    }

    if ($oldversion < 2026082805) {
        // H7 freezes History V1 with migration, reconciliation, privacy, performance, and downstream contract services.
        upgrade_plugin_savepoint(true, 2026082805, 'local', 'flwhistory');
    }

    return true;
}
