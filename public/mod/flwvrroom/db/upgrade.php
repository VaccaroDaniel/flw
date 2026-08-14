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

    if ($oldversion < 2026061210) {
        $table = new xmldb_table('flwvrroom');
        $field = new xmldb_field('roommode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'panorama', 'scenario');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061210, 'flwvrroom');
    }

    if ($oldversion < 2026061211) {
        upgrade_mod_savepoint(true, 2026061211, 'flwvrroom');
    }

    if ($oldversion < 2026061212) {
        upgrade_mod_savepoint(true, 2026061212, 'flwvrroom');
    }

    if ($oldversion < 2026061213) {
        upgrade_mod_savepoint(true, 2026061213, 'flwvrroom');
    }

    if ($oldversion < 2026061214) {
        upgrade_mod_savepoint(true, 2026061214, 'flwvrroom');
    }

    if ($oldversion < 2026061215) {
        upgrade_mod_savepoint(true, 2026061215, 'flwvrroom');
    }

    if ($oldversion < 2026061216) {
        $roomtable = new xmldb_table('flwvrroom');
        $urlfield = new xmldb_field('speakingscoringurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'customhotspots');
        if (!$dbman->field_exists($roomtable, $urlfield)) {
            $dbman->add_field($roomtable, $urlfield);
        }

        $table = new xmldb_table('flwvrroom_attempts');
        $fields = [
            new xmldb_field('kpcodes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'completedobjects'),
            new xmldb_field('speakingtext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'kpcodes'),
            new xmldb_field('aifeedback', XMLDB_TYPE_TEXT, null, null, null, null, null, 'speakingtext'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061216, 'flwvrroom');
    }

    if ($oldversion < 2026061217) {
        $table = new xmldb_table('flwvrroom_attempts');
        $fields = [
            new xmldb_field('taskcomplete', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'aifeedback'),
            new xmldb_field('durationseconds', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'taskcomplete'),
            new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'durationseconds'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $columns = $DB->get_columns('flwvrroom_attempts');
        $attempts = $DB->get_records('flwvrroom_attempts');
        foreach ($attempts as $attempt) {
            $changed = false;

            if (empty($attempt->timecreated)) {
                $attempt->timecreated = 0;
                foreach (['timemodified', 'timefinished', 'timestarted'] as $fieldname) {
                    if (isset($columns[$fieldname]) && !empty($attempt->{$fieldname})) {
                        $attempt->timecreated = (int) $attempt->{$fieldname};
                        break;
                    }
                }
                $changed = true;
            }

            if (empty($attempt->durationseconds) && isset($columns['timestarted']) && isset($columns['timefinished'])) {
                $start = (int) ($attempt->timestarted ?? 0);
                $finish = (int) ($attempt->timefinished ?? 0);
                if ($start > 0 && $finish > $start) {
                    $attempt->durationseconds = $finish - $start;
                    $changed = true;
                }
            }

            if (isset($columns['completed']) && empty($attempt->taskcomplete)) {
                $attempt->taskcomplete = !empty($attempt->completed) ? 1 : 0;
                $changed = true;
            }

            if ($changed) {
                $DB->update_record('flwvrroom_attempts', $attempt);
            }
        }

        upgrade_mod_savepoint(true, 2026061217, 'flwvrroom');
    }

    if ($oldversion < 2026061218) {
        $table = new xmldb_table('flwvrroom');
        $fields = [
            new xmldb_field('rolecharacterenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'speakingscoringurl'),
            new xmldb_field('rolecharactername', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'rolecharacterenabled'),
            new xmldb_field('rolecharacterrole', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'rolecharactername'),
            new xmldb_field('rolecharacterline', XMLDB_TYPE_TEXT, null, null, null, null, null, 'rolecharacterrole'),
            new xmldb_field('roleexpectedanswer', XMLDB_TYPE_TEXT, null, null, null, null, null, 'rolecharacterline'),
            new xmldb_field('rolekpcodes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'roleexpectedanswer'),
            new xmldb_field('rolescore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '20', 'rolekpcodes'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061218, 'flwvrroom');
    }

    if ($oldversion < 2026061219) {
        $table = new xmldb_table('flwvrroom');
        $field = new xmldb_field('rolecharacterposition', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'rolescore');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061219, 'flwvrroom');
    }

    if ($oldversion < 2026061220) {
        upgrade_mod_savepoint(true, 2026061220, 'flwvrroom');
    }

    if ($oldversion < 2026061221) {
        upgrade_mod_savepoint(true, 2026061221, 'flwvrroom');
    }

    if ($oldversion < 2026061222) {
        $table = new xmldb_table('flwvrroom');
        $field = new xmldb_field('roleturns', XMLDB_TYPE_TEXT, null, null, null, null, null, 'rolecharacterposition');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061222, 'flwvrroom');
    }

    if ($oldversion < 2026061223) {
        $table = new xmldb_table('flwvrroom');
        $fields = [
            new xmldb_field('roleaienabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'roleturns'),
            new xmldb_field('roleaiturns', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3', 'roleaienabled'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061223, 'flwvrroom');
    }

    if ($oldversion < 2026071402) {
        $guestrole = $DB->get_record('role', ['shortname' => 'guest'], 'id', IGNORE_MISSING);
        if ($guestrole) {
            assign_capability(
                'mod/flwvrroom:view',
                CAP_ALLOW,
                (int)$guestrole->id,
                context_system::instance()->id,
                true
            );
        }

        upgrade_mod_savepoint(true, 2026071402, 'flwvrroom');
    }

    if ($oldversion < 2026081400) {
        $table = new xmldb_table('flwvrroom');
        if ($dbman->table_exists($table)) {
            $columns = $DB->get_columns('flwvrroom');

            $kpcodesfield = new xmldb_field('kpcodes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'roommode');
            if (!$dbman->field_exists($table, $kpcodesfield)) {
                $dbman->add_field($table, $kpcodesfield);
                $columns = $DB->get_columns('flwvrroom');
            }

            if (isset($columns['knowledgepoints']) && isset($columns['kpcodes'])) {
                $records = $DB->get_records('flwvrroom', null, '', 'id, knowledgepoints, kpcodes');
                foreach ($records as $record) {
                    if (trim((string)($record->kpcodes ?? '')) === '' &&
                            trim((string)($record->knowledgepoints ?? '')) !== '') {
                        $DB->set_field('flwvrroom', 'kpcodes', $record->knowledgepoints, ['id' => $record->id]);
                    }
                }
            }

            $scenariomap = [
                'cafe' => 'At the Cafe',
                'classroom' => 'In the Classroom',
                'hotel' => 'At the Hotel',
                'airport' => 'At the Airport',
                'supermarket' => 'At the Supermarket',
            ];
            $validlevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
            $records = $DB->get_records('flwvrroom', null, '', 'id, cefrlevel, scenario');
            foreach ($records as $record) {
                $changed = false;
                $level = strtoupper(trim((string)($record->cefrlevel ?? '')));
                if ($level === 'PRE-A1' || $level === 'PREA1') {
                    $level = 'A1';
                }
                if (!in_array($level, $validlevels, true)) {
                    $level = 'A1';
                }
                if ($level !== (string)$record->cefrlevel) {
                    $record->cefrlevel = $level;
                    $changed = true;
                }

                $scenario = trim((string)($record->scenario ?? ''));
                $scenariokey = core_text::strtolower($scenario);
                if (isset($scenariomap[$scenariokey])) {
                    $record->scenario = $scenariomap[$scenariokey];
                    $changed = true;
                }

                if ($changed) {
                    $DB->update_record('flwvrroom', $record);
                }
            }

            $cefrfield = new xmldb_field('cefrlevel', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, 'A1', 'introformat');
            if ($dbman->field_exists($table, $cefrfield)) {
                $dbman->change_field_precision($table, $cefrfield);
                $dbman->change_field_default($table, $cefrfield);
            }

            $scenariofield = new xmldb_field('scenario', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, 'At the Cafe', 'cefrlevel');
            if ($dbman->field_exists($table, $scenariofield)) {
                $dbman->change_field_default($table, $scenariofield);
            }

            $passingfield = new xmldb_field('passinggrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '70', 'roleaiturns');
            if ($dbman->field_exists($table, $passingfield)) {
                $dbman->change_field_precision($table, $passingfield);
            }

            $courseandnameindex = new xmldb_index('course_name', XMLDB_INDEX_NOTUNIQUE, ['course', 'name']);
            if ($dbman->index_exists($table, $courseandnameindex)) {
                $dbman->drop_index($table, $courseandnameindex);
            }

            $knowledgepointsfield = new xmldb_field('knowledgepoints', XMLDB_TYPE_TEXT);
            if ($dbman->field_exists($table, $knowledgepointsfield)) {
                $dbman->drop_field($table, $knowledgepointsfield);
            }
        }

        $attempttable = new xmldb_table('flwvrroom_attempts');
        if ($dbman->table_exists($attempttable)) {
            $columns = $DB->get_columns('flwvrroom_attempts');
            $attempts = $DB->get_records('flwvrroom_attempts');
            foreach ($attempts as $attempt) {
                $changed = false;
                if (isset($columns['completed']) && isset($columns['taskcomplete']) && empty($attempt->taskcomplete)) {
                    $attempt->taskcomplete = !empty($attempt->completed) ? 1 : 0;
                    $changed = true;
                }
                if (isset($columns['timestarted']) && isset($columns['timefinished']) &&
                        isset($columns['durationseconds']) && empty($attempt->durationseconds)) {
                    $start = (int)($attempt->timestarted ?? 0);
                    $finish = (int)($attempt->timefinished ?? 0);
                    if ($start > 0 && $finish > $start) {
                        $attempt->durationseconds = $finish - $start;
                        $changed = true;
                    }
                }
                if (isset($columns['timecreated']) && empty($attempt->timecreated)) {
                    foreach (['timemodified', 'timefinished', 'timestarted'] as $fieldname) {
                        if (isset($columns[$fieldname]) && !empty($attempt->{$fieldname})) {
                            $attempt->timecreated = (int)$attempt->{$fieldname};
                            $changed = true;
                            break;
                        }
                    }
                }
                if ($changed) {
                    $DB->update_record('flwvrroom_attempts', $attempt);
                }
            }

            foreach (['maxscore', 'completedquiz', 'completed', 'timestarted', 'timefinished', 'timemodified'] as $fieldname) {
                $field = new xmldb_field($fieldname);
                if ($dbman->field_exists($attempttable, $field)) {
                    $dbman->drop_field($attempttable, $field);
                }
            }
        }

        upgrade_mod_savepoint(true, 2026081400, 'flwvrroom');
    }

    return true;
}
