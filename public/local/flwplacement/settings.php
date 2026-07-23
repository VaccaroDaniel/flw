<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $DB;

    $settings = new admin_settingpage('local_flwplacement', get_string('pluginname', 'local_flwplacement'));
    $ADMIN->add('localplugins', $settings);

    $languages = [
        'en' => 'English',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'de' => 'German',
        'ja' => 'Japanese',
        'fr' => 'French',
        'es' => 'Spanish',
    ];

    $quizoptions = [0 => get_string('builtadaptiveplacement', 'local_flwplacement')];
    if ($DB->get_manager()->table_exists('quiz')) {
        $sql = "SELECT q.id, q.name, c.fullname AS coursename, cm.id AS cmid
                  FROM {quiz} q
                  JOIN {course} c ON c.id = q.course
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm
                    ON cm.instance = q.id
                   AND cm.module = m.id
                   AND cm.course = q.course
                 WHERE cm.deletioninprogress = 0
              ORDER BY c.fullname ASC, q.name ASC";
        foreach ($DB->get_records_sql($sql, ['modname' => 'quiz']) as $quiz) {
            $quizoptions[(int)$quiz->id] =
                clean_param($quiz->coursename, PARAM_TEXT) . ' / ' .
                clean_param($quiz->name, PARAM_TEXT) . ' (#' . (int)$quiz->cmid . ')';
        }
    }

    foreach ($languages as $code => $label) {
        $settings->add(new admin_setting_configselect(
            'local_flwplacement/quizid_' . $code,
            get_string('quizidforlanguage', 'local_flwplacement', $label),
            get_string('quizid_help', 'local_flwplacement'),
            0,
            $quizoptions
        ));
    }
}
