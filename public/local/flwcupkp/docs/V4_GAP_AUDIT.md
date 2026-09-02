# C-UP-KP V4 Gap Audit

Date: 2026-08-13

Source prompt: `D:/WinPro.Delta/Projects/C-UP-KP/FLW_CUPKP_Operational_Learning_Path_and_Learner_Evaluation_Codex_Master_Prompt_V4.0.md`

Plugin audited: `local_flwcupkp`

## Current Coverage Before This Pass

Implemented major V4 foundations:

- C-UP-KP curriculum graph tables for frameworks, competencies, UP, KP, object mappings, prerequisite mappings, and imports.
- Moodle course/activity/quiz evidence adapters for U038 and generic unit paths.
- Mastery state calculation for KP, UP, and competencies.
- Teacher evidence verification and override audit trail.
- Student unit progress pages and course-page role-aware cards.
- Moodle native competency sync writer for achieved competency states.
- Calibration snapshots, threshold proposals, controlled recalculation, and health checks.
- Unit Setup Wizard so admins can activate units from the UI.

## Missing V4 Learner Evaluation Layer

The V4 prompt requires learner evaluation as a distinct subsystem, not only current mastery state display. Before this pass the plugin did not yet provide:

- Evaluation periods/checkpoints.
- Immutable learner evaluation snapshots with rule/version/checksum metadata.
- Learner self-evaluation records.
- Diagnostic inference records derived from state/evidence/self-evaluation gaps.
- External API endpoints for learner evaluation profile, snapshots, periods, self-evaluation, and class summary.
- Student/teacher/admin page for evaluation profiles.
- Privacy metadata/export/delete support for learner evaluation data.
- Production health checks and PHPUnit coverage for evaluation tables and behavior.

## Implemented In This Pass

Data model:

- `flwcupkp_eval_period`
- `flwcupkp_eval_snapshot`
- `flwcupkp_selfeval`
- `flwcupkp_diagnostic`

Service layer:

- `local_flwcupkp\local\learner_evaluation`
- Generic course/framework/unit/period scoped profile generation.
- Synthetic empty target rows for mapped targets with no evidence yet.
- Period save/list helpers.
- Self-evaluation recording.
- Diagnostic inference for mastery gaps, low confidence, review due, missing evidence, stale evidence, and self-evaluation mismatch.
- Immutable snapshot creation with summary JSON, state/evidence IDs, diagnostics, recommendations, rule versions, and checksum.
- Course/class summary.

Moodle integration:

- `evaluation.php` shared student/teacher/admin page.
- Admin dashboard link.
- Course navigation link per active C-UP-KP unit.
- External web service functions for periods, profile, snapshot, self-evaluation, and class summary.
- Privacy provider coverage for export/delete/user listing.
- Health check coverage for new tables/files.
- PHPUnit coverage for period, self-evaluation, diagnostics, snapshots, and class summary.

## Acceptance Notes

The learner evaluation subsystem is intentionally additive:

- It does not replace `flwcupkp_state`; it evaluates and snapshots the current evidence-backed mastery picture.
- It includes mapped targets without evidence, so gap reports do not hide unattempted KP/UP/competency targets.
- Snapshot payloads preserve derived summaries plus references to evidence/state rows available at creation time.
- Diagnostics are rule-versioned and auditable.

## Remaining Outside This Subsystem

The V4 prompt is broader than learner evaluation. These are still future product hardening areas, not blockers for this learner-evaluation implementation:

- Richer visual analytics for longitudinal evaluation trend comparison.
- Full admin UI for creating/editing evaluation periods beyond API/service calls.
- Scheduled/bulk snapshot automation for all learners in a period.
- Advanced psychometric calibration beyond current threshold proposal/recalculation layer.
