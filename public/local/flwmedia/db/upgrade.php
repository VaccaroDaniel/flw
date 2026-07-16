<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for FLW Media.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_local_flwmedia_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071000) {
        $table = new xmldb_table('local_flwmedia_items');
        $index = new xmldb_index('lang', XMLDB_INDEX_NOTUNIQUE, ['lang']);

        if ($dbman->table_exists($table) && !$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'flwmedia');
    }

    if ($oldversion < 2026071001) {
        $table = new xmldb_table('local_flwmedia_categories');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('lang', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'en');
            $table->add_field('categorykey', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('lang', XMLDB_INDEX_NOTUNIQUE, ['lang']);
            $table->add_index('categorykey', XMLDB_INDEX_NOTUNIQUE, ['categorykey']);
            $table->add_index('lang-key-mode', XMLDB_INDEX_UNIQUE, ['lang', 'categorykey', 'mode']);
            $table->add_index('visible', XMLDB_INDEX_NOTUNIQUE, ['visible']);

            $dbman->create_table($table);
        }

        $records = $DB->get_records_sql(
            "SELECT MIN(id) AS id, lang, category, mode
               FROM {local_flwmedia_items}
              WHERE category <> ''
           GROUP BY lang, category, mode
           ORDER BY lang ASC, category ASC, mode ASC"
        );
        $sortorder = 10;
        foreach ($records as $record) {
            $exists = $DB->record_exists('local_flwmedia_categories', [
                'lang' => $record->lang,
                'categorykey' => $record->category,
                'mode' => $record->mode,
            ]);
            if ($exists) {
                continue;
            }
            $now = time();
            $DB->insert_record('local_flwmedia_categories', (object)[
                'lang' => $record->lang,
                'categorykey' => $record->category,
                'name' => \local_flwmedia\manager::label_from_key($record->category),
                'mode' => $record->mode,
                'description' => '',
                'sortorder' => $sortorder,
                'visible' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $sortorder += 10;
        }

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'flwmedia');
    }

    if ($oldversion < 2026071002) {
        $records = $DB->get_records_sql(
            "SELECT MIN(id) AS id, lang, category, mode
               FROM {local_flwmedia_items}
              WHERE category <> ''
           GROUP BY lang, category, mode
           ORDER BY lang ASC, category ASC, mode ASC"
        );
        $sortorder = 10;
        foreach ($records as $record) {
            $exists = $DB->record_exists('local_flwmedia_categories', [
                'lang' => $record->lang,
                'categorykey' => $record->category,
                'mode' => $record->mode,
            ]);
            if ($exists) {
                continue;
            }
            $now = time();
            $DB->insert_record('local_flwmedia_categories', (object)[
                'lang' => $record->lang,
                'categorykey' => $record->category,
                'name' => \local_flwmedia\manager::label_from_key($record->category),
                'mode' => $record->mode,
                'description' => '',
                'sortorder' => $sortorder,
                'visible' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $sortorder += 10;
        }

        upgrade_plugin_savepoint(true, 2026071002, 'local', 'flwmedia');
    }

    return true;
}
