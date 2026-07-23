<?php
// Upgrade script for local_mldict.

defined('MOODLE_INTERNAL') || die();

/**
 * xmldb_local_mldict_upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_mldict_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072100) {
        $table = new xmldb_table('local_mldict_entry');

        $sourcelangheadword = new xmldb_index('sourcelang_headword', XMLDB_INDEX_NOTUNIQUE, ['sourcelang', 'headword']);
        if (!$dbman->index_exists($table, $sourcelangheadword)) {
            $dbman->add_index($table, $sourcelangheadword);
        }

        $sourcelang = new xmldb_index('sourcelang', XMLDB_INDEX_NOTUNIQUE, ['sourcelang']);
        if (!$dbman->index_exists($table, $sourcelang)) {
            $dbman->add_index($table, $sourcelang);
        }

        upgrade_plugin_savepoint(true, 2026072100, 'local', 'mldict');
    }

    return true;
}
