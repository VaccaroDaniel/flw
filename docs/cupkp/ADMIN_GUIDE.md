# Admin Guide

Install by copying `local/flwcupkp` into a Moodle root and running Moodle upgrade.

Required capabilities:

- `local/flwcupkp:manageframeworks`
- `local/flwcupkp:import`
- `local/flwcupkp:viewreports`
- `local/flwcupkp:viewlearnerpath`
- `local/flwcupkp:override`
- `local/flwcupkp:synccompetencies`

Start with import validation, the production health check, and Moodle competency sync dry-run before enabling production writes.

Operational admin pages:

- `/local/flwcupkp/index.php`: admin landing page and coverage summary.
- `/local/flwcupkp/curriculum.php`: graph browser for Framework -> Competency -> UP -> KP -> Learning Object.
- `/local/flwcupkp/edit_entity.php`: controlled create/edit page for frameworks, competencies, UPs, KPs, and learning objects.
- `/local/flwcupkp/mappings.php`: mapping manager for Competency->UP, UP->KP, KP prerequisites, and Learning Object mappings.
- `/local/flwcupkp/import_export.php`: validate/import C-UP-KP JSON and export the current package.
- `/local/flwcupkp/manual_evidence.php`: record teacher-entered evidence for speaking, writing, project, portfolio, and external evidence.
- `/local/flwcupkp/student.php?courseid=124&unitcode=U038` and `/local/flwcupkp/teacher.php?courseid=124&unitcode=U038`: reusable unit-level learner and teacher views for any mapped unit code.
- `/local/flwcupkp/performance_u038.php?courseid=124`: focused U038 teacher scoring page for speaking, writing, and project performance evidence.
- `/local/flwcupkp/student_u038.php?courseid=124` and `/local/flwcupkp/teacher_u038.php?courseid=124`: U038 learner and teacher views showing KP progress plus parent UP/competency mastery overview.
- `/local/flwcupkp/sync.php`: Moodle competency sync readiness review and dry-run logging.

Use JSON import/export as the backup/restore path for curriculum definitions. Keep native Moodle competency write mode disabled until every C-UP-KP framework and competency has a verified Moodle target ID.

Production commands from the Moodle root:

```bash
php local/flwcupkp/cli/health_check.php
php local/flwcupkp/cli/link_moodle_competencies.php
php local/flwcupkp/cli/recalculate_rollups.php
php local/flwcupkp/cli/recalculate_rollups.php --userid=5
php local/flwcupkp/cli/sync_moodle_competencies.php
php local/flwcupkp/cli/sync_moodle_competencies.php --execute
php local/flwcupkp/cli/export_package.php --output=/path/flw-cupkp-export.json
php local/flwcupkp/cli/status.php
```

Production safety controls:

- The web import page accepts pasted JSON or plugin-relative files under `local/flwcupkp/fixtures/` and `local/flwcupkp/imports/`.
- Evidence writes validate the learner, course enrolment, target existence, object scope, and object-target mapping before storage.
- Teacher manual evidence is scoped to the selected course/unit filters.
- The U038 performance page only exposes mapped U038 performance tasks and stores rubric-backed teacher scores as UP or competency evidence.
- The U038 student and teacher pages display the parent UP/competency states beside the existing KP evidence detail, so direct performance evidence can be checked against the Moodle-linked competency result.
- The U038 teacher page supports teacher confirmation, override, and clear-override decisions for parent UP/competency states. Parent decisions are audited; UP changes recalculate dependent competency states; competency changes attempt native Moodle competency rating sync when write mode is ready.
- The parent overview includes a `Needs teacher decision` queue. Course-page competency/UP metric links open the queue for incomplete parent metrics, and parent actions advance to the next undecided urgent parent row.
- The U038 course-page teacher overview card shows parent decision queue counts for competencies and Use Points, with each count linked to the matching filtered queue.
- The U038 teacher verification page includes a parent decision queue summary above the filters, showing queue counts, the next learner/target in each queue, and quick links into queue work or teacher decision history.
- The generic unit teacher page now uses the shared unit report service to show parent UP/competency overview rows and decision queue counts for any mapped unit code. For U038 KP evidence approval/override and performance workflows, use the rich U038 verification and performance pages.
- The generic unit teacher page now supports parent UP/competency teacher decisions for any mapped unit code. Teachers with `local/flwcupkp:override` can confirm, override, or clear parent mastery states; every decision is audited, UP changes recalculate dependent competencies, and competency changes attempt native Moodle competency sync when write mode is ready.
- Course pages now discover mapped C-UP-KP units automatically. Students receive role-aware progress and next-action cards; teachers/admins receive class overview cards with progress links, teacher overview/verification links, and parent decision queue counts for each mapped unit.
- Direct mapping edits reject missing endpoints and cross-framework relationships.
- The sync page, scheduled task, and web service all treat Moodle competency writes as dry-run unless every framework and competency has a positive Moodle link ID.
- KP, UP, and competency evidence paths now roll parent states upward through the C-UP-KP topology. Teacher KP overrides trigger the same roll-up.
- The native rating writer maps C-UP-KP competency states to Moodle user competency ratings. `achieved`, `sustained`, and `mastered` become proficient. Lower evidence-backed states become not yet proficient. Course-scoped ratings are used when the learner is enrolled in the source course; otherwise the writer uses the global user competency record.
