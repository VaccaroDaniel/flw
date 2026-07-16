<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Seed a conservative English Real World A1 exam profile.
 */
function xmldb_local_flwexam_install(): void {
    global $DB;

    $now = time();
    if ($DB->record_exists('local_flwexam_exams', ['code' => 'EN-RW-A1-CERT'])) {
        return;
    }

    $exam = (object)[
        'code' => 'EN-RW-A1-CERT',
        'name' => 'English Real World A1 Certificate Exam',
        'language' => 'en',
        'learningcoursecategory' => 'real_world',
        'cefrlevel' => 'A1',
        'requiredthreshold' => 70,
        'requiredskillfloor' => 60,
        'moderationrequired' => 1,
        'criticalkpjson' => json_encode([
            'a1_greetings',
            'a1_personal_information',
            'a1_everyday_transactions',
        ]),
        'profilejson' => json_encode([
            'description' => 'Baseline FLW certificate profile for English A1 Real World.',
            'skills' => ['listening', 'speaking', 'reading', 'writing'],
        ]),
        'visible' => 1,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $examid = $DB->insert_record('local_flwexam_exams', $exam);
    local_flwexam_seed_sample_questions($examid);
}

/**
 * Seed objective questions for the sample English A1 exam.
 *
 * @param int $examid
 */
function local_flwexam_seed_sample_questions(int $examid): void {
    global $DB;

    if ($DB->record_exists('local_flwexam_questions', ['examid' => $examid])) {
        return;
    }

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
        foreach ($options as $optionindex => $optiontext) {
            $key = chr(97 + $optionindex);
            $optionrows[] = [
                'key' => $key,
                'text' => $optiontext,
            ];
            if ($optiontext === $correct) {
                $correctkey = $key;
            }
        }
        $DB->insert_record('local_flwexam_questions', (object)[
            'examid' => $examid,
            'qtype' => 'multichoice',
            'questiontext' => $text,
            'optionsjson' => json_encode($optionrows),
            'correctanswer' => $correctkey ?? 'a',
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
