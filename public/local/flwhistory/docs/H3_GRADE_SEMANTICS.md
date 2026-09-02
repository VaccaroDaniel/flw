# H3 Grade Semantics

Gate: H3 - Grade-Version History + Attempt Semantics + Reconciliation

Status: PASS

## Purpose

H3 makes Program 2 trustworthy for grade history without replacing Moodle Gradebook or adding C-UP-KP mastery logic.

The core rule is that these values are always separate:

- `latest_attempt`: the most recent normalized attempt fact for the Moodle grade item.
- `best_attempt`: the highest normalized attempt score for the Moodle grade item.
- `official_moodle_grade`: the current Moodle Gradebook grade for the learner and grade item.

The values can disagree. H3 preserves the disagreement instead of collapsing it into a single score.

## Attempt Semantics

Attempts remain source facts in `flwhist_attempt`. They are not official grades.

Attempt ordering uses the attempt finish or modification time. Best attempt uses normalized score where available, then the raw score fallback. A newer lower attempt can be the latest attempt while an older higher attempt remains the best attempt.

## Official Grade Semantics

Moodle Gradebook remains authoritative for official current grade values. H3 reads `grade_grades`, `grade_items`, and Moodle grade events/history. Production code does not write core Moodle grade tables.

The derived local summary table `flwhist_grade_summary` stores current read models for:

- latest attempt
- best attempt
- official Moodle grade
- latest captured grade version
- reconciliation status

This summary is repairable and may be recalculated. It is not historical source truth.

## Grade Version Semantics

Grade versions are source-linked facts in `flwhist_grade_version`. They record official grade changes where Moodle supplies reliable evidence from current grade objects, grade events, and `grade_grades_history`.

Supported H3 actions:

- `INITIAL`
- `RETAKE`
- `REGRADE`
- `TEACHER_OVERRIDE`
- `CORRECTION`
- `IMPORT`
- `OTHER`

H3 classifies only what source data supports. It does not fabricate precise reasons. Free-text reasons are stored only when the source or caller supplies them.

## Non-Goals

H3 does not compute mastery, adaptive recommendations, risk, progress trend, or improvement percentages. Trend and improvement displays must remain unavailable or explicitly insufficient-evidence until a later comparability gate validates which attempts and grades are comparable.

