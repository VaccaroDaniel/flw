# Program 3 Gate C2 Report

Status: complete

Date: 2026-08-28

## Completed

- Froze the C2 relationship graph contract in code and docs.
- Defined all eight required relation types: `SUPPORTS`, `REQUIRES`,
  `EVIDENCE_FOR`, `TRAINS`, `EXTENDS`, `ALTERNATIVE_TO`, `REVIEW_OF`,
  and `REPLACED_BY`.
- Defined allowed source/target types, direction, cardinality, symmetry,
  transitivity, cycle rules, inference behavior, version behavior, and
  deprecation behavior for each relation.
- Centralized graph validation and traversal in
  `local_flwcupkp\local\relationship_graph_contract`.
- Wired C2 into JSON package validation and exposed a separate `graph` result.
- Wired C2 into manual mapping saves.
- Wired C2 into JSON and CSV import paths.
- Added package-time and save-time hard prerequisite cycle prevention.
- Added replacement-cycle prevention.
- Updated the package schema, README, C0 audit metadata, and implementation plan.
- Added no-schema version checkpoint `2026082805`.
- Upgraded live Moodle and purged caches.

## Files

Plugin:

- `classes/local/relationship_graph_contract.php`
- `classes/local/ontology_boundary.php`
- `classes/local/validator.php`
- `classes/local/curriculum_manager.php`
- `classes/local/import_service.php`
- `classes/local/program3_repository_audit.php`
- `tests/relationship_graph_contract_test.php`
- `schemas/cupkp_package.schema.json`
- `README.md`
- `version.php`
- `db/upgrade.php`

Documentation:

- `docs/cupkp/P3_C2_RELATIONSHIP_GRAPH_CONTRACT.md`
- `docs/cupkp/P3_C2_VALIDATION_MATRIX.md`
- `docs/cupkp/P3_C2_GATE_REPORT.md`
- `docs/cupkp/P3_C2_MANIFEST.json`

## Validation

PHP lint:

- `classes/local/relationship_graph_contract.php`: pass
- `classes/local/validator.php`: pass
- `classes/local/curriculum_manager.php`: pass
- `classes/local/import_service.php`: pass
- `tests/relationship_graph_contract_test.php`: pass

JSON schema parse:

```text
pass
```

PHPUnit:

- Focused C2 test: OK, 8 tests, 123 assertions.
- Focused C1B+C2 tests: OK, 18 tests, 157 assertions.
- Full `local_flwcupkp_testsuite`: OK, 53 tests, 421 assertions.
- Full `local_flwhistory_testsuite`: OK, 51 tests, 384 assertions.

Live operations:

- `local_flwcupkp` upgraded to `2026082805`.
- Moodle caches purged.
- `relationship_graph_contract::graph_status(124, 0, 100)` returned `frozen`.
- `relationship_graph_contract::graph_status(126, 0, 100)` returned `frozen`.
- Both live checks scanned 50 graph edges and returned no findings.

## Stop Boundary

C2 did not implement:

- C3 content/evidence mapping contracts
- C3B evidence quality semantics
- C4 lifecycle/governance workflows
- C5 Foundation V1 freeze
- adaptive decision logic
- mastery recalculation
- raw Moodle log scraping

## Next Gate

Program 3 Gate C3: freeze content and evidence mapping contracts while keeping
History V1 as the only normal source-history input.
