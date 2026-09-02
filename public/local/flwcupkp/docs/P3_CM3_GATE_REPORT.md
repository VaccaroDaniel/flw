# Program 3 Gate CM3 Gate Report

Date: 2026-08-29

Status: complete

## Implemented

- Added `local_flwcupkp\local\coverage_bulk_governance_manager`.
- Added the admin Coverage Governance page at `/local/flwcupkp/governance.php`.
- Added six bounded coverage checks:
  competency coverage, KP teaching coverage, UP practice coverage, UP assessment
  coverage, evidence-quality coverage, and production/interaction coverage.
- Added governance findings for orphans, taught-not-assessed,
  assessed-not-taught, interaction targets with recognition-only evidence,
  missing prerequisites, deprecated references, evidence ceilings, and coverage
  imbalance.
- Added bulk import dry-run previews for JSON and CSV packages.
- Confirmed imports delegate to the existing transactional/idempotent importer.
- Added JSON export with checksum metadata.
- Added controlled rollback preview and rollback-request audit markers for
  historical import batches.
- Added lifecycle governance dashboard data for version, review, publication,
  deprecation, replacement, and impact.
- Added admin navigation from the C-UP-KP home page and Curriculum Manager.
- Advanced repository/foundation runtime handoff to CM4.
- Added CM3 PHPUnit coverage.

## Preserved Boundaries

- History V1 remains the only normal source-history input.
- Foundation V1, CM1, and CM2 contracts remain authoritative dependencies.
- No adaptive path selection was added.
- No mastery-policy recalculation or learner-state mutation was added.
- No recommendation-policy change was added.
- No History V1 source capture or raw Moodle log scraping was introduced.
- Rollback does not blindly delete historical import rows because existing
  import batches are checksum-owned, not row-owned. CM3 records controlled
  rollback requests and audit evidence instead.

## Validation

```text
php -l changed PHP files: passed
tests/coverage_bulk_governance_manager_test.php: OK (5 tests, 60 assertions)
tests/core_curriculum_manager_test.php: OK (6 tests, 42 assertions)
tests/relationship_where_used_manager_test.php: OK (6 tests, 43 assertions)
tests/foundation_v1_contract_test.php: OK (6 tests, 61 assertions)
tests/program3_repository_audit_test.php: OK (3 tests, 115 assertions)
local_flwcupkp_testsuite: OK (100 tests, 813 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
live upgrade.php --non-interactive: passed
live purge_caches.php: passed
live service smoke: CM3 ready, Foundation frozen, next_allowed_gate CM4
live logged-in PHP render smoke: governance.php rendered Coverage Governance,
Coverage matrix, Bulk import/export, and Import/rollback history
live logged-in PHP render smoke: index.php shows Coverage Governance card;
curriculum.php shows Coverage Governance toolbar link
browser visual smoke: blocked by Moodle login page; no signed-in browser
session was available
```

## Next

Program 3 Gate CM4 should freeze the management V1 surface for production
consumers, using the completed CM1, CM2, and CM3 management contracts without
adding adaptive logic.
