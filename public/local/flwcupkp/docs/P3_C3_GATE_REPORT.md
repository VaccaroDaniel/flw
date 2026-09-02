# Program 3 Gate C3 Report

Status: complete

Date: 2026-08-28

## Completed

- Froze `FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1`.
- Added `local_flwcupkp\local\content_evidence_mapping_contract`.
- Defined stable Program 1 identity fields for learning objects.
- Preserved Program 1 identity and C3/History contracts in object metadata.
- Defined canonical pedagogical roles: `TEACHES`, `PRACTICES`, `ASSESSES`,
  and `EVIDENCE_FOR`.
- Defined accepted evidence source types from Program 2 attempts, grades,
  completion, teacher observation, placement, checkpoint, and external
  assessment.
- Enforced the rule that completion is not mastery and only counts as evidence
  when the mapped role/purpose permits it.
- Preserved mapping-level `completion_counts_as_evidence` flags as object
  metadata overrides keyed by target type and target ID.
- Wired C3 into package validation, CSV validation, JSON/CSV import, manual
  curriculum saves, evidence guards, and all current evidence adapters.
- Added C3 status API `content_mapping_status()`.
- Updated ontology vocabulary, package schema, README, repository audit, and
  implementation plan.
- Added no-schema Moodle version checkpoint `2026082806`.
- Upgraded live Moodle and purged caches.

## Files

Plugin:

- `classes/local/content_evidence_mapping_contract.php`
- `classes/local/evidence_guard.php`
- `classes/local/activity_evidence_adapter.php`
- `classes/local/quiz_evidence_adapter.php`
- `classes/local/specialized_evidence_adapter.php`
- `classes/local/flwvrroom_evidence_adapter.php`
- `classes/local/import_service.php`
- `classes/local/curriculum_manager.php`
- `classes/local/validator.php`
- `classes/local/ontology_boundary.php`
- `classes/local/program3_repository_audit.php`
- `tests/content_evidence_mapping_contract_test.php`
- `schemas/cupkp_package.schema.json`
- `README.md`
- `version.php`
- `db/upgrade.php`

Documentation:

- `docs/cupkp/P3_C3_CONTENT_EVIDENCE_MAPPING_CONTRACT.md`
- `docs/cupkp/P3_C3_VALIDATION_MATRIX.md`
- `docs/cupkp/P3_C3_GATE_REPORT.md`
- `docs/cupkp/P3_C3_MANIFEST.json`

## Live Smoke

Course `124`, unit `U038`:

```text
plugin_version: 2026082806
status: frozen
c2: frozen
history_v1: ready
objects: 7
object_maps: 10
stable_identity_objects: 7
PRACTICES: 3
ASSESSES: 7
completion_evidence_allowed_maps: 8
findings: none
```

Repository audit:

```text
audit_status: ready_for_c3b
content_evidence_mapping_contract class: present
next_allowed_gate: C3B
```

Course `126`, unit `U038`:

```text
status: frozen
c2: frozen
history_v1: ready
objects: 0
object_maps: 0
findings: none
```

## Stop Boundary

C3 did not implement:

- C3B evidence semantics and quality dimensions;
- E1 History V1 evidence reprocessing;
- mastery policy changes;
- adaptive policy or path selection;
- learner UX changes;
- raw Moodle log scraping.

## Next Gate

Program 3 Gate C3B: implement evidence semantics and quality model while
preserving C1/C1B/C2/C3 foundation contracts and History V1 as the only normal
source-history input.
