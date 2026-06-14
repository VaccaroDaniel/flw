<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for FLW VR Room.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_flwvrroom_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061203) {
        $table = new xmldb_table('flwvrroom');

        $fields = [
            new xmldb_field('customsceneenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'kpcodes'),
            new xmldb_field('custombackgroundurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'customsceneenabled'),
            new xmldb_field('custommissiontitle', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'custombackgroundurl'),
            new xmldb_field('custommissiontext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'custommissiontitle'),
            new xmldb_field('customquizquestion', XMLDB_TYPE_TEXT, null, null, null, null, null, 'custommissiontext'),
            new xmldb_field('customanswers', XMLDB_TYPE_TEXT, null, null, null, null, null, 'customquizquestion'),
            new xmldb_field('customhotspots', XMLDB_TYPE_TEXT, null, null, null, null, null, 'customanswers'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061203, 'flwvrroom');
    }

    return true;
}
