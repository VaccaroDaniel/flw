# FLW C-UP-KP Implementation Plan

## Repository Findings

The writable working tree available to this Codex session is `C:\Users\com\Documents\Estimation Speaking`. It currently contains no Moodle core checkout or existing FLW source files beyond an incomplete `.git` directory. The wider FLW collection exists at `D:\Codex\Estimation Speaking`, but it is outside the writable root for this run. The C-UP-KP reference folder at `D:\WinPro.Delta\Projects\C-UP-KP` contains the master prompt and one completed unit package, `REW3_U038_V31_text_image_moodle_package.zip`.

Reference inputs inspected:

- `FLW_C_UP_KP_SCI_Paper_V3_Journal_Ready_Manuscript.docx`: journal-style Version 3 manuscript defining the C-UP-KP model, Moodle integration concept, and curriculum intelligence pipeline.
- `REW3_U038_V31_text_image_moodle_package.zip`: REW Unit 38, B1, "Problem Solving" package with `unit_profile.json`, `U038_KP_UP_Lesson_Map.csv`, `U038_Quiz_Corpus_Traceable.csv`, lesson plans, corpora, and image assets.
- `Master Prompt.txt`: operational implementation requirements for a Moodle-local plugin named `local_flwcupkp`.

The safest implementation target is therefore a portable Moodle local plugin source tree at:

```text
local/flwcupkp
```

It can be copied into an installed Moodle root as `local/flwcupkp`.

## Existing Reusable Components

The U038 package already provides usable curriculum traceability inputs:

- unit profile and unit aim;
- lesson-level KP/UP map;
- lesson plans generated from corpus sources;
- quiz items with source IDs and audit rules;
- vocabulary, grammar, reading, listening, speaking, writing, and project corpora.

These are sufficient for the first importer fixture and quality-audit workflow.

## Proposed Architecture

Implement `local_flwcupkp` as an isolated Moodle local plugin.

Primary layers:

- Domain tables for frameworks, competencies, Use Points, Knowledge Points, mappings, learning objects, evidence, learner states, rules, recommendations, imports, and audit logs.
- Repository services wrapping Moodle DML calls and transactions.
- Import/validation service for schema-checked JSON packages and CSV-derived maps.
- Mastery engine that calculates KP, UP, and competency states separately.
- Recommendation engine that stores explainable learning-path recommendations.
- External service endpoints for versioned API access.
- Admin pages for import, validation, coverage reports, and learner-path inspection.
- Scheduled tasks for recalculation and Moodle competency synchronization.

## Database Strategy

Use Moodle XMLDB tables with normalized names under the plugin namespace. Because Moodle table names are length-sensitive, table names use compact forms such as `flwcupkp_framework`, `flwcupkp_comp`, `flwcupkp_up`, and `flwcupkp_kp`.

Design requirements:

- stable external IDs;
- status/version fields;
- soft deletion through `status`;
- timestamps and user IDs for audit;
- unique indexes on stable IDs;
- mapping uniqueness indexes;
- learner/state indexes;
- import batch checksums for idempotency.

## Migration Strategy

Initial installation creates all base tables. Future upgrades should add nullable columns first, backfill through scheduled tasks, then enforce stricter validation in later versions. Existing Moodle grades, completions, competencies, and learner data must not be modified by install or upgrade scripts.

## API Strategy

Use Moodle external functions with capability checks and validated parameters. The first phase provides service functions for:

- listing frameworks;
- importing a C-UP-KP package;
- recording evidence;
- retrieving learner state;
- retrieving recommendations;
- coverage report generation;
- Moodle sync dry-run.

The implementation maps the prompt's REST-style endpoints to Moodle external services rather than custom public routes.

## Moodle Integration Strategy

Moodle remains the system of record for users, courses, enrolments, activities, grades, completion, and native competencies. `local_flwcupkp` owns Use Points, Knowledge Points, deeper mappings, evidence interpretation, mastery state, recommendations, and curriculum-quality reports.

Moodle competency sync is dry-run first. It links or proposes Moodle competencies only for the competency layer, never for UP or KP unless explicitly configured.

## Implementation Phases

1. Domain/database foundation, capabilities, privacy provider, and documentation.
2. JSON import with pilot fixtures and validation reports.
3. Evidence ingestion and explainable mastery calculation.
4. Recommendation engine and learner-path APIs.
5. Admin, teacher, and student dashboards.
6. Moodle competency synchronization.
7. Hardening: tests, accessibility, performance, backup/restore.

This pass implements Phase 1 plus functional Phase 2/3 foundations and clearly marks remaining UI breadth as deferred.

## Risks

- No live Moodle root is available in the writable workspace, so plugin install/upgrade cannot be executed against a real database in this run.
- Event adapter class names must be verified inside the target Moodle installation before enabling observers beyond conservative defaults.
- U038 is B1 Unit 38, while the master prompt's built-in demonstration asks for B2 Unit 37. Both are kept separate.
- Large-scale mastery thresholds require empirical calibration after real learner data is available.

## Assumptions

- The target Moodle version is 4.1 or later, matching recent FLW plugin references.
- The plugin will be installed at `local/flwcupkp`.
- The first production import format is JSON, with CSV support represented through derived JSON examples and documented templates.
- The offline STT endpoint remains an internal adapter input, not a public unauthenticated client endpoint.

## Rollback Strategy

- Install only creates plugin-owned tables.
- Imports are tracked by batch checksum and can be marked rolled back.
- Imported rows include source batch metadata where applicable.
- Moodle competency synchronization supports dry-run and logs proposed changes before write mode.
- Removing the plugin should not alter Moodle gradebook, completion, or native competency records.
