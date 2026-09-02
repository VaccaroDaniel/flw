# Program 3 Gate C1B - Ontology Boundary

Status: complete

Date: 2026-08-28

## Frozen Contract

Contract:

```text
FLW_CUPKP_ONTOLOGY_BOUNDARY_V1
```

Implementation entry point:

```text
local_flwcupkp\local\ontology_boundary
```

Dependency:

```text
FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1
```

## Purpose

C1B prevents C/KP/UP category drift after the C1 canonical model freeze.

Operational authoring checks:

- Competency: is this a meaningful integrated ability?
- KP: does linguistic/content knowledge itself define the object?
- UP: is this the same knowledge, but a different required use or demonstration?

## Boundary Rules

C1B detects:

- overly narrow competency;
- KP written as a learner task;
- UP containing unmodeled new knowledge;
- semantic duplicate across C/UP/KP types;
- unsupported status, role, purpose, or evidence-strength vocabulary;
- lifecycle-incompatible package mappings.

## Current Vocabulary

Entity statuses:

```text
draft, validated, active, reference, pilot, inactive, archived, deprecated,
retired, test
```

Relationship roles:

```text
required, supporting, support, optional, extension, remediation, enrichment,
assessment, evidence
```

Object/map roles:

```text
teaches, trains, practice, practices, assessment, assesses, evidence_for,
diagnostic, placement, review, review_of, remediation, extension, project
```

Object purposes:

```text
lesson, teach, practice, assessment, diagnostic, placement, review, remediation,
extension, project, instruction, practice_evidence, performance_evidence,
integrated_performance
```

Evidence strengths:

```text
recognition, guided_performance, controlled_production,
independent_performance, direct_performance, indirect_signal, diagnostic,
checkpoint, weak, medium, strong
```

Prerequisite relationship labels remain current vocabulary only. C2 will freeze
full graph semantics.

## Stop Boundary

C1B does not implement adaptive decisions, mastery recalculation, evidence
quality policy, or C2 relationship direction/cardinality semantics.

Normal source-history input remains History V1:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

