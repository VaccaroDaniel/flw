# Program 3 Gate C3B Validation Matrix

Status: complete

Date: 2026-08-28

## Matrix

| Requirement | Implementation | Test |
| --- | --- | --- |
| Freeze evidence-event semantics | `evidence_semantics_quality_contract::contract()` | `test_contract_freezes_semantics_quality_and_history_boundary` |
| Preserve History V1 as normal source boundary | C3B contract and source key metadata store `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` and `use_history_v1_adapter_not_raw_moodle_logs` | `test_contract_freezes_semantics_quality_and_history_boundary`, `test_semantic_normalization_is_deterministic` |
| Represent result states | `positive`, `negative`, `partial`, `inconclusive` | `test_result_states_separate_positive_partial_negative_and_inconclusive` |
| Inconclusive must not directly reduce mastery | Mastery score weighting skips explicit C3B `inconclusive` rows | `test_inconclusive_evidence_does_not_directly_reduce_mastery` |
| Represent performance modes | C3B mode normalization translates current evidence strengths into frozen conceptual modes | `test_semantic_normalization_is_deterministic` |
| Preserve direct/inferred evidence | C3B stores `evidence_direction`; inferred evidence stores `inference_path` | `test_semantic_normalization_is_deterministic` |
| Represent quality dimensions | C3B stores 10 normalized dimensions in `rubricjson` | `test_semantic_normalization_is_deterministic` |
| Retry semantics | Hints or answer exposure lower independence, support level, and confidence | `test_retry_and_explicit_quality_semantics_are_preserved` |
| Evidence ceilings | C3B stores advisory `evidence_ceiling_hint` without changing thresholds | `test_evidence_guard_augments_stored_evidence_and_keeps_policy_versions_separate` |
| Evidence policy versioning | `cupkp-evidence-quality-v1` is separate from mastery `ruleversion` | `test_evidence_guard_augments_stored_evidence_and_keeps_policy_versions_separate` |
| Read-only status API | `evidence_semantics_status()` does not write evidence, state, or audit rows | `test_evidence_semantics_status_is_read_only` |

## Validation Commands

```powershell
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe -l D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwcupkp\classes\local\evidence_semantics_quality_contract.php
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\vendor\bin\phpunit --configuration D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\phpunit.xml local_flwcupkp\evidence_semantics_quality_contract_test D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwcupkp\tests\evidence_semantics_quality_contract_test.php
```

## Production Boundary

C3B is a semantics and quality model only. It does not perform History V1
reprocessing, adaptive recommendation selection, or quality-weighted mastery
policy decisions.
