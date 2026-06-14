# FLW Context Summary

This file records the current FLW direction gathered from the supplied ChatGPT logs.

## Core Identity

FLW means Foreign Language World. It is a multi-language learning system, not only an English course.

The English branch should use CEFR as the main organizing structure:

- A1 English Foundation
- A2 Everyday English
- B1 Practical English
- B2 Independent English
- C1 Advanced English
- C2 English Mastery

Other languages can reuse the same FLW architecture with CEFR or equivalent level frameworks.

## Main Product Idea

FLW should become a learning navigation system, not just a set of Moodle courses or PDF lessons.

It should help each learner answer:

- Where am I?
- What am I missing?
- What should I learn next?
- Am I progressing?

## Required System Flow

1. Placement test
2. CEFR or language-level result
3. Knowledge gap analysis
4. Personalized learning path
5. Unit study and practice
6. Progress dashboard
7. Unit/level advancement
8. Optional exam branches

## Curriculum Methodology

Use a hybrid model:

- Cambridge Empower-style CEFR structure, unit outcomes, skill balance, and assessment.
- English File-style listening, speaking, pronunciation, and communicative practice.
- Cambridge exam-style progression for reliable level tests.

Do not copy copyrighted coursebooks. Use them only as methodological inspiration.

## Coursebook Unit Template

A professional FLW unit should include:

- Unit cover
- CEFR target
- Can-do statements
- Knowledge point list
- Learning map
- Vocabulary lesson
- Grammar lesson
- Reading lesson
- Listening lesson
- Speaking lesson
- Writing lesson
- Pronunciation or functional English
- Review quiz
- Progress check
- Next learning path
- Achievement or completion marker

For A1-A2, one unit should represent about 8-12 hours of study.

## First Model Unit

English A1 Unit 01: Greetings and Introductions

Can-do target:

Learners can greet people politely, introduce themselves, and ask and answer basic personal questions.

Initial knowledge points:

- `EN-A1-U01-VOC-001` Greetings
- `EN-A1-U01-VOC-002` Personal information
- `EN-A1-U01-GRA-001` Be verb
- `EN-A1-U01-GRA-002` Subject pronouns
- `EN-A1-U01-REA-001` Personal profiles
- `EN-A1-U01-LIS-001` Basic introductions
- `EN-A1-U01-SPK-001` Self introduction
- `EN-A1-U01-WRI-001` Personal profile writing
- `EN-A1-U01-PRO-001` Greeting intonation
- `EN-A1-U01-FUN-001` Asking basic personal questions

## Content Policy

High-quality external media from BBC Learning English, British Council LearnEnglish, and Cambridge English can be linked or embedded where allowed. FLW should not download, redistribute, or package copyrighted video/audio from those sources.

## Immediate Technical Direction

The Moodle workspace already contains:

- `theme_flwacademy`
- `mod_flwvrroom`

The next missing foundation is:

- `local_flwkp`: central FLW knowledge point and curriculum data plugin

This plugin should become the shared source of truth for curriculum mapping, placement, dashboards, and recommendations.
