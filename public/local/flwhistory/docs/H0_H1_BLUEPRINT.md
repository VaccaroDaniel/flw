# H0 to H1 Blueprint

## H1 Objective

Create the new `local_flwhistory` Moodle plugin with database schema, capabilities, privacy provider, repository/service contracts, and developer-level smoke tests. H1 should not register production observers or change visible learner/teacher/admin pages.

## Proposed Plugin Skeleton

Path:

- `D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwhistory`

Initial files:

- `version.php`
- `db/install.xml`
- `db/access.php`
- `classes/privacy/provider.php`
- `classes/local/source_identity.php`
- `classes/local/p1_resolver.php`
- `classes/local/repository.php`
- `classes/local/normalizer.php`
- `classes/local/coverage_service.php`
- `classes/local/grade_history_service.php`
- `classes/local/reconciliation_service.php`
- `tests/source_identity_test.php`
- `tests/repository_test.php`
- `README.md`

## Proposed H1 Tables

Names are intentionally draft-level. H1 may adjust final names during implementation, but must preserve these responsibilities.

| Table | Purpose |
| --- | --- |
| `flwhist_source_event` | Append-oriented normalized source facts with idempotency key, source component, source entity id, event type, user/course/cmid/unit references, event time, normalized summary, and source payload hash. |
| `flwhist_attempt` | Normalized attempt records across quiz, SCORM, assignment, media, exam, placement, AI speaking, VR, and AI assessment. |
| `flwhist_question_attempt` | Question or item-level attempt facts, including question usage, slot, question id/version, response summary, fraction/mark, and correctness state. |
| `flwhist_grade_version` | Grade value versions from Moodle gradebook and FLW result sources, including previous/current value, grader/source, and correction reason where available. |
| `flwhist_completion` | Completion state transitions for course modules and courses. |
| `flwhist_content_link` | Resolved Program 1 identity cache for course, section, cmid, SCO, unit, activity, revision, and freshness. |
| `flwhist_reconcile_run` | Backfill/repair/replay run metadata, status, counts, actor, and error summary. |

## Required H1 Capabilities

- `local/flwhistory:viewown`
- `local/flwhistory:viewcourse`
- `local/flwhistory:viewall`
- `local/flwhistory:reconcile`
- `local/flwhistory:manage`

## Service Contract Draft

`source_identity`:

- Build stable source keys.
- Hash compact payload summaries.
- Validate source component/entity fields.

`p1_resolver`:

- Resolve course id to world/stage.
- Resolve section id or cmid to unit.
- Resolve SCORM cmid plus SCO identifier to activity id.
- Return freshness/revision state from Program 1 mappings.

`repository`:

- Upsert source event by idempotency key.
- Insert or update normalized attempt by source key.
- Insert grade version if value/provenance changed.
- Insert completion transition if state/time changed.
- Query learner timeline and course source coverage.

`normalizer`:

- Convert Moodle/FLW source rows into neutral Program 2 DTOs.
- Keep source plugin specifics out of the repository.

`reconciliation_service`:

- Record reconcile run start/end/failure.
- Provide later H2/H3 repair hooks.

## H1 Test Plan

- Validate XMLDB schema parses.
- Validate capability definitions load.
- Validate privacy provider class loads.
- Unit-test source identity key stability.
- Unit-test repository idempotent insert/update behavior with Moodle advanced test case.
- Unit-test Program 1 resolver fallback behavior when no map is available.
- Run Moodle upgrade and purge caches only after implementation is ready.

## H1 Exit Criteria

- `local_flwhistory` installs cleanly.
- No visual page changes occur.
- No production observers are registered unless explicitly approved by a later gate.
- Source event/attempt/grade/completion records can be created through service tests.
- Program 1 resolver boundary exists, even if H2 fills some adapters.
- Manifest for H1 reports PASS before H2 begins.

