# Program 3 Gate E3 Report

Status: implemented

## Completed

- Added `local_flwcupkp\local\retention_review_service`.
- Added retention contract `FLW_CUPKP_RETENTION_RETRIEVAL_REVIEW_V1`.
- Extended `flwcupkp_state` with retention snapshot fields and index.
- Added read-only status, learner retention state, class retention summary,
  rebuild preview, rebuild apply, and recent rebuild history.
- Added admin page, CLI, web-service functions, OpenAPI entries, privacy
  metadata, and home-page navigation.
- Updated repository audit and Foundation V1 boundary to hand off to A1.

## Preserved Boundaries

- History V1 remains the only normal source-history input.
- E3 consumes E1-derived C-UP-KP evidence and E2 current-state rows.
- E3 does not scrape raw Moodle logs.
- E3 does not mechanically decay mastery because time passed.
- Failed review changes retention state, not the E2 mastery state.
- Adaptive path selection and learning-goal modeling remain out of scope.

## Tests

Primary coverage:

```text
local_flwcupkp\retention_review_service_test
local_flwcupkp\program3_repository_audit_test
local_flwcupkp\foundation_v1_contract_test
local_flwcupkp_testsuite
```

Latest result:

```text
119 tests, 1049 assertions
```

Expected checks:

- contract exposes E3 retention states and A1 handoff;
- E3 status is read-only;
- KP and UP can produce different retention states;
- `REVIEW_DUE` does not decay mastery;
- failed review sets `RELEARNING` without erasing mastery;
- apply rebuild writes retention fields only and records audit history;
- earlier Program 3 foundation/audit tests remain green.

## Live U038 Smoke Check

Completed after Moodle upgrade and cache purge:

```text
courseid=124
unitcode=U038
/local/flwcupkp/retention_review.php?courseid=124&unitcode=U038
local/flwcupkp/cli/retention_review.php --action=status --courseid=124 --unitcode=U038
local/flwcupkp/cli/retention_review.php --action=preview --courseid=124 --unitcode=U038
local/flwcupkp/cli/retention_review.php --action=class --courseid=124 --unitcode=U038
```

- E3 status returned `ready`.
- All six E3 readiness criteria passed.
- Retention schema fields were present on `flwcupkp_state`.
- U038 cache snapshot reported `state_rows=6` and
  `retention_missing_rows=6`.
- Preview stayed read-only and applied no changes.
- Preview/class summary found two scoped learner IDs that fail active Moodle
  course-enrollment validation, so both were reported as `skipped_unenrolled`.
- The protected admin page URL returned HTTP `303`, confirming the route is
  reachable and redirects through Moodle login/session handling.

## Next Gate

```text
Program 3 Gate A1 - Competency-Centered Learning Goal
```
