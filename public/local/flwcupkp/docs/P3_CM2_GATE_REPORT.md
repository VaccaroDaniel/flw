# Program 3 Gate CM2 Gate Report

Date: 2026-08-29

Status: complete

## Implemented

- Added `local_flwcupkp\local\relationship_where_used_manager`.
- Added CM2 readiness, relationship preview/apply, mapping impact, entity
  where-used impact, and coverage governance services.
- Replaced the mapping manager route with a controlled preview/confirm
  relationship editor.
- Added edit links and preview-delete controls for existing mapping rows.
- Added semantic relationship labels to mapping tables.
- Added a CM2 where-used impact panel to entity detail pages before workflow
  actions.
- Required deprecation workflow posts to carry the CM2 impact acknowledgement.
- Advanced repository/foundation runtime handoff to CM3.
- Added CM2 PHPUnit coverage.

## Preserved Boundaries

- History V1 remains the only normal source-history input.
- Foundation V1 contracts remain authoritative.
- No adaptive path selection was added.
- No mastery-policy recalculation or learner-state mutation was added.
- No History V1 evidence reprocessing write was added.
- No raw Moodle log scraping was introduced.

## Validation

```text
php -l changed PHP files: passed
tests/relationship_where_used_manager_test.php: OK (6 tests, 43 assertions)
tests/core_curriculum_manager_test.php,
tests/foundation_v1_contract_test.php,
tests/program3_repository_audit_test.php,
tests/curriculum_manager_test.php: OK (19 tests, 243 assertions)
local_flwcupkp_testsuite: OK (95 tests, 753 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
live upgrade.php --non-interactive: passed
live purge_caches.php: passed
live service smoke: CM2 ready, Foundation frozen, next_allowed_gate CM3
live page render smokes: mappings.php and entity.php passed, no missing strings
```

## Next

Program 3 Gate CM3 should add bulk coverage management and governance UI at FLW
scale, preserving CM1/CM2 contracts and still avoiding adaptive logic.
