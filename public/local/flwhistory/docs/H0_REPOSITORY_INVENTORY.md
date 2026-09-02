# H0 Repository Inventory

## Environment

- Workspace root: `C:\Users\com\Documents\Estimation Speaking`
- Workspace git state: not a git repository.
- Live Moodle root: `D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public`
- Live Moodle git state: dirty worktree with unrelated existing theme/config changes. H0 did not modify them.
- Moodle release: 5.1.5 (Build: 20260608)
- Moodle branch: 501
- Moodle version: 2025100605.00
- PHP runtime: PHP 8.2.4 CLI, bundled with the Moodle Windows Installer package.

## Program 1 Contract Artifacts

Program 1 was declared complete by the user. The available downstream contract artifacts were found under:

- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\P1_CONTENT_DEPLOYMENT_CONTRACT_V1.md`
- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\PROGRAM2_HISTORY_HANDOFF.md`
- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\PROGRAM3_CUPKP_HANDOFF.md`
- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\S9_REPORT.md`
- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\S9_MANIFEST.json`
- `C:\Users\com\Documents\Estimation Speaking\adventure_scorm_gui\docs\moodle-export-v2\SMART_COURSE_EDITOR_MOODLE_EXPORT_V8_FINAL_REPORT.md`

The Program 1 content deployment contract exposes stable lookup responsibilities for:

- FLW world/stage from Moodle course id.
- FLW unit from Moodle section id.
- FLW unit from Moodle course module id.
- FLW activity from Moodle course module id and SCORM SCO identifier.
- Parent substantial activity for micro-activities.
- Current and historical unit deployments.
- Content revision and deployment freshness.

H0 records the earlier S9 conditional note about expected scope count drift as release-accepted, because the user stated Program 1 is finished and out of scope for further work.

## FLW Components Found

| Component | Path | Version | Release | Requires |
| --- | --- | ---: | --- | ---: |
| `local_flwcupkp` | `local\flwcupkp` | 2026081416 | 0.1.0-alpha | 2022112800 |
| `local_flwaiassessment` | `local\flwaiassessment` | 2026061400 | 0.1.0 alpha | 2025100600 |
| `local_flwexam` | `local\flwexam` | 2026081400 | 0.1.0 alpha | 2025100600 |
| `local_flwkp` | `local\flwkp` | 2026061200 | 0.1.0 alpha | 2025100600 |
| `local_flwmedia` | `local\flwmedia` | 2026071002 | 0.1.0 alpha | 2024100700 |
| `local_flwplacement` | `local\flwplacement` | 2026072101 | 0.1.0 alpha | 2025100600 |
| `local_flwtextbookimport` | `local\flwtextbookimport` | 2026081202 | 0.5.0-alpha | 2022112800 |
| `mod_flwaispeaking` | `mod\flwaispeaking` | 2026061501 | 0.2.0-alpha | 2025100600 |
| `mod_flwvrroom` | `mod\flwvrroom` | 2026081501 | 0.1.0-alpha | 2022112800 |
| `theme_flwacademy` | `theme\flwacademy` | 2026081402 | 1.3.0 - FLW Clean Theme v3 | 2025100600 |
| `theme_flwclean` | `theme\flwclean` | 2026070100 | FLW Clean Mode v1 | 2025041400 |

`local_flwhistory` is not installed yet. That is expected before Program 2 H1.

## Existing C-UP-KP Implementation

`local_flwcupkp` already owns C-UP-KP curriculum, mappings, evidence, learner states, learner evaluation, recommendations, calibration, teacher verification, Moodle competency sync, and health/repair workflows.

Important files:

- `local\flwcupkp\db\install.xml`
- `local\flwcupkp\db\events.php`
- `local\flwcupkp\db\tasks.php`
- `local\flwcupkp\db\services.php`
- `local\flwcupkp\classes\observer.php`
- `local\flwcupkp\classes\local\quiz_evidence_adapter.php`
- `local\flwcupkp\classes\local\specialized_evidence_adapter.php`
- `local\flwcupkp\classes\local\flwvrroom_evidence_adapter.php`
- `local\flwcupkp\classes\local\mastery_engine.php`
- `local\flwcupkp\classes\local\rollup_engine.php`
- `local\flwcupkp\classes\local\recommendation_engine.php`
- `local\flwcupkp\classes\local\learner_evaluation.php`
- `local\flwcupkp\classes\local\moodle_competency_writer.php`

Program 2 must not duplicate these responsibilities. Its role is normalized, source-grounded learning and grade history. Program 3 can later consume Program 2 history as an upstream evidence source.

## Existing Moodle Event Sources

Verified Moodle event classes include:

- Quiz: `attempt_started`, `attempt_submitted`, `attempt_graded`, `attempt_regraded`, `attempt_manual_grading_completed`, `attempt_deleted`, `attempt_reopened`, and related quiz slot/report events.
- Assignment: `assessable_submitted`, `submission_created`, `submission_updated`, `submission_graded`, `workflow_state_updated`, `extension_granted`, `submission_removed`.
- SCORM: `sco_launched`, `status_submitted`, `scoreraw_submitted`, `cmielement_submitted`, `attempt_deleted`, `tracks_viewed`.
- Completion and grades: `course_module_completion_updated`, `course_completion_updated`, `user_graded`, `grade_item_created`, `grade_item_updated`, `grade_item_deleted`, `grade_deleted`, `grade_report_viewed`.
- H5P: `mod_h5pactivity\event\statement_received` exists in this Moodle tree.
- VR: `mod_flwvrroom\event\attempt_submitted` exists and is triggered by `mod\flwvrroom\classes\external\submit_attempt.php`.

## Existing FLW Observers

`local_flwcupkp` observes:

- `\core\event\course_module_completion_updated`
- `\mod_quiz\event\attempt_submitted`
- `\mod_quiz\event\attempt_graded`
- `\mod_assign\event\assessable_submitted`
- `\mod_assign\event\submission_graded`
- `\mod_h5pactivity\event\statement_received`
- `\mod_scorm\event\status_submitted`
- `\mod_scorm\event\scoreraw_submitted`
- `\mod_flwvrroom\event\attempt_submitted`

`local_flwexam` observes:

- `\mod_quiz\event\attempt_submitted`
- `\mod_quiz\event\attempt_graded`
- `\mod_quiz\event\attempt_manual_grading_completed`

`local_flwplacement` observes:

- `\mod_quiz\event\attempt_submitted`
- `\mod_quiz\event\attempt_graded`
- `\mod_quiz\event\attempt_manual_grading_completed`

## Existing Tasks and Services

`local_flwcupkp` scheduled tasks:

- `local_flwcupkp\task\recalculate_states`
- `local_flwcupkp\task\sync_competencies`
- `local_flwcupkp\task\calibration_recalculation`

`local_flwaiassessment` scheduled task:

- `local_flwaiassessment\task\process_pending`

Known web service functions:

- `local_flwcupkp`: framework CRUD, competency/use point/KP CRUD, mapping CRUD, import validation/import, evidence recording, VR attempt recording, learner states, learning path, recommendations, learner evaluation, coverage/orphan/gap reports, CEFR alignment, Moodle competency sync, sync status.
- `local_flwexam`: exam history/result/certificate/result submission APIs.
- `local_flwmedia`: media item retrieval, progress saving, speaking/reading/dictation attempt saving.
- `mod_flwvrroom`: attempt submission, speaking score, room editor save, role waiter.

## Data Source Tables

Moodle core and module tables relevant to Program 2:

- Quiz: `quiz`, `quiz_slots`, `quiz_grade_items`, `quiz_attempts`, `quiz_grades`.
- Question engine: `question_usages`, `question_attempts`, `question_attempt_steps`, `question_attempt_step_data`, plus question bank tables.
- Assignment: `assign`, `assign_submission`, `assign_grades`.
- SCORM: `scorm`, `scorm_scoes`, `scorm_attempt`, `scorm_element`, `scorm_scoes_value`.
- Completion: `course_modules_completion`, `course_completions`.
- Gradebook: `grade_items`, `grade_grades`, `grade_grades_history`, `grade_items_history`.

FLW tables relevant as upstream sources:

- `local_flwexam_*`
- `local_flwmedia_progress`
- `local_flwmedia_attempts`
- `local_flwplacement`
- `local_flwplacement_profile`
- `local_flwai_results`
- `flwaispeaking_submissions`
- `flwvrroom_attempts`
- `flwcupkp_evidence`
- `flwcupkp_state`
- `flwcupkp_eval_snapshot`
- `flwcupkp_selfeval`
- `flwcupkp_diagnostic`
- `flwcupkp_audit`

## Visual Baseline

The current user-facing surfaces to preserve are:

- Moodle course page with FLW Academy theme shell and C-UP-KP course cards.
- `local/flwcupkp/setup.php`
- `local/flwcupkp/student.php` and `student_u038.php`
- `local/flwcupkp/teacher.php` and `teacher_u038.php`
- `local/flwcupkp/evaluation.php`
- `local/flwcupkp/evidence_sync.php`
- `local/flwcupkp/performance.php`
- Existing `local_flwexam`, `local_flwplacement`, `local_flwmedia`, `mod_flwaispeaking`, and `mod_flwvrroom` pages.

H0 freezes these as the current visual baseline. Program 2 H1 should add backend schema/service contracts only; learner dashboard UX changes belong to later gates and must preserve the theme shell.

