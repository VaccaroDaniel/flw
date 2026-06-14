<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Seed the first FLW curriculum records.
 */
function xmldb_local_flwkp_install(): void {
    global $DB;

    $now = time();

    $languageid = $DB->insert_record('local_flwkp_languages', (object) [
        'code' => 'en',
        'name' => 'English',
        'framework' => 'CEFR',
        'sortorder' => 10,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    $levels = [
        ['A1', 'English Foundation', 'Basic survival English for simple personal and everyday communication.', 10],
        ['A2', 'Everyday English', 'Simple daily communication across familiar situations.', 20],
        ['B1', 'Practical English', 'Independent communication about experiences, plans, opinions, and routine needs.', 30],
        ['B2', 'Independent English', 'Clear communication in study, work, social, and abstract topics.', 40],
        ['C1', 'Advanced English', 'Flexible, precise communication in complex academic, professional, and social contexts.', 50],
        ['C2', 'English Mastery', 'Near-native precision, nuance, fluency, and control across demanding contexts.', 60],
    ];

    $levelids = [];
    foreach ($levels as [$code, $name, $description, $sortorder]) {
        $levelids[$code] = $DB->insert_record('local_flwkp_levels', (object) [
            'languageid' => $languageid,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    $domains = [
        ['VOC', 'Vocabulary', 10],
        ['GRA', 'Grammar', 20],
        ['REA', 'Reading', 30],
        ['LIS', 'Listening', 40],
        ['SPK', 'Speaking', 50],
        ['WRI', 'Writing', 60],
        ['PRO', 'Pronunciation', 70],
        ['FUN', 'Functional English', 80],
        ['STU', 'Study Skills', 90],
        ['EXA', 'Exam Skills', 100],
    ];

    $domainids = [];
    foreach ($domains as [$code, $name, $sortorder]) {
        $domainids[$code] = $DB->insert_record('local_flwkp_domains', (object) [
            'code' => $code,
            'name' => $name,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    $unitid = $DB->insert_record('local_flwkp_units', (object) [
        'levelid' => $levelids['A1'],
        'code' => 'EN-A1-U01',
        'name' => 'Greetings and Introductions',
        'canstatement' => 'I can greet people politely, introduce myself, and ask and answer basic personal questions.',
        'estimatedhours' => 8.00,
        'sortorder' => 10,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    $points = [
        ['EN-A1-U01-VOC-001', 'VOC', 'Greetings', 'Common greetings and leave-taking phrases.', 'Learners can choose and use hello, good morning, goodbye, and similar greetings appropriately.', 10],
        ['EN-A1-U01-VOC-002', 'VOC', 'Personal information', 'Names, countries, nationalities, jobs, and age.', 'Learners can understand and produce basic personal information words and phrases.', 20],
        ['EN-A1-U01-GRA-001', 'GRA', 'Be verb', 'Positive, negative, and question forms of am, is, and are.', 'Learners can form simple sentences and questions with the be verb.', 30],
        ['EN-A1-U01-GRA-002', 'GRA', 'Subject pronouns', 'I, you, he, she, it, we, and they.', 'Learners can match subject pronouns to people and use them in short sentences.', 40],
        ['EN-A1-U01-REA-001', 'REA', 'Personal profiles', 'Short profiles with name, country, job, and simple interests.', 'Learners can identify key personal details in a short profile.', 50],
        ['EN-A1-U01-LIS-001', 'LIS', 'Basic introductions', 'Short spoken greetings and introduction exchanges.', 'Learners can understand names, countries, and simple greeting phrases in short conversations.', 60],
        ['EN-A1-U01-SPK-001', 'SPK', 'Self introduction', 'Short spoken self-introduction.', 'Learners can say their name, country, job or role, and a polite greeting.', 70],
        ['EN-A1-U01-WRI-001', 'WRI', 'Personal profile writing', 'A short written profile using simple sentences.', 'Learners can write four to six simple sentences introducing themselves.', 80],
        ['EN-A1-U01-PRO-001', 'PRO', 'Greeting intonation', 'Rising and falling intonation in simple greeting exchanges.', 'Learners can repeat basic greeting phrases with understandable rhythm and intonation.', 90],
        ['EN-A1-U01-FUN-001', 'FUN', 'Asking basic personal questions', 'What is your name? Where are you from? What do you do?', 'Learners can ask and answer simple personal questions politely.', 100],
    ];

    foreach ($points as [$code, $domaincode, $name, $description, $outcome, $sortorder]) {
        $DB->insert_record('local_flwkp_points', (object) [
            'unitid' => $unitid,
            'domainid' => $domainids[$domaincode],
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'outcome' => $outcome,
            'masterythreshold' => 80,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
