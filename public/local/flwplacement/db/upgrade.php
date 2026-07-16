<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for FLW Placement.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_local_flwplacement_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070700) {
        $table = new xmldb_table('local_flwplacement_profile');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('coursekey', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, '');
            $table->add_field('latestresultid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('overallcefr', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
            $table->add_field('recommendedstartunit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('nextcheckpointunit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('placementconfidence', XMLDB_TYPE_NUMBER, '10, 4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('placementstatus', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
            $table->add_field('skilllevelsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('kpmasteryjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('supportflagsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('speakingprofilejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('learningpathjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('profilejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('placementhistoryjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('user-coursekey', XMLDB_INDEX_UNIQUE, ['userid', 'coursekey']);
            $table->add_index('latestresultid', XMLDB_INDEX_NOTUNIQUE, ['latestresultid']);
            $table->add_index('overallcefr', XMLDB_INDEX_NOTUNIQUE, ['overallcefr']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070700, 'local', 'flwplacement');
    }

    return true;
}
