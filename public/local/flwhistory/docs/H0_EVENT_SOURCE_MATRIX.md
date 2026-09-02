# H0 Event Source Matrix

## Status Legend

- VERIFIED: file, event class, service, or table exists in the current Moodle tree.
- PARTIAL: source exists, but H1/H2 must add a Program 2 adapter or resolver.
- DEFERRED: not required for H1 schema, but required before full production capture.

## Matrix

| Source Area | Verified Events or APIs | Verified Tables | H0 Status | H1/H2 Action |
| --- | --- | --- | --- | --- |
| Moodle quiz attempt lifecycle | `\mod_quiz\event\attempt_started`, `attempt_submitted`, `attempt_graded`, `attempt_regraded`, `attempt_manual_grading_completed`, `attempt_reopened`, `attempt_deleted` | `quiz`, `quiz_slots`, `quiz_grade_items`, `quiz_attempts`, `quiz_grades` | VERIFIED | Define normalized attempt and grade version rows. Register Program 2 observers after H1 schema. |
| Moodle quiz question attempts | Quiz attempt `uniqueid` links to question usage. | `question_usages`, `question_attempts`, `question_attempt_steps`, `question_attempt_step_data`, question bank/version tables | VERIFIED | Define question coverage extraction and stable per-question attempt keys. |
| Moodle assignment | `\mod_assign\event\assessable_submitted`, `submission_created`, `submission_updated`, `submission_graded`, `workflow_state_updated`, `extension_granted`, `submission_removed` | `assign`, `assign_submission`, `assign_grades` | VERIFIED | Normalize submission and grade history. Keep file/plugin submission payload references, not raw file copies. |
| Moodle SCORM | `\mod_scorm\event\sco_launched`, `status_submitted`, `scoreraw_submitted`, `cmielement_submitted`, `attempt_deleted` | `scorm`, `scorm_scoes`, `scorm_attempt`, `scorm_element`, `scorm_scoes_value` | VERIFIED | Use Program 1 `cmid + scoIdentifier` contract to resolve stable FLW activity ids. |
| Moodle gradebook | `\core\event\user_graded`, `grade_item_created`, `grade_item_updated`, `grade_item_deleted`, `grade_deleted`, grade report events | `grade_items`, `grade_grades`, `grade_grades_history`, `grade_items_history` | VERIFIED | Model grade versions and corrections; later reconcile from history tables. |
| Moodle completion | `\core\event\course_module_completion_updated`, `\core\event\course_completion_updated` | `course_modules_completion`, `course_completions` | VERIFIED | Store completion state transitions and link to Program 1 unit/activity identity when possible. |
| H5P | `\mod_h5pactivity\event\statement_received` | H5P activity tables in Moodle install | VERIFIED | Define xAPI statement normalizer after H1; do not add to H1 schema beyond generic payload references. |
| FLW Exam | `local_flwexam` quiz observers and `submit_result` service | `local_flwexam_exams`, `local_flwexam_sessions`, `local_flwexam_attempts`, `local_flwexam_results`, `local_flwexam_skill_scores`, `local_flwexam_kp_results`, `local_flwexam_questions`, certificates/audit tables | VERIFIED | Normalize exam result, skill score, and KP result events as source history. |
| FLW Placement | `local_flwplacement` quiz observers | `local_flwplacement`, `local_flwplacement_profile` | VERIFIED | Normalize placement attempts and level/profile transitions. |
| FLW Media | `get_items`, `save_progress`, `save_speaking_attempt`, `save_reading_attempt`, `save_dictation_attempt` services | `local_flwmedia_items`, `local_flwmedia_categories`, `local_flwmedia_progress`, `local_flwmedia_attempts` | VERIFIED | Add Program 2 adapter/backfill path for service-created media attempts. |
| FLW AI Assessment | Scheduled task `local_flwaiassessment\task\process_pending` | `local_flwai_results` | PARTIAL | Define adapter for finalized AI assessment results. Do not assume all pending rows are evidence. |
| FLW AI Speaking | Activity tables are present. | `flwaispeaking`, `flwaispeaking_submissions` | PARTIAL | Capture finalized speaking submission history from source table/service path. |
| FLW VR Room | `mod_flwvrroom\event\attempt_submitted` exists and is triggered by `mod_flwvrroom\classes\external\submit_attempt.php` | `flwvrroom`, `flwvrroom_attempts` | VERIFIED | Normalize VR attempt submissions; leave C-UP-KP evidence conversion to Program 3. |
| C-UP-KP evidence | `local_flwcupkp` observers, external evidence APIs, repair/sync pages | `flwcupkp_evidence`, `flwcupkp_state`, `flwcupkp_eval_snapshot`, `flwcupkp_selfeval`, `flwcupkp_diagnostic`, `flwcupkp_audit` | VERIFIED | Treat as downstream/Program 3 state. H2+ may offer migration bridge, but H1 must not duplicate mastery. |

## Source Identity Candidates

| Source | Candidate Idempotency Key |
| --- | --- |
| Quiz attempt | `moodle:quiz_attempt:{quiz_attempts.id}:{eventname}:{timemodified}` |
| Quiz question attempt | `moodle:question_attempt:{question_attempts.id}:{latest_step_id}` |
| Assignment submission | `moodle:assign_submission:{assign_submission.id}:{timemodified}` |
| Assignment grade | `moodle:assign_grade:{assign_grades.id}:{timemodified}` |
| SCORM element | `moodle:scorm_track:{scorm_scoes_value.id}:{element}:{timemodified}` |
| Gradebook grade | `moodle:grade_grade:{grade_grades.id}:{timemodified}` |
| Completion | `moodle:cm_completion:{course_modules_completion.id}:{timemodified}` |
| FLW Exam result | `flwexam:result:{local_flwexam_results.id}:{timemodified}` |
| FLW Placement attempt | `flwplacement:attempt:{local_flwplacement.id}:{timemodified}` |
| FLW Media attempt | `flwmedia:attempt:{local_flwmedia_attempts.id}:{timemodified}` |
| FLW AI assessment | `flwaiassessment:result:{local_flwai_results.id}:{timemodified}` |
| FLW AI speaking | `flwaispeaking:submission:{flwaispeaking_submissions.id}:{timemodified}` |
| FLW VR Room | `flwvrroom:attempt:{flwvrroom_attempts.id}:{timemodified}` |

H1 should refine these into final unique indexes. Keys must remain replay-safe: the same source fact can be captured multiple times without duplicate history rows.

