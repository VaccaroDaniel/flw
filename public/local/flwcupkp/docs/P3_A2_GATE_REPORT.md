# Program 3 Gate A2 Report

## Scope

Implemented Program 3 Gate A2 - Placement + Diagnostic + Cold Start.

## Delivered

- Added `placement_diagnostic_service` with frozen A2 contract, status,
  current learner placement, class summary, controlled preview/apply
  reprocessing, and recent audit history.
- Added `flwcupkp_placement_state` schema plus Moodle upgrade path.
- Added `/local/flwcupkp/placement_diagnostic.php` for teacher/admin preview,
  apply, class summary, selected learner state, and repair history.
- Added CLI and web-service endpoints for status, current placement, class
  summary, preview, and apply.
- Added README/OpenAPI/privacy coverage and home-page discovery cards.
- Updated Program 3 repository audit to hand off from A2 to A3.

## Boundary

- History V1 remains the only normal source-history input.
- Placement is diagnostic evidence, not permanent truth.
- Overall placement level or score alone does not create C-UP-KP evidence.
- C-UP-KP evidence is written only for explicitly assessed dimensions with
  explicit target mapping.
- Adaptive path selection remains future work.

## Verification

Completed in this task:

- PHP syntax checks passed for A2 service, page, CLI, external API, privacy
  provider, upgrade file, index page, and touched tests.
- `placement_diagnostic_service_test.php`: 5 tests, 100 assertions.
- Focused Program 3/audit/privacy/A1 tests: 13 tests, 279 assertions.
- Full `local_flwcupkp_testsuite`: 127 tests, 1232 assertions.
- Moodle PHPUnit schema initialization completed successfully after XMLDB table
  addition.
- Moodle CLI upgrade completed successfully for `local_flwcupkp`.
- Moodle caches were purged.
- U038 live CLI smoke returned A2 `ready`, seven of seven criteria passed, and
  reported zero current History V1 placement facts to reprocess.
- U038 placement diagnostic web route redirects from IP to canonical host and
  then to Moodle login as an authenticated/protected page.

## Next Gate

Program 3 Gate A3 - Adaptive Decision Policy V1.
