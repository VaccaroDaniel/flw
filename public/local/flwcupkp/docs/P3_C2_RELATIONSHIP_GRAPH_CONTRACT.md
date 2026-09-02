# Program 3 Gate C2 - Relationship Graph Contract

Status: frozen

Date: 2026-08-28

## Contract

```text
FLW_CUPKP_RELATIONSHIP_GRAPH_V1
```

C2 freezes the graph semantics used by C-UP-KP curriculum mappings. It depends
on:

- `FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1`
- `FLW_CUPKP_ONTOLOGY_BOUNDARY_V1`
- `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` as the only normal source
  history input

## Relation Types

| Relation | Direction | Cardinality | Symmetric | Transitive | Cycle rule |
| --- | --- | --- | --- | --- | --- |
| `SUPPORTS` | source supports target | many-to-many | no | no | allowed, not inferred |
| `REQUIRES` | source requires target | many-to-many | no | yes for dependency analysis only | hard prerequisite cycles forbidden |
| `EVIDENCE_FOR` | object produces evidence for target | many-to-many | no | no | not applicable |
| `TRAINS` | object trains target | many-to-many | no | no | not applicable |
| `EXTENDS` | source extends target | many-to-many same type | no | no | discouraged, not inferred |
| `ALTERNATIVE_TO` | undirected peer pair | many-to-many same type | yes | no | allowed |
| `REVIEW_OF` | source reviews target | many-to-many | no | no | allowed, not inferred |
| `REPLACED_BY` | source replaced by target | one successor unless split | no | yes for lineage only | forbidden |

## Existing Table Semantics

| Table | Direction | C2 semantic source |
| --- | --- | --- |
| `flwcupkp_comp_up` | competency -> UP | `role` |
| `flwcupkp_up_kp` | UP -> KP | `role` |
| `flwcupkp_kp_prereq` | KP -> prerequisite KP | `relationshiptype` and `requirement` |
| `flwcupkp_object_map` | object -> C/UP/KP target | `role` |

Layered `extension` roles on `comp_up` and `up_kp` are treated as `SUPPORTS`.
Same-type lineage `EXTENDS` is reserved for same-type graph edges such as
KP-to-KP extension/replacement semantics.

## Hard Prerequisites

A KP prerequisite is hard only when:

```text
mapping table = flwcupkp_kp_prereq
semantic relation = REQUIRES
requirement = mandatory
```

Hard prerequisite cycles are invalid. Soft `SUPPORTS`, `REVIEW_OF`, and
`ALTERNATIVE_TO` links do not create dependency inference.

## Central APIs

The frozen service is:

```text
local_flwcupkp\local\relationship_graph_contract
```

It owns:

- `contract()`
- `relation_types()`
- `semantic_for_mapping()`
- `validate_mapping_row()`
- `assert_mapping_row()`
- `assert_mapping_change()`
- `validate_package_graph()`
- `detect_hard_prerequisite_cycles()`
- `adjacency()`
- `dependencies_for_target()`
- `where_used()`
- `graph_status()`

## Stop Boundary

C2 does not implement adaptive path choice, mastery recalculation, evidence
quality policy, or raw Moodle log scraping.
