# Program 2 Gate H1 Service Contracts

## `source_identity`

Path: `local/flwhistory/classes/local/source_identity.php`

Responsibilities:

- Build source keys from `sourcesystem`, `sourcetype`, `sourceid`, `sourceversion`, and `eventtype`.
- Enforce required identity parts.
- Shorten long keys safely.
- Build stable JSON and payload hashes.
- Convert Moodle events to source-event DTOs.

## `repository`

Path: `local/flwhistory/classes/local/repository.php`

Responsibilities:

- Upsert source events by source key.
- Upsert attempts by source key.
- Upsert question/item attempts by source key.
- Upsert placement facts by source key.
- Upsert grade versions by source key.
- Upsert completion transitions by source key.
- Upsert Program 1 content links by source key.
- Upsert reconciliation runs by source key.
- Record correction/supersession links.
- Query learner timelines, grade versions, source counts, placement history, and content links.

Repositories contain no pedagogical mastery policy.

## `normalizer`

Path: `local/flwhistory/classes/local/normalizer.php`

Responsibilities:

- Convert Moodle events to source-event DTOs.
- Convert Moodle quiz attempts to normalized attempts.
- Convert Moodle question attempts to item history records.
- Convert Moodle grade rows to grade versions.
- Convert completion rows to completion history records.
- Convert FLW placement rows to attempt and placement-history DTOs.

H1 normalization is structural only. H1B owns normalization policy version semantics.

## `p1_resolver`

Path: `local/flwhistory/classes/local/p1_resolver.php`

Responsibilities:

- Resolve course, section, cmid, and SCORM SCO identity from cached Program 1 links.
- Cache Program 1 content links.
- Return unresolved results without guessing when no link is available.

This is the Program 1 boundary, not a reimplementation of Smart Course Editor mapping.

## Service Wrappers

| Service | Path | Purpose |
| --- | --- | --- |
| `history_service` | `classes/local/history_service.php` | Source event append/query service. |
| `attempt_service` | `classes/local/attempt_service.php` | Attempt and question-attempt recording/query service. |
| `grade_history_service` | `classes/local/grade_history_service.php` | Grade version and grade correction service. |
| `completion_service` | `classes/local/completion_service.php` | Completion transition service. |
| `placement_history_service` | `classes/local/placement_history_service.php` | Placement source fact service. |
| `coverage_service` | `classes/local/coverage_service.php` | Query-only timeline and course source coverage summaries. |
| `correction_service` | `classes/local/correction_service.php` | Generic correction/supersession service. |
| `reconciliation_service` | `classes/local/reconciliation_service.php` | Repair/backfill/replay run metadata service. |
| `evidence_source_adapter` | `classes/local/evidence_source_adapter.php` | Read-only Program 3 source payload adapter. |

## Program 3 Adapter Boundary

`evidence_source_adapter` exposes history facts to Program 3 without deciding:

- evidence strength
- KP/UP/competency mastery
- recommendations
- Moodle competency ratings

Those decisions remain in `local_flwcupkp`.

## Observer and Task Scaffold

Files:

- `db/events.php`
- `db/tasks.php`
- `classes/observer.php`

H1 registers no active observers and no active scheduled tasks. The observer class contains a no-op scaffold only.

