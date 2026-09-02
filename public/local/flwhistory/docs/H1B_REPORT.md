# Program 2 Gate H1B Report

## Result

Status: PASS

H1B froze the history coverage and normalization-version semantics before production capture. The live Moodle plugin `local_flwhistory` is upgraded to `2026082702`, the coverage table exists, and the normalized history records now carry source-family, source-fact, and normalization-policy fields.

## Acceptance Check

| Criterion | Result |
| --- | --- |
| Coverage states are explicit. | PASS - COMPLETE, PARTIAL, SOURCE_LIMITED, NOT_BACKFILLED, and UNKNOWN are defined in `history_policy`. |
| No-event semantics are explicit. | PASS - EVENT_AVAILABLE, NO_EVENT_OCCURRED, and NO_EVENT_AVAILABLE are separated. |
| Coverage can be scoped. | PASS - coverage records support source family, learner, course/world/stage/unit, and time ranges. |
| Required coverage facts exist. | PASS - capture, backfill, earliest reliable event, latest reconciled, source availability, reason, and detail fields exist. |
| Inactivity is guarded. | PASS - inactivity checks require COMPLETE coverage across the requested interval. |
| Program 3 gets coverage context. | PASS - evidence payloads include `coverage`, `sourcefamily`, `sourcefactkey`, and `normpolicyversion`. |
| Normalization version is frozen. | PASS - current policy is `H1B-20260827.1`; DB field is `normpolicyversion`. |
| Rule changes remain auditable. | PASS - normalization supersession creates version-linked rows and correction audit entries. |
| Production capture remains off. | PASS - H1B does not add active observers or scheduled capture tasks. |

## Implementation Summary

Upgraded Moodle plugin:

- `D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwhistory`

Schema version:

- `local_flwhistory` disk and live DB version: `2026082702`

Added table:

- `flwhist_coverage`

Added fields:

- `sourcefactkey`
- `sourcefamily`
- `normpolicyversion`

Updated services:

- `history_policy`
- `coverage_service`
- `repository`
- `history_service`
- `source_identity`
- `normalizer`
- `evidence_source_adapter`
- `privacy\provider`

Added tests:

- `tests/coverage_policy_test.php`

## Verification

Commands/checks run:

- PHP lint on every `local_flwhistory` PHP file: PASS.
- XML parse for `db/install.xml`: PASS.
- Moodle CLI upgrade: PASS.
- Live DB version check: PASS, `local_flwhistory = 2026082702`.
- Live DB field/table existence check for H1B coverage and normalization fields: PASS.
- PHPUnit environment init: PASS.
- Focused PHPUnit suite `local_flwhistory_testsuite`: PASS, 16 tests and 58 assertions.
- Moodle cache purge after upgrade/tests: PASS.

The PHPUnit initializer emitted the known unrelated `exastud/mysource` default-setting debugging warning, but completed successfully.

## Notes

- The Moodle tree has unrelated pre-existing dirty theme/config/vendor files. They were not reverted.
- The H1B coverage service includes fallback lookup for unit-specific, learner/course, course-level, and source-family coverage before returning UNKNOWN.
- H1B is a semantic freeze gate. It intentionally stops before production source capture.

## Next Gate

Proceed to H2 only when instructed: enable production-safe historical source capture with active observers/tasks using the frozen H1B coverage and normalization semantics.
