# Program 3 Gate A1 Report

## Scope

Implemented Program 3 Gate A1 - Competency-Centered Learning Goal.

## Delivered

- Added `learning_goal_service` with the frozen A1 contract, status, current
  goal, class summary, target options, versioned save, and recent audit history.
- Added `flwcupkp_goal` and `flwcupkp_goal_version` schema plus upgrade path.
- Added `/local/flwcupkp/learning_goal.php` for admin, teacher, and student
  goal viewing/editing.
- Added CLI and web-service endpoints for status, current goal, class summary,
  target options, and save.
- Added README/OpenAPI/privacy coverage.
- Updated Program 3 repository audit to hand off from A1 to A2.

## Boundary

- History V1 remains the only normal source-history input.
- Goal changes create immutable versions.
- A1 does not erase history, mastery, retention, evidence, or recommendations.
- Placement, diagnostics, cold start, and adaptive path selection remain future
  work.

## Verification

Completed in this task:

- PHP syntax checks passed for A1 service, page, CLI, external API, privacy
  provider, upgrade/services files, and touched tests.
- `learning_goal_service_test.php`: 3 tests, 65 assertions.
- Focused Program 3/audit/privacy/E3 tests: 14 tests, 250 assertions.
- Full `local_flwcupkp_testsuite`: 122 tests, 1125 assertions.
- Moodle CLI upgrade completed successfully for `local_flwcupkp`.
- Moodle caches were purged.
- U038 live CLI smoke returned A1 `ready`, six of six criteria passed, and
  listed the U038 competency, UP, and KP targets.
- U038 learning-goal web route redirects from IP to canonical host and then to
  Moodle login as an authenticated/protected page.

## Next Gate

Program 3 Gate A2 - Placement + Diagnostic + Cold Start.
