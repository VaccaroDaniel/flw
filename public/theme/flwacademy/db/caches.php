<?php
// Cache definitions for theme_flwacademy.

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'course_progress' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 120,
    ],
    'category_progress' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 120,
    ],
    'language_rank' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 300,
    ],
    'language_streak' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 120,
    ],
    'user_courses' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 180,
    ],
    'learning_map' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 120,
    ],
    'dashboard' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => false,
        'simpledata' => true,
        'ttl' => 240,
    ],
];
