# Program 3 Gate C0 - Integrated Repository Audit

Status: complete

Date: 2026-08-28

## Objective

Inspect `local_flwcupkp`, verify Program 1 and Program 2 contract readiness, and
classify the existing C-UP-KP/adaptive/learner UX surface before any C1-C5
foundation changes.

## Source-History Boundary

Program 3 normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
local_flwhistory\local\evidence_source_adapter
```

Raw Moodle logs and direct Moodle table reads are diagnostic or legacy paths
only. New Program 3 learner intelligence must use History V1 facts rather than
scraping raw Moodle logs.

## Program 1 Contract Check

Verified through History V1 `content_identities` facts.

Required identity fields are available through the downstream contract:

- world
- stage
- unit
- lesson
- activity
- assessment/question
- Moodle course
- Moodle section
- Moodle cmid
- freshness/status lifecycle fields

Live sample on course `126` returned zero content identity rows, but the contract
and bounded adapter method are present. Zero rows are treated as empty data, not
as a missing capability.

## Program 2 Contract Check

Verified through History V1 downstream adapter.

Required query families:

- source event history: available
- attempt history: available
- grade history: available
- completion: available
- placement/checkpoint facts: available
- source identity/content identity: available

Live C0 audit status:

```text
ready_for_c1
```

Normalization policy:

```text
H1B-20260827.1
```

## Current Plugin Inventory

Core tables:

- `flwcupkp_framework`
- `flwcupkp_comp`
- `flwcupkp_up`
- `flwcupkp_kp`
- `flwcupkp_comp_up`
- `flwcupkp_up_kp`
- `flwcupkp_kp_prereq`
- `flwcupkp_object`
- `flwcupkp_object_map`
- `flwcupkp_evidence`
- `flwcupkp_state`
- `flwcupkp_rule`
- `flwcupkp_recommend`
- `flwcupkp_import`
- `flwcupkp_calsnapshot`
- `flwcupkp_calproposal`
- `flwcupkp_calrecalc`
- `flwcupkp_eval_period`
- `flwcupkp_eval_snapshot`
- `flwcupkp_selfeval`
- `flwcupkp_diagnostic`
- `flwcupkp_audit`

Core services:

- `history_v1_consumer_contract`
- `curriculum_manager`
- `import_service`
- `evidence_guard`
- `mastery_engine`
- `rollup_engine`
- `recommendation_engine`
- `learner_evaluation`
- `moodle_competency_writer`
- `output_hooks`

Main UI pages:

- `index.php`
- `setup.php`
- `curriculum.php`
- `teacher.php`
- `student.php`
- `evaluation.php`
- `sync.php`
- `evidence_sync.php`
- `calibration.php`
- `calibration_proposal.php`
- `performance.php`
- `trace.php`
- `mappings.php`
- `import_export.php`
- `manual_evidence.php`

## C0 Classification

| Area | Classification | Current state | Next foundation action |
| --- | --- | --- | --- |
| Schema | KEEP, EXTEND | Base C-UP-KP, evidence, state, recommendation, evaluation, calibration, import, and audit tables exist. | Extend for History V1 source keys, evidence semantics, quality dimensions, and versioned governance. |
| C/KP/UP | KEEP, EXTEND | Competency, Use Point, and Knowledge Point tables exist with stable external IDs and core descriptive fields. | C1 must freeze meanings, code rules, CEFR-vs-stage separation, and validation semantics. |
| Mappings | KEEP, EXTEND | Competency-UP, UP-KP, and object-target mappings exist and are guarded against cross-framework mistakes. | C3 must add explicit evidence roles, Program 1 identity linkage, and mapping lifecycle/version semantics. |
| Prerequisites | KEEP, EXTEND | KP prerequisite table exists with relationship type, strength, requirement, and notes. | C2 must validate prerequisite direction, cycles, role vocabulary, and relationship governance. |
| Evidence | REFACTOR, EXTEND | Evidence records and adapters exist, but normal ingestion still comes from direct Moodle observers/module reads. | E1 must consume History V1 facts and store source contract, source keys, evidence role, result state, quality, and policy version. |
| Mastery | KEEP, EXTEND | Explainable mastery calculation, calibrated thresholds, rollups, and Moodle competency sync exist. | E2 must separate evidence quality from mastery policy and keep policy versions explicit. |
| Learner state | KEEP, EXTEND | Current KP, UP, and competency states are stored with score, confidence, evidence count, review, and manual override fields. | C5/E2 must clarify current-state invariants, historical snapshots, and teacher-decision overlay semantics. |
| Goal | UNKNOWN, EXTEND | No dedicated learner goal model is present; evaluation periods and recommendations partially cover the need. | A1 must add a competency-centered learning goal contract after foundation freeze. |
| Placement | EXTEND | Learner evaluation can show placement-style profile data; History V1 exposes placement facts. | A2 must define how placement/checkpoint facts seed diagnostics without asserting final mastery. |
| Recommendation | REFACTOR | Simple recommendation generation exists from current learner states. | A3-A5 must replace this with a versioned adaptive policy and candidate eligibility engine. |
| Timeline | EXTEND | Learner evaluation and visuals include timeline-style summaries. | UX1 must integrate History V1 past, current C-UP-KP state, and future next actions. |
| Teacher/admin UI | KEEP, EXTEND | Home, setup, curriculum, evidence sync, calibration, sync, teacher, performance, trace, and evaluation pages exist. | C5B/UX3 must add a foundation inspector and stronger explainability for evidence quality and overrides. |
| Tests | KEEP, EXTEND | PHPUnit tests cover import, curriculum, evidence guards, mastery, rollups, evaluation, calibration, privacy, A0, and C0. | C1-C5 need invariant tests for ontology, mapping contracts, evidence semantics, lifecycle, and freeze readiness. |
| Privacy | KEEP, EXTEND | Moodle privacy provider exports/deletes learner-owned rows and anonymizes operational actor fields. | New History V1 source metadata and learner goal/state history fields must be added to privacy coverage when introduced. |
| Backup/restore | UNKNOWN, EXTEND | No local plugin backup/restore implementation was found in the current source tree. | C4/C5 must decide export/backup ownership and add tests or an explicit non-backup policy. |

## C0 Runtime Audit Service

Added:

```text
local_flwcupkp\local\program3_repository_audit::audit_status()
```

The service is read-only. It checks expected tables, classes, main files,
Program 1 identity readiness, Program 2 History V1 readiness, subsystem
classification, and C1-C5 foundation gaps.

## Stop Boundary

C0 does not change:

- canonical domain model
- mapping schema
- evidence schema
- mastery policy
- adaptive policy
- learner path generation
- learner states
- C-UP-KP evidence

