<?php
// Upgrade steps for local_flwtextbookimport.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the FLW textbook importer schema.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_flwtextbookimport_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081102) {
        $table = new xmldb_table('flwtbi_review');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('packagehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sectionnum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activityidnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('moodlemodule', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('activitytype', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('sourcecomponent', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('sourcepdf', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('sourcerange', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('reviewstatus', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('approved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('cefr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('skill', XMLDB_TYPE_CHAR, '80', null, null, null, null);
            $table->add_field('kptags', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('courseact_uix', XMLDB_INDEX_UNIQUE, ['courseid', 'activityidnumber']);
            $table->add_index('package_ix', XMLDB_INDEX_NOTUNIQUE, ['packagehash']);
            $table->add_index('course_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081102, 'local', 'flwtextbookimport');
    }

    return true;
}
