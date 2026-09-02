# Program 3 Gate C1 - Canonical C-UP-KP Domain Model

Status: complete

Date: 2026-08-28

## Frozen Contract

Contract:

```text
FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1
```

Implementation entry point:

```text
local_flwcupkp\local\canonical_domain_model
```

Gate:

```text
P3_C1
```

## Entity Meanings

Competency:

```text
Meaningful integrated ability a learner can demonstrate in a real communicative
or operational context.
```

Use Point:

```text
Observable use point describing how relevant knowledge must be used or
demonstrated.
```

Knowledge Point:

```text
Linguistic, strategic, cultural, procedural, or content knowledge needed for
use.
```

## Topology

C1 explicitly does not force a strict C -> UP -> KP tree.

Frozen relation shape:

```text
competency_to_up = many_to_many
up_to_kp = many_to_many
kp_to_prerequisite_kp = many_to_many
learning_object_to_target = many_to_many
```

Existing tables that carry this shape:

- `flwcupkp_comp_up`
- `flwcupkp_up_kp`
- `flwcupkp_kp_prereq`
- `flwcupkp_object_map`

## Stable Code Policy

Canonical examples:

```text
C-FR-A2-SI-004
KP-FR-A2-FUNC-031
UP-FR-A2-SI-031-04
```

Existing FLW-style imported identifiers remain valid when they carry a clear
semantic marker, for example:

```text
FLW-REW-B1-UP-038-01
FLW-EN-B1-LEX-038-001
```

Wrong entity prefixes are rejected. Untyped legacy IDs are allowed only with a
warning so existing content is not broken during the C1 freeze.

## CEFR And FLW Stage

CEFR level is frozen as official macro level only:

```text
PRE-A1, A1, A2, B1, B2, C1, C2
```

FLW stage is separate from CEFR. The model rejects A2.1/A2.2-style pseudo-CEFR
values in CEFR fields and also rejects them when used as stage labels. This
prevents UI mockups from silently becoming curriculum semantics.

## Learner State Separation

Curriculum definition rows must not store learner mastery fields. Learner state
belongs in:

```text
flwcupkp_state
```

Evidence belongs in:

```text
flwcupkp_evidence
```

The C1 validator rejects mastery/confidence/override-style fields on
competency, UP, and KP definition rows.

## History Boundary

Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

Program 3 must consume source history through the History V1 services from
`local_flwhistory`. Raw Moodle logs remain diagnostic-only and are not normal
C-UP-KP/adaptive inputs.

