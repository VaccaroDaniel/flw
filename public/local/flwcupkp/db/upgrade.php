<?php
// Upgrade steps for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

function xmldb_local_flwcupkp_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071700) {
        // Initial install schema is handled by install.xml.
        upgrade_plugin_savepoint(true, 2026071700, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026072300) {
        $table = new xmldb_table('flwcupkp_calsnapshot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('reportjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'unitcode', 'targettype']);
            $table->add_index('time_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $table->add_index('checksum_ix', XMLDB_INDEX_NOTUNIQUE, ['checksum']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026072301) {
        $table = new xmldb_table('flwcupkp_calproposal');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('snapshotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('version', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'draft');
            $table->add_field('thresholdsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('previewjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('activatedruleid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('snapshot_ix', XMLDB_INDEX_NOTUNIQUE, ['snapshotid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('version_ix', XMLDB_INDEX_NOTUNIQUE, ['version']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072301, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026080700) {
        $table = new xmldb_table('flwcupkp_calrecalc');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('proposalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'queued');
            $table->add_field('mode', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'immediate');
            $table->add_field('candidate_total', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('changed_or_created', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('applied', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('skipped', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
            $table->add_field('simulationjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('resultjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('errorsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('proposal_ix', XMLDB_INDEX_NOTUNIQUE, ['proposalid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('time_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026080700, 'local', 'flwcupkp');
    }

    if ($oldversion < 2026081300) {
        $table = new xmldb_table('flwcupkp_eval_period');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, null);
            $table->add_field('periodtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unit');
            $table->add_field('datestart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('dateend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('cefr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('unitcode', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('configjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('scope_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'frameworkid', 'unitcode']);
            $table->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['periodtype', 'status']);
            $table->add_index('date_ix', XMLDB_INDEX_NOTUNIQUE, ['datestart', 'dateend']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_eval_snapshot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('evaluationtype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'unit');
            $table->add_field('cefrinterpretation', XMLDB_TYPE_CHAR, '120', null, null, null, null);
            $table->add_field('masteryruleversion', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('evaluationruleversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'eval-v1');
            $table->add_field('frameworkversion', XMLDB_TYPE_CHAR, '40', null, null, null, null);
            $table->add_field('evidencecutoff', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('snapshotversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'current');
            $table->add_field('summaryjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('stateidsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('diagnosticsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('recommendationsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('useridcreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid', 'evaluationtype']);
            $table->add_index('cutoff_ix', XMLDB_INDEX_NOTUNIQUE, ['evidencecutoff']);
            $table->add_index('checksum_ix', XMLDB_INDEX_NOTUNIQUE, ['checksum']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_selfeval');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('selfrating', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('reflection', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('provenance', XMLDB_TYPE_CHAR, '80', null, null, null, 'learner');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targettype', 'targetid']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('flwcupkp_diagnostic');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('periodid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('targettype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('gapcategory', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
            $table->add_field('diagnosticreason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('stateidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('evidenceidsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('confidence', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, 0);
            $table->add_field('ruleversion', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, 'diagnostic-v1');
            $table->add_field('status', XMLDB_TYPE_CHAR, '40', null, null, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('learner_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targettype', 'targetid']);
            $table->add_index('category_ix', XMLDB_INDEX_NOTUNIQUE, ['gapcategory', 'status']);
            $table->add_index('period_ix', XMLDB_INDEX_NOTUNIQUE, ['periodid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081300, 'local', 'flwcupkp');
    }

    return true;
}
