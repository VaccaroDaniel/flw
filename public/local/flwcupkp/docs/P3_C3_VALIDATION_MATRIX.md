# Program 3 Gate C3 - Validation Matrix

Status: complete

Date: 2026-08-28

| Area | Check | Result |
| --- | --- | --- |
| Contract shape | `content_evidence_mapping_contract::contract()` declares C3, C1/C1B/C2 dependencies, History V1 source boundary, roles, source types, and stop boundary. | Covered by PHPUnit |
| Stable identity | Learning objects require stable `externalid`; object mappings reject title-only identity such as `object_title` without `object_externalid/objectid`. | Covered by PHPUnit |
| Package validation | `validator::validate_package()` exposes separate `content_evidence` validation output. | Covered by PHPUnit |
| Import metadata | Imported learning objects preserve Program 1 identity fields, mapping-level completion overrides, and C3/History contract metadata in `metadatajson`. | Covered by PHPUnit |
| CSV validation | Activity mapping CSV validates C3 object mapping semantics and accepts `completion_counts_as_evidence`. | Covered by full suite |
| Manual authoring | `curriculum_manager` enforces C3 for object rows and object-target mappings. | Covered by full suite |
| Evidence guard | All evidence writes pass through C3 source/map validation and rubric augmentation. | Covered by PHPUnit |
| Completion guard | Completion evidence is rejected for plain practice/lesson mappings and accepted for assessment mappings. | Covered by PHPUnit |
| Source normalization | Assignment grades resolve to `grade_linked_assessment`; quiz attempts resolve to `program2_attempt`; manual teacher evidence resolves to `teacher_observation`. | Covered by PHPUnit |
| Adapter guardrails | Activity, quiz, assignment/H5P/SCORM/STT, and FLW VR Room adapters reject pedagogically invalid object maps before recording evidence. | Covered by lint/full suite |
| Runtime status | `content_mapping_status()` is read-only and reports C2/History dependencies plus mapping samples. | Covered by PHPUnit/live smoke |
| Live U038 status | Course `124`, unit `U038`: status `frozen`, C2 `frozen`, History V1 `ready`, no findings. | Passed live smoke |

## Validation Commands

PHP lint passed for:

```text
classes/local/content_evidence_mapping_contract.php
classes/local/evidence_guard.php
classes/local/activity_evidence_adapter.php
classes/local/quiz_evidence_adapter.php
classes/local/specialized_evidence_adapter.php
classes/local/flwvrroom_evidence_adapter.php
classes/local/import_service.php
classes/local/curriculum_manager.php
classes/local/validator.php
classes/local/ontology_boundary.php
db/upgrade.php
tests/content_evidence_mapping_contract_test.php
```

JSON schema parse:

```text
pass
```

PHPUnit:

```text
content_evidence_mapping_contract_test.php: OK (8 tests, 50 assertions)
local_flwcupkp_testsuite: OK (61 tests, 471 assertions)
local_flwhistory_testsuite: OK (51 tests, 384 assertions)
```

Live operations:

```text
local_flwcupkp upgraded to 2026082806
Moodle caches purged
content_mapping_status(124, "U038", 100): frozen
content_mapping_status(126, "U038", 100): frozen
```
