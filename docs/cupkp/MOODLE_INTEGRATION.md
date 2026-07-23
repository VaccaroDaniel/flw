# Moodle Integration

Moodle owns users, enrolments, courses, activities, grades, completion, competencies, and learning plans.

`local_flwcupkp` owns:

- Use Points;
- Knowledge Points;
- prerequisite graph;
- lesson/activity mappings;
- evidence interpretation;
- learner mastery state;
- adaptive recommendations;
- curriculum-quality audits.

Competency synchronization is dry-run by default. UP and KP records are not synchronized into Moodle competencies unless a future administrator setting explicitly enables it.

Write mode remains effectively locked until every C-UP-KP framework has a `moodleframeworkid` and every C-UP-KP competency has a `moodlecompetencyid`. The sync UI, scheduled task, and external service all use the same readiness check, so a stale enabled setting still behaves as dry-run when links are incomplete.

Evidence adapters are intentionally scoped:

- mapped quizzes write evidence from graded attempts;
- mapped non-quiz activities write evidence from Moodle completion;
- the U038 performance assessment page writes teacher-scored speaking, writing, and project evidence for mapped UP/competency targets;
- manual teacher evidence validates learner enrolment and the selected course/unit before writing;
- all evidence paths reject missing targets and object mappings.

Native Moodle competency rating sync:

- `local/flwcupkp/cli/link_moodle_competencies.php` creates or reuses Moodle competency frameworks/competencies and stores their IDs on C-UP-KP framework/competency rows.
- `local/flwcupkp/cli/recalculate_rollups.php` recalculates UP and competency parent states from mapped child KP/UP states. Use `--userid=N` for one learner and `--no-sync` to suppress immediate Moodle rating writes.
- `local/flwcupkp/cli/sync_moodle_competencies.php` dry-runs the C-UP-KP state to Moodle rating writer.
- `local/flwcupkp/cli/sync_moodle_competencies.php --execute` writes native Moodle ratings when write mode and readiness are both enabled.
- The scheduled `local_flwcupkp\task\sync_competencies` task uses the same writer.
- New C-UP-KP KP, UP, and competency evidence attempts immediate best-effort parent-state roll-up. New or changed competency roll-up states then attempt a native Moodle rating sync when write mode and readiness are enabled.
- The rich U038 student and teacher pages show the same parent UP/competency states next to KP evidence detail, making it visible when direct performance evidence has updated the Moodle-linked competency.
- The generic unit student and teacher pages share the reusable `unit_report` service. The teacher view can show parent UP/competency overview rows, parent decision queue counts, learner filters, and anchor links for any unit code with mapped C-UP-KP objects.
- Generic unit teacher pages can now write parent UP/competency teacher decisions. Confirm, override, and clear-override actions share the same audit, roll-up, and Moodle competency sync behavior as the U038 parent decision workflow, scoped by `unitcode`.
- Course-page cards also use mapped-unit discovery. The output hook replaces any legacy/static C-UP-KP course block, or inserts one when absent, with role-aware student next-action cards or teacher class overview cards for every mapped unit in the course.

Roll-up competency achievement remains evidence-gated: child KP/UP mastery can make a competency `provisionally_achieved`, but `achieved`/`sustained` requires direct competency performance evidence or mapped UP performance evidence that satisfies the competency evidence rule.
