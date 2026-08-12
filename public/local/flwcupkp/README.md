# FLW C-UP-KP Moodle Local Plugin

This plugin implements the first operational layer of the FLW Competency, Use Point, and Knowledge Point framework.

Install path inside Moodle:

```text
local/flwcupkp
```

First validation command from a Moodle root:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json
```

Normal admin setup path:

```text
local/flwcupkp/setup.php
```

Use the Unit Setup Wizard to choose a Moodle course, enter or select a unit code, validate/import a JSON package, review link status, and activate the unit by linking mapped learning objects to Moodle activities. The CLI commands below remain available for scripted setup.

CSV validation examples:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/activity_cupkp_mapping.csv --format=csv --type=activity_mappings
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings
```

JSON package templates are also available under `local/flwcupkp/templates`, including
`unit_control_packet.json`, `cupkp_map.json`, `lesson_cupkp_map.json`,
`project_competency_mapping.json`, and `cupkp_validation_report.json`. JSON imports accept
`lesson_mappings` as a learning-object mapping alias and `project_competency_mappings` as a
project-to-competency mapping alias.

Import command:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json --import
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings --import
```

Production health check:

```bash
php local/flwcupkp/cli/health_check.php
```

Evidence calibration review:

```text
local/flwcupkp/calibration.php
```

The calibration page can export the current filtered report as JSON or CSV, save named snapshots, and compare the current summary with the latest matching snapshot.

Threshold proposals:

```text
local/flwcupkp/calibration_proposal.php
```

Use a saved calibration snapshot to draft threshold changes, preview projected mastery outcome changes, and activate a reviewed calibrated rule version. After activation, admins can simulate affected learner-state recalculation, apply changes immediately, or queue a controlled recalculation run for the scheduled task. Each run is stored in `flwcupkp_calrecalc` with candidate, applied, skipped, and error counts.

Traceability report:

```text
local/flwcupkp/trace.php
```

The traceability report follows CEFR-aligned competencies through Use Points, Knowledge Points, Moodle activities, evidence counts, and learner/class state summaries. The curriculum manager also includes a compact relationship view plus the detailed graph browser.

Generic unit Moodle shell/link command:

```bash
php local/flwcupkp/cli/link_unit.php --create-shell --unitcode=U037
php local/flwcupkp/cli/link_unit.php --status --unitcode=U037
```

Native Moodle competency linking:

```bash
php local/flwcupkp/cli/link_moodle_competencies.php
```

Native Moodle competency rating sync:

```bash
php local/flwcupkp/cli/recalculate_rollups.php
php local/flwcupkp/cli/recalculate_rollups.php --userid=5
php local/flwcupkp/cli/sync_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php --execute
```

Backup/export command:

```bash
php local/flwcupkp/cli/export_package.php --output=/path/flw-cupkp-export.json
```

Moodle competency synchronization is dry-run by default. Write mode is locked until every C-UP-KP framework and competency has a verified native Moodle competency ID.

KP and UP evidence rolls upward through the C-UP-KP graph. KP states recalculate parent UPs and competencies; UP states recalculate parent competencies; teacher KP overrides trigger the same roll-up. Child mastery alone can make a competency `provisionally_achieved`; `achieved` and `sustained` require direct competency or mapped UP performance evidence that satisfies the competency evidence rule.

Teachers can record speaking, writing, and project performance evidence for any mapped unit at:

```text
local/flwcupkp/performance.php?courseid=124&unitcode=U038
```

Production safety notes:

- Web import paths are limited to plugin-relative `local/flwcupkp/fixtures/`, `local/flwcupkp/imports/`, and `local/flwcupkp/templates/` paths, or pasted JSON/CSV.
- Manual, API, quiz, assignment, H5P, SCORM, STT, and activity-completion evidence writes validate target IDs, object mappings, Moodle course scope, and Moodle course enrolment before storing evidence.
- External write web-service calls are session-rate-limited and protected by Moodle capability checks.
- Curriculum mappings must reference existing records in the same C-UP-KP framework.
- C-UP-KP competency states sync to native Moodle user competency ratings only when sync write mode is enabled and Moodle framework/competency links are complete.

Admin UI entry points:

- `local/flwcupkp/index.php` - admin landing page.
- `local/flwcupkp/setup.php` - Unit Setup Wizard for course selection, package import, link status, and activation.
- `local/flwcupkp/curriculum.php` - curriculum graph browser with audited bulk status changes and controlled framework version cloning; cloned drafts clear native competency and live activity links until explicitly linked.
- `local/flwcupkp/edit_entity.php` - controlled entity editor.
- `local/flwcupkp/mappings.php` - mapping manager.
- `local/flwcupkp/import_export.php` - JSON and CSV validate/import plus JSON export.
- `local/flwcupkp/manual_evidence.php` - teacher/admin manual evidence entry.
- `local/flwcupkp/performance.php` - generic unit performance assessment scoring.
- `local/flwcupkp/performance_u038.php` - legacy U038 performance assessment scoring.
- `local/flwcupkp/sync.php` - Moodle competency sync review controls.
- `local/flwcupkp/calibration.php` - evidence distribution, threshold, mastery outcome, export, and saved-snapshot review.
- `local/flwcupkp/calibration_proposal.php` - draft, preview, activate, simulate recalculation, apply, queue, and review calibrated mastery threshold recalculation runs.
- `local/flwcupkp/trace.php` - competency-to-evidence traceability report.
