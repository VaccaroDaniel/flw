# API

Moodle external service functions map the prompt's REST-style API to Moodle-native web services.

Initial functions:

- `local_flwcupkp_get_frameworks`
- `local_flwcupkp_import_package`
- `local_flwcupkp_record_evidence`
- `local_flwcupkp_get_learner_states`
- `local_flwcupkp_get_recommendations`
- `local_flwcupkp_get_coverage_report`
- `local_flwcupkp_sync_moodle_competencies`

All functions perform parameter validation and capability checks.

Production hardening:

- `local_flwcupkp_record_evidence` validates target existence and course enrolment before writing learner evidence.
- `local_flwcupkp_sync_moodle_competencies` reports effective dry-run status unless sync write readiness is complete.
- Import package calls are idempotent by checksum and should be paired with `health_check.php` after major curriculum loads.
