# API

Moodle external service functions map the prompt's REST-style API to Moodle-native web services.

Moodle web-service functions:

- `local_flwcupkp_get_frameworks`
- `local_flwcupkp_save_framework`
- `local_flwcupkp_get_competencies`
- `local_flwcupkp_save_competency`
- `local_flwcupkp_get_use_points`
- `local_flwcupkp_save_use_point`
- `local_flwcupkp_get_knowledge_points`
- `local_flwcupkp_save_knowledge_point`
- `local_flwcupkp_get_mappings`
- `local_flwcupkp_save_mapping`
- `local_flwcupkp_delete_mapping`
- `local_flwcupkp_validate_import`
- `local_flwcupkp_import_package`
- `local_flwcupkp_validate_csv_import`
- `local_flwcupkp_import_csv`
- `local_flwcupkp_record_evidence`
- `local_flwcupkp_get_learner_states`
- `local_flwcupkp_get_learner_learning_path`
- `local_flwcupkp_get_recommendations`
- `local_flwcupkp_get_coverage_report`
- `local_flwcupkp_get_orphans_report`
- `local_flwcupkp_get_evidence_gaps_report`
- `local_flwcupkp_get_cefr_alignment_report`
- `local_flwcupkp_get_import_validation`
- `local_flwcupkp_sync_moodle_competencies`
- `local_flwcupkp_get_sync_status`

All functions perform parameter validation and capability checks.

The CRUD, mapping, learner-path, and quality-report calls return Moodle-native structures or a JSON envelope (`json`) where the REST-style response shape is richer than Moodle external returns comfortably express. Enable the service and issue tokens from Moodle web-service administration before production API use.

OpenAPI documentation is provided at `local/flwcupkp/openapi.json`. It describes the logical REST-style contract from the Master Prompt and maps each operation to the Moodle external-service function through `x-moodle-wsfunction`.

Production hardening:

- `local_flwcupkp_record_evidence` validates target existence and course enrolment before writing learner evidence.
- External write calls are session-rate-limited through Moodle MUC cache area `externalwrites`.
- `local_flwcupkp_sync_moodle_competencies` reports effective dry-run status unless sync write readiness is complete.
- JSON and CSV import calls are idempotent by checksum and should be paired with `health_check.php` after major curriculum loads.
