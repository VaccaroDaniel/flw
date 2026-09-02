# Program 3 Gate C0 - C1-C5 Foundation Gap Plan

Status: complete

Date: 2026-08-28

## C1 - Canonical C-UP-KP Domain Model

Gaps to close:

- Freeze Competency, Use Point, and Knowledge Point meanings in code and docs.
- Validate stable semantic codes.
- Separate CEFR level from FLW stage.
- Confirm many-to-many topology without forcing a strict tree.

Likely implementation surface:

- `classes/local/curriculum_manager.php`
- `classes/local/validator.php`
- `schemas/cupkp_package.schema.json`
- `db/install.xml` if new nullable semantic fields are needed
- API and import validation tests

## C1B - Ontology Boundary + Validation

Gaps to close:

- Define allowed entity roles, domains, statuses, and relationship vocabularies.
- Reject cross-framework, circular, duplicate, and lifecycle-incompatible links.
- Separate curriculum ontology errors from file-format/import errors.

Likely implementation surface:

- `classes/local/curriculum_manager.php`
- `classes/local/import_service.php`
- `classes/local/validator.php`
- `tests/curriculum_manager_test.php`
- new ontology invariant tests

## C2 - Relationships + Prerequisites

Status: complete as of Program 3 Gate C2.

Closed:

- Freeze relationship direction and prerequisite strength semantics.
- Add graph traversal and cycle checks for KP prerequisites and C-UP-KP
  dependencies.
- Expose where-used and dependency impact as read-only services before editing
  UX expands.

Implemented surface:

- `flwcupkp_comp_up`
- `flwcupkp_up_kp`
- `flwcupkp_kp_prereq`
- `flwcupkp_object_map`
- `classes/local/relationship_graph_contract.php`
- `classes/local/curriculum_manager.php`
- `classes/local/import_service.php`
- `classes/local/validator.php`
- `tests/relationship_graph_contract_test.php`

## C3 - Content + Evidence Mapping Contracts

Status: complete as of Program 3 Gate C3.

Closed:

- Preserve Program 1 identity metadata on learning objects.
- Freeze canonical object-map roles: `TEACHES`, `PRACTICES`, `ASSESSES`, and
  `EVIDENCE_FOR`.
- Preserve unresolved Program 1 identities as unresolved facts, not fabricated
  title-based mappings.
- Define and enforce completion as evidence only when object mapping rules
  permit it.

Implemented surface:

- `flwcupkp_object`
- `flwcupkp_object_map`
- `flwcupkp_evidence.rubricjson`
- `classes/local/content_evidence_mapping_contract.php`
- `classes/local/evidence_guard.php`
- `classes/local/import_service.php`
- `classes/local/curriculum_manager.php`
- `classes/local/validator.php`
- evidence adapter classes
- `tests/content_evidence_mapping_contract_test.php`

## C3B - Evidence Semantics + Quality Model

Status: complete as of Program 3 Gate C3B.

Closed:

- Add History V1 contract/source keys to C-UP-KP evidence metadata.
- Represent result state: positive, negative, partial, inconclusive.
- Represent performance mode, evidence role, and direct/inferred evidence.
- Represent quality dimensions such as validity, reliability, independence,
  authenticity, production demand, contextual transfer, support level,
  difficulty, recency, and confidence.
- Version evidence policy separately from mastery policy.
- Preserve attempt/retry semantics and advisory evidence ceilings.

Implemented surface:

- `flwcupkp_evidence.rubricjson`
- `classes/local/evidence_semantics_quality_contract.php`
- `classes/local/evidence_guard.php`
- `classes/local/mastery_engine.php`
- `classes/local/program3_repository_audit.php`
- `tests/evidence_semantics_quality_contract_test.php`

## C4 - Lifecycle + Versioning + Governance

Status: complete as of Program 3 Gate C4.

Closed:

- Freeze lifecycle states: `DRAFT`, `REVIEW`, `APPROVED`, `PUBLISHED`,
  `DEPRECATED`, and `ARCHIVED`.
- Preserve legacy FLW lifecycle labels as aliases without keeping them as new
  canonical values.
- Prevent published semantic rows from being overwritten in place.
- Preserve framework clone/version behavior as the revision path for published
  semantic changes.
- Enforce `REPLACED_BY` governance with deprecated/archived source and
  approved/published successor.
- Prevent physical deletion of object mappings that already carry learner
  evidence.
- Classify duplicate codes, invalid relationships, orphan rows, missing evidence
  routes, invalid replacements, and invalid published states.

Implemented surface:

- `classes/local/lifecycle_governance_contract.php`
- `classes/local/curriculum_manager.php`
- `classes/local/import_service.php`
- `classes/local/validator.php`
- `classes/local/program3_repository_audit.php`
- `schemas/cupkp_package.schema.json`
- `tests/lifecycle_governance_contract_test.php`

## C5 - Foundation Freeze V1

Status: complete as of Program 3 Gate C5.

Closed:

- Published `FLW_CUPKP_FOUNDATION_V1`.
- Recorded `curriculum_contract_version`, `relationship_contract_version`, and
  `evidence_policy_version`.
- Created invariant tests for C1-C4 dependency readiness through the Foundation
  V1 status service.
- Created read-only migration checks for source keys, mappings, evidence
  semantics, and lifecycle states.
- Defined the exact read-only APIs evidence, mastery, adaptive, and UX consumers
  may rely on after C5.
- Stopped adaptive-path work until a later gate explicitly introduces adaptive
  logic.

Implemented surface:

- `classes/local/foundation_v1_contract.php`
- `classes/local/program3_repository_audit.php`
- `tests/foundation_v1_contract_test.php`
- `tests/program3_repository_audit_test.php`
- `docs/cupkp/CUPKP_FOUNDATION_V1.md`
- `docs/cupkp/P3_C5_VALIDATION_MATRIX.md`
- `docs/cupkp/P3_C5_GATE_REPORT.md`
- `docs/cupkp/P3_C5_MANIFEST.json`
- plugin README and version checkpoint
- PHPUnit invariant suite
- live smoke checks on available course data

## C5B Foundation Inspector

C5B is complete as of 2026-08-29. It adds a read-only admin Foundation
Inspector page at:

```text
/local/flwcupkp/foundation.php
```

The inspector displays Foundation V1 status, dependency contracts, migration
readiness, non-blocking findings, C/UP/KP entity rows, prerequisite graph
relations, content/evidence mappings, authoritative implementation ownership,
and the adaptive API boundary. It does not write learner state, change mastery
policy, reprocess History V1 evidence, or add adaptive logic.

The next build gate is CM1, the Core C-UP-KP Curriculum Manager.
