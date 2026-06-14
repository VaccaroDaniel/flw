# FLW Knowledge Points

Plugin component: `local_flwkp`  
Version: `0.1.0-alpha`

This plugin is the first Moodle-side "brain" for Foreign Language World (FLW).
It stores the curriculum layer that should sit underneath coursebooks, PDFs,
question banks, VR activities, placement tests, dashboards, and next-unit
recommendations.

## Design Decisions From The FLW Chat Logs

- FLW is a multi-language learning framework, not only an English course.
- English should use CEFR as the main spine: A1, A2, B1, B2, C1, C2.
- IELTS, TOEFL, and Cambridge should become exam-preparation branches, not the main curriculum spine.
- Course materials should be based on a hybrid model:
  - Cambridge Empower-style CEFR structure and assessment discipline.
  - English File-style listening, speaking, pronunciation, and communicative flow.
  - Cambridge-style level and exam-aligned assessment logic.
- The system must answer three learner questions:
  - Where am I in the level map?
  - What should I do next?
  - Am I progressing?
- Every activity, question, resource, PDF lesson, VR room, placement item, and quiz should map to one or more FLW knowledge points.

## Three-Layer FLW Curriculum Model

1. Levels: A1, A2, B1, B2, C1, C2 or language-specific equivalents.
2. Domains: Vocabulary, Grammar, Reading, Listening, Speaking, Writing, Pronunciation, Functional English, Study Skills, Exam Skills.
3. Knowledge points: atomic learning targets such as `EN-A1-U01-GRA-001 Be verb`.

## MVP Seed Data

The installer currently seeds:

- Language: English
- Levels: A1-C2
- Domains: VOC, GRA, REA, LIS, SPK, WRI, PRO, FUN, STU, EXA
- Unit: `EN-A1-U01 Greetings and Introductions`
- Initial A1 Unit 01 knowledge points:
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

## Moodle Integration Path

1. Install `local_flwkp`.
2. Update `mod_flwvrroom` and future FLW activities to validate their knowledge point codes against `local_flwkp_points`.
3. Map quiz questions, assignments, pages, books, H5P activities, and VR activities into `local_flwkp_mappings`.
4. Build learner progress calculations from mapped attempt grades and mastery thresholds.
5. Use weak knowledge points to generate next-unit and booster recommendations.

## Next Development Steps

1. Add an admin UI for importing/editing languages, levels, units, domains, and knowledge points.
2. Add CSV import/export for the master curriculum database.
3. Add A1 Unit 02-12 seed data from the FLW curriculum blueprint.
4. Add progress aggregation tables or services.
5. Add placement test mapping and recommendation rules.
