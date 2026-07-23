<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for FLW Exam.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_flwexam_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026071001) {
        $table = new xmldb_table('local_flwexam_questions');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('examid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('qtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'multichoice');
        $table->add_field('questiontext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('optionsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('correctanswer', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, '');
        $table->add_field('skill', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('kpcode', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, '');
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('examid', XMLDB_INDEX_NOTUNIQUE, ['examid']);
        $table->add_index('exam-sort', XMLDB_INDEX_NOTUNIQUE, ['examid', 'sortorder']);
        $table->add_index('skill', XMLDB_INDEX_NOTUNIQUE, ['skill']);
        $table->add_index('kpcode', XMLDB_INDEX_NOTUNIQUE, ['kpcode']);
        $table->add_index('visible', XMLDB_INDEX_NOTUNIQUE, ['visible']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $exam = $DB->get_record('local_flwexam_exams', ['code' => 'EN-RW-A1-CERT'], 'id', IGNORE_MISSING);
        if ($exam && !$DB->record_exists('local_flwexam_questions', ['examid' => (int)$exam->id])) {
            local_flwexam_upgrade_seed_sample_questions((int)$exam->id);
        }

        upgrade_plugin_savepoint(true, 2026071001, 'local', 'flwexam');
    }

    if ($oldversion < 2026072000) {
        $table = new xmldb_table('local_flwexam_exams');
        $field = new xmldb_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'profilejson');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('quizid', XMLDB_INDEX_NOTUNIQUE, ['quizid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072000, 'local', 'flwexam');
    }

    if ($oldversion < 2026072300) {
        $table = new xmldb_table('local_flwexam_sessions');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('sessiontype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'self');
        $table->add_field('examid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('questioncount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '20');
        $table->add_field('maxattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('accesscode', XMLDB_TYPE_CHAR, '80', null, XMLDB_NOTNULL, null, '');
        $table->add_field('branchname', XMLDB_TYPE_CHAR, '120', null, XMLDB_NOTNULL, null, '');
        $table->add_field('proctoruserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('requireproctor', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'open');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('type-status', XMLDB_INDEX_NOTUNIQUE, ['sessiontype', 'status', 'visible']);
        $table->add_index('examid', XMLDB_INDEX_NOTUNIQUE, ['examid']);
        $table->add_index('course-group', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'groupid']);
        $table->add_index('time-window', XMLDB_INDEX_NOTUNIQUE, ['timestart', 'timeend']);
        $table->add_index('createdby', XMLDB_INDEX_NOTUNIQUE, ['createdby']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $attempttable = new xmldb_table('local_flwexam_attempts');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'examid');
        if (!$dbman->field_exists($attempttable, $field)) {
            $dbman->add_field($attempttable, $field);
        }

        $index = new xmldb_index('sessionid', XMLDB_INDEX_NOTUNIQUE, ['sessionid']);
        if (!$dbman->index_exists($attempttable, $index)) {
            $dbman->add_index($attempttable, $index);
        }

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'flwexam');
    }

    if ($oldversion < 2026072301) {
        if ($dbman->table_exists('local_flwexam_sessions')) {
            $DB->set_field('local_flwexam_sessions', 'sessiontype', 'teacher', ['sessiontype' => 'self']);
        }

        upgrade_plugin_savepoint(true, 2026072301, 'local', 'flwexam');
    }

    return true;
}

/**
 * Seed objective questions during upgrade.
 *
 * @param int $examid
 */
function local_flwexam_upgrade_seed_sample_questions(int $examid): void {
    global $DB;

    $now = time();
    $questions = [
        ['listening', 'a1_greetings', 'You hear: "Good morning. My name is Ana." What is the best reply?', 'Good morning, Ana. Nice to meet you.', ['Good morning, Ana. Nice to meet you.', 'I am from price.', 'Yesterday is fine.', 'No, I cannot blue.']],
        ['listening', 'a1_greetings', 'You hear: "How are you today?" Choose the natural answer.', 'I am fine, thank you.', ['I am fine, thank you.', 'It is a pencil.', 'At seven yesterday.', 'She are teacher.']],
        ['listening', 'a1_personal_information', 'You hear: "I am from Canada." What information did the speaker give?', 'Country', ['Country', 'Age', 'Phone number', 'Price']],
        ['speaking', 'a1_greetings', 'Choose the best sentence to introduce yourself.', 'Hello, my name is Sam.', ['Hello, my name is Sam.', 'Hello, name my Sam is.', 'Hello, I Sam name.', 'Hello, is name Sam.']],
        ['speaking', 'a1_personal_information', 'Choose the best question to ask someone their name.', 'What is your name?', ['What is your name?', 'Where name your?', 'How much name?', 'When are name?']],
        ['speaking', 'a1_everyday_transactions', 'Choose the best phrase to ask for help in a shop.', 'Can you help me, please?', ['Can you help me, please?', 'I help you yesterday.', 'Please me can price.', 'You are shop help.']],
        ['reading', 'a1_personal_information', 'Read: "Mina is 20. She is a student." What is Mina?', 'A student', ['A student', 'A teacher', 'A city', 'A price']],
        ['reading', 'a1_personal_information', 'Read: "Luis lives in Tokyo." Where does Luis live?', 'Tokyo', ['Tokyo', 'Luis', 'A school', 'Monday']],
        ['reading', 'a1_everyday_transactions', 'Read: "The ticket is five dollars." How much is the ticket?', 'Five dollars', ['Five dollars', 'Five tickets', 'Five days', 'Five people']],
        ['writing', 'a1_greetings', 'Choose the correctly written greeting.', 'Nice to meet you.', ['Nice to meet you.', 'Nice meet to you.', 'Meet nice you to.', 'You nice meet to.']],
        ['writing', 'a1_personal_information', 'Choose the correct sentence.', 'I live in Paris.', ['I live in Paris.', 'I lives in Paris.', 'I living in Paris.', 'I am live Paris.']],
        ['writing', 'a1_everyday_transactions', 'Choose the correct sentence for ordering.', 'I would like a coffee, please.', ['I would like a coffee, please.', 'I would a coffee like please.', 'Coffee I please would like.', 'I like would please coffee.']],
    ];

    foreach ($questions as $index => $question) {
        [$skill, $kpcode, $text, $correct, $options] = $question;
        $optionrows = [];
        $correctkey = 'a';
        foreach ($options as $optionindex => $optiontext) {
            $key = chr(97 + $optionindex);
            $optionrows[] = ['key' => $key, 'text' => $optiontext];
            if ($optiontext === $correct) {
                $correctkey = $key;
            }
        }
        $DB->insert_record('local_flwexam_questions', (object)[
            'examid' => $examid,
            'qtype' => 'multichoice',
            'questiontext' => $text,
            'optionsjson' => json_encode($optionrows),
            'correctanswer' => $correctkey,
            'skill' => $skill,
            'kpcode' => $kpcode,
            'weight' => 1,
            'sortorder' => $index + 1,
            'visible' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
