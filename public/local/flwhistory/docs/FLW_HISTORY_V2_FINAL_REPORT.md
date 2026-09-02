# FLW History V2 Final Report

Program: FLW Learning and Grade History, Program 2.

Final gate: H7.

Frozen output: `FLW_HISTORY_V1`.

Downstream contract: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`.

## Completed Scope

H0 through H7 are implemented for Program 2. The plugin now provides a source-grounded history layer for:

- events
- attempts
- question attempts
- grades
- grade summaries
- completion
- placement facts
- stable Program 1 content identities
- learner dashboard history
- teacher history analytics
- migration/backfill
- reconciliation
- privacy export/delete coverage
- performance/freeze checks
- downstream evidence adapter

## H7 Implementation

H7 added:

- `local_flwhistory\local\history_v1_service`
- `local_flwhistory\local\evidence_source_adapter`
- `local/flwhistory/cli/history_v1.php`
- plugin version checkpoint `2026082805`
- privacy export coverage for `flwhist_coverage`
- this final report
- downstream evidence contract V1

## Migration and Backfill

The H7 backfill service supports:

- dry-run by default
- controlled execute mode
- bounded batches
- resume cursors
- source filtering
- idempotency keys
- source labels
- run audit rows in `flwhist_reconcile_run`

Recoverable sources:

- Moodle quiz attempts
- Moodle course/module completion
- Moodle grade history
- Moodle current grades
- FLW placement facts when the placement source table is installed

Backfill does not fabricate missing data. Unknown timestamps are skipped. Unknown grade reasons remain unknown. C-UP-KP evidence, mastery, and recommendations are not created.

## Reconciliation

The H7 reconciliation service checks:

- official grade summaries against Moodle Gradebook
- completion/current unit facts against Moodle completion and Program 1 structure
- orphan FLW mappings when the mapping table exists
- duplicate source identities

Execute mode repairs only local History V1 derived summaries and completion capture where Moodle source state is reliable.

## Security and Privacy

H7 freeze status verifies:

- learner own/other access boundaries
- teacher context boundaries
- grade audit capability boundary
- privacy export methods
- privacy delete methods
- parameter validation through Moodle external APIs and page parameters
- escaped web output
- no state-changing History V1 web forms

## Performance

H7 measures:

- learner present summary
- learning journey
- learning history pagination
- grade detail
- class history view

The CLI command is:

`php local/flwhistory/cli/history_v1.php --action=performance --courseid={courseid} --userid={userid} --limit=25`

## Freeze Command

Run:

`php local/flwhistory/cli/history_v1.php --action=freeze --courseid={courseid} --limit=25`

Expected result:

- `status` is `frozen` when no blocking implementation checks fail.
- reconciliation findings remain visible for operator cleanup but do not create adaptive policy.

## Local Verification

- Focused H7 PHPUnit: `OK (6 tests, 67 assertions)`
- Full `local_flwhistory` PHPUnit suite: `OK (51 tests, 384 assertions)`
- Plugin-wide PHP lint: clean
- Moodle upgrade/cache purge: complete
- Live plugin version: `2026082805`
- Live freeze smoke, course `126`, learner `5`: `frozen`
- Live backfill execute, course `126`: 44 History V1 rows created, 0 failures
- Live reconcile execute, course `126`: grade summaries repaired; one Program 1 content identity cache finding remains for operator cleanup
- Live grade-history compatibility execute, course `126`: 0 creates, 0 failures

## Downstream Boundary

Program 3 can consume History V1 facts through `evidence_source_adapter`. Program 2 must remain descriptive and source-grounded.

Program 2 does not own:

- C-UP-KP mastery
- adaptive learning path logic
- recommendation policy
- risk labels
- native Moodle competency rating writes

Those belong to Program 3 or other declared downstream plugins.
