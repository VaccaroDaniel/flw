<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/flwexam/locallib.php');

use local_flwexam\service\exam_service;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'replace' => false,
    ],
    [
        'h' => 'help',
        'r' => 'replace',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unknown option(s):\n  {$unrecognized}");
}

if (!empty($options['help'])) {
    echo "Seed all FLW Exam definitions and generated questions.\n\n";
    echo "Options:\n";
    echo "  --replace, -r   Replace questions on each generated exam with 20 generated questions.\n";
    echo "  --help, -h      Show this help.\n";
    exit(0);
}

if (!$DB->get_manager()->table_exists('local_flwexam_exams') ||
        !$DB->get_manager()->table_exists('local_flwexam_questions')) {
    cli_error('The FLW Exam database tables are not installed. Run Moodle upgrade first.');
}

$replacequestions = !empty($options['replace']);
$languages = exam_service::get_learning_language_options();
$levels = exam_service::get_cefr_level_options();
$now = time();
$createdexams = 0;
$updatedexams = 0;
$createdquestions = 0;
$replacedquestions = 0;

foreach ($languages as $languagecode => $languagelabel) {
    $tracks = exam_service::get_track_options_for_language($languagecode);
    foreach ($tracks as $trackcode => $tracklabel) {
        foreach (array_keys($levels) as $level) {
            $examcode = local_flwexam_seed_exam_code($languagecode, $trackcode, $level);
            $examname = "{$tracklabel} {$level} Certificate Exam";
            [$threshold, $skillfloor] = local_flwexam_seed_thresholds($level);

            $exam = $DB->get_record('local_flwexam_exams', ['code' => $examcode], '*', IGNORE_MISSING);
            if ($exam) {
                $exam->name = $examname;
                $exam->language = $languagecode;
                $exam->learningcoursecategory = $trackcode;
                $exam->cefrlevel = $level;
                $exam->requiredthreshold = $threshold;
                $exam->requiredskillfloor = $skillfloor;
                $exam->moderationrequired = 1;
                $exam->criticalkpjson = json_encode(local_flwexam_seed_critical_kps($languagecode, $trackcode, $level));
                $exam->profilejson = json_encode(local_flwexam_seed_profile($languagelabel, $tracklabel, $level));
                $exam->visible = 1;
                $exam->timemodified = $now;
                $DB->update_record('local_flwexam_exams', $exam);
                $examid = (int)$exam->id;
                $updatedexams++;
            } else {
                $examid = $DB->insert_record('local_flwexam_exams', (object)[
                    'code' => $examcode,
                    'name' => $examname,
                    'language' => $languagecode,
                    'learningcoursecategory' => $trackcode,
                    'cefrlevel' => $level,
                    'requiredthreshold' => $threshold,
                    'requiredskillfloor' => $skillfloor,
                    'moderationrequired' => 1,
                    'criticalkpjson' => json_encode(local_flwexam_seed_critical_kps($languagecode, $trackcode, $level)),
                    'profilejson' => json_encode(local_flwexam_seed_profile($languagelabel, $tracklabel, $level)),
                    'visible' => 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $createdexams++;
            }

            if ($replacequestions) {
                $replacedquestions += $DB->count_records('local_flwexam_questions', ['examid' => $examid]);
                $DB->delete_records('local_flwexam_questions', ['examid' => $examid]);
            }

            $existingcount = $DB->count_records('local_flwexam_questions', ['examid' => $examid]);
            if ($existingcount >= 20) {
                continue;
            }

            $questions = local_flwexam_seed_question_blueprints($languagecode, $languagelabel, $tracklabel, $level);
            for ($index = $existingcount; $index < 20; $index++) {
                $question = $questions[$index];
                $sortorder = $index + 1;
                $kpcode = local_flwexam_seed_kp_code($languagecode, $trackcode, $level, $question['skill'], $sortorder);
                [$qtype, $questiontext, $optionsjson, $correctkey] = local_flwexam_seed_render_question(
                    $languagecode,
                    $question,
                    $examcode,
                    $level,
                    $sortorder
                );
                $DB->insert_record('local_flwexam_questions', (object)[
                    'examid' => $examid,
                    'qtype' => $qtype,
                    'questiontext' => $questiontext,
                    'optionsjson' => $optionsjson,
                    'correctanswer' => $correctkey,
                    'skill' => $question['skill'],
                    'kpcode' => $kpcode,
                    'weight' => 1,
                    'sortorder' => $sortorder,
                    'visible' => 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $createdquestions++;
            }
        }
    }
}

/**
 * Render one generated question into a stored qtype, stem, options, and answer.
 *
 * @param string $languagecode
 * @param array $question
 * @param string $examcode
 * @param string $level
 * @param int $sortorder
 * @return array
 */
function local_flwexam_seed_render_question(
    string $languagecode,
    array $question,
    string $examcode,
    string $level,
    int $sortorder
): array {
    if ($sortorder % 4 === 2) {
        $istrue = ($sortorder % 8) !== 2;
        $statement = $istrue ? $question['correct'] : ($question['distractors'][0] ?? $question['correct']);
        return [
            'truefalse',
            local_flwexam_seed_truefalse_prompt($languagecode, $statement),
            json_encode(local_flwexam_seed_truefalse_options($languagecode)),
            $istrue ? 'true' : 'false',
        ];
    }

    if ($sortorder % 4 === 3) {
        return [
            'shortanswer',
            local_flwexam_seed_shortanswer_prompt($languagecode, $question['skill'], $level),
            json_encode([]),
            local_flwexam_seed_skill_term($languagecode, $question['skill']),
        ];
    }

    [$optionsjson, $correctkey] = local_flwexam_seed_options(
        $question['correct'],
        $question['distractors'],
        $examcode,
        $sortorder
    );
    return ['multichoice', $question['text'], $optionsjson, $correctkey];
}

/**
 * Build a localized true/false prompt.
 *
 * @param string $languagecode
 * @param string $statement
 * @return string
 */
function local_flwexam_seed_truefalse_prompt(string $languagecode, string $statement): string {
    $prefixes = [
        'ru' => 'Верно или неверно: ',
        'zh' => '判断正误：',
        'ja' => '正しいか間違いか：',
        'de' => 'Richtig oder falsch: ',
        'fr' => 'Vrai ou faux : ',
        'es' => 'Verdadero o falso: ',
    ];
    return ($prefixes[$languagecode] ?? 'True or false: ') . $statement;
}

/**
 * Build localized true/false options with stable answer values.
 *
 * @param string $languagecode
 * @return array
 */
function local_flwexam_seed_truefalse_options(string $languagecode): array {
    $labels = [
        'ru' => ['true' => 'Верно', 'false' => 'Неверно'],
        'zh' => ['true' => '正确', 'false' => '错误'],
        'ja' => ['true' => '正しい', 'false' => '間違い'],
        'de' => ['true' => 'Richtig', 'false' => 'Falsch'],
        'fr' => ['true' => 'Vrai', 'false' => 'Faux'],
        'es' => ['true' => 'Verdadero', 'false' => 'Falso'],
    ];
    $set = $labels[$languagecode] ?? ['true' => 'True', 'false' => 'False'];
    return [
        ['key' => 'true', 'text' => $set['true']],
        ['key' => 'false', 'text' => $set['false']],
    ];
}

/**
 * Build a localized short-answer prompt.
 *
 * @param string $languagecode
 * @param string $skill
 * @param string $level
 * @return string
 */
function local_flwexam_seed_shortanswer_prompt(string $languagecode, string $skill, string $level): string {
    $skillterm = local_flwexam_seed_skill_term($languagecode, $skill);
    $prompts = [
        'ru' => "Введите название проверяемого навыка уровня {$level}.",
        'zh' => "请输入本题考查的{$level}级技能名称。",
        'ja' => "{$level}レベルで問われている技能名を入力してください。",
        'de' => "Geben Sie den geprüften Kompetenzbereich auf Niveau {$level} ein.",
        'fr' => "Saisissez la compétence évaluée au niveau {$level}.",
        'es' => "Escriba la habilidad evaluada en el nivel {$level}.",
    ];
    return ($prompts[$languagecode] ?? "Type the skill assessed at level {$level}.") .
        ' [' . local_flwexam_seed_shortanswer_hint($languagecode, $skillterm) . ']';
}

/**
 * Short answer hint text.
 *
 * @param string $languagecode
 * @param string $skillterm
 * @return string
 */
function local_flwexam_seed_shortanswer_hint(string $languagecode, string $skillterm): string {
    $hints = [
        'ru' => 'ответ одним словом',
        'zh' => '用一个词回答',
        'ja' => '一語で答えてください',
        'de' => 'Antwort mit einem Wort',
        'fr' => 'réponse en un mot',
        'es' => 'respuesta de una palabra',
    ];
    return $hints[$languagecode] ?? 'one-word answer';
}

/**
 * Localized skill term used as the expected short answer.
 *
 * @param string $languagecode
 * @param string $skill
 * @return string
 */
function local_flwexam_seed_skill_term(string $languagecode, string $skill): string {
    $terms = [
        'en' => [
            'listening' => 'listening',
            'speaking' => 'speaking',
            'reading' => 'reading',
            'writing' => 'writing',
        ],
        'ru' => [
            'listening' => 'аудирование',
            'speaking' => 'говорение',
            'reading' => 'чтение',
            'writing' => 'письмо',
        ],
        'zh' => [
            'listening' => '听力',
            'speaking' => '口语',
            'reading' => '阅读',
            'writing' => '写作',
        ],
        'ja' => [
            'listening' => 'リスニング',
            'speaking' => 'スピーキング',
            'reading' => '読解',
            'writing' => '作文',
        ],
        'de' => [
            'listening' => 'hören',
            'speaking' => 'sprechen',
            'reading' => 'lesen',
            'writing' => 'schreiben',
        ],
        'fr' => [
            'listening' => 'écoute',
            'speaking' => 'expression orale',
            'reading' => 'lecture',
            'writing' => 'écriture',
        ],
        'es' => [
            'listening' => 'escucha',
            'speaking' => 'expresión oral',
            'reading' => 'lectura',
            'writing' => 'escritura',
        ],
    ];
    return $terms[$languagecode][$skill] ?? $skill;
}

$totalexams = $DB->count_records('local_flwexam_exams', ['visible' => 1]);
$totalquestions = $DB->count_records_sql(
    'SELECT COUNT(1)
       FROM {local_flwexam_questions} q
       JOIN {local_flwexam_exams} e ON e.id = q.examid
      WHERE e.visible = 1 AND q.visible = 1'
);

echo "FLW Exam seed complete.\n";
echo "Created exams: {$createdexams}\n";
echo "Updated exams: {$updatedexams}\n";
echo "Replaced questions: {$replacedquestions}\n";
echo "Created questions: {$createdquestions}\n";
echo "Visible exams: {$totalexams}\n";
echo "Visible questions on visible exams: {$totalquestions}\n";

/**
 * Build a stable exam code.
 *
 * @param string $language
 * @param string $track
 * @param string $level
 * @return string
 */
function local_flwexam_seed_exam_code(string $language, string $track, string $level): string {
    $trackcodes = [
        'adventure_world' => 'AW',
        'real_world' => 'RW',
    ];
    $trackcode = $trackcodes[$track] ?? 'W';
    return strtoupper($language) . '-' . $trackcode . '-' . strtoupper($level) . '-CERT';
}

/**
 * Threshold defaults by CEFR band.
 *
 * @param string $level
 * @return array
 */
function local_flwexam_seed_thresholds(string $level): array {
    if (in_array($level, ['C1', 'C2'], true)) {
        return [80, 70];
    }
    if (in_array($level, ['B1', 'B2'], true)) {
        return [75, 65];
    }
    return [70, 60];
}

/**
 * Certificate profile metadata.
 *
 * @param string $language
 * @param string $track
 * @param string $level
 * @return array
 */
function local_flwexam_seed_profile(string $language, string $track, string $level): array {
    return [
        'description' => "Generated FLW {$level} certificate profile for {$track}.",
        'language' => $language,
        'track' => $track,
        'level' => $level,
        'skills' => ['listening', 'speaking', 'reading', 'writing'],
        'source' => 'local_flwexam_seed_all_exams',
    ];
}

/**
 * Critical KP list.
 *
 * @param string $language
 * @param string $track
 * @param string $level
 * @return array
 */
function local_flwexam_seed_critical_kps(string $language, string $track, string $level): array {
    return [
        local_flwexam_seed_kp_code($language, $track, $level, 'listening', 1),
        local_flwexam_seed_kp_code($language, $track, $level, 'speaking', 6),
        local_flwexam_seed_kp_code($language, $track, $level, 'reading', 11),
        local_flwexam_seed_kp_code($language, $track, $level, 'writing', 16),
    ];
}

/**
 * Build a compact KP code.
 *
 * @param string $language
 * @param string $track
 * @param string $level
 * @param string $skill
 * @param int $number
 * @return string
 */
function local_flwexam_seed_kp_code(string $language, string $track, string $level, string $skill, int $number): string {
    $trackpart = str_replace('_world', '', $track);
    $skillpart = substr($skill, 0, 3);
    return strtolower($language . '_' . $trackpart . '_' . $level . '_' . $skillpart . '_' . sprintf('%02d', $number));
}

/**
 * Deterministically shuffle answer options.
 *
 * @param string $correct
 * @param array $distractors
 * @param string $examcode
 * @param int $sortorder
 * @return array
 */
function local_flwexam_seed_options(string $correct, array $distractors, string $examcode, int $sortorder): array {
    $rows = [['text' => $correct, 'correct' => true]];
    foreach (array_slice($distractors, 0, 3) as $distractor) {
        $rows[] = ['text' => $distractor, 'correct' => false];
    }

    $seed = abs(crc32($examcode . ':' . $sortorder));
    for ($i = count($rows) - 1; $i > 0; $i--) {
        $j = $seed % ($i + 1);
        $seed = intdiv($seed, 7) + 13;
        $tmp = $rows[$i];
        $rows[$i] = $rows[$j];
        $rows[$j] = $tmp;
    }

    $options = [];
    $correctkey = 'a';
    foreach ($rows as $index => $row) {
        $key = chr(97 + $index);
        $options[] = [
            'key' => $key,
            'text' => $row['text'],
        ];
        if ($row['correct']) {
            $correctkey = $key;
        }
    }

    return [json_encode($options), $correctkey];
}

/**
 * Generate 20 question blueprints for an exam.
 *
 * @param string $languagecode
 * @param string $language
 * @param string $track
 * @param string $level
 * @return array
 */
function local_flwexam_seed_question_blueprints(string $languagecode, string $language, string $track, string $level): array {
    if ($languagecode !== 'en') {
        return local_flwexam_seed_localized_question_blueprints($languagecode, $level);
    }

    $context = "{$language} {$level}";
    $trackcontext = "{$track} {$level}";
    return [
        [
            'skill' => 'listening',
            'text' => "In an {$context} listening task, what should the learner identify first in a short greeting?",
            'correct' => 'The speaker, greeting, and purpose of the message.',
            'distractors' => ['Only the last word of the message.', 'The page number of the textbook.', 'The spelling of every unfamiliar word.'],
        ],
        [
            'skill' => 'listening',
            'text' => "A speaker gives a time, place, and action in {$language}. Which note is the best listening note?",
            'correct' => 'A short note with the time, place, and action.',
            'distractors' => ['A translation of every sound.', 'A guess based only on the title.', 'A list of unrelated vocabulary.'],
        ],
        [
            'skill' => 'listening',
            'text' => "For {$trackcontext}, which strategy best confirms the main idea of an audio message?",
            'correct' => 'Listen for repeated keywords and the final request.',
            'distractors' => ['Ignore context and choose the longest answer.', 'Focus only on background noise.', 'Stop listening after the first sentence.'],
        ],
        [
            'skill' => 'listening',
            'text' => "The learner hears two choices in a practical exchange. What should the learner compare?",
            'correct' => 'The options, prices, times, or conditions mentioned by the speakers.',
            'distractors' => ['The color of the audio player.', 'The number of letters in each name.', 'Only the first word spoken.'],
        ],
        [
            'skill' => 'listening',
            'text' => "Which response shows successful {$level} listening for instructions?",
            'correct' => 'The learner follows the sequence in the order it was given.',
            'distractors' => ['The learner changes the task topic.', 'The learner answers before hearing the instruction.', 'The learner repeats an unrelated phrase.'],
        ],
        [
            'skill' => 'speaking',
            'text' => "In an {$context} speaking exam, what makes a self-introduction effective?",
            'correct' => 'Clear basic information with understandable pronunciation.',
            'distractors' => ['Memorized words with no connection.', 'Speaking only in isolated letters.', 'Avoiding all personal information.'],
        ],
        [
            'skill' => 'speaking',
            'text' => "A learner needs clarification during a {$language} conversation. Which action is best?",
            'correct' => 'Ask a simple clarification question politely.',
            'distractors' => ['Stay silent for the rest of the task.', 'Change to an unrelated subject.', 'Answer with a random memorized sentence.'],
        ],
        [
            'skill' => 'speaking',
            'text' => "For {$trackcontext}, which speaking answer best supports a personal opinion?",
            'correct' => 'A clear opinion followed by one relevant reason.',
            'distractors' => ['A reason without any opinion.', 'A copied question with no answer.', 'A list of numbers only.'],
        ],
        [
            'skill' => 'speaking',
            'text' => "Which behavior is strongest in a role-play speaking task?",
            'correct' => 'Respond to the partner and keep the exchange moving.',
            'distractors' => ['Read every answer without listening.', 'Use only one-word answers for every turn.', 'Correct the partner instead of answering.'],
        ],
        [
            'skill' => 'speaking',
            'text' => "What should a {$level} learner do if they make a small speaking error?",
            'correct' => 'Self-correct briefly or continue with clear meaning.',
            'distractors' => ['End the exam immediately.', 'Repeat the same error many times on purpose.', 'Switch to an unrelated topic.'],
        ],
        [
            'skill' => 'reading',
            'text' => "In an {$context} reading passage, what is the best way to find the main idea?",
            'correct' => 'Use the title, first sentence, and repeated keywords.',
            'distractors' => ['Read only the punctuation marks.', 'Choose the answer with the most words.', 'Ignore headings and repeated words.'],
        ],
        [
            'skill' => 'reading',
            'text' => "A notice includes dates and conditions. Which detail should the learner check?",
            'correct' => 'The date, requirement, exception, and action needed.',
            'distractors' => ['Only the logo at the top.', 'Only words already known from another lesson.', 'The longest sentence regardless of meaning.'],
        ],
        [
            'skill' => 'reading',
            'text' => "For {$trackcontext}, what does an inference question usually require?",
            'correct' => 'Using clues in the text to choose a likely meaning.',
            'distractors' => ['Choosing an answer not supported by the text.', 'Counting all commas in the passage.', 'Ignoring the sentence before and after.'],
        ],
        [
            'skill' => 'reading',
            'text' => "Which reading strategy helps with unfamiliar words?",
            'correct' => 'Use context, word parts, and the purpose of the sentence.',
            'distractors' => ['Stop reading at the first new word.', 'Assume every new word means the same thing.', 'Skip the whole paragraph.'],
        ],
        [
            'skill' => 'reading',
            'text' => "What is the best evidence for a reading answer?",
            'correct' => 'A specific sentence or phrase from the passage.',
            'distractors' => ['A personal preference only.', 'A memory from another course.', 'The answer position in the list.'],
        ],
        [
            'skill' => 'writing',
            'text' => "In an {$context} writing task, what should the learner plan first?",
            'correct' => 'Audience, purpose, and two or three key points.',
            'distractors' => ['Font color before meaning.', 'A list of unrelated words.', 'The final score before writing.'],
        ],
        [
            'skill' => 'writing',
            'text' => "Which sentence quality matters most in a short {$language} message?",
            'correct' => 'Clear meaning with correct basic word order.',
            'distractors' => ['Using the longest possible sentence.', 'Leaving out the main verb every time.', 'Writing only abbreviations.'],
        ],
        [
            'skill' => 'writing',
            'text' => "For {$trackcontext}, what makes a paragraph coherent?",
            'correct' => 'One main idea with connected supporting sentences.',
            'distractors' => ['Every sentence about a different topic.', 'No link between examples and conclusion.', 'Only copied phrases from the prompt.'],
        ],
        [
            'skill' => 'writing',
            'text' => "Which revision step should come before submission?",
            'correct' => 'Check task completion, grammar, spelling, and punctuation.',
            'distractors' => ['Delete all examples.', 'Add unrelated advanced words.', 'Change the answer to a different task.'],
        ],
        [
            'skill' => 'writing',
            'text' => "What should a {$level} learner do when asked to compare two options in writing?",
            'correct' => 'State both options and give a clear comparison.',
            'distractors' => ['Mention only one option with no comparison.', 'Write a greeting and stop.', 'Copy the question without answering.'],
        ],
    ];
}

/**
 * Generate localized 20-question blueprints for a non-English exam.
 *
 * @param string $languagecode
 * @param string $level
 * @return array
 */
function local_flwexam_seed_localized_question_blueprints(string $languagecode, string $level): array {
    $packs = [
        'ru' => [
            'listen_intro' => 'В задании на аудирование уровня ' . $level . ' что нужно сначала определить в коротком приветствии?',
            'listen_intro_correct' => 'Говорящего, приветствие и цель сообщения.',
            'listen_intro_wrong' => ['Только последнее слово сообщения.', 'Номер страницы учебника.', 'Написание каждого незнакомого слова.'],
            'listen_note' => 'Говорящий называет время, место и действие. Какая запись будет лучшей?',
            'listen_note_correct' => 'Краткая запись с временем, местом и действием.',
            'listen_note_wrong' => ['Перевод каждого звука.', 'Догадка только по названию.', 'Список несвязанных слов.'],
            'listen_main' => 'Какая стратегия лучше всего подтверждает главную мысль аудиосообщения?',
            'listen_main_correct' => 'Слушать повторяющиеся ключевые слова и финальную просьбу.',
            'listen_main_wrong' => ['Выбирать самый длинный ответ.', 'Слушать только фоновый шум.', 'Прекратить слушать после первого предложения.'],
            'listen_compare' => 'Ученик слышит два варианта в практическом диалоге. Что нужно сравнить?',
            'listen_compare_correct' => 'Варианты, цены, время или условия, названные говорящими.',
            'listen_compare_wrong' => ['Цвет аудиоплеера.', 'Количество букв в именах.', 'Только первое произнесенное слово.'],
            'listen_sequence' => 'Какой ответ показывает успешное понимание инструкции?',
            'listen_sequence_correct' => 'Ученик выполняет действия в указанном порядке.',
            'listen_sequence_wrong' => ['Ученик меняет тему задания.', 'Ученик отвечает до инструкции.', 'Ученик повторяет несвязанную фразу.'],
            'speak_intro' => 'Что делает самопрезентацию эффективной в устном экзамене уровня ' . $level . '?',
            'speak_intro_correct' => 'Понятная основная информация и разборчивое произношение.',
            'speak_intro_wrong' => ['Заученные слова без связи.', 'Только отдельные буквы.', 'Отсутствие личной информации.'],
            'speak_clarify' => 'Ученик не понял собеседника. Какое действие лучше?',
            'speak_clarify_correct' => 'Вежливо задать простой уточняющий вопрос.',
            'speak_clarify_wrong' => ['Молчать до конца задания.', 'Перейти к другой теме.', 'Ответить случайной заученной фразой.'],
            'speak_opinion' => 'Какой устный ответ лучше поддерживает личное мнение?',
            'speak_opinion_correct' => 'Четкое мнение и одна уместная причина.',
            'speak_opinion_wrong' => ['Причина без мнения.', 'Повтор вопроса без ответа.', 'Только список чисел.'],
            'speak_roleplay' => 'Какое поведение самое сильное в ролевом диалоге?',
            'speak_roleplay_correct' => 'Отвечать партнеру и поддерживать разговор.',
            'speak_roleplay_wrong' => ['Читать ответы без слушания.', 'Всегда отвечать одним словом.', 'Исправлять партнера вместо ответа.'],
            'speak_error' => 'Что должен сделать ученик, если допустил небольшую ошибку в речи?',
            'speak_error_correct' => 'Коротко исправиться или продолжить, сохраняя смысл.',
            'speak_error_wrong' => ['Сразу закончить экзамен.', 'Намеренно повторять ошибку.', 'Перейти к несвязанной теме.'],
            'read_main' => 'Как лучше найти главную мысль текста уровня ' . $level . '?',
            'read_main_correct' => 'Использовать заголовок, первое предложение и повторяющиеся ключевые слова.',
            'read_main_wrong' => ['Читать только знаки препинания.', 'Выбрать самый длинный ответ.', 'Игнорировать заголовки и повторы.'],
            'read_notice' => 'В объявлении есть даты и условия. Какую информацию нужно проверить?',
            'read_notice_correct' => 'Дату, требование, исключение и нужное действие.',
            'read_notice_wrong' => ['Только логотип сверху.', 'Только уже знакомые слова.', 'Самое длинное предложение без учета смысла.'],
            'read_infer' => 'Что обычно требуется в вопросе на вывод?',
            'read_infer_correct' => 'Использовать подсказки текста, чтобы выбрать вероятный смысл.',
            'read_infer_wrong' => ['Выбрать ответ без опоры на текст.', 'Считать все запятые.', 'Игнорировать соседние предложения.'],
            'read_words' => 'Какая стратегия помогает с незнакомыми словами?',
            'read_words_correct' => 'Использовать контекст, части слова и цель предложения.',
            'read_words_wrong' => ['Остановиться на первом новом слове.', 'Считать все новые слова одинаковыми.', 'Пропустить весь абзац.'],
            'read_evidence' => 'Что является лучшим доказательством ответа по чтению?',
            'read_evidence_correct' => 'Конкретное предложение или фраза из текста.',
            'read_evidence_wrong' => ['Только личное мнение.', 'Воспоминание из другого курса.', 'Позиция ответа в списке.'],
            'write_plan' => 'Что нужно сначала спланировать в письменном задании уровня ' . $level . '?',
            'write_plan_correct' => 'Адресата, цель и два-три ключевых пункта.',
            'write_plan_wrong' => ['Цвет шрифта до смысла.', 'Список несвязанных слов.', 'Итоговый балл до письма.'],
            'write_sentence' => 'Что важнее всего в коротком письменном сообщении?',
            'write_sentence_correct' => 'Ясный смысл и правильный базовый порядок слов.',
            'write_sentence_wrong' => ['Самое длинное предложение.', 'Постоянно пропускать глагол.', 'Писать только сокращения.'],
            'write_paragraph' => 'Что делает абзац связным?',
            'write_paragraph_correct' => 'Одна главная мысль и связанные поддерживающие предложения.',
            'write_paragraph_wrong' => ['Каждое предложение о новой теме.', 'Нет связи между примерами и выводом.', 'Только скопированные фразы из задания.'],
            'write_check' => 'Какой шаг проверки нужен перед отправкой?',
            'write_check_correct' => 'Проверить выполнение задания, грамматику, орфографию и пунктуацию.',
            'write_check_wrong' => ['Удалить все примеры.', 'Добавить несвязанные сложные слова.', 'Изменить ответ на другую тему.'],
            'write_compare' => 'Что нужно сделать, если задание просит сравнить два варианта?',
            'write_compare_correct' => 'Назвать оба варианта и дать ясное сравнение.',
            'write_compare_wrong' => ['Упомянуть только один вариант.', 'Написать приветствие и остановиться.', 'Скопировать вопрос без ответа.'],
        ],
        'zh' => [
            'listen_intro' => '在' . $level . '级听力题中，听到简短问候时首先应识别什么？',
            'listen_intro_correct' => '说话者、问候语和信息目的。',
            'listen_intro_wrong' => ['只听最后一个词。', '课本的页码。', '每个生词的拼写。'],
            'listen_note' => '说话者给出时间、地点和动作。哪种笔记最好？',
            'listen_note_correct' => '简短记录时间、地点和动作。',
            'listen_note_wrong' => ['翻译每一个声音。', '只根据标题猜测。', '写一串无关词汇。'],
            'listen_main' => '哪种策略最能确认音频的主旨？',
            'listen_main_correct' => '听重复的关键词和最后的请求。',
            'listen_main_wrong' => ['选择最长的答案。', '只注意背景噪音。', '第一句后就停止听。'],
            'listen_compare' => '学习者在实际对话中听到两个选择。应该比较什么？',
            'listen_compare_correct' => '说话者提到的选项、价格、时间或条件。',
            'listen_compare_wrong' => ['播放器的颜色。', '名字里的字母数量。', '只听第一个词。'],
            'listen_sequence' => '哪种反应说明听懂了指令？',
            'listen_sequence_correct' => '按给出的顺序完成步骤。',
            'listen_sequence_wrong' => ['改变任务主题。', '听完指令前就回答。', '重复无关短语。'],
            'speak_intro' => $level . '级口语考试中，什么让自我介绍有效？',
            'speak_intro_correct' => '清楚的基本信息和可理解的发音。',
            'speak_intro_wrong' => ['没有联系的背诵词。', '只说单个字母。', '避开所有个人信息。'],
            'speak_clarify' => '学习者没有听懂对方时，最好怎么做？',
            'speak_clarify_correct' => '礼貌地问一个简单的澄清问题。',
            'speak_clarify_wrong' => ['一直沉默到结束。', '换到无关话题。', '说随机背诵句。'],
            'speak_opinion' => '哪种口语回答最能支持个人观点？',
            'speak_opinion_correct' => '清楚表达观点，并给出一个相关理由。',
            'speak_opinion_wrong' => ['只有理由没有观点。', '重复问题但不回答。', '只列数字。'],
            'speak_roleplay' => '角色扮演中最好的表现是什么？',
            'speak_roleplay_correct' => '回应伙伴并保持交流继续。',
            'speak_roleplay_wrong' => ['不听就读答案。', '每次只回答一个词。', '只纠正对方而不回答。'],
            'speak_error' => '口语中出现小错误时，学习者应怎么做？',
            'speak_error_correct' => '简短自我纠正，或继续表达清楚意思。',
            'speak_error_wrong' => ['立刻结束考试。', '故意反复说同一个错误。', '换到无关话题。'],
            'read_main' => '怎样最好地找到' . $level . '级阅读文章的主旨？',
            'read_main_correct' => '利用标题、第一句和重复关键词。',
            'read_main_wrong' => ['只读标点符号。', '选择字数最多的答案。', '忽略标题和重复词。'],
            'read_notice' => '通知中有日期和条件。应检查哪项信息？',
            'read_notice_correct' => '日期、要求、例外和需要采取的行动。',
            'read_notice_wrong' => ['只看顶部标志。', '只看已经认识的词。', '选最长的句子而不看意思。'],
            'read_infer' => '推断题通常要求什么？',
            'read_infer_correct' => '根据文本线索选择最可能的意思。',
            'read_infer_wrong' => ['选择文本不支持的答案。', '数所有逗号。', '忽略前后句。'],
            'read_words' => '哪种策略有助于理解生词？',
            'read_words_correct' => '利用上下文、词的组成和句子目的。',
            'read_words_wrong' => ['遇到第一个生词就停止。', '认为所有生词意思相同。', '跳过整段。'],
            'read_evidence' => '阅读答案最好的证据是什么？',
            'read_evidence_correct' => '文章中的具体句子或短语。',
            'read_evidence_wrong' => ['只有个人喜好。', '另一个课程的记忆。', '答案在列表中的位置。'],
            'write_plan' => $level . '级写作任务首先应计划什么？',
            'write_plan_correct' => '读者、目的和两三个要点。',
            'write_plan_wrong' => ['先考虑字体颜色。', '列无关词语。', '写作前先想最终分数。'],
            'write_sentence' => '短文信息中最重要的句子质量是什么？',
            'write_sentence_correct' => '意思清楚，基本语序正确。',
            'write_sentence_wrong' => ['句子越长越好。', '总是省略主要动词。', '只写缩写。'],
            'write_paragraph' => '什么让段落连贯？',
            'write_paragraph_correct' => '一个中心思想和相关支持句。',
            'write_paragraph_wrong' => ['每句都是不同主题。', '例子和结论没有联系。', '只复制题目短语。'],
            'write_check' => '提交前应做哪一步修改？',
            'write_check_correct' => '检查是否完成任务、语法、拼写和标点。',
            'write_check_wrong' => ['删除所有例子。', '加入无关高级词。', '改成另一个任务。'],
            'write_compare' => '如果写作要求比较两个选项，应怎么做？',
            'write_compare_correct' => '说明两个选项，并给出清楚比较。',
            'write_compare_wrong' => ['只提一个选项。', '写问候语后停止。', '抄题但不回答。'],
        ],
        'ja' => [
            'listen_intro' => $level . 'レベルのリスニングで短いあいさつを聞いたとき、最初に確認することは何ですか。',
            'listen_intro_correct' => '話し手、あいさつ、メッセージの目的。',
            'listen_intro_wrong' => ['最後の単語だけ。', '教科書のページ番号。', '知らない単語すべてのつづり。'],
            'listen_note' => '話し手が時間、場所、行動を言いました。最もよいメモはどれですか。',
            'listen_note_correct' => '時間、場所、行動を短く書いたメモ。',
            'listen_note_wrong' => ['すべての音を翻訳したもの。', 'タイトルだけからの推測。', '関係のない語彙リスト。'],
            'listen_main' => '音声メッセージの主旨を確認する最もよい方法はどれですか。',
            'listen_main_correct' => '繰り返されるキーワードと最後の依頼を聞く。',
            'listen_main_wrong' => ['一番長い答えを選ぶ。', '背景音だけに注目する。', '最初の文の後で聞くのをやめる。'],
            'listen_compare' => '実用的な会話で二つの選択肢を聞きました。何を比べますか。',
            'listen_compare_correct' => '選択肢、値段、時間、条件。',
            'listen_compare_wrong' => ['音声プレーヤーの色。', '名前の文字数。', '最初の単語だけ。'],
            'listen_sequence' => '指示を理解できたことを示す反応はどれですか。',
            'listen_sequence_correct' => '言われた順番どおりに行動する。',
            'listen_sequence_wrong' => ['課題の話題を変える。', '指示を聞く前に答える。', '関係のない表現を繰り返す。'],
            'speak_intro' => $level . 'レベルのスピーキング試験で、よい自己紹介とは何ですか。',
            'speak_intro_correct' => '基本情報が明確で、発音が理解しやすいこと。',
            'speak_intro_wrong' => ['つながりのない暗記語句。', '一文字ずつだけ話すこと。', '個人情報をすべて避けること。'],
            'speak_clarify' => '相手の言ったことが分からないとき、最もよい行動はどれですか。',
            'speak_clarify_correct' => '丁寧に簡単な確認質問をする。',
            'speak_clarify_wrong' => ['最後まで黙る。', '関係のない話題に変える。', '暗記した文を適当に言う。'],
            'speak_opinion' => '個人的な意見を支える最もよい話し方はどれですか。',
            'speak_opinion_correct' => '明確な意見と関連する理由を一つ述べる。',
            'speak_opinion_wrong' => ['理由だけで意見がない。', '質問を繰り返すだけ。', '数字だけを並べる。'],
            'speak_roleplay' => 'ロールプレイで最もよい行動はどれですか。',
            'speak_roleplay_correct' => '相手に返答し、会話を続ける。',
            'speak_roleplay_wrong' => ['聞かずに答えを読む。', '毎回一語だけで答える。', '答えずに相手を直す。'],
            'speak_error' => '話しているときに小さな間違いをしたら、どうしますか。',
            'speak_error_correct' => '短く直すか、意味が伝わるように続ける。',
            'speak_error_wrong' => ['すぐに試験を終える。', '同じ間違いを何度も言う。', '関係のない話題に変える。'],
            'read_main' => $level . 'レベルの読解文で主旨を見つける最もよい方法はどれですか。',
            'read_main_correct' => 'タイトル、最初の文、繰り返されるキーワードを使う。',
            'read_main_wrong' => ['句読点だけを読む。', '一番長い答えを選ぶ。', '見出しと反復語を無視する。'],
            'read_notice' => 'お知らせに日付と条件があります。確認すべき情報は何ですか。',
            'read_notice_correct' => '日付、条件、例外、必要な行動。',
            'read_notice_wrong' => ['上のロゴだけ。', '知っている単語だけ。', '意味を考えず一番長い文。'],
            'read_infer' => '推論問題で普通必要なことは何ですか。',
            'read_infer_correct' => '本文の手がかりから最もありそうな意味を選ぶ。',
            'read_infer_wrong' => ['本文にない答えを選ぶ。', 'すべての読点を数える。', '前後の文を無視する。'],
            'read_words' => '知らない語を理解する助けになる方法はどれですか。',
            'read_words_correct' => '文脈、語の部分、文の目的を使う。',
            'read_words_wrong' => ['最初の新語で読むのをやめる。', 'すべての新語を同じ意味だと思う。', '段落全体を飛ばす。'],
            'read_evidence' => '読解の答えの最もよい根拠は何ですか。',
            'read_evidence_correct' => '本文中の具体的な文や表現。',
            'read_evidence_wrong' => ['個人的な好みだけ。', '別の授業の記憶。', '選択肢の位置。'],
            'write_plan' => $level . 'レベルの作文で最初に計画することは何ですか。',
            'write_plan_correct' => '読み手、目的、二つか三つの要点。',
            'write_plan_wrong' => ['意味より先に文字色。', '関係のない単語リスト。', '書く前の最終点数。'],
            'write_sentence' => '短いメッセージで最も大切な文の質は何ですか。',
            'write_sentence_correct' => '意味が明確で基本語順が正しいこと。',
            'write_sentence_wrong' => ['できるだけ長い文。', '主な動詞をいつも省くこと。', '略語だけを書くこと。'],
            'write_paragraph' => '段落をまとまりのあるものにするものは何ですか。',
            'write_paragraph_correct' => '一つの主題とつながった支持文。',
            'write_paragraph_wrong' => ['各文が別の話題。', '例と結論がつながらない。', '課題文からコピーした表現だけ。'],
            'write_check' => '提出前に行うべき確認はどれですか。',
            'write_check_correct' => '課題達成、文法、つづり、句読点を確認する。',
            'write_check_wrong' => ['すべての例を消す。', '関係のない難語を足す。', '別の課題に変える。'],
            'write_compare' => '二つの選択肢を比較する作文では何をしますか。',
            'write_compare_correct' => '両方の選択肢を述べ、明確に比較する。',
            'write_compare_wrong' => ['一つだけ述べる。', 'あいさつだけ書いて終わる。', '質問を写して答えない。'],
        ],
    ];

    if (!isset($packs[$languagecode])) {
        $packs[$languagecode] = local_flwexam_seed_romance_question_pack($languagecode, $level);
    }

    $p = $packs[$languagecode];
    $items = [
        ['listening', 'listen_intro'], ['listening', 'listen_note'], ['listening', 'listen_main'],
        ['listening', 'listen_compare'], ['listening', 'listen_sequence'],
        ['speaking', 'speak_intro'], ['speaking', 'speak_clarify'], ['speaking', 'speak_opinion'],
        ['speaking', 'speak_roleplay'], ['speaking', 'speak_error'],
        ['reading', 'read_main'], ['reading', 'read_notice'], ['reading', 'read_infer'],
        ['reading', 'read_words'], ['reading', 'read_evidence'],
        ['writing', 'write_plan'], ['writing', 'write_sentence'], ['writing', 'write_paragraph'],
        ['writing', 'write_check'], ['writing', 'write_compare'],
    ];

    $questions = [];
    foreach ($items as [$skill, $key]) {
        $questions[] = [
            'skill' => $skill,
            'text' => $p[$key],
            'correct' => $p[$key . '_correct'],
            'distractors' => $p[$key . '_wrong'],
        ];
    }
    return $questions;
}

/**
 * Localized packs for German, French, and Spanish.
 *
 * @param string $languagecode
 * @param string $level
 * @return array
 */
function local_flwexam_seed_romance_question_pack(string $languagecode, string $level): array {
    $packs = [
        'de' => [
            'listen_intro' => 'Was sollte man in einer Hörverstehensaufgabe auf Niveau ' . $level . ' bei einer kurzen Begrüßung zuerst erkennen?',
            'listen_intro_correct' => 'Den Sprecher, die Begrüßung und den Zweck der Nachricht.',
            'listen_intro_wrong' => ['Nur das letzte Wort der Nachricht.', 'Die Seitenzahl des Lehrbuchs.', 'Die Schreibweise jedes unbekannten Wortes.'],
            'listen_note' => 'Ein Sprecher nennt Zeit, Ort und Handlung. Welche Notiz ist am besten?',
            'listen_note_correct' => 'Eine kurze Notiz mit Zeit, Ort und Handlung.',
            'listen_note_wrong' => ['Eine Übersetzung jedes Geräuschs.', 'Eine Vermutung nur anhand des Titels.', 'Eine Liste unverbundener Wörter.'],
            'listen_main' => 'Welche Strategie bestätigt die Hauptidee einer Audionachricht am besten?',
            'listen_main_correct' => 'Auf wiederholte Schlüsselwörter und die letzte Bitte achten.',
            'listen_main_wrong' => ['Die längste Antwort wählen.', 'Nur auf Hintergrundgeräusche achten.', 'Nach dem ersten Satz aufhören zuzuhören.'],
            'listen_compare' => 'Der Lernende hört zwei Optionen in einem praktischen Gespräch. Was sollte verglichen werden?',
            'listen_compare_correct' => 'Optionen, Preise, Zeiten oder Bedingungen der Sprecher.',
            'listen_compare_wrong' => ['Die Farbe des Audioplayers.', 'Die Anzahl der Buchstaben in Namen.', 'Nur das erste gesprochene Wort.'],
            'listen_sequence' => 'Welche Reaktion zeigt, dass eine Anweisung verstanden wurde?',
            'listen_sequence_correct' => 'Der Lernende folgt der Reihenfolge der Anweisung.',
            'listen_sequence_wrong' => ['Der Lernende wechselt das Thema.', 'Der Lernende antwortet vor der Anweisung.', 'Der Lernende wiederholt einen unpassenden Satz.'],
            'speak_intro' => 'Was macht eine Selbstvorstellung in einer mündlichen Prüfung auf Niveau ' . $level . ' wirksam?',
            'speak_intro_correct' => 'Klare Grundinformationen mit verständlicher Aussprache.',
            'speak_intro_wrong' => ['Auswendig gelernte Wörter ohne Zusammenhang.', 'Nur einzelne Buchstaben sprechen.', 'Alle persönlichen Informationen vermeiden.'],
            'speak_clarify' => 'Der Lernende braucht Klärung. Was ist am besten?',
            'speak_clarify_correct' => 'Höflich eine einfache Rückfrage stellen.',
            'speak_clarify_wrong' => ['Bis zum Ende schweigen.', 'Zu einem anderen Thema wechseln.', 'Einen zufälligen auswendig gelernten Satz sagen.'],
            'speak_opinion' => 'Welche Antwort stützt eine persönliche Meinung am besten?',
            'speak_opinion_correct' => 'Eine klare Meinung mit einem passenden Grund.',
            'speak_opinion_wrong' => ['Ein Grund ohne Meinung.', 'Die Frage ohne Antwort wiederholen.', 'Nur Zahlen aufzählen.'],
            'speak_roleplay' => 'Was ist im Rollenspiel am stärksten?',
            'speak_roleplay_correct' => 'Auf den Partner reagieren und das Gespräch weiterführen.',
            'speak_roleplay_wrong' => ['Antworten lesen, ohne zuzuhören.', 'Immer nur mit einem Wort antworten.', 'Den Partner korrigieren statt zu antworten.'],
            'speak_error' => 'Was sollte ein Lernender bei einem kleinen Sprechfehler tun?',
            'speak_error_correct' => 'Sich kurz korrigieren oder mit klarem Sinn fortfahren.',
            'speak_error_wrong' => ['Die Prüfung sofort beenden.', 'Den Fehler absichtlich wiederholen.', 'Zu einem unpassenden Thema wechseln.'],
            'read_main' => 'Wie findet man die Hauptidee eines Lesetextes auf Niveau ' . $level . ' am besten?',
            'read_main_correct' => 'Titel, ersten Satz und wiederholte Schlüsselwörter nutzen.',
            'read_main_wrong' => ['Nur Satzzeichen lesen.', 'Die Antwort mit den meisten Wörtern wählen.', 'Überschriften und Wiederholungen ignorieren.'],
            'read_notice' => 'Eine Mitteilung enthält Daten und Bedingungen. Was sollte geprüft werden?',
            'read_notice_correct' => 'Datum, Anforderung, Ausnahme und nötige Handlung.',
            'read_notice_wrong' => ['Nur das Logo oben.', 'Nur bereits bekannte Wörter.', 'Den längsten Satz ohne Bedeutung.'],
            'read_infer' => 'Was verlangt eine Schlussfolgerungsfrage normalerweise?',
            'read_infer_correct' => 'Hinweise im Text nutzen, um die wahrscheinliche Bedeutung zu wählen.',
            'read_infer_wrong' => ['Eine nicht belegte Antwort wählen.', 'Alle Kommas zählen.', 'Die Sätze davor und danach ignorieren.'],
            'read_words' => 'Welche Strategie hilft bei unbekannten Wörtern?',
            'read_words_correct' => 'Kontext, Wortteile und Satzfunktion nutzen.',
            'read_words_wrong' => ['Beim ersten neuen Wort aufhören.', 'Alle neuen Wörter gleich verstehen.', 'Den ganzen Absatz überspringen.'],
            'read_evidence' => 'Was ist der beste Beleg für eine Leseantwort?',
            'read_evidence_correct' => 'Ein konkreter Satz oder Ausdruck aus dem Text.',
            'read_evidence_wrong' => ['Nur eine persönliche Vorliebe.', 'Eine Erinnerung aus einem anderen Kurs.', 'Die Position der Antwort.'],
            'write_plan' => 'Was sollte man in einer Schreibaufgabe auf Niveau ' . $level . ' zuerst planen?',
            'write_plan_correct' => 'Adressat, Zweck und zwei oder drei Kernpunkte.',
            'write_plan_wrong' => ['Die Schriftfarbe vor dem Sinn.', 'Eine Liste unverbundener Wörter.', 'Die Endpunktzahl vor dem Schreiben.'],
            'write_sentence' => 'Was ist bei einer kurzen Nachricht am wichtigsten?',
            'write_sentence_correct' => 'Klare Bedeutung mit richtiger Grundwortstellung.',
            'write_sentence_wrong' => ['Der längste mögliche Satz.', 'Das Hauptverb immer weglassen.', 'Nur Abkürzungen schreiben.'],
            'write_paragraph' => 'Was macht einen Absatz kohärent?',
            'write_paragraph_correct' => 'Eine Hauptidee mit verbundenen Stützssätzen.',
            'write_paragraph_wrong' => ['Jeder Satz hat ein anderes Thema.', 'Kein Zusammenhang zwischen Beispiel und Schluss.', 'Nur kopierte Phrasen aus der Aufgabe.'],
            'write_check' => 'Welcher Überarbeitungsschritt gehört vor die Abgabe?',
            'write_check_correct' => 'Aufgabenerfüllung, Grammatik, Rechtschreibung und Zeichensetzung prüfen.',
            'write_check_wrong' => ['Alle Beispiele löschen.', 'Unpassende schwierige Wörter hinzufügen.', 'Die Antwort auf eine andere Aufgabe ändern.'],
            'write_compare' => 'Was tut man, wenn zwei Optionen verglichen werden sollen?',
            'write_compare_correct' => 'Beide Optionen nennen und klar vergleichen.',
            'write_compare_wrong' => ['Nur eine Option nennen.', 'Nur eine Begrüßung schreiben.', 'Die Frage abschreiben, ohne zu antworten.'],
        ],
        'fr' => [
            'listen_intro' => 'Dans une tâche de compréhension orale de niveau ' . $level . ', que faut-il identifier d’abord dans une courte salutation ?',
            'listen_intro_correct' => 'Le locuteur, la salutation et le but du message.',
            'listen_intro_wrong' => ['Seulement le dernier mot.', 'Le numéro de page du manuel.', 'L’orthographe de chaque mot inconnu.'],
            'listen_note' => 'Un locuteur donne une heure, un lieu et une action. Quelle note est la meilleure ?',
            'listen_note_correct' => 'Une note courte avec l’heure, le lieu et l’action.',
            'listen_note_wrong' => ['Une traduction de chaque son.', 'Une supposition basée seulement sur le titre.', 'Une liste de vocabulaire sans lien.'],
            'listen_main' => 'Quelle stratégie confirme le mieux l’idée principale d’un message audio ?',
            'listen_main_correct' => 'Écouter les mots-clés répétés et la demande finale.',
            'listen_main_wrong' => ['Choisir la réponse la plus longue.', 'Se concentrer seulement sur le bruit.', 'Arrêter après la première phrase.'],
            'listen_compare' => 'L’apprenant entend deux choix dans un échange pratique. Que doit-il comparer ?',
            'listen_compare_correct' => 'Les options, prix, horaires ou conditions mentionnés.',
            'listen_compare_wrong' => ['La couleur du lecteur audio.', 'Le nombre de lettres dans les noms.', 'Seulement le premier mot.'],
            'listen_sequence' => 'Quelle réponse montre que les consignes sont comprises ?',
            'listen_sequence_correct' => 'L’apprenant suit les étapes dans l’ordre donné.',
            'listen_sequence_wrong' => ['Il change le sujet.', 'Il répond avant les consignes.', 'Il répète une phrase sans rapport.'],
            'speak_intro' => 'Dans un oral de niveau ' . $level . ', qu’est-ce qui rend une présentation personnelle efficace ?',
            'speak_intro_correct' => 'Des informations de base claires avec une prononciation compréhensible.',
            'speak_intro_wrong' => ['Des mots mémorisés sans lien.', 'Parler seulement en lettres isolées.', 'Éviter toute information personnelle.'],
            'speak_clarify' => 'L’apprenant a besoin d’une clarification. Que doit-il faire ?',
            'speak_clarify_correct' => 'Poser poliment une question simple de clarification.',
            'speak_clarify_wrong' => ['Rester silencieux jusqu’à la fin.', 'Changer de sujet.', 'Répondre avec une phrase mémorisée au hasard.'],
            'speak_opinion' => 'Quelle réponse orale soutient le mieux une opinion personnelle ?',
            'speak_opinion_correct' => 'Une opinion claire suivie d’une raison pertinente.',
            'speak_opinion_wrong' => ['Une raison sans opinion.', 'La question copiée sans réponse.', 'Une liste de nombres seulement.'],
            'speak_roleplay' => 'Quel comportement est le plus fort dans un jeu de rôle ?',
            'speak_roleplay_correct' => 'Répondre au partenaire et maintenir l’échange.',
            'speak_roleplay_wrong' => ['Lire sans écouter.', 'Répondre toujours par un seul mot.', 'Corriger le partenaire au lieu de répondre.'],
            'speak_error' => 'Que faire après une petite erreur à l’oral ?',
            'speak_error_correct' => 'Se corriger brièvement ou continuer avec un sens clair.',
            'speak_error_wrong' => ['Terminer immédiatement l’examen.', 'Répéter l’erreur exprès.', 'Changer vers un sujet sans lien.'],
            'read_main' => 'Comment trouver au mieux l’idée principale d’un texte de niveau ' . $level . ' ?',
            'read_main_correct' => 'Utiliser le titre, la première phrase et les mots-clés répétés.',
            'read_main_wrong' => ['Lire seulement la ponctuation.', 'Choisir la réponse la plus longue.', 'Ignorer les titres et répétitions.'],
            'read_notice' => 'Un avis contient des dates et des conditions. Que faut-il vérifier ?',
            'read_notice_correct' => 'La date, l’exigence, l’exception et l’action nécessaire.',
            'read_notice_wrong' => ['Seulement le logo.', 'Seulement les mots déjà connus.', 'La phrase la plus longue sans le sens.'],
            'read_infer' => 'Que demande généralement une question d’inférence ?',
            'read_infer_correct' => 'Utiliser les indices du texte pour choisir le sens probable.',
            'read_infer_wrong' => ['Choisir une réponse sans preuve.', 'Compter toutes les virgules.', 'Ignorer les phrases voisines.'],
            'read_words' => 'Quelle stratégie aide avec les mots inconnus ?',
            'read_words_correct' => 'Utiliser le contexte, les parties du mot et le but de la phrase.',
            'read_words_wrong' => ['S’arrêter au premier mot nouveau.', 'Penser que tous les mots nouveaux ont le même sens.', 'Sauter tout le paragraphe.'],
            'read_evidence' => 'Quelle est la meilleure preuve pour une réponse de lecture ?',
            'read_evidence_correct' => 'Une phrase ou expression précise du texte.',
            'read_evidence_wrong' => ['Une préférence personnelle seulement.', 'Un souvenir d’un autre cours.', 'La position de la réponse.'],
            'write_plan' => 'Dans une tâche écrite de niveau ' . $level . ', que faut-il planifier d’abord ?',
            'write_plan_correct' => 'Le destinataire, le but et deux ou trois points clés.',
            'write_plan_wrong' => ['La couleur de police avant le sens.', 'Une liste de mots sans lien.', 'La note finale avant d’écrire.'],
            'write_sentence' => 'Quelle qualité compte le plus dans un court message ?',
            'write_sentence_correct' => 'Un sens clair avec un ordre de mots de base correct.',
            'write_sentence_wrong' => ['La phrase la plus longue possible.', 'Omettre toujours le verbe principal.', 'Écrire seulement des abréviations.'],
            'write_paragraph' => 'Qu’est-ce qui rend un paragraphe cohérent ?',
            'write_paragraph_correct' => 'Une idée principale avec des phrases de soutien liées.',
            'write_paragraph_wrong' => ['Chaque phrase a un sujet différent.', 'Aucun lien entre exemples et conclusion.', 'Seulement des phrases copiées.'],
            'write_check' => 'Quelle révision faire avant l’envoi ?',
            'write_check_correct' => 'Vérifier la tâche, la grammaire, l’orthographe et la ponctuation.',
            'write_check_wrong' => ['Supprimer tous les exemples.', 'Ajouter des mots difficiles sans lien.', 'Changer vers une autre tâche.'],
            'write_compare' => 'Que faire si la tâche demande de comparer deux options ?',
            'write_compare_correct' => 'Présenter les deux options et faire une comparaison claire.',
            'write_compare_wrong' => ['Mentionner une seule option.', 'Écrire seulement une salutation.', 'Copier la question sans répondre.'],
        ],
        'es' => [
            'listen_intro' => 'En una tarea de comprensión auditiva de nivel ' . $level . ', ¿qué debe identificar primero el estudiante en un saludo breve?',
            'listen_intro_correct' => 'El hablante, el saludo y el propósito del mensaje.',
            'listen_intro_wrong' => ['Solo la última palabra.', 'El número de página del libro.', 'La ortografía de cada palabra desconocida.'],
            'listen_note' => 'Un hablante da una hora, un lugar y una acción. ¿Cuál es la mejor nota?',
            'listen_note_correct' => 'Una nota breve con la hora, el lugar y la acción.',
            'listen_note_wrong' => ['Una traducción de cada sonido.', 'Una suposición solo por el título.', 'Una lista de vocabulario sin relación.'],
            'listen_main' => '¿Qué estrategia confirma mejor la idea principal de un audio?',
            'listen_main_correct' => 'Escuchar palabras clave repetidas y la petición final.',
            'listen_main_wrong' => ['Elegir la respuesta más larga.', 'Escuchar solo el ruido de fondo.', 'Dejar de escuchar después de la primera frase.'],
            'listen_compare' => 'El estudiante oye dos opciones en un intercambio práctico. ¿Qué debe comparar?',
            'listen_compare_correct' => 'Las opciones, precios, horarios o condiciones mencionados.',
            'listen_compare_wrong' => ['El color del reproductor.', 'El número de letras en los nombres.', 'Solo la primera palabra.'],
            'listen_sequence' => '¿Qué respuesta muestra comprensión de instrucciones?',
            'listen_sequence_correct' => 'El estudiante sigue la secuencia en el orden indicado.',
            'listen_sequence_wrong' => ['Cambia el tema de la tarea.', 'Responde antes de oír la instrucción.', 'Repite una frase sin relación.'],
            'speak_intro' => 'En un examen oral de nivel ' . $level . ', ¿qué hace efectiva una presentación personal?',
            'speak_intro_correct' => 'Información básica clara con pronunciación comprensible.',
            'speak_intro_wrong' => ['Palabras memorizadas sin conexión.', 'Hablar solo con letras aisladas.', 'Evitar toda información personal.'],
            'speak_clarify' => 'El estudiante necesita aclaración. ¿Qué acción es mejor?',
            'speak_clarify_correct' => 'Hacer una pregunta sencilla de aclaración con cortesía.',
            'speak_clarify_wrong' => ['Quedarse callado hasta el final.', 'Cambiar a un tema no relacionado.', 'Responder con una frase memorizada al azar.'],
            'speak_opinion' => '¿Qué respuesta oral apoya mejor una opinión personal?',
            'speak_opinion_correct' => 'Una opinión clara seguida de una razón relevante.',
            'speak_opinion_wrong' => ['Una razón sin opinión.', 'La pregunta copiada sin respuesta.', 'Solo una lista de números.'],
            'speak_roleplay' => '¿Cuál es el mejor comportamiento en un juego de roles?',
            'speak_roleplay_correct' => 'Responder al compañero y mantener el intercambio.',
            'speak_roleplay_wrong' => ['Leer sin escuchar.', 'Responder siempre con una sola palabra.', 'Corregir al compañero en vez de responder.'],
            'speak_error' => '¿Qué debe hacer el estudiante si comete un pequeño error al hablar?',
            'speak_error_correct' => 'Corregirse brevemente o continuar con significado claro.',
            'speak_error_wrong' => ['Terminar el examen de inmediato.', 'Repetir el error a propósito.', 'Cambiar a un tema sin relación.'],
            'read_main' => '¿Cuál es la mejor manera de encontrar la idea principal de un texto de nivel ' . $level . '?',
            'read_main_correct' => 'Usar el título, la primera oración y palabras clave repetidas.',
            'read_main_wrong' => ['Leer solo la puntuación.', 'Elegir la respuesta con más palabras.', 'Ignorar títulos y repeticiones.'],
            'read_notice' => 'Un aviso incluye fechas y condiciones. ¿Qué detalle debe revisar el estudiante?',
            'read_notice_correct' => 'La fecha, el requisito, la excepción y la acción necesaria.',
            'read_notice_wrong' => ['Solo el logotipo superior.', 'Solo palabras ya conocidas.', 'La oración más larga sin mirar el sentido.'],
            'read_infer' => '¿Qué requiere normalmente una pregunta de inferencia?',
            'read_infer_correct' => 'Usar pistas del texto para elegir el significado probable.',
            'read_infer_wrong' => ['Elegir una respuesta sin apoyo del texto.', 'Contar todas las comas.', 'Ignorar las oraciones cercanas.'],
            'read_words' => '¿Qué estrategia ayuda con palabras desconocidas?',
            'read_words_correct' => 'Usar el contexto, partes de la palabra y el propósito de la oración.',
            'read_words_wrong' => ['Detenerse en la primera palabra nueva.', 'Pensar que todas las palabras nuevas significan lo mismo.', 'Saltar todo el párrafo.'],
            'read_evidence' => '¿Cuál es la mejor evidencia para una respuesta de lectura?',
            'read_evidence_correct' => 'Una oración o frase específica del texto.',
            'read_evidence_wrong' => ['Solo una preferencia personal.', 'Un recuerdo de otro curso.', 'La posición de la respuesta.'],
            'write_plan' => 'En una tarea escrita de nivel ' . $level . ', ¿qué debe planificar primero el estudiante?',
            'write_plan_correct' => 'El destinatario, el propósito y dos o tres puntos clave.',
            'write_plan_wrong' => ['El color de la fuente antes del significado.', 'Una lista de palabras sin relación.', 'La nota final antes de escribir.'],
            'write_sentence' => '¿Qué calidad importa más en un mensaje breve?',
            'write_sentence_correct' => 'Significado claro con orden básico correcto de palabras.',
            'write_sentence_wrong' => ['La oración más larga posible.', 'Omitir siempre el verbo principal.', 'Escribir solo abreviaturas.'],
            'write_paragraph' => '¿Qué hace coherente un párrafo?',
            'write_paragraph_correct' => 'Una idea principal con oraciones de apoyo conectadas.',
            'write_paragraph_wrong' => ['Cada oración trata un tema diferente.', 'No hay conexión entre ejemplos y conclusión.', 'Solo frases copiadas de la consigna.'],
            'write_check' => '¿Qué revisión debe hacerse antes de entregar?',
            'write_check_correct' => 'Revisar cumplimiento de la tarea, gramática, ortografía y puntuación.',
            'write_check_wrong' => ['Eliminar todos los ejemplos.', 'Añadir palabras avanzadas sin relación.', 'Cambiar la respuesta a otra tarea.'],
            'write_compare' => '¿Qué debe hacer el estudiante si debe comparar dos opciones?',
            'write_compare_correct' => 'Presentar ambas opciones y hacer una comparación clara.',
            'write_compare_wrong' => ['Mencionar solo una opción.', 'Escribir un saludo y detenerse.', 'Copiar la pregunta sin responder.'],
        ],
    ];

    return $packs[$languagecode] ?? $packs['es'];
}
