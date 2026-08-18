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

The current pilot unit is `U038` in Moodle course `124`, but the plugin now
supports generic units through the Unit Setup Wizard and generic student,
teacher, evaluation, and performance pages.

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

## Main Entry Points

Replace `https://192.168.129.79` with your Moodle site's `$CFG->wwwroot`.

| Role | Normal page | Example |
| --- | --- | --- |
| Admin | C-UP-KP Home | `/local/flwcupkp/index.php` |
| Admin | Unit Setup Wizard | `/local/flwcupkp/setup.php` |
| Admin | Curriculum Manager | `/local/flwcupkp/curriculum.php` |
| Admin | Evidence Sync Health | `/local/flwcupkp/evidence_sync.php` |
| Admin | Moodle Competency Sync | `/local/flwcupkp/sync.php` |
| Admin | Calibration | `/local/flwcupkp/calibration.php` |
| Teacher | Teacher Review | `/local/flwcupkp/teacher.php?courseid=124&unitcode=U038` |
| Teacher | Speaking / Writing Assessment | `/local/flwcupkp/performance.php?courseid=124&unitcode=U038` |
| Student | My Progress | `/local/flwcupkp/student.php?courseid=124&unitcode=U038` |
| Student | My Learning Path | `/local/flwcupkp/evaluation.php?courseid=124&unitcode=U038` |

Current U038 pilot URLs:

```text
https://192.168.129.79/local/flwcupkp/index.php
https://192.168.129.79/local/flwcupkp/setup.php?courseid=124&unitcode=U038
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

8. Open the Moodle course page and confirm the role-aware C-UP-KP block:

   - Students see My Progress and their next action.
   - Teachers/admins see My Progress, Teacher Review, class overview, evidence
     queues, and sync health.

9. Collect evidence by having learners use the mapped activities.

10. Use Teacher Review to approve evidence, override/confirm states, and manage
    UP/competency decisions.

11. Use Evidence Sync Health to find and repair missing quiz evidence.

12. Use Moodle Competency Sync to dry-run and then enable native Moodle
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
student.php                       Generic student progress page.
student_u038.php                  U038 legacy student progress page.
teacher.php                       Generic teacher verification page.
teacher_u038.php                  U038 legacy teacher verification page.
evaluation.php                    Learner Evaluation profile.
performance.php                   Generic performance evidence page.
performance_u038.php              U038 legacy performance page.
evidence_sync.php                 Admin Evidence Sync Health page.
repair_sync.php                   POST endpoint for evidence repair actions.
sync.php                          Moodle competency sync review controls.
calibration.php                   Calibration report and snapshots.
calibration_proposal.php          Threshold proposal and recalculation workflow.
trace.php                         Traceability report.
manual_evidence.php               Manual evidence entry.
classes/local/import_service.php  JSON/CSV import service.
classes/local/mastery_engine.php  KP state calculation.
classes/local/rollup_engine.php   UP/competency rollups.
classes/local/learner_evaluation.php
                                  Learner Evaluation service.
classes/local/quiz_evidence_adapter.php
                                  Moodle quiz evidence adapter.
classes/local/evidence_sync_repair.php
                                  Pending quiz evidence repair service.
classes/local/moodle_competency_writer.php
                                  Native Moodle competency rating writer.
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

