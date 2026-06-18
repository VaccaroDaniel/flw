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

    if ($oldversion < 2026061500) {
        $table = new xmldb_table('flwaispeaking');

        $field = new xmldb_field('tasktype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'topic', 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('targettext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'prompttext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('referenceaudiourl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'targettext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061500, 'flwaispeaking');
    }

    if ($oldversion < 2026061501) {
        flwaispeaking_repair_attempt_numbers();

        $table = new xmldb_table('flwaispeaking_submissions');
        $index = new xmldb_index('activity-user-attempt', XMLDB_INDEX_UNIQUE, ['flwaispeakingid', 'userid', 'attemptnumber']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026061501, 'flwaispeaking');
    }

    return true;
}

/**
 * Renumber existing attempts so each activity/user pair has unique display numbers.
 */
function flwaispeaking_repair_attempt_numbers(): void {
    global $DB;

    $pairs = $DB->get_records_sql(
        'SELECT MIN(id) AS id, flwaispeakingid, userid
           FROM {flwaispeaking_submissions}
       GROUP BY flwaispeakingid, userid
       ORDER BY flwaispeakingid, userid'
    );

    foreach ($pairs as $pair) {
        $submissions = $DB->get_records('flwaispeaking_submissions', [
            'flwaispeakingid' => $pair->flwaispeakingid,
            'userid' => $pair->userid,
        ], 'timecreated ASC, id ASC', 'id, attemptnumber');

        $attemptnumber = 1;
        foreach ($submissions as $submission) {
            if ((int) $submission->attemptnumber !== $attemptnumber) {
                $DB->set_field('flwaispeaking_submissions', 'attemptnumber', $attemptnumber, ['id' => $submission->id]);
            }
            $attemptnumber++;
        }
    }
}
