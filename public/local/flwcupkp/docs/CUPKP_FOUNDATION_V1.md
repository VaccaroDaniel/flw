# C-UP-KP Foundation V1

Status: frozen

Date: 2026-08-28

Gate: Program 3 Gate C5

Contract version:

```text
FLW_CUPKP_FOUNDATION_V1
```

Foundation V1 freezes the C-UP-KP semantic surface before evidence
reprocessing, mastery-policy, adaptive-path, or learner-UX intelligence work
continues. It does not add adaptive logic.

## Recorded Versions

| Identifier | Value |
| --- | --- |
| `curriculum_contract_version` | `FLW_CUPKP_CURRICULUM_CONTRACT_V1` |
| `relationship_contract_version` | `FLW_CUPKP_RELATIONSHIP_GRAPH_V1` |
| `evidence_policy_version` | `cupkp-evidence-quality-v1` |
| `foundation_contract_version` | `FLW_CUPKP_FOUNDATION_V1` |
| `history_contract_version` | `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` |

Component contracts:

- `FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1`
- `FLW_CUPKP_ONTOLOGY_BOUNDARY_V1`
- `FLW_CUPKP_RELATIONSHIP_GRAPH_V1`
- `FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1`
- `FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1`
- `FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1`

## Authoritative Implementations

| Area | Authoritative implementation |
| --- | --- |
| Competency identification | `local_flwcupkp\local\canonical_domain_model`, `flwcupkp_comp` |
| KP identification | `local_flwcupkp\local\canonical_domain_model`, `flwcupkp_kp` |
| UP identification | `local_flwcupkp\local\canonical_domain_model`, `flwcupkp_up` |
| Ontology rules | `local_flwcupkp\local\ontology_boundary` |
| Relationships and prerequisites | `local_flwcupkp\local\relationship_graph_contract` |
| Content mappings | `local_flwcupkp\local\content_evidence_mapping_contract` |
| Evidence semantics | `local_flwcupkp\local\evidence_semantics_quality_contract` |
| Evidence write/provenance guard | `local_flwcupkp\local\evidence_guard` |
| Lifecycle and versioning | `local_flwcupkp\local\lifecycle_governance_contract` |
| Package validation | `local_flwcupkp\local\validator` |

## Frozen Invariants

1. Competencies are identified by `flwcupkp_comp.id` plus stable `externalid`.
   Their meaning is integrated ability, not a single quiz item or KP.
2. Knowledge Points are identified by `flwcupkp_kp.id` plus stable `externalid`.
   Their meaning is required knowledge.
3. Use Points are identified by `flwcupkp_up.id` plus stable `externalid`.
   Their meaning is observable use or demonstration.
4. Relationship queries must use `relationship_graph_contract`, especially
   `adjacency()`, `dependencies_for_target()`, and `where_used()`.
5. Hard prerequisites are C2 `REQUIRES` edges with `mandatory` requirement.
6. Content mappings use stable Program 1 identity facts and C3 object-map roles.
   Titles are not identity.
7. Evidence representation is C3B metadata in `flwcupkp_evidence.rubricjson`:
   History V1 source key, result state, performance mode, direct/inferred flag,
   retry semantics, quality dimensions, and evidence policy version.
8. Deprecated and archived records remain available for history and explanation.
   New active links must follow C4 lifecycle governance.
9. Published semantic rows are immutable. New meanings require clone/revision
   behavior and explicit lifecycle or replacement links.
10. Evidence, mastery, adaptive, and UX consumers may read Foundation V1 but
    C5 itself does not change learner state, mastery policy, or adaptive path
    selection.

## Adaptive-Path Allowed APIs

Adaptive-path gates after C5 may call these read-only APIs:

- `canonical_domain_model::contract()`
- `canonical_domain_model::target_types()`
- `canonical_domain_model::kp_domains()`
- `ontology_boundary::contract()`
- `relationship_graph_contract::contract()`
- `relationship_graph_contract::adjacency()`
- `relationship_graph_contract::dependencies_for_target()`
- `relationship_graph_contract::where_used()`
- `content_evidence_mapping_contract::contract()`
- `content_evidence_mapping_contract::identity_from_object()`
- `content_evidence_mapping_contract::content_mapping_status()`
- `evidence_semantics_quality_contract::contract()`
- `evidence_semantics_quality_contract::semantics_for_evidence()`
- `evidence_semantics_quality_contract::source_key_from_evidence()`
- `evidence_semantics_quality_contract::quality_profile()`
- `lifecycle_governance_contract::contract()`
- `lifecycle_governance_contract::lifecycle_statuses()`
- `foundation_v1_contract::contract()`
- `foundation_v1_contract::version_record()`
- `foundation_v1_contract::foundation_status()`

## Still Forbidden After C5

- Raw Moodle log reads as normal learner-intelligence input.
- Adaptive path ranking or selection.
- New mastery-state policy writes.
- History V1 evidence reprocessing writes.
- Learning-goal creation.

## Readiness Status

Code entry point:

```text
local_flwcupkp\local\foundation_v1_contract::foundation_status()
```

Foundation V1 passes only when there are no unresolved `BLOCKER` or `HIGH`
findings. Dependency warnings are retained as `MEDIUM` findings and do not block
the freeze.

Program 3 Gate C5B added the read-only admin Foundation Inspector on
2026-08-29. Program 3 Gate CM1 then added operational curriculum authoring over
that frozen surface. Program 3 Gate CM2 added controlled relationship editing
with where-used impact previews. The current next allowed gate is:

```text
CM3
```
