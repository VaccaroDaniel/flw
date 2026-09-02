# Program 2 Gate H2 Capture Matrix

Status: PASS

H2 enables active source capture only for verified sources with defensible educational meaning. It does not mine raw logs for learner dashboards and does not calculate C-UP-KP mastery.

| Source | Event or API | Normalized event | Persisted rows | Program 1 mapping | Coverage state |
| --- | --- | --- | --- | --- | --- |
| Moodle quiz | `\mod_quiz\event\attempt_started` | `ACTIVITY_ATTEMPTED` | `flwhist_source_event`, `flwhist_attempt` when `quiz_attempts` row exists | Resolved by cmid when available; otherwise `unresolved_mapping` | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_submitted` | `ASSESSMENT_COMPLETED` | Source event, attempt, question attempts | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_graded` | `ASSESSMENT_COMPLETED` | Source event, attempt, question attempts, score/result summary | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_regraded` | `ASSESSMENT_COMPLETED` | Source event plus current attempt snapshot | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_manual_grading_completed` | `ASSESSMENT_COMPLETED` | Source event plus current attempt snapshot | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_reopened` | `ACTIVITY_ATTEMPTED` | Source event plus current attempt snapshot | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle quiz | `\mod_quiz\event\attempt_deleted` | `ACTIVITY_ATTEMPTED` | Source event plus deleted-attempt stub when the attempt row is gone | Resolved by cmid/course when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle completion | `\core\event\course_module_completion_updated` plus `course_modules_completion` | `CHECKPOINT_COMPLETED` | Source event and `flwhist_completion` | Resolved by cmid when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Moodle completion | `\core\event\course_completion_updated` | `CHECKPOINT_COMPLETED` | Source event only in H2 | Resolved by course when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| FLW VR Room | `\mod_flwvrroom\event\attempt_submitted` plus `flwvrroom_attempts` | `SPEAKING_ATTEMPTED` | Source event and `flwhist_attempt` | Resolved by cmid/course when available | `NOT_BACKFILLED`, `EVENT_AVAILABLE` |
| Coverage refresh | `\local_flwhistory\task\refresh_capture_coverage` | Diagnostic coverage fact | `flwhist_coverage`, `flwhist_reconcile_run` | Not applicable | Course aggregate `NOT_BACKFILLED` |

## Active Registration

Observer count: 10

Scheduled task:

| Task | Schedule | Enabled |
| --- | --- | --- |
| `\local_flwhistory\task\refresh_capture_coverage` | `*/15` minutes | Yes |

## Exclusions

H2 does not capture page views as completion, does not infer time-on-task from open browser time, does not fabricate FLW activity IDs, does not create learner-facing dashboards, and does not alter Moodle core.
