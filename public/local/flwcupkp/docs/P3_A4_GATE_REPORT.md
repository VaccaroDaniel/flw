# Program 3 Gate A4 Gate Report

## Result

A4 is implemented and ready for A4B.

## Implemented

- Added `goal_gap_path_service` with frozen A4 contract and policy.
- Added explainable learner path generation:
  - missing KP/UP/competency
  - satisfied KP/UP/competency
  - blocked-by-prerequisite items
  - candidate next targets
  - next target, projected roadmap, destination
- Added admin/teacher/student page at `initial_path.php`.
- Added CLI at `cli/initial_path.php`.
- Added three external web-service methods.
- Updated Program 3 repository audit boundary to A4B.
- Added A4 home-page cards.

## Verification

Focused PHPUnit:

`OK (4 tests, 60 assertions)`

Full local plugin PHPUnit suite:

`OK (137 tests, 1378 assertions)`

## Stop Boundary

Do not resolve the A4 target-level path to Moodle activities yet.

Next gate: `Program 3 Gate A4B - Candidate Eligibility + Activity Resolution`.
