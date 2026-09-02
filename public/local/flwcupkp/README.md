# FLW C-UP-KP Moodle Local Plugin

`local_flwcupkp` connects FLW learning units to a Moodle course by tracking
Competencies, Use Points, Knowledge Points, Moodle activity mappings, learner
evidence, mastery states, learning-path recommendations, teacher decisions, and
native Moodle competency ratings.

Install path inside Moodle:

```text
local/flwcupkp
```

Minimum Moodle version:

```text
Moodle 4.1 or later
```

The original pilot was `U038` in Moodle course `124`. Course IDs are deployment
data, not plugin configuration; use the Unit Setup Wizard to link a unit to an
active course. The plugin supports generic units through the wizard and generic
student, teacher, evaluation, and performance pages.

## What C-UP-KP Means

C-UP-KP is the FLW learning graph:

- Competency: the broad CEFR-aligned outcome a learner should achieve.
- Use Point: an observable communicative use of the competency.
- Knowledge Point: a smaller learning point that can be practiced and evidenced.
- Learning object: a Moodle activity, quiz, assignment, H5P, SCORM, FLW VR Room
  task, or manual assessment mapped to one or more C-UP-KP targets.
- Evidence: a normalized record showing that a learner attempted, completed,
  scored, demonstrated, or was assessed against a mapped target.
- State: the calculated learner mastery state for a KP, UP, or competency.

Evidence normally enters at the activity or quiz level, updates KP states, rolls
up to UPs and competencies, and can then sync achieved competency states into
native Moodle competency ratings.

## Program 3 Foundation Contracts

Current frozen Program 3 foundation contracts:

- `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`: the only normal source-history
  input from `local_flwhistory`.
- `FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1`: canonical C/UP/KP meanings,
  many-to-many topology, and CEFR/FLW-stage separation.
- `FLW_CUPKP_ONTOLOGY_BOUNDARY_V1`: C/KP/UP category-drift and vocabulary
  validation.
- `FLW_CUPKP_RELATIONSHIP_GRAPH_V1`: relationship direction, cardinality,
  prerequisite strength semantics, hard-cycle prevention, and centralized graph
  traversal.
- `FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1`: stable Program 1 content
  identity mapping, pedagogical object-map roles, evidence source typing, and
  completion-as-evidence guardrails.
- `FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1`: History V1 source keys, result
  states, performance modes, direct/inferred evidence, retry semantics, quality
  dimensions, and evidence policy versioning.
- `FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1`: curriculum lifecycle states, published
  row immutability, framework clone/revision behavior, replacement governance,
  and runtime validation findings.
- `FLW_CUPKP_FOUNDATION_V1`: the frozen Foundation V1 surface for evidence,
  mastery, adaptive, and UX consumers. It records
  `curriculum_contract_version`, `relationship_contract_version`, and
  `evidence_policy_version`, and blocks only on unresolved `BLOCKER` or `HIGH`
  findings.
- `FLW_CUPKP_CORE_CURRICULUM_MANAGER_V1`: CM1 operational authoring over the
  frozen Foundation V1 surface, including navigation facets, selected-row
  details, validation, evidence coverage, audit history, and governed lifecycle
  workflow actions.
- `FLW_CUPKP_RELATIONSHIP_WHERE_USED_V1`: CM2 controlled relationship editing
  with preview-before-write, where-used impact counts, protected delete checks,
  semantic relationship labels, coverage governance summaries, and audit
  logging for confirmed relationship writes.
- `FLW_CUPKP_COVERAGE_BULK_GOVERNANCE_V1`: CM3 bulk coverage governance with
  six bounded coverage checks, bulk import dry-runs, duplicate detection, JSON
  export, and controlled rollback requests.
- `FLW_CUPKP_MANAGEMENT_V1`: CM4 production management freeze over CM1-CM3,
  exposing a read-only consumer snapshot.
- `FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1`: E1 History V1 to C-UP-KP evidence
  adapter with read-only preview, controlled apply reprocessing, unresolved
  mapping reporting, and idempotent derived evidence keys.
- `FLW_CUPKP_PLACEMENT_DIAGNOSTIC_COLD_START_V1`: A2 placement, diagnostic,
  and cold-start policy. It treats placement as initial diagnostic evidence,
  supports `NOT_TAKEN`, `VALID`, `STALE`, `INCOMPLETE`, `LOW_CONFIDENCE`, and
  `TEACHER_OVERRIDE`, and writes C-UP-KP evidence only for explicitly assessed
  dimensions with explicit target mapping.
- `FLW_CUPKP_CANDIDATE_ACTIVITY_RESOLUTION_V1`: A4B target-to-activity
  resolution. It consumes the A4 initial path and C3 object mappings, checks
  learner enrollment, Moodle availability, visibility, dates, quiz attempt
  limits, teacher restrictions, launch capability, and diversity, then returns
  an eligible NEXT ACTIVITY or a diagnostic fallback. It does not write
  recommendation rows or run continuous adaptation.
- `FLW_CUPKP_CONTINUOUS_ADAPTIVE_PATH_ENGINE_V1`: A5 controlled continuous
  adaptation. It recomputes from the frozen A4B resolution, supports the eight
  frozen adaptive actions, persists a complete version/policy/source snapshot,
  and uses a deterministic hash to avoid duplicate recommendations. Its write
  boundary is limited to `flwcupkp_recommend` and `flwcupkp_audit`.
- `FLW_CUPKP_STAFF_INTELLIGENCE_V1`: UX3 authorized staff explainability and
  controlled interventions. It answers six frozen recommendation questions,
  keeps the UX2 learner surface unchanged, and records append-only intervention
  versions without changing History V1 or A5 policy ownership.
- `FLW_CUPKP_ADAPTIVE_UX_V3_PRODUCTION_VALIDATION_V1`: F1 final integrated,
  read-only validation across Program 1 content identity and Moodle deployment,
  History V1 facts, evidence, mastery, retention, adaptive decisions,
  eligibility, recommendation history, UX2 learner presentation, UX3 staff
  explainability, ownership, security/privacy, invariants, and performance.
  Production readiness is scope-specific and requires zero unresolved
  `BLOCKER` or `HIGH` findings.

The C2 graph service is:

```text
local_flwcupkp\local\relationship_graph_contract
```

It maps the existing `comp_up`, `up_kp`, `kp_prereq`, and `object_map` tables
to frozen relation semantics: `SUPPORTS`, `REQUIRES`, `EVIDENCE_FOR`, `TRAINS`,
`EXTENDS`, `ALTERNATIVE_TO`, `REVIEW_OF`, and `REPLACED_BY`. Hard mandatory KP
prerequisite cycles are rejected during package validation, import, and manual
mapping saves.

The C4 lifecycle governance service is:

```text
local_flwcupkp\local\lifecycle_governance_contract
```

It defines canonical statuses `draft`, `review`, `approved`, `published`,
`deprecated`, and `archived`. Legacy labels remain accepted on import and bulk
updates: `validated` becomes `approved`, while `active` and `reference` become
`published`. Published semantic rows cannot be edited in place; clone a new
framework revision, then deprecate or replace the old row with `REPLACED_BY`.

The C5 Foundation V1 service is:

```text
local_flwcupkp\local\foundation_v1_contract
```

It exposes:

```text
contract()
version_record()
adaptive_api_contract()
authoritative_implementation_status()
foundation_status()
```

Use `foundation_status()` as the read-only preflight before later evidence,
mastery, adaptive, or UX gates. Foundation V1 does not implement adaptive path
selection, learning-goal modeling, History V1 evidence reprocessing writes, or
mastery policy changes.

The C5B Foundation Inspector admin page is:

```text
/local/flwcupkp/foundation.php
```

Use it to inspect Foundation V1 status, contracts, migration readiness,
C/UP/KP rows, prerequisite graph relations, content/evidence mappings,
implementation ownership, and non-blocking findings before CM1 curriculum
authoring begins.

The CM1 Core Curriculum Manager admin page is:

```text
/local/flwcupkp/curriculum.php
```

It lets admins browse curriculum by language, CEFR level, FLW stage, domain or
skill, and entity type. Each row opens a governed detail page:

```text
/local/flwcupkp/entity.php?type=kp&id=ENTITYID
```

Use the detail page to inspect definition, stable code, version, status,
relationships, prerequisites, content usage, evidence coverage, validation,
history, and lifecycle actions before editing or publishing.

The CM2 Relationship Editor admin page is:

```text
/local/flwcupkp/mappings.php
```

It lets admins add, edit, and delete `comp_up`, `up_kp`, `kp_prereq`, and
`object_map` relationships through a preview/confirm flow. The preview shows
C2 semantic labels, validation results, affected C/UP/KP graph nodes, learning
objects, courses, units, lessons, activity CMIDs, Program 1 question IDs,
checkpoint counts, evidence rows, and learner-state references. Confirmed
writes are audited. Object mappings with learner evidence are protected from
physical deletion.

The CM3 Coverage Governance admin page is:

```text
/local/flwcupkp/governance.php
```

It lets admins inspect coverage, governance findings, lifecycle counts, bulk
import dry-runs, confirmed imports, export packages, and controlled rollback
requests at FLW scale.

The CM4 Management V1 Freeze admin page is:

```text
/local/flwcupkp/management.php
```

It freezes the production management surface for consumers. The page and
`local_flwcupkp_get_management_v1_status` web service report the ten CM4 pass
criteria, dependency status, coverage snapshot, and the current downstream
handoff boundary.

The E1 History Evidence Adapter admin page is:

```text
/local/flwcupkp/history_evidence.php
```

It previews and applies controlled History V1 to C-UP-KP evidence
reprocessing. The adapter consumes `FLW_CUPKP_MANAGEMENT_V1` and
`FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`, converts History `attempts` and
eligible `completion` facts into derived evidence through
`mastery_engine::record_evidence()`, and marks unresolved mappings without
fabricating evidence. E1 hands off to E2 mastery, confidence, and current
learner-state consumption.

The E2 Mastery State admin page is:

```text
/local/flwcupkp/mastery_state.php
```

It inspects current learner KP, UP, and competency state, shows confidence and
cache freshness separately from mastery, and previews/applies controlled state
cache rebuilds. E2 stores reproducibility metadata on `flwcupkp_state`,
including policy version, trend, evidence IDs/hash, and calculated time.
Manual teacher overrides are preserved. E2 hands off to E3 retention,
retrieval, and review states.

The E3 Retention / Retrieval / Review admin page is:

```text
/local/flwcupkp/retention_review.php
```

It keeps demonstrated mastery separate from long-term retrievability. E3 reads
E1-derived C-UP-KP evidence and E2 current-state rows, then previews/applies
retention snapshots on `flwcupkp_state`: `retentionstate`,
`retentionconfidence`, `retentionnextreview`, `retentionlastretrieval`,
`retentionretrievalcount`, `retentionpolicyversion`, evidence IDs/hash, and
calculated time. Time can make review due, but it does not decay mastery.
Failed review can set `RELEARNING` without erasing the E2 mastery state. E3
hands off to A1 competency-centered learning goals.

The A1 Competency-Centered Learning Goal page is:

```text
/local/flwcupkp/learning_goal.php
```

It models each learner destination as a versioned goal. The preferred target is
a desired competency/skill profile, with optional CEFR, FLW stage, purpose,
priority skills, target date, weekly target, and selected competency/UP/KP
targets. Goal sources are `STUDENT`, `TEACHER`, and `INSTITUTION`. Each changed
goal creates an immutable version in `flwcupkp_goal_version`; A1 does not erase
evidence, mastery, retention, or recommendation history. A1 hands off to A2
placement, diagnostic, and cold-start modeling.

The A2 Placement Diagnostic page is:

```text
/local/flwcupkp/placement_diagnostic.php
```

It previews and applies controlled History V1 placement reprocessing. Raw
placement facts remain in `local_flwhistory`; A2 stores interpreted current
diagnostic state in `flwcupkp_placement_state`. Valid, partial, low-confidence,
and teacher-override placement facts may enter the C-UP-KP evidence/state
pipeline only when a profile dimension has an explicit score and target or a
placement/diagnostic object map explicitly names that dimension. Overall CEFR
or placement score alone remains diagnostic and does not fabricate KP, UP, or
competency evidence. A2 hands off to A3 adaptive decision policy.

The A3 Adaptive Decision page is:

```text
/local/flwcupkp/adaptive_decision.php
```

It freezes deterministic decision rules before path generation. A3 reads the
A1 goal, A2 placement diagnostic state, E2 mastery/confidence state, E3
retention/review state, and C2 prerequisites, then returns an explainable
decision, next target, projected roadmap, and destination. Thresholds,
candidate priority, tie-breaking, stability/hysteresis, anti-loop, and fallback
rules are visible in the contract; A3 does not write recommendations, mutate
learner state, or resolve targets to Moodle activities. A3 hands off to A4
goal-gap and initial personalized path generation.

The A4 Initial Personalized Path page is:

```text
/local/flwcupkp/initial_path.php
```

It computes the first target-level route from the learner goal, current learner
state, C-UP-KP requirements, prerequisites, retention/review state, and A3
adaptive policy. The output separates missing, satisfied, and prerequisite-
blocked KP/UP/competency rows, then shows candidate next targets, NEXT TARGET,
PROJECTED ROADMAP, and DESTINATION. A4 is read-only: it does not persist path
rows, write recommendations, mutate learner state, resolve Moodle activities,
or run continuous adaptation. A4 hands off to A4B candidate eligibility and
activity resolution.

The A4B Activity Resolution page is:

```text
/local/flwcupkp/activity_resolution.php
```

It resolves A4 candidate targets to Moodle activities the learner can actually
open now. The hard invariant is that an inaccessible activity can never become
NEXT. A4B checks curriculum validity, prerequisite handoff, course/unit scope,
enrollment, Moodle course-module availability, visibility, active availability
windows, quiz attempt limits, teacher restrictions, launch capability, and
diversity. If the first A4 target is blocked, A4B falls back to the next-best
eligible target; if none is eligible, it returns a diagnostic. A4B is read-only
and hands off to A5 continuous adaptive path execution.

The A5 Continuous Adaptive Path page is:

```text
/local/flwcupkp/adaptive_path.php
```

It turns the frozen A4B result into a previewable and controlled persistent
recommendation. A changed source hash supersedes the prior A5 row while keeping
it for history; an unchanged hash performs no write. A5 stores the goal and
curriculum versions, learner-state snapshot, evidence/mastery/retention/
adaptive policy versions, selected target/activity, candidate hash, reason
codes, and decision time. It never bypasses Moodle availability and never
mutates History V1, evidence, mastery, retention, placement, goals, mappings,
or course-module completion. A5 stops at A5B trajectory simulation and
invariant testing.

The A5B Trajectory Simulation page is:

```text
/local/flwcupkp/trajectory_simulation.php
```

It exercises the frozen A5 policy over deterministic success/failure,
remediation, retention, mastery uncertainty, modality diversity, goal change,
hidden-activity fallback, hard-prerequisite, and replay trajectories. Global
detectors fail loops, oscillation, repetitive modality, impossible paths,
unavailable NEXT activities, prerequisite skips, mastery collapse, retention
flooding, and nondeterministic output. The normal suite runs 512 trajectories
and 12,288 steps; administrators may raise this to 2,000 trajectories and 100
steps each. A5B is read-only and stores no projection, learner state, or
recommendation. Its frozen handoff is A5C Progress and Goal Readiness Contract.

The A5C Progress and Goal Readiness page is:

```text
/local/flwcupkp/progress_readiness.php
```

A5C keeps four different ideas separate: completion progress, mastery
progress, goal readiness, and path progress. Goal Readiness is the preferred
learner measure only when an active, versioned goal and complete semantic
targets make a percentage defensible. Otherwise the page shows a qualitative
milestone such as `GOAL_NOT_SET`, `EVIDENCE_NEEDED`, or
`RETENTION_CHECK_NEEDED`. Every percentage exposes its numerator, denominator,
weights, missing evidence, confidence, retention, evidence ceilings, mandatory
gaps, and `progress_policy_version`. Goal achievement additionally requires all
semantic target, confidence, evidence, retention, and prerequisite conditions;
a numeric 100% alone does not achieve the goal. A5C is read-only and freezes
its handoff to UX1 Past-Present-Future Dashboard Integration.

The UX1 Student Learning Timeline page is:

```text
/local/flwcupkp/learning_timeline.php?courseid=COURSEID&unitcode=UNITCODE
```

UX1 composes one read-only `StudentLearningTimelineView`. Past remains owned and
rendered by Program 2 History V1, including Grade History, Detailed Learning
History, Recent Activity, Attempt History, and the historical Learning Journey.
Present adds the frozen A5C C-UP-KP mastery, current skill state, and Goal
Readiness. Future adds the A5/A4B adaptive next action, a compact projected
roadmap, persisted recommendation history, and version-aware reasons when the
path changes. Templates receive compact presentation DTOs rather than raw
curriculum graph objects. UX1 does not rebuild History, recalculate learner
state, or write recommendations.

UX2 now uses that frozen view to provide the learner-facing **My Learning**
experience at the same URL. Its first level contains six vertical sections:
My History, Where I Am Now, What I Should Do Next, Coming Up, My Milestone,
and My Goal. History is compressed, Current is expanded, and Future is
summarized to at most three items. Show History and Show Roadmap provide level
2 disclosure; Why This Activity? and More Details provide level 3 disclosure.
The learner sees friendly terms such as Skill, Learning Point, Practice Target,
Ability, Needed First, Extra Practice, and Learning Results while internal
ontology names remain unchanged. Continue Learning appears only for the one
currently eligible A4B/A5 Moodle activity. UX2 is mobile-first and read-only.

UX3 adds the staff-only **Learner Intelligence** page:

```text
/local/flwcupkp/staff_intelligence.php?courseid=COURSEID&unitcode=UNITCODE
```

Teachers with report access can inspect C/KP/UP state, mastery, confidence,
retention, evidence provenance, prerequisites, recommendation reasons, policy
versions, and path decisions. Staff with `local/flwcupkp:override` can assign a
currently eligible target/activity, force review, hold advancement, override a
recommendation, adjust a goal, or record direct teacher evidence. Every action
requires a reason, uses an existing audited writer, and appends an immutable
intervention version. Releasing a path intervention appends another version;
automation never silently erases it. Selected activities are rechecked against
current A4B eligibility each time the path is composed.

## Main Entry Points

Replace `https://192.168.129.79` with your Moodle site's `$CFG->wwwroot`.

| Role | Normal page | Example |
| --- | --- | --- |
| Admin | C-UP-KP Home | `/local/flwcupkp/index.php` |
| Admin | Unit Setup Wizard | `/local/flwcupkp/setup.php` |
| Admin | Foundation Inspector | `/local/flwcupkp/foundation.php` |
| Admin | Curriculum Manager | `/local/flwcupkp/curriculum.php` |
| Admin | Relationship Editor | `/local/flwcupkp/mappings.php` |
| Admin | Coverage Governance | `/local/flwcupkp/governance.php` |
| Admin | Management V1 Freeze | `/local/flwcupkp/management.php` |
| Admin | History Evidence Adapter | `/local/flwcupkp/history_evidence.php` |
| Admin | Mastery State | `/local/flwcupkp/mastery_state.php` |
| Admin | Retention Review | `/local/flwcupkp/retention_review.php` |
| Admin | Learning Goal | `/local/flwcupkp/learning_goal.php` |
| Admin | Placement Diagnostic | `/local/flwcupkp/placement_diagnostic.php` |
| Admin | Adaptive Decision | `/local/flwcupkp/adaptive_decision.php` |
| Admin | Initial Personalized Path | `/local/flwcupkp/initial_path.php` |
| Admin | Activity Resolution | `/local/flwcupkp/activity_resolution.php` |
| Admin | Continuous Adaptive Path | `/local/flwcupkp/adaptive_path.php` |
| Admin | Trajectory Simulation | `/local/flwcupkp/trajectory_simulation.php` |
| Admin | Progress and Goal Readiness | `/local/flwcupkp/progress_readiness.php` |
| Admin | Student Learning Timeline | `/local/flwcupkp/learning_timeline.php?courseid=124&unitcode=U038` |
| Admin | Learner Intelligence | `/local/flwcupkp/staff_intelligence.php?courseid=124&unitcode=U038` |
| Admin | Curriculum Entity Detail | `/local/flwcupkp/entity.php?type=kp&id=ENTITYID` |
| Admin | Evidence Sync Health | `/local/flwcupkp/evidence_sync.php` |
| Admin | Moodle Competency Sync | `/local/flwcupkp/sync.php` |
| Admin | Calibration | `/local/flwcupkp/calibration.php` |
| Teacher | Teacher Review | `/local/flwcupkp/teacher.php?courseid=124&unitcode=U038` |
| Teacher | Speaking / Writing Assessment | `/local/flwcupkp/performance.php?courseid=124&unitcode=U038` |
| Teacher | Initial Personalized Path | `/local/flwcupkp/initial_path.php?courseid=124&unitcode=U038` |
| Teacher | Activity Resolution | `/local/flwcupkp/activity_resolution.php?courseid=124&unitcode=U038` |
| Teacher | Continuous Adaptive Path | `/local/flwcupkp/adaptive_path.php?courseid=124&unitcode=U038` |
| Teacher | Trajectory Simulation | `/local/flwcupkp/trajectory_simulation.php?courseid=124&unitcode=U038` |
| Teacher | Progress and Goal Readiness | `/local/flwcupkp/progress_readiness.php?courseid=124&unitcode=U038` |
| Teacher | Student Learning Timeline | `/local/flwcupkp/learning_timeline.php?courseid=124&unitcode=U038` |
| Teacher | Learner Intelligence | `/local/flwcupkp/staff_intelligence.php?courseid=124&unitcode=U038` |
| Student | My Progress | `/local/flwcupkp/student.php?courseid=124&unitcode=U038` |
| Student | My Learning Path | `/local/flwcupkp/evaluation.php?courseid=124&unitcode=U038` |
| Student | Initial Personalized Path | `/local/flwcupkp/initial_path.php?courseid=124&unitcode=U038` |
| Student | Activity Resolution | `/local/flwcupkp/activity_resolution.php?courseid=124&unitcode=U038` |
| Student | Continuous Adaptive Path | `/local/flwcupkp/adaptive_path.php?courseid=124&unitcode=U038` |
| Student | Progress and Goal Readiness | `/local/flwcupkp/progress_readiness.php?courseid=124&unitcode=U038` |
| Student | My Learning Timeline | `/local/flwcupkp/learning_timeline.php?courseid=124&unitcode=U038` |

Current U038 pilot URLs:

```text
https://192.168.129.79/local/flwcupkp/index.php
https://192.168.129.79/local/flwcupkp/setup.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/foundation.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/governance.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/management.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/history_evidence.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/mastery_state.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/retention_review.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/learning_goal.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/placement_diagnostic.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/adaptive_decision.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/initial_path.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/activity_resolution.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/adaptive_path.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/trajectory_simulation.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/progress_readiness.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/learning_timeline.php?courseid=124&unitcode=U038
https://192.168.129.79/course/view.php?id=124
https://192.168.129.79/local/flwcupkp/student_u038.php?courseid=124
https://192.168.129.79/local/flwcupkp/teacher_u038.php?courseid=124
https://192.168.129.79/local/flwcupkp/evaluation.php?courseid=124&unitcode=U038
https://192.168.129.79/local/flwcupkp/performance_u038.php?courseid=124
https://192.168.129.79/local/flwcupkp/evidence_sync.php?courseid=124&unitcode=U038
```

## Recommended Admin Workflow

Use the web UI for normal administration. Keep CLI commands for scripted,
developer, or recovery workflows.

1. Open C-UP-KP Home:

   ```text
   /local/flwcupkp/index.php
   ```

2. Open Unit Setup Wizard.

   ```text
   /local/flwcupkp/setup.php
   ```

3. Choose or create a Moodle course.

4. Choose or enter the unit code, for example `U038`.

5. Import or validate the unit package.

   The wizard can use a plugin-relative JSON/CSV package path or pasted package
   data. Safe web import paths are limited to:

   ```text
   local/flwcupkp/fixtures/
   local/flwcupkp/imports/
   local/flwcupkp/templates/
   ```

6. Review activity link status.

   Imported objects must be linked to actual Moodle course modules through the
   `cmid` stored on the C-UP-KP object. A unit is not operational until its key
   learning objects point to real Moodle activities.

7. Activate the unit for the selected course.

8. Open Curriculum Manager to inspect C/UP/KP rows, learning objects,
   relationship coverage, evidence coverage, validation, and audit history.

9. Use the selected entity detail page to send rows to review, approve,
   publish, or deprecate them. Published/deprecated semantic rows should be
   cloned/revisioned instead of overwritten in place.

10. Open the Moodle course page and confirm the role-aware C-UP-KP block:

   - Students see My Progress and their next action.
   - Teachers/admins see My Progress, Teacher Review, class overview, evidence
     queues, and sync health.

11. Collect evidence by having learners use the mapped activities.

12. Use Teacher Review to approve evidence, override/confirm states, and manage
    UP/competency decisions.

13. Use Evidence Sync Health to find and repair missing quiz evidence.

14. Use Placement Diagnostic to preview and then apply History V1 placement
    reprocessing. Use this before adaptive path decisions so cold-start learners
    are classified without treating placement as permanent truth.

15. Open Continuous Adaptive Path to preview each learner's eligible NEXT
    activity. Teachers can apply one changed recommendation or run the bounded
    class refresh; unchanged paths are idempotent and create no duplicate row.

16. Use Moodle Competency Sync to dry-run and then enable native Moodle
    competency rating writes when links are complete.

## Student Workflow

Students normally start from the Moodle course page. The course page shows a
small next-action card driven by the learner's current gap/mastery state.

Student pages:

```text
/local/flwcupkp/student.php?courseid=COURSEID&unitcode=UNITCODE
/local/flwcupkp/evaluation.php?courseid=COURSEID&unitcode=UNITCODE
```

U038 legacy shortcut:

```text
/local/flwcupkp/student_u038.php?courseid=124
```

Students can:

- View KP mastery and current gaps.
- See next recommended learning actions.
- Review rank, streak, placement level, last lesson, today learning, unit map,
  vocabulary review, and exam/placement sync panels where data exists.
- Open My Learning Path for the learner evaluation profile.
- Record self-evaluation ratings and reflections.
- Create immutable evaluation snapshots when allowed by their role/capability.

## Teacher Workflow

Teachers normally start from the Moodle course page or C-UP-KP Home.

Teacher pages:

```text
/local/flwcupkp/teacher.php?courseid=COURSEID&unitcode=UNITCODE
/local/flwcupkp/performance.php?courseid=COURSEID&unitcode=UNITCODE
/local/flwcupkp/evaluation.php?courseid=COURSEID&unitcode=UNITCODE&userid=USERID
```

U038 legacy shortcuts:

```text
/local/flwcupkp/teacher_u038.php?courseid=124
/local/flwcupkp/performance_u038.php?courseid=124
```

Teachers can:

- Filter learner evidence by learner, state, lesson, KP domain, evidence source,
  and parent target.
- Approve evidence rows.
- Confirm or override KP, UP, and competency states.
- Clear manual overrides and recalculate from evidence.
- Record speaking, writing, and project performance evidence.
- View class overview cards for KP mastery, UP demonstrated, competency achieved,
  evidence review queue, and parent decision queue.
- Open learner evaluation profiles for individual students.

Teacher decisions are written to the C-UP-KP audit log.

## Learner Evaluation System

The Learner Evaluation page is the V4 evaluation profile:

```text
/local/flwcupkp/evaluation.php?courseid=COURSEID&unitcode=UNITCODE
```

It shows:

- KP mastery.
- UP demonstrated rate.
- Competency achieved rate.
- Diagnostic gaps.
- Recommendations.
- Self-evaluation.
- Latest immutable evaluation snapshot.
- Visual progress rings, diagnostic breakdown, C-UP-KP hierarchy, and evaluation
  timeline.

Teachers can select learners in their course. Students see their own profile.

Snapshots are stored separately from the live profile so admins/teachers can
compare learner progress over time.

## Evidence Sources

The plugin observes Moodle events and converts mapped activity events into
normalized C-UP-KP evidence.

Observed sources:

- Moodle quiz attempt submitted.
- Moodle quiz attempt graded.
- Moodle course module completion updated.
- Moodle assignment submitted.
- Moodle assignment graded.
- Moodle H5P statement received.
- Moodle SCORM status submitted.
- Moodle SCORM raw score submitted.
- FLW VR Room attempt submitted.
- Manual teacher/admin evidence.
- External web-service evidence, when the service is enabled and the caller has
  the required capability.

Quiz evidence is mapped through the Moodle course module ID and the imported
C-UP-KP object mappings. If a finished mapped quiz attempt exists but no evidence
was created, admins can repair it from Evidence Sync Health.

## Program 3 History V1 Boundary

Program 3 Gate A0 freezes the downstream source-history boundary for the next
C-UP-KP and adaptive-learning phases.

Normal Program 3 learner intelligence must consume:

```text
local_flwhistory\local\evidence_source_adapter
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

History V1 provides bounded, read-only source facts for source events, attempts,
grades, completion, placement, and Program 1 content identities. C-UP-KP remains
responsible for deciding whether those facts become evidence, mastery, learner
state, recommendations, or teacher-facing explanations.

Raw Moodle log access is diagnostic-only. Existing direct Moodle event observers
remain legacy capture paths until a later Program 3 evidence reprocessing gate
replaces normal production evidence ingestion with History V1-based adapters.

Read-only preflight status is available in code through:

```text
local_flwcupkp\local\history_v1_consumer_contract::contract_status()
```

Program 3 Gate C0 adds a read-only integrated repository audit:

```text
local_flwcupkp\local\program3_repository_audit::audit_status()
```

The audit classifies the current schema, C/KP/UP model, mappings,
prerequisites, evidence, mastery, learner state, goals, placement,
recommendations, timeline, teacher/admin UI, tests, privacy, and backup/restore
surface as KEEP, EXTEND, REFACTOR, DEPRECATE, REMOVE, or UNKNOWN. It also lists
the C1-C5 foundation gates that must be closed before production adaptive
learner intelligence is enabled.

Program 3 Gate C1 freezes the canonical C-UP-KP domain model:

```text
local_flwcupkp\local\canonical_domain_model::contract()
local_flwcupkp\local\canonical_domain_model::freeze_status()
```

The C1 contract defines Competency as integrated ability, Use Point as observable
use/demonstration, and Knowledge Point as required knowledge. It preserves
many-to-many C-UP-KP relationships, keeps CEFR macro levels separate from FLW
stage labels, rejects A2.1/A2.2-style pseudo-CEFR values, and prevents learner
mastery fields from being stored on curriculum definitions.

Program 3 Gate C1B freezes ontology boundary validation:

```text
local_flwcupkp\local\ontology_boundary::contract()
local_flwcupkp\local\ontology_boundary::boundary_status()
```

The C1B boundary prevents category drift by detecting overly narrow
competencies, Knowledge Points written as learner tasks, Use Points that contain
unmodeled new knowledge, and semantic duplicates across C/UP/KP types. It also
defines the current safe vocabulary for statuses, mapping roles, object roles,
evidence-strength labels, and prerequisite labels while keeping C2 graph
semantics as a later gate.

Program 3 Gate C2 freezes relationship and prerequisite graph semantics:

```text
local_flwcupkp\local\relationship_graph_contract::contract()
local_flwcupkp\local\relationship_graph_contract::graph_status()
```

Program 3 Gate C3 freezes content/evidence mapping contracts:

```text
local_flwcupkp\local\content_evidence_mapping_contract::contract()
local_flwcupkp\local\content_evidence_mapping_contract::content_mapping_status()
```

C3 maps learning objects by stable IDs, not titles, and preserves Program 1
identity fields in `flwcupkp_object.metadatajson`. Object-target mappings are
normalized into `TEACHES`, `PRACTICES`, `ASSESSES`, or `EVIDENCE_FOR`.
Completion is never mastery by itself; it can become evidence only when the
mapped role/purpose permits it. Evidence rubric JSON records the C3 contract,
History V1 contract, source type, content identity, pedagogical role, and target
identity.

Program 3 Gate C3B freezes evidence semantics and quality:

```text
local_flwcupkp\local\evidence_semantics_quality_contract::contract()
local_flwcupkp\local\evidence_semantics_quality_contract::evidence_semantics_status()
```

C3B stores the evidence policy version `cupkp-evidence-quality-v1` in
`flwcupkp_evidence.rubricjson.cupkp_c3b_semantics`, separate from mastery rule
versions. New evidence rows receive History V1 source-key metadata, evidence
role, result state (`positive`, `negative`, `partial`, or `inconclusive`),
performance mode, direct/inferred flag, inference path when needed, retry
semantics, advisory evidence ceiling, and normalized quality dimensions for
validity, reliability, independence, authenticity, production demand,
contextual transfer, support level, difficulty, recency, and confidence.

Explicit C3B `inconclusive` rows remain stored for audit/explanation but do not
directly reduce the current mastery score. C3B does not add quality-weighted
mastery, adaptive selection, History V1 reprocessing, or raw Moodle log
scraping; those belong to later gates.

Program 3 Gate C5 freezes Foundation V1:

```text
local_flwcupkp\local\foundation_v1_contract::contract()
local_flwcupkp\local\foundation_v1_contract::foundation_status()
```

Foundation V1 records:

```text
curriculum_contract_version = FLW_CUPKP_CURRICULUM_CONTRACT_V1
relationship_contract_version = FLW_CUPKP_RELATIONSHIP_GRAPH_V1
evidence_policy_version = cupkp-evidence-quality-v1
```

After C5, downstream Program 3 gates may consume the read-only Foundation V1
APIs, but adaptive path ranking, learner goal creation, History V1 evidence
reprocessing writes, and mastery-policy changes remain explicitly out of scope
until later gates.

Program 3 Gate CM2 adds:

```text
local_flwcupkp\local\relationship_where_used_manager::contract()
local_flwcupkp\local\relationship_where_used_manager::status()
local_flwcupkp\local\relationship_where_used_manager::where_used_impact()
local_flwcupkp\local\relationship_where_used_manager::preview_mapping_change()
local_flwcupkp\local\relationship_where_used_manager::apply_mapping_change()
```

Use `/local/flwcupkp/mappings.php` for normal relationship maintenance. CLI or
direct SQL edits should remain developer/recovery tools because they bypass the
preview and where-used impact workflow.

## Evidence Sync Health

Admin page:

```text
/local/flwcupkp/evidence_sync.php
```

Example:

```text
/local/flwcupkp/evidence_sync.php?courseid=124&unitcode=U038
```

This page is for admins/managers with `local/flwcupkp:synccompetencies`.

It includes:

- Course filter.
- Unit filter.
- Repair-history status filter.
- History row limit.
- Pending finished Moodle quiz attempts that have no matching C-UP-KP quiz
  evidence.
- Per-attempt repair button.
- Repair all pending sync button.
- Full repair audit history, including requested, queued, completed, warning,
  and failed repair events.

Use this page when the Dashboard health tile says Quiz evidence needs sync.

## Moodle Competency Sync

Admin page:

```text
/local/flwcupkp/sync.php
```

C-UP-KP competency states can update native Moodle user competency ratings, but
write mode is locked until:

- Every C-UP-KP framework has a verified native Moodle framework link.
- Every C-UP-KP competency that should sync has a verified native Moodle
  competency ID.
- The admin has reviewed sync readiness.
- Moodle competency write mode is enabled.

The sync page supports dry-run review and toggling write mode. The CLI sync is
also dry-run by default.

CLI examples from Moodle root:

```bash
php local/flwcupkp/cli/link_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php --execute
php local/flwcupkp/cli/sync_moodle_competencies.php --execute --limit=50
```

## Calibration And Controlled Recalculation

Calibration page:

```text
/local/flwcupkp/calibration.php
```

Threshold proposal page:

```text
/local/flwcupkp/calibration_proposal.php
```

Admins can:

- Review evidence distributions and mastery outcomes.
- Export calibration reports as JSON or CSV.
- Save named calibration snapshots.
- Compare the current report with the latest matching snapshot.
- Draft threshold proposal changes.
- Preview projected mastery outcome changes.
- Activate a reviewed calibrated rule version.
- Simulate recalculation after activation.
- Apply recalculation immediately after confirmation.
- Queue recalculation for the scheduled task.
- Review recalculation run history.

Controlled recalculation writes run records to `flwcupkp_calrecalc`.

## Curriculum Management

Admin curriculum pages:

```text
/local/flwcupkp/curriculum.php
/local/flwcupkp/edit_entity.php
/local/flwcupkp/mappings.php
/local/flwcupkp/import_export.php
/local/flwcupkp/trace.php
```

Use these pages to:

- Manage frameworks, competencies, UPs, and KPs.
- Browse the C-UP-KP graph.
- Clone controlled framework versions.
- Make audited bulk status changes.
- Edit individual entities.
- Manage object mappings and relationship mappings.
- Validate/import/export JSON and CSV packages.
- Trace competencies through UPs, KPs, Moodle activities, evidence counts, and
  learner/class state summaries.

When framework versions are cloned, native Moodle competency links and live
activity links are cleared until explicitly relinked.

## Package Files

Built-in templates:

```text
local/flwcupkp/templates/unit_control_packet.json
local/flwcupkp/templates/cupkp_map.json
local/flwcupkp/templates/lesson_cupkp_map.json
local/flwcupkp/templates/project_competency_mapping.json
local/flwcupkp/templates/cupkp_validation_report.json
local/flwcupkp/templates/activity_cupkp_mapping.csv
local/flwcupkp/templates/quiz_kp_mapping.csv
```

Built-in fixture:

```text
local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json
```

JSON imports accept:

- Unit control packet data.
- C-UP-KP map data.
- Lesson/object mappings.
- Project-to-competency mappings.
- Validation report-style package data.

CSV imports currently support:

- `activity_mappings`
- `quiz_kp_mappings`

## CLI Commands

Run these from the Moodle root, where `config.php` exists.

Validate a JSON package:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json
```

Import a JSON package:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/fixtures/rew_u038_problem_solving_reference.json --import
```

Validate/import activity mapping CSV:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/activity_cupkp_mapping.csv --format=csv --type=activity_mappings
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/activity_cupkp_mapping.csv --format=csv --type=activity_mappings --import
```

Validate/import quiz KP mapping CSV:

```bash
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings
php local/flwcupkp/cli/validate_import.php --file=local/flwcupkp/templates/quiz_kp_mapping.csv --format=csv --type=quiz_kp_mappings --import
```

Create or link a unit course shell:

```bash
php local/flwcupkp/cli/link_unit.php --create-shell --unitcode=U037
php local/flwcupkp/cli/link_unit.php --create-shell --unitcode=U037 --shortname=FLW-U037
php local/flwcupkp/cli/link_unit.php --link --unitcode=U037 --courseid=125
php local/flwcupkp/cli/link_unit.php --status --unitcode=U037
php local/flwcupkp/cli/link_unit.php --status --unitcode=U037 --courseid=125
```

U038 legacy linker:

```bash
php local/flwcupkp/cli/link_u038.php
```

Recalculate rollups:

```bash
php local/flwcupkp/cli/recalculate_rollups.php
php local/flwcupkp/cli/recalculate_rollups.php --userid=5
```

Native Moodle competency sync:

```bash
php local/flwcupkp/cli/link_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php --execute
```

Production health check:

```bash
php local/flwcupkp/cli/health_check.php
php local/flwcupkp/cli/health_check.php --strict
```

Final integrated F1 validation:

```bash
php local/flwcupkp/cli/production_validation.php --action=discover
php local/flwcupkp/cli/production_validation.php --action=contract
php local/flwcupkp/cli/production_validation.php --action=validate --courseid=125 --unitcode=U038
php local/flwcupkp/cli/production_validation.php --action=validate --courseid=125 --unitcode=U038 --userid=5 --performance=0
```

`discover` reports valid and orphaned C-UP-KP course scopes. `validate` never
writes source history, evidence, state, goals, recommendations, interventions,
or audit rows. A repository-complete result does not by itself make a live
course production-ready; the selected course, unit, and learner must demonstrate
the complete 13-step F1 pipeline.

Export backup/package:

```bash
php local/flwcupkp/cli/export_package.php --output=/path/flw-cupkp-export.json
```

CLI commands are intended for scripted setup, developer diagnostics, and recovery.
For normal use, start with the Unit Setup Wizard.

## Scheduled Tasks

The plugin registers these scheduled tasks:

| Task | Schedule | Purpose |
| --- | --- | --- |
| `local_flwcupkp\task\recalculate_states` | Every 15 minutes | Recalculate queued learner states. |
| `local_flwcupkp\task\sync_competencies` | Daily at 02:10 | Run Moodle competency sync task. |
| `local_flwcupkp\task\calibration_recalculation` | Every 10 minutes | Process queued controlled threshold recalculation runs. |

Confirm Moodle cron is running in production.

## Web Services

The service is named:

```text
FLW C-UP-KP service
```

It is disabled by default and restricted to explicitly allowed users.

Major function groups:

- Framework CRUD.
- Competency CRUD.
- Use Point CRUD.
- Knowledge Point CRUD.
- Mapping CRUD.
- JSON/CSV package validation and import.
- Evidence recording.
- FLW VR Room attempt evidence.
- Learner states and recommendations.
- Learner evaluation periods, profiles, snapshots, and self-evaluation.
- Course evaluation summary.
- Coverage, orphan, evidence gap, and CEFR alignment reports.
- Moodle competency sync and sync status.

The OpenAPI description is available in:

```text
local/flwcupkp/openapi.json
```

## Capabilities

| Capability | Normal archetypes | Purpose |
| --- | --- | --- |
| `local/flwcupkp:manageframeworks` | Manager | Manage C-UP-KP framework data. |
| `local/flwcupkp:import` | Manager | Validate/import C-UP-KP packages. |
| `local/flwcupkp:viewreports` | Manager, editing teacher, teacher | View teacher/admin reports. |
| `local/flwcupkp:viewlearnerpath` | Manager, editing teacher, teacher, student | View learner progress/path pages. |
| `local/flwcupkp:override` | Manager, editing teacher | Approve evidence and override/confirm states. |
| `local/flwcupkp:synccompetencies` | Manager | Manage Moodle competency sync and evidence repair. |

Students must also be enrolled in the course to see their learner path for that
course.

## Data Model

Core tables:

```text
flwcupkp_framework
flwcupkp_comp
flwcupkp_up
flwcupkp_kp
flwcupkp_comp_up
flwcupkp_up_kp
flwcupkp_kp_prereq
flwcupkp_object
flwcupkp_object_map
flwcupkp_evidence
flwcupkp_state
flwcupkp_recommend
flwcupkp_rule
flwcupkp_import
flwcupkp_calsnapshot
flwcupkp_calproposal
flwcupkp_calrecalc
flwcupkp_eval_period
flwcupkp_eval_snapshot
flwcupkp_selfeval
flwcupkp_diagnostic
flwcupkp_audit
```

Important relationships:

- `flwcupkp_object.courseid` stores the Moodle course ID.
- `flwcupkp_object.unitcode` stores the C-UP-KP unit code.
- `flwcupkp_object.cmid` stores the Moodle course module ID when the object is
  linked to a Moodle activity.
- `flwcupkp_object_map` links objects to competencies, UPs, or KPs.
- `flwcupkp_evidence` stores learner evidence events.
- `flwcupkp_state` stores calculated or overridden learner states.
- `flwcupkp_audit` stores import, evidence, teacher decision, sync, repair, and
  recalculation audit events.

To find where a unit was imported, use the Unit Setup Wizard or inspect
`flwcupkp_object` by `unitcode`, `courseid`, and `cmid`.

## State And Rollup Rules

KP and UP evidence rolls upward through the C-UP-KP graph.

- KP states recalculate parent UPs and competencies.
- UP states recalculate parent competencies.
- Teacher KP overrides trigger the same rollup path.
- Child mastery alone can make a competency `provisionally_achieved`.
- `achieved` and `sustained` require direct competency or mapped UP performance
  evidence that satisfies the competency evidence rule.
- Manual teacher overrides are respected during controlled recalculation unless
  the workflow explicitly clears or replaces them.

## Production Safety

The plugin includes these safety controls:

- Web import paths are restricted to approved plugin-relative directories.
- Evidence writes validate target IDs, object mappings, Moodle course scope, and
  learner enrolment.
- Evidence writes preserve the C3 content/evidence mapping contract and reject
  pedagogically invalid completion evidence.
- Manual, API, quiz, assignment, H5P, SCORM, STT, FLW VR Room, and
  activity-completion evidence pass through guard checks before storage.
- External write web-service calls require Moodle capabilities and are
  session-rate-limited.
- Curriculum mappings must reference existing records in the same C-UP-KP
  framework.
- Native Moodle competency writes are dry-run by default.
- Moodle competency write mode is blocked until framework and competency links
  are complete.
- Teacher/admin decisions and repair runs are audited.
- The final F1 validator is read-only, compares mutation counts before and after
  validation, and refuses production-ready status when an invariant fails or a
  `BLOCKER`/`HIGH` finding remains.

Do not put passwords, API tokens, or learner-sensitive exports into this README
or into committed package fixtures.

## Quick Test Checklist

After installation or deployment:

1. Purge Moodle caches.

   ```bash
   php admin/cli/purge_caches.php
   ```

2. Run the health check.

   ```bash
   php local/flwcupkp/cli/health_check.php
   ```

3. Open C-UP-KP Home.

   ```text
   /local/flwcupkp/index.php
   ```

4. Open Unit Setup Wizard for the target unit.

   ```text
   /local/flwcupkp/setup.php?courseid=124&unitcode=U038
   ```

5. Open the Moodle course page and confirm role-aware C-UP-KP cards.

   ```text
   /course/view.php?id=124
   ```

6. Log in as a student and open My Progress.

7. Submit or grade a mapped Moodle quiz attempt.

8. Open Evidence Sync Health and confirm the attempt is either converted into
   evidence or appears as a repairable pending attempt.

9. Log in as a teacher and approve/override at least one evidence row.

10. Create a learner evaluation snapshot.

11. Run Moodle competency sync as a dry-run.

    ```bash
    php local/flwcupkp/cli/sync_moodle_competencies.php
    ```

12. Enable write mode only after sync readiness is complete, then test with a
    small limit before syncing all records.

    ```bash
    php local/flwcupkp/cli/sync_moodle_competencies.php --execute --limit=10
    ```

## Troubleshooting

No mapped courses appear:

- Import a package first.
- Check `flwcupkp_object.courseid`.
- Use Unit Setup Wizard to link or activate the unit.

Course page C-UP-KP cards do not appear:

- Confirm the course has imported objects for the unit.
- Confirm the user has the expected C-UP-KP capability.
- Purge Moodle caches.
- Confirm `db/hooks.php` is installed and Moodle upgrade has run.

Student cannot open My Progress:

- Confirm the student is enrolled in the Moodle course.
- Confirm the role has `local/flwcupkp:viewlearnerpath` in the course context.
- Confirm the unit has active linked objects.

Quiz evidence is missing:

- Confirm the quiz course module is linked to a `flwcupkp_object.cmid`.
- Confirm the attempt is finished and not a preview.
- Open Evidence Sync Health.
- Use per-attempt repair or Repair all pending sync.

Teacher cannot approve or override:

- Confirm the teacher has `local/flwcupkp:override`.
- Editing teachers have this by default; non-editing teachers may only have
  report viewing unless the role is customized.

Native Moodle competency ratings do not update:

- Open Moodle Competency Sync.
- Complete Moodle framework and competency links.
- Run dry-run sync.
- Enable write mode only after readiness is complete.
- Confirm the scheduled task or CLI sync has run.

Calibration changes do not affect states:

- Activate the reviewed proposal first.
- Run recalculation simulation.
- Confirm and apply or queue the controlled recalculation.
- Check recalculation run history for errors.

## Files To Know

```text
index.php                         Role-based C-UP-KP home.
setup.php                         Unit Setup Wizard.
curriculum.php                    Framework/competency/UP/KP manager.
mappings.php                      Controlled relationship editor with CM2 previews.
student.php                       Generic student progress page.
student_u038.php                  U038 legacy student progress page.
teacher.php                       Generic teacher verification page.
teacher_u038.php                  U038 legacy teacher verification page.
evaluation.php                    Learner Evaluation profile.
performance.php                   Generic performance evidence page.
performance_u038.php              U038 legacy performance page.
evidence_sync.php                 Admin Evidence Sync Health page.
foundation.php                    Admin read-only Foundation Inspector.
repair_sync.php                   POST endpoint for evidence repair actions.
sync.php                          Moodle competency sync review controls.
calibration.php                   Calibration report and snapshots.
calibration_proposal.php          Threshold proposal and recalculation workflow.
trace.php                         Traceability report.
manual_evidence.php               Manual evidence entry.
classes/local/import_service.php  JSON/CSV import service.
classes/local/mastery_engine.php  KP state calculation.
classes/local/rollup_engine.php   UP/competency rollups.
classes/local/retention_review_service.php
                                  Program 3 E3 retention/retrieval/review service.
classes/local/learning_goal_service.php
                                  Program 3 A1 versioned learner destination goals.
classes/local/goal_gap_path_service.php
                                  Program 3 A4 goal-gap and initial personalized path.
classes/local/staff_intelligence_service.php
                                  Program 3 UX3 staff explainability and interventions.
classes/local/staff_intelligence_renderer.php
                                  Staff-only UX3 dashboard renderer.
classes/local/production_validation_service.php
                                  Program 3 F1 final integrated read-only validator.
cli/production_validation.php     F1 scope discovery, contract, and validation CLI.
classes/local/learner_evaluation.php
                                  Learner Evaluation service.
classes/local/quiz_evidence_adapter.php
                                  Moodle quiz evidence adapter.
classes/local/evidence_sync_repair.php
                                  Pending quiz evidence repair service.
classes/local/moodle_competency_writer.php
                                  Native Moodle competency rating writer.
classes/local/foundation_v1_contract.php
                                  Program 3 Foundation V1 freeze contract.
classes/local/relationship_where_used_manager.php
                                  Program 3 CM2 relationship editor and impact service.
classes/local/output_hooks.php    Course page and Dashboard UI hooks.
```

## Development Notes

- Keep generic unit behavior in `student.php`, `teacher.php`, and
  `performance.php`.
- Keep `*_u038.php` pages only as compatibility shortcuts for the pilot unit.
- Prefer Unit Setup Wizard for normal unit administration.
- Use CLI tools for scripted setup, diagnostics, and recovery.
- Add tests when changing import validation, evidence adapters, rollups,
  competency sync, learner evaluation, or teacher override behavior.
- Keep UI labels short and role-specific. Admin pages should explain operations;
  student and teacher pages should lead with next action and status.
