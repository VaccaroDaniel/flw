# H3 Grade Version Contract

Gate: H3 - Grade-Version History + Attempt Semantics + Reconciliation

Status: PASS

## Source Boundaries

H3 captures grade history from Moodle-owned sources:

- `core\event\user_graded`
- `core\event\grade_deleted`
- `grade_grades`
- `grade_grades_history`
- `grade_items`

Moodle Gradebook remains the authoritative writer for official grades. `local_flwhistory` records source-linked history and derived summaries only.

## Stored Grade Version Fields

`flwhist_grade_version` stores:

- source key and source fact key
- source event id when the version came from a Moodle event
- learner, course, cmid, grade item, and grade grade identifiers
- Moodle grade history row id when available
- item module, item instance, and item number
- raw grade, final grade, and previous final grade where available
- reliable grader or actor id
- H3 action vocabulary
- source-supplied reason or source label when available
- grade time
- correction and supersession links
- normalization policy version
- compact source payload JSON

Source keys are deterministic for the source identity, version time, and action. Replaying the same source does not create duplicate grade versions.

## Action Classification

The service classifies grade changes conservatively:

- Explicit approved action from a controlled caller wins.
- Overridden Moodle grade or event override flag becomes `TEACHER_OVERRIDE`.
- Source labels containing `import` become `IMPORT`.
- Source labels containing `regrade` become `REGRADE`.
- Gradebook/manual source labels become `TEACHER_OVERRIDE`.
- Insert actions or first known grade facts become `INITIAL`.
- Multiple attempts may support `RETAKE` when no more specific source signal exists.
- Otherwise the action is `OTHER`.

## Correction Contract

Explicit corrections use `flwhist_correction` and link affected `flwhist_grade_version` rows through `correctionof` and `supersededby`. A correction records the audit relation; it does not mutate the original source fact into a different fact.

## Security And Audit

Actor/grader ids are recorded only from Moodle events, grade history, or grade objects. H3 tests protect these fields by verifying teacher override capture and by verifying reconciliation does not rewrite grade-version rows.

Teacher/admin-only display rules belong to later UI/API gates. H3 stores the audit data so those gates can filter it safely.

