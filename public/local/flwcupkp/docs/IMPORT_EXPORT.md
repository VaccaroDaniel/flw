# Import and Export

Imports use JSON as the canonical package format. CSV unit artifacts are supported for the shipped mapping templates and are imported through the same validation, checksum, and audit path.

Required import behavior:

- schema validation;
- checksum tracking;
- idempotent re-import;
- transactional writes;
- warnings and errors;
- import batch audit;
- rollback status marker.

Example packages are stored in `local/flwcupkp/fixtures`.

JSON package keys include `learning_objects`, `lesson_mappings`, `activity_mappings`,
`project_competency_mappings`, `assessment_rules`, and `project_evidence`. Lesson mapping
rows are normalized into learning objects plus object-target mappings; project competency
mapping rows are normalized into project-to-competency activity mappings.

Named template artifacts are stored in `local/flwcupkp/templates`:

- `unit_control_packet.json`
- `cupkp_map.json`
- `lesson_cupkp_map.json`
- `activity_cupkp_mapping.csv`
- `quiz_kp_mapping.csv`
- `project_competency_mapping.json`
- `cupkp_validation_report.json`

CSV import supports:

- `activity_mappings`: `object_externalid,target_type,target_externalid,role,evidence_strength`
- `quiz_kp_mappings`: `item_id,object_externalid,kp_externalid,evidence_strength,notes`

CLI examples:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/activity_cupkp_mapping.csv --format=csv --type=activity_mappings
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings
```

The admin Import / Export page can paste JSON packages, paste CSV rows, or read safe plugin-relative files under `local/flwcupkp/fixtures`, `local/flwcupkp/imports`, and `local/flwcupkp/templates`.
