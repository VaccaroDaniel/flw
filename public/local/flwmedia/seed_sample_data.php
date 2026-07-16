<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

require_login();
require_admin();
require_capability('local/flwmedia:seedtestdata', context_system::instance());

$courseid = optional_param('courseid', SITEID, PARAM_INT);
$language = optional_param('language', 'en', PARAM_ALPHANUMEXT);
$language = \local_flwmedia\manager::normalize_language($language);
$unitcode = optional_param('unitcode', 'REW2_U001', PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$url = new moodle_url('/local/flwmedia/seed_sample_data.php', [
    'courseid' => $courseid,
    'language' => $language,
    'unitcode' => $unitcode,
]);

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Seed FLW media sample data');
$PAGE->set_heading('Seed FLW media sample data');

$now = time();
$video = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
$poster = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.jpg';
$audio = 'https://upload.wikimedia.org/wikipedia/commons/c/c8/Example.ogg';

$samples = [
    ['watch', 'unit_watch', 'Unit Watch Sample 1', $video, $poster, '', 'This is a sample watch video.', '', 5, 'watch,sample,unit'],
    ['watch', 'story', 'Unit Watch Sample 2', $video, $poster, '', 'The flower is moving in the wind.', '', 5, 'watch,story,sample'],
    ['watch', 'review', 'Unit Watch Sample 3', $video, $poster, '', 'I can watch FLW practice video.', '', 5, 'watch,review,sample'],
    ['listen', 'vocabulary', 'Listen Sample 1', $audio, '', 'Hello. My name is Daniel.', 'Hello. My name is Daniel.', '', 2, 'listen,vocabulary,sample'],
    ['listen', 'model_dialogue', 'Listen Sample 2', $audio, '', 'I listen and repeat.', 'I listen and repeat.', '', 2, 'listen,dialogue,sample'],
    ['listen', 'review', 'Listen Sample 3', $audio, '', 'This is my first FLW practice.', 'This is my first FLW practice.', '', 2, 'listen,review,sample'],
    ['speak', 'model_sentence', 'Speak Sample 1', $audio, '', '', 'Hello. My name is Daniel.', '', 2, 'speak,model_sentence'],
    ['speak', 'pronunciation', 'Speak Sample 2', $audio, '', '', 'I listen and repeat.', '', 2, 'speak,pronunciation'],
    ['speak', 'model_dialogue', 'Speak Sample 3', $audio, '', '', 'This is my first FLW practice.', '', 2, 'speak,dialogue'],
    ['read', 'reading', 'Read Sample 1', '', '', '', '', 'This is my first FLW practice. I can watch, listen, speak, read, and dictate.', 0, 'read,practice'],
    ['read', 'story', 'Read Sample 2', '', '', '', '', 'The flower is moving in the wind. I can describe what I see.', 0, 'read,story'],
    ['read', 'project', 'Read Sample 3', '', '', '', '', 'Make a short project sentence about your first FLW practice.', 0, 'read,project'],
    ['dictate', 'dictation', 'Dictate Sample 1', $audio, '', '', 'Hello. My name is Daniel.', '', 2, 'dictate,audio,sample'],
    ['dictate', 'dictation', 'Dictate Sample 2', $audio, '', '', 'I listen and repeat.', '', 2, 'dictate,audio,sample'],
    ['dictate', 'dictation', 'Dictate Sample 3', $audio, '', '', 'I can watch, listen, speak, read, and dictate.', '', 2, 'dictate,audio,sample'],
];

$created = 0;
$updated = 0;
$sortorder = 10;

foreach ($samples as $sample) {
    [$mode, $category, $title, $mediaurl, $posterurl, $transcript, $expectedtext, $readtext, $duration, $kptags] = $sample;
    $record = (object)[
        'courseid' => $course->id,
        'unitcode' => $unitcode,
        'lessoncode' => '',
        'mode' => $mode,
        'category' => $category,
        'title' => $title,
        'description' => 'Development sample for FLW media practice.',
        'mediaurl' => $mediaurl,
        'posterurl' => $posterurl,
        'subtitleurl' => '',
        'transcript' => $transcript,
        'readtext' => $readtext,
        'expectedtext' => $expectedtext,
        'duration' => $duration,
        'lang' => $language,
        'cefr' => 'A1',
        'kptags' => $kptags,
        'sortorder' => $sortorder,
        'visible' => 1,
        'timemodified' => $now,
    ];

    $existing = $DB->get_record('local_flwmedia_items', [
        'courseid' => $course->id,
        'unitcode' => $unitcode,
        'title' => $title,
    ]);

    if ($existing) {
        $record->id = $existing->id;
        $record->timecreated = $existing->timecreated;
        $DB->update_record('local_flwmedia_items', $record);
        $updated++;
    } else {
        $record->timecreated = $now;
        $DB->insert_record('local_flwmedia_items', $record);
        $created++;
    }

    $sortorder += 10;
}

echo $OUTPUT->header();
echo $OUTPUT->heading('FLW media sample data seeded');
echo html_writer::tag('p', 'Language: ' . s($language));
echo html_writer::tag('p', 'Legacy course container: ' . s($course->fullname) . ' (' . (int)$course->id . ')');
echo html_writer::tag('p', 'Unit code: ' . s($unitcode));
echo html_writer::tag('p', 'Created: ' . $created . ', updated: ' . $updated . '.');
echo html_writer::tag('p', 'Development/test only. Replace sample media URLs with secured FLW media server URLs for production.');
echo $OUTPUT->footer();
