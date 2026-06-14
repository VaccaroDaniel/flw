<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for mod_flwaispeaking.
 *
 * @param int $oldversion Old plugin version.
 * @return bool
 */
function xmldb_flwaispeaking_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061401) {
        $table = new xmldb_table('flwaispeaking');
        $field = new xmldb_field('submissionmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'transcript', 'kpcodes');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('flwaispeaking_submissions');
        $field = new xmldb_field('submissiontype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'transcript', 'attemptnumber');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('audiofilename', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'transcript');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('audiomimetype', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'audiofilename');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061401, 'flwaispeaking');
    }

    return true;
}
