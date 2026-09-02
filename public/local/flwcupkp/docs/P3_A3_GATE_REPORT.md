# Program 3 Gate A3 Report

## Scope

Implemented Program 3 Gate A3 - Adaptive Decision Policy V1.

## Delivered

- Added `adaptive_decision_policy_service` with frozen A3 contract, visible
  policy thresholds, deterministic learner decision, class summary, decision
  hash, source snapshots, tie-breaking, stability/hysteresis, anti-loop, and
  fallback rules.
- Added `/local/flwcupkp/adaptive_decision.php` for teacher/admin policy,
  status, current learner decision, and class summary inspection.
- Added CLI and web-service endpoints for A3 status, policy, learner decision,
  and class summary.
- Added README/OpenAPI coverage and home-page discovery cards.
- Updated Program 3 repository audit to hand off from A3 to A4.

## Boundary

- History V1 remains the only normal source-history input.
- A3 consumes A1 goals, A2 placement diagnostics, E2 mastery/confidence, E3
  retention/review, and C2 prerequisites through trusted services.
- A3 is read-only: no learner state, recommendation, path, placement, goal, or
  History V1 source rows are written.
- Moodle activity resolution remains future work.

## Verification

Completed in this task:

- PHP syntax checks passed for A3 service, page, CLI, external API, index page,
  language file, repository audit, Foundation contract, upgrade file, and A3
  tests.
- `openapi.json` decoded successfully.
- `adaptive_decision_policy_service_test.php`: 6 tests, 77 assertions.
- Focused A3/audit/Foundation test run: 15 tests, 283 assertions.
- Full `local_flwcupkp_testsuite`: 133 tests, 1313 assertions.
- Moodle PHPUnit schema initialization completed successfully after the A3
  version checkpoint.
- Moodle CLI upgrade completed successfully for `local_flwcupkp`.
- Moodle caches were purged.
- U038 live CLI smoke returned A3 `ready`, nine of nine criteria passed, visible
  thresholds and decision states present, and `next_allowed_gate` set to `A4`.
- U038 live class summary returned zero active learners because course id `124`
  is not present in the current live database snapshot; stale U038 rows tied to
  missing course `124` are no longer counted as skipped learner decisions.
- The U038 A3 web route redirects from IP to the canonical Moodle host and then
  to Moodle login as an authenticated/protected page.

## Next Gate

Program 3 Gate A4 - Goal-Gap + Initial Personalized Path.
