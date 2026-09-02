# Program 3 Gate C1 Validation Matrix

Status: complete

Date: 2026-08-28

## Contract Checks

| Requirement | Implementation | Coverage |
| --- | --- | --- |
| Freeze C, UP, and KP meanings | `canonical_domain_model::contract()` | `test_contract_freezes_meanings_topology_and_history_boundary` |
| Preserve many-to-many topology | `contract()['topology']` and existing mapping tables | `test_contract_freezes_meanings_topology_and_history_boundary` |
| Preserve History V1 as normal source input | `source_history_boundary` delegates to A0 contract | `test_contract_freezes_meanings_topology_and_history_boundary` |
| Accept canonical semantic codes | `semantic_code_status()` | `test_semantic_code_policy_accepts_canonical_and_existing_flw_styles` |
| Accept existing FLW-style U038 codes | `semantic_code_status()` | `test_semantic_code_policy_accepts_canonical_and_existing_flw_styles` |
| Reject wrong entity prefixes | `semantic_code_status()` | `test_semantic_code_policy_rejects_wrong_entity_prefix` |
| Separate CEFR from FLW stage | `validate_curriculum_row()` | `test_cefr_and_stage_are_kept_separate` |
| Reject A2.1/A2.2 pseudo-CEFR values | CEFR and stage validators | `test_cefr_and_stage_are_kept_separate` |
| Reject learner mastery fields on curriculum definitions | `validate_curriculum_row()` | `test_learner_mastery_fields_are_rejected_on_curriculum_definitions` |
| Apply C1 checks during imports | `validator::validate_package()` | `test_package_validation_uses_c1_semantics` |
| Apply C1 checks during manual entity save | `curriculum_manager::save_entity()` | `test_curriculum_save_rejects_learner_state_on_definition` |
| Keep C1 status read-only | `freeze_status()` | `test_freeze_status_is_read_only` |

## Live Smoke

Live plugin version:

```text
2026082803
```

Course `124`:

```text
status = frozen
gate = P3_C1
contract = FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1
history.status = ready
history.requiredcontract = FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
history.normpolicyversion = H1B-20260827.1
findings = []
```

Course `126`:

```text
status = frozen
gate = P3_C1
contract = FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1
history.status = ready
history.requiredcontract = FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
history.normpolicyversion = H1B-20260827.1
findings = []
```

