# Program 3 Gate E2 Report

Status: implemented

## Completed

- Added `local_flwcupkp\local\mastery_state_service`.
- Added deterministic mastery/confidence current-state contract
  `FLW_CUPKP_MASTERY_CONFIDENCE_STATE_V1`.
- Extended `flwcupkp_state` with E2 cache metadata:
  `policyversion`, `trend`, `evidencehash`, `evidenceidsjson`, and
  `calculatedtime`.
- Updated the canonical state writer to store E2 snapshot metadata while
  preserving manual override behavior.
- Added confidence modeling that remains separate from mastery score/state.
- Added current learner state, class current-state summary, read-only preview
  rebuild, and controlled apply rebuild services.
- Added admin page, CLI, web-service functions, OpenAPI entries, privacy
  metadata, and home-page navigation.
- Updated repository audit handoff to E3.

## Preserved Boundaries

- History V1 remains the only normal source-history input.
- E2 consumes E1-derived C-UP-KP evidence; it does not scrape raw Moodle logs.
- Grades are not collapsed into mastery.
- Confidence is calculated separately from mastery.
- Retention/retrieval/review behavior is not implemented in E2.
- Adaptive path selection and recommendation policy are unchanged.
- Manual teacher overrides are never overwritten by automated rebuild.

## Tests

Primary coverage:

```text
local_flwcupkp\mastery_state_service_test
local_flwcupkp_testsuite
```

Latest result: `115 tests, 1002 assertions`.

Expected checks:

- contract exposes all three learner state types;
- E2 status is read-only and points to E3;
- current learner state consumes E1 History-backed evidence;
- state rows store policy version, evidence references/hash, calculated time,
  confidence, and trend;
- rebuild preview is read-only;
- controlled rebuild apply is audited and updates stale cache rows;
- manual overrides are skipped;
- scoped state/evidence users outside active Moodle enrollment are reported and
  skipped without rebuild writes.

Live U038 smoke check:

- `courseid=124`, `unitcode=U038` E2 status returned `ready`.
- Rebuild preview found scoped learner/state data and stayed read-only.
- Current live U038 data has two scoped learner IDs that fail Moodle active
  course-enrollment validation, so preview reported `skipped_unenrolled=2`
  and applied no state changes.

## Next Gate

```text
Program 3 Gate E3 - Retention / Retrieval / Review
```
