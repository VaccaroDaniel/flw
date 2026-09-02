# H0 As-Is / To-Be

## Ownership Summary

| Subsystem | As-Is Owner | To-Be Owner | H0 Decision |
| --- | --- | --- | --- |
| Smart Course Editor import/export | Program 1 artifacts | Program 1 | Frozen and consumed through contract only. |
| Stable FLW source identity for Moodle objects | Program 1 contract artifacts | Program 1, consumed by Program 2 | Program 2 must call or mirror the P1 mapping contract. |
| Normalized learning and grade history | Moodle/FLW source tables only | New `local_flwhistory` | Create in H1. |
| Grade version timeline | Moodle gradebook history only | `local_flwhistory` | H1 designs normalized grade versions. |
| Attempt/event timeline | Moodle and FLW plugin tables/events | `local_flwhistory` | H1 designs idempotent source events and attempts. |
| Question-level evidence history | Moodle quiz/question engine | `local_flwhistory` | H1 normalizes question attempt data without changing quiz. |
| C-UP-KP curriculum, mappings, mastery rules | `local_flwcupkp` | `local_flwcupkp` | Program 2 does not duplicate. |
| C-UP-KP learner evaluation snapshots | `local_flwcupkp` | `local_flwcupkp`, later fed by `local_flwhistory` | Program 2 provides source history only. |
| Adaptive learning path and recommendations | `local_flwcupkp` | `local_flwcupkp` | Program 2 provides reliable history/coverage. |
| Moodle competency writing | `local_flwcupkp` | `local_flwcupkp` | Preserve existing owner. |
| Learner dashboard shell | `theme_flwacademy` plus FLW plugins | Program 3/theme surfaces | Program 2 can expose APIs but does not redesign H1. |

## Functional Gaps

| Area | As-Is | To-Be | H1 Implication |
| --- | --- | --- | --- |
| Cross-source history | Spread across Moodle, FLW plugins, and C-UP-KP evidence tables. | One normalized immutable event/attempt/grade history layer. | Add `local_flwhistory` schema and repository/service contracts. |
| Idempotency | Source plugins use their own primary keys and event callbacks. | Stable source identity per user/course/unit/object/attempt/source revision. | Define source key format and unique constraints. |
| Program 1 mapping | Available in Python/docs artifacts. | Program 2 can resolve Moodle source objects to stable FLW ids. | Build PHP adapter or import/cached map strategy in H1/H2. |
| Grade changes | Moodle gradebook has current grades and history tables. | Teacher/admin can see grade evolution and correction provenance. | H1 defines grade version model; later gates capture events/backfill. |
| Question coverage | Quiz question usage tables exist, but not unified with C-UP-KP coverage. | History can answer which FLW activity/KP/question was attempted and when. | H1 defines coverage rows linked to P1 and Program 3 mapping ids. |
| Repair/reconciliation | `local_flwcupkp` has evidence repair for its own evidence layer. | Program 2 has source-history reconciliation runs and replayable adapters. | H1 defines reconciliation records; implementation deferred. |
| Privacy/export/delete | Existing FLW plugins each own their privacy providers. | `local_flwhistory` has privacy provider and scoped capabilities. | H1 adds privacy/capability blueprint and schema support. |

## Integration Model

Program 2 sits between source systems and C-UP-KP intelligence:

1. Source systems produce Moodle/FLW attempts, grades, completion, placement, media, speaking, VR, and exam events.
2. Program 2 normalizes those facts into append-oriented history records using stable source identity.
3. Program 2 resolves content identity through Program 1 contract artifacts.
4. Program 3 consumes Program 2 history for mastery/evaluation/recommendations when the later cross-program contract is implemented.

## To-Be Boundary Rules

- Program 2 may read source plugin tables and Moodle core tables.
- Program 2 may store normalized history and reconciliation metadata in `local_flwhistory`.
- Program 2 may expose read APIs for timeline, grade history, coverage, and source health.
- Program 2 must not write C-UP-KP mastery states.
- Program 2 must not write Moodle competency ratings.
- Program 2 must not alter imported course content or SCORM packages.
- Program 2 must not become a second adaptive recommendation engine.

## H1 Readiness

H1 can proceed without guessing because H0 identified:

- The Program 1 contract source and its lookup responsibilities.
- The current Moodle version and component paths.
- The current FLW plugin versions and data/event surfaces.
- The owner for each conceptual subsystem.
- The duplicate-ownership risks around `local_flwcupkp` and adaptive learning.
- The visual baseline that H1 must leave unchanged.

