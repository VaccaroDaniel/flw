# Program 3 Gate CM1 Gate Report

Date: 2026-08-29

Status: complete

## Implemented

- Added `local_flwcupkp\local\core_curriculum_manager`.
- Added CM1 readiness/status, navigation model, selected-entity detail model,
  permission matrix, and workflow-action discovery.
- Added selected entity page at `/local/flwcupkp/entity.php`.
- Updated `/local/flwcupkp/curriculum.php` with CM1 navigation facets and
  governed detail links.
- Updated direct entity editing so published/deprecated semantic rows are not
  overwritten in place.
- Added governed per-row lifecycle transitions with audit log writes.
- Advanced repository/foundation runtime handoff to CM2.
- Added CM1 PHPUnit coverage and Behat page expectation.

## Preserved Boundaries

- History V1 remains the only normal source-history input.
- Foundation V1 remains read-only.
- No adaptive path selection was added.
- No mastery-policy recalculation or learner-state mutation was added.
- No raw Moodle log scraping was introduced.

## Validation

```text
php -l changed PHP files: passed
tests/core_curriculum_manager_test.php: OK (6 tests, 42 assertions)
tests/foundation_v1_contract_test.php: OK (6 tests, 60 assertions)
tests/program3_repository_audit_test.php: OK (3 tests, 114 assertions)
tests/curriculum_manager_test.php: OK (4 tests, 25 assertions)
local_flwcupkp_testsuite: OK (89 tests, 708 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
```

## Next

Program 3 Gate CM2 should add controlled relationship/prerequisite editing,
where-used impact previews, and coverage governance without changing adaptive
policy.
