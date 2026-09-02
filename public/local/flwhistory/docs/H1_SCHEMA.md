# Program 2 Gate H1 Schema

## Installed Tables

| Table | Purpose | Unique Source Key |
| --- | --- | --- |
| `flwhist_source_event` | Normalized immutable source facts from Moodle and FLW systems. | `sourcekey` |
| `flwhist_attempt` | Normalized learner attempts across quiz, SCORM, assignment, media, exam, placement, AI speaking, VR, and AI assessment sources. | `sourcekey` |
| `flwhist_placement` | Placement level/profile source fact history. | `sourcekey` |
| `flwhist_question_attempt` | Normalized item-level and question-level attempt facts. | `sourcekey` |
| `flwhist_grade_version` | Grade versions and correction history. | `sourcekey` |
| `flwhist_completion` | Learner completion state transitions. | `sourcekey` |
| `flwhist_content_link` | Cached Program 1 content/deployment identity links. | `sourcekey` |
| `flwhist_reconcile_run` | Repair, replay, and reconciliation run metadata. | `sourcekey` |
| `flwhist_correction` | Explicit correction and supersession links across history records. | `sourcekey` |

## Source Event

`flwhist_source_event` stores the common source fact envelope:

- source identity: `sourcekey`, `sourcesystem`, `sourcetype`, `sourceid`, `sourceversion`, `eventtype`
- Moodle identity: `userid`, `courseid`, `sectionid`, `cmid`, `gradeitemid`, `sourceattemptid`, `attemptid`
- FLW identity: `worldid`, `stageid`, `unitid`, `lessonid`, `componentid`, `activityid`, `assessmentid`, `questionid`
- traceability: `eventtime`, `status`, `normalizer`, `summaryjson`, `payloadhash`
- correction: `correctionof`, `supersededby`

Indexes support source identity, learner timeline, learner/course, course/time, cmid, and source lookup.

## Attempt

`flwhist_attempt` stores attempts without deciding official grade or mastery:

- source identity fields
- learner/course/module fields
- Program 1/FLW activity fields
- attempt number/state
- raw/max/scaled score
- start/finish times
- last source event and compact summary

Indexes support source identity, learner/time, learner/course, cmid, and activity lookup.

## Placement

`flwhist_placement` stores placement source facts:

- source identity fields
- learner/course
- previous and current level
- status, score, confidence
- profile JSON summary
- placement time

This keeps placement/profile history distinct from generic attempt history.

## Question Attempt

`flwhist_question_attempt` stores item-level facts:

- source identity
- linked normalized attempt/source event
- learner/course/module
- question usage, question attempt, slot
- Moodle/FLW question ids
- state, response hash, raw/max/fraction
- step time and summary

H1 does not yet define normalization policy versions; H1B owns that.

## Grade Version

`flwhist_grade_version` stores grade changes while Moodle Gradebook remains authoritative:

- learner/course/module
- grade item, grade grade, and grade history ids
- item module/instance/number
- raw, final, and previous grade values
- grader/action/reason
- grade time
- correction and supersession links

Indexes support learner-grade, course-grade, learner-time, and Moodle grade row lookup.

## Completion

`flwhist_completion` stores completion transitions:

- source identity
- learner/course/module
- completion state, viewed flag, override actor
- completion time and details JSON

## Content Link

`flwhist_content_link` caches Program 1 identity resolution:

- Moodle course/section/cmid/SCO references
- FLW world/stage/unit/lesson/component/activity/assessment/question references
- source revision
- freshness state
- resolver and status

This is a cache/boundary, not a second Program 1 implementation.

## Reconciliation and Correction

`flwhist_reconcile_run` records repair/backfill/replay run metadata and counts.

`flwhist_correction` records explicit correction/supersession links. It is separate from privacy deletion.

