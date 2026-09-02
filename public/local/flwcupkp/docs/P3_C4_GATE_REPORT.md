# Program 3 Gate C4 Report

Status: complete

Date: 2026-08-28

## Completed

- Froze `FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1`.
- Added `local_flwcupkp\local\lifecycle_governance_contract`.
- Defined canonical lifecycle states: `draft`, `review`, `approved`,
  `published`, `deprecated`, and `archived`.
- Preserved legacy status aliases, including `validated -> approved`,
  `active -> published`, and `reference -> published`.
- Added transition governance for framework, competency, UP, and KP writes.
- Blocked in-place semantic overwrites of published curriculum rows.
- Preserved framework clone/version behavior as the required revision path for
  published semantic changes.
- Added C4 package validation under `validator::validate_package()['governance']`.
- Guarded JSON imports, CSV mapping imports, manual entity saves, manual mapping
  saves, mapping deletes, bulk status changes, and framework version cloning.
- Enforced `REPLACED_BY` replacement semantics: deprecated/archived source and
  approved/published successor.
- Prevented physical deletion of object mappings that already carry learner
  evidence.
- Added read-only C4 runtime status through `governance_status()`.
- Updated Program 3 repository audit, plugin README, implementation plan, gap
  plan, package schema, version checkpoint, and tests.

## Files

Plugin:

- `classes/local/lifecycle_governance_contract.php`
- `classes/local/curriculum_manager.php`
- `classes/local/import_service.php`
- `classes/local/ontology_boundary.php`
- `classes/local/validator.php`
- `classes/local/program3_repository_audit.php`
- `tests/lifecycle_governance_contract_test.php`
- `tests/curriculum_manager_test.php`
- `tests/program3_repository_audit_test.php`
- `schemas/cupkp_package.schema.json`
- `curriculum.php`
- `README.md`
- `version.php`
- `db/upgrade.php`

Documentation:

- `docs/cupkp/P3_C4_LIFECYCLE_GOVERNANCE_CONTRACT.md`
- `docs/cupkp/P3_C4_VALIDATION_MATRIX.md`
- `docs/cupkp/P3_C4_GATE_REPORT.md`
- `docs/cupkp/P3_C4_MANIFEST.json`
- `docs/cupkp/CUPKP_M4_GOVERNANCE.md`

## Validation

```text
PHP lint: pass
Focused C4 PHPUnit: OK (9 tests, 25 assertions)
Neighboring affected PHPUnit: OK (35 tests, 355 assertions)
local_flwcupkp_testsuite: OK (77 tests, 602 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
Live Moodle upgrade: pass
Moodle cache purge: pass
```

## Live Smoke

Course `124`, unit `U038`:

```text
plugin_version: 2026082808
c4_status: frozen
c2: frozen
c3: frozen
c3b: frozen
history_v1: ready
duplicate_codes: 0
invalid_relationships: 0
up_without_competency: 0
kp_without_up: 0
object_without_target: 0
published_targets_missing_evidence_routes: 2
invalid_replacements: 0
invalid_published_states: 0
repository_audit_status: ready_for_c5
lifecycle_governance_contract class: present
next_allowed_gate: C5
```

Live warnings:

- `UP FLW-REW-B1-UP-038-01 is published without an object evidence route.`
- `UP FLW-REW-B1-UP-038-02 is published without an object evidence route.`

These are warnings, not blockers. Existing U038 KP evidence routes and rollups
remain valid; C4 now exposes the missing direct UP routes for future authoring
cleanup.

## Stop Boundary

C4 did not implement:

- Foundation V1 publication;
- adaptive path selection;
- learner path generation;
- History V1 source capture or reprocessing;
- mastery recalculation or quality-weighted mastery;
- raw Moodle log scraping.

## Next Gate

Program 3 Gate C5: freeze Foundation V1 for evidence, mastery, adaptive, and UX
consumption.
