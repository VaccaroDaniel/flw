# FLW History Downstream Evidence Contract V1

Status: frozen for Program 3 consumption.

Provider: `local_flwhistory`

Contract id: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`

History version: `FLW_HISTORY_V1`

Normalization policy: `H1B-20260827.1`

## Purpose

This contract is the trusted Program 2 output surface for downstream learner intelligence. Program 3 can consume these source-grounded facts without scraping raw Moodle logs as its normal evidence source.

Program 2 still does not calculate C-UP-KP mastery, adaptive recommendations, intervention priority, or Moodle competency ratings.

## Fact Types

- `source_event`: normalized source facts with source identity, event type, timestamp, Program 1 mapping fields, payload hash, and coverage state.
- `attempt`: distinct learner attempts, including latest and repeated attempts, source attempt id, attempt state, score fields, and attempt timestamps.
- `question_attempt`: question-level attempt facts held by History V1 storage and available through direct service/table access when Program 3 needs item detail.
- `grade`: grade-version rows preserving latest attempt, best attempt, and official Moodle grade as separate concepts through `flwhist_grade_summary`.
- `completion`: Moodle module/course completion facts with completion state, viewed flag, completion time, and source identity.
- `placement`: placement facts from FLW placement sources when the source table is installed and timestamped.
- `content_identity`: stable Program 1 mapping cache for course, section, cmid, SCO, world, stage, unit, lesson, component, activity, assessment, and question identities.

## Public Adapter

Use `local_flwhistory\local\evidence_source_adapter`.

Bounded course methods:

- `source_events_for_course(int $courseid, int $limit = 100, int $offset = 0)`
- `attempts_for_course(int $courseid, int $limit = 100, int $offset = 0)`
- `grades_for_course(int $courseid, int $limit = 100, int $offset = 0)`
- `completions_for_course(int $courseid, int $limit = 100, int $offset = 0)`
- `placements_for_course(int $courseid, int $limit = 100, int $offset = 0)`
- `content_identities_for_course(int $courseid, int $limit = 100, int $offset = 0)`

Each method returns:

- `type`
- `contract`
- `facttable`
- `courseid`
- `pagination`
- `records`

Limits are bounded by the adapter. Consumers must paginate instead of requesting unbounded data.

## Source Identity Rules

Every fact keeps:

- `sourcekey`: stable row identity for idempotent storage.
- `sourcefactkey`: stable logical fact identity for duplicate detection and downstream joins.
- `sourcesystem`, `sourcefamily`, `sourcetype`, `sourceid`, and `sourceversion` where available.
- `normpolicyversion`.

Program 3 should treat unresolved Program 1 mappings as unresolved facts, not missing evidence.

## Fabrication Policy

H7 backfill only records facts reliably recoverable from Moodle or FLW source tables.

- Missing attempt order: skipped.
- Unknown timestamps: skipped.
- Unknown grade reasons: left as `null`.
- C-UP-KP evidence: not created by Program 2.
- Mastery state: not created by Program 2.
- Adaptive recommendation: not created by Program 2.

## Privacy and Security

Downstream access must continue to enforce Moodle context permissions:

- Own learner access is allowed through the secured learner services.
- Other learner access requires course or system history capabilities.
- Grade audit detail requires grade-audit capability or system history access.
- Exports and deletions are handled by the Moodle privacy provider.
- State-changing web actions are not part of History V1.

## Program 3 Consumption Rule

Program 3 should consume History V1 adapter payloads and H4-H6 trusted services. Raw Moodle logs may be used only as an exceptional diagnostic path, not as the normal evidence source.
