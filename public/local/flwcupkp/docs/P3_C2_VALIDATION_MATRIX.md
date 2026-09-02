# Program 3 Gate C2 Validation Matrix

Status: passed

Date: 2026-08-28

| Requirement | Implementation | Verification |
| --- | --- | --- |
| Freeze relation semantics | `relationship_graph_contract::contract()` defines all eight relation types | `test_contract_freezes_all_relation_attributes_and_boundaries` |
| Preserve C1/C1B contracts | Contract depends on C1 and C1B versions | `test_contract_freezes_all_relation_attributes_and_boundaries` |
| Preserve History V1 boundary | Contract names `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` | `test_contract_freezes_all_relation_attributes_and_boundaries` |
| Map existing table roles to semantics | `semantic_for_mapping()` maps current mapping rows | `test_existing_mapping_shapes_resolve_to_frozen_semantics` |
| Forbid hard prerequisite cycles | Package and save-time cycle detection | `test_package_graph_rejects_hard_prerequisite_cycle`, `test_manual_mapping_save_rejects_new_hard_prerequisite_cycle` |
| Keep current FLW support vocabulary valid | Current support labels map to `SUPPORTS` unless mandatory requires otherwise | `test_package_graph_accepts_current_support_prerequisite_vocabulary` |
| Expose graph result from package validation | `validator::validate_package()` returns `graph` | `test_validator_exposes_c2_graph_result` |
| Centralize traversal/query APIs | `adjacency()`, `dependencies_for_target()`, `where_used()` | `test_adjacency_dependencies_and_where_used_are_centralized` |
| Keep status check read-only | `graph_status()` does not write evidence, state, or audit rows | `test_graph_status_is_read_only` |

## Command Results

```text
PHP lint: pass
JSON schema parse: pass
Focused C2 PHPUnit: OK (8 tests, 123 assertions)
Focused C1B+C2 PHPUnit: OK (18 tests, 157 assertions)
local_flwcupkp_testsuite: OK (53 tests, 421 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
Live upgrade: pass
Live cache purge: pass
Live graph status course 124: frozen, 50 edges, no findings
Live graph status course 126: frozen, 50 edges, no findings
```
