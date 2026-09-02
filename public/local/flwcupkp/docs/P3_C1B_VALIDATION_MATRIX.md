# Program 3 Gate C1B Validation Matrix

Status: complete

Date: 2026-08-28

## Contract Checks

| Requirement | Implementation | Coverage |
| --- | --- | --- |
| Freeze ontology boundary contract | `ontology_boundary::contract()` | `test_contract_extends_c1_and_preserves_history_boundary` |
| Depend on C1 canonical model | `depends_on = FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1` | `test_contract_extends_c1_and_preserves_history_boundary` |
| Preserve History V1 boundary | C1B contract reuses C1 source-history boundary | `test_contract_extends_c1_and_preserves_history_boundary` |
| Provide authoring examples/counterexamples | `authoring_reference` | `test_contract_extends_c1_and_preserves_history_boundary` |
| Accept multi-skill/language model examples | `validate_package()` | `test_multi_skill_language_example_package_passes_boundary_validation` |
| Detect overly narrow competency | `validate_curriculum_row()` | `test_boundary_detects_overly_narrow_competency` |
| Detect KP written as task | `validate_curriculum_row()` | `test_boundary_detects_kp_written_as_task` |
| Detect UP containing unmodeled knowledge | `validate_curriculum_row()` | `test_boundary_detects_up_containing_unmodeled_new_knowledge` |
| Detect semantic duplicate across types | `validate_package()` | `test_boundary_detects_semantic_duplicate_across_types` |
| Separate ontology validation in import results | `validator::validate_package()['ontology']` | `test_validator_returns_separate_ontology_result` |
| Reject cross-framework/lifecycle package links | `validate_package()` | `test_package_mapping_rejects_cross_framework_and_lifecycle_drift` |
| Enforce manual entity/mapping saves | `curriculum_manager::save_entity()` and `save_mapping()` | `test_manual_entity_and_mapping_saves_enforce_boundary` |
| Keep C1B status read-only | `boundary_status()` | `test_boundary_status_is_read_only` |

## Live Smoke

Live plugin version:

```text
2026082804
```

Course `124`:

```text
status = guarded
gate = P3_C1B
contract = FLW_CUPKP_ONTOLOGY_BOUNDARY_V1
checked = 2 competencies, 8 UPs, 15 KPs, 12 objects, 50 mappings
findings = []
```

Course `126`:

```text
status = guarded
gate = P3_C1B
contract = FLW_CUPKP_ONTOLOGY_BOUNDARY_V1
checked = 2 competencies, 8 UPs, 15 KPs, 12 objects, 50 mappings
findings = []
```

