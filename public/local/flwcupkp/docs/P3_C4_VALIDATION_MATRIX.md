# Program 3 Gate C4 Validation Matrix

Status: complete

Date: 2026-08-28

## Matrix

| Requirement | Implementation | Test |
| --- | --- | --- |
| Freeze lifecycle statuses | `lifecycle_governance_contract::contract()`, `lifecycle_statuses()`, `status_options()` | `test_contract_freezes_c4_lifecycle_and_history_boundary` |
| Preserve legacy lifecycle labels | `canonical_status()` maps `validated`, `active`, `reference`, `pilot`, `inactive`, `retired`, and `test` | `test_validator_returns_governance_result`, `curriculum_manager_test::test_bulk_update_status_updates_framework_scope_and_audits` |
| Enforce lifecycle transitions | `validate_entity_write()` and `assert_entity_write()` | `test_curriculum_save_allows_published_deprecation_without_semantic_change` |
| Prevent published semantic overwrite | `changed_semantic_fields()` blocks in-place edits to published rows | `test_curriculum_save_rejects_published_semantic_overwrite` |
| Preserve version history by clone/revision | `assert_framework_clone()` and existing `curriculum_manager::clone_framework_version()` | `curriculum_manager_test::test_clone_framework_version_copies_curriculum_graph_only` |
| Validate package governance | `validator::validate_package()` returns `governance` result from C4 | `test_validator_returns_governance_result` |
| Detect missing evidence routes | `validate_package_governance()` and `governance_status()` | `test_package_governance_requires_published_evidence_routes` |
| Validate replacements | `validate_mapping_change()` requires deprecated/archived source and approved/published successor for `REPLACED_BY` | `test_replaced_by_rejects_non_deprecated_source`, `test_replaced_by_accepts_deprecated_source_and_approved_successor` |
| Prevent deleting evidence-bearing object maps | `validate_mapping_delete()` and `curriculum_manager::delete_mapping()` | `test_object_mapping_with_learner_evidence_cannot_be_deleted` |
| Runtime governance health is read-only | `governance_status()` writes no evidence, state, or audit rows | `test_governance_status_is_read_only_and_points_to_c5` |
| Preserve History V1 boundary | C4 contract and runtime dependencies require History V1; no raw Moodle log scraping is added | `test_contract_freezes_c4_lifecycle_and_history_boundary` |

## Validation Commands

```powershell
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe -l D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwcupkp\classes\local\lifecycle_governance_contract.php
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\vendor\bin\phpunit --configuration D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\phpunit.xml D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwcupkp\tests\lifecycle_governance_contract_test.php
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\vendor\bin\phpunit --configuration D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\phpunit.xml --testsuite local_flwcupkp_testsuite
D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\vendor\bin\phpunit --configuration D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\phpunit.xml --testsuite local_flwhistory_testsuite
```

## Production Boundary

C4 governs curriculum lifecycle and version safety only. It does not own
adaptive recommendations, History V1 evidence reprocessing, or mastery policy
changes.
