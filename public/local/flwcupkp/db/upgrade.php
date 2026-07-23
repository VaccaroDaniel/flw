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

    return true;
}
