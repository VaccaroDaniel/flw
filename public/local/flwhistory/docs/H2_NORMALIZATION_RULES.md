# Program 2 Gate H2 Normalization Rules

Status: PASS

Current history normalization policy version: `H1B-20260827.1`

Every normalized H2 row retains `normpolicyversion`, the H1B equivalent of `history_normalization_policy_version`.

## Event Vocabulary

H2 uses only supported vocabulary:

| Source fact | Normalized event |
| --- | --- |
| Quiz attempt started, reopened, or deleted | `ACTIVITY_ATTEMPTED` |
| Quiz attempt submitted, graded, regraded, or manual grading completed | `ASSESSMENT_COMPLETED` |
| Moodle module/course completion update | `CHECKPOINT_COMPLETED` |
| FLW VR Room attempt submission | `SPEAKING_ATTEMPTED` |

Reserved vocabulary not activated in H2: `UNIT_STARTED`, `LESSON_COMPLETED`, `WRITING_SUBMITTED`, `PROJECT_SUBMITTED`, and `PLACEMENT_COMPLETED`.

## Source Fact First

Capture persists `flwhist_source_event` before attempt/completion post-processing. If post-processing fails, H2 records a `flwhist_reconcile_run` failure with `status = failed_after_source`; the original Moodle action is not rolled back by the observer.

## Idempotency

Source identity is built from source system, source type, source id, source version, and event type. Replaying the same source event updates the same row instead of duplicating it. Attempt/detail rows use stable source keys from Moodle attempt and question-attempt source identifiers.

## Mapping

When Program 1 mapping resolves, H2 stores stable IDs such as `worldid`, `stageid`, `unitid`, `lessonid`, `componentid`, and `activityid`.

When mapping is missing, H2 still stores the source fact with `status = unresolved_mapping`. It does not fabricate C-UP-KP evidence or block later reconciliation.

## Payload Bounds

Event `other` payloads are reduced to bounded scalar fields. Arrays, objects, long strings, and non-scalar source details are not copied wholesale into history.

## Timing

H2 stores only reliable timing:

| Source | Timing retained |
| --- | --- |
| Quiz attempts | `timestart`, `timefinish`, and calculated `durationseconds` from `quiz_attempts` |
| Completion | `timemodified` from `course_modules_completion` |
| FLW VR Room | `timecreated` and `durationseconds` from `flwvrroom_attempts` |

Page-open elapsed time is not treated as study time.

## Coverage

Captured H2 rows create `NOT_BACKFILLED` coverage facts with `EVENT_AVAILABLE`. This means production capture is active for new events, but historical backfill is not yet complete.
