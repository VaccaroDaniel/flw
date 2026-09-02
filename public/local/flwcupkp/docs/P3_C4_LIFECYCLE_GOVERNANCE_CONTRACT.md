# Program 3 Gate C4 - Lifecycle, Versioning, and Governance

Status: complete

Date: 2026-08-28

Frozen contract:

```text
FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1
```

## Purpose

C4 makes curriculum truth governable after the C1/C1B/C2/C3/C3B foundation
contracts are frozen. It controls status changes, version history, replacement
links, and validation findings without changing evidence semantics, mastery
rules, adaptive path selection, or the History V1 source boundary.

Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
use_history_v1_adapter_not_raw_moodle_logs
```

## Lifecycle

Canonical statuses:

- `draft`
- `review`
- `approved`
- `published`
- `deprecated`
- `archived`

Legacy import aliases remain accepted:

| Legacy | Canonical |
| --- | --- |
| `validated` | `approved` |
| `active` | `published` |
| `reference` | `published` |
| `pilot` | `review` |
| `inactive` | `archived` |
| `retired` | `archived` |
| `test` | `draft` |

Allowed transitions:

| From | To |
| --- | --- |
| `draft` | `draft`, `review`, `approved`, `archived` |
| `review` | `draft`, `review`, `approved`, `archived` |
| `approved` | `review`, `approved`, `published`, `deprecated`, `archived` |
| `published` | `published`, `deprecated` |
| `deprecated` | `deprecated`, `archived` |
| `archived` | `archived` |

## Versioning

Published semantic changes are immutable in place. If a published framework,
competency, UP, or KP needs semantic changes, admins must clone/revision the
framework graph and then deprecate or replace the old row.

Accepted semantic versions include numeric versions such as `1.0` and legacy
revision-prefixed labels such as `reference-1.0`.

Framework clone behavior:

- copies framework, C/UP/KP rows, learning objects, and mappings;
- resets cloned rows to `draft`;
- gives cloned rows new external IDs by suffix;
- does not clone learner evidence, learner states, recommendations, imports, or
  audit rows;
- clears Moodle-native framework, competency, course module, and course links
  that must be re-linked deliberately.

## Deprecation And Replacement

C4 preserves published entities that carry learner evidence. The operational
path is:

```text
PUBLISHED -> DEPRECATED
DEPRECATED source --REPLACED_BY--> APPROVED or PUBLISHED successor
DEPRECATED -> ARCHIVED when appropriate
```

`REPLACED_BY` is governed through the frozen C2 relationship graph. Replacement
links are lineage only; they do not copy learner evidence or mastery state.

## Validation

C4 classifies:

- duplicate codes;
- invalid relationships;
- cycles reported by C2;
- orphan UP/KP/object rows;
- missing evidence routes;
- invalid replacements;
- invalid published states;
- published semantic overwrites.

Severity levels:

- `ERROR`: blocks package validity or runtime readiness.
- `WARNING`: highlights authoring gaps that do not block the frozen contract.
- `INFO`: describes normalized contract facts.

## APIs

```text
local_flwcupkp\local\lifecycle_governance_contract::contract()
local_flwcupkp\local\lifecycle_governance_contract::lifecycle_statuses()
local_flwcupkp\local\lifecycle_governance_contract::status_options()
local_flwcupkp\local\lifecycle_governance_contract::canonical_status()
local_flwcupkp\local\lifecycle_governance_contract::normalize_entity_payload()
local_flwcupkp\local\lifecycle_governance_contract::validate_entity_write()
local_flwcupkp\local\lifecycle_governance_contract::assert_entity_write()
local_flwcupkp\local\lifecycle_governance_contract::validate_mapping_change()
local_flwcupkp\local\lifecycle_governance_contract::assert_mapping_change()
local_flwcupkp\local\lifecycle_governance_contract::validate_mapping_delete()
local_flwcupkp\local\lifecycle_governance_contract::assert_mapping_delete()
local_flwcupkp\local\lifecycle_governance_contract::assert_framework_clone()
local_flwcupkp\local\lifecycle_governance_contract::validate_package_governance()
local_flwcupkp\local\lifecycle_governance_contract::governance_status()
```

## Stop Boundary

C4 does not implement:

- adaptive policy changes;
- learner path generation;
- History V1 source capture;
- History V1 evidence reprocessing;
- mastery recalculation or quality-weighted mastery;
- raw Moodle log scraping.

Next gate:

```text
Program 3 Gate C5 - Foundation Freeze V1
```
