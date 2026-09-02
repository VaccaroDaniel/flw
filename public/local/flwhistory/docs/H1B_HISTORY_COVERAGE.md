# Program 2 Gate H1B History Coverage Contract

## Purpose

H1B freezes the historical coverage semantics that all later capture, analytics, learner UI, and Program 3 C-UP-KP consumers must honor. The key rule is simple: missing rows are not automatically evidence of learner inactivity or non-learning.

## Coverage Statuses

| Status | Meaning | Consumer rule |
| --- | --- | --- |
| COMPLETE | The source family is reliable for the requested scope and time range. | Consumers may evaluate absence of events as meaningful when the interval is covered. |
| PARTIAL | Some history exists, but the requested scope or time range has known gaps. | Consumers may display available facts, but must not infer inactivity from missing facts. |
| SOURCE_LIMITED | The source family cannot provide complete history, for example because a plugin is unavailable or a source never records required events. | Consumers must report the limitation and avoid negative inference from missing facts. |
| NOT_BACKFILLED | Production capture may exist, but historical import/backfill has not completed for the scope. | Consumers must avoid historical comparisons that depend on pre-capture data. |
| UNKNOWN | No coverage fact exists for the requested scope. | Consumers must treat missing events as unavailable history. |

The plugin implements these constants in `local_flwhistory\local\history_policy` and stores coverage facts in `flwhist_coverage`.

## Event Availability Semantics

H1B explicitly separates source coverage from event availability:

| Event availability | Meaning |
| --- | --- |
| EVENT_AVAILABLE | At least one source event is available in the scoped coverage fact. |
| NO_EVENT_OCCURRED | Coverage is COMPLETE and the event count is zero for the requested scope. This is the only state where absence may be treated as meaningful. |
| NO_EVENT_AVAILABLE | Coverage is UNKNOWN, PARTIAL, SOURCE_LIMITED, or NOT_BACKFILLED, or a source is unavailable. Absence must not be treated as learner behavior. |

The repository infers event availability from `coveragestatus` and `eventcount` unless a trusted caller supplies the field explicitly.

## Coverage Scope

Coverage facts may be scoped by:

- Learner: `userid`
- Source family: `sourcefamily`
- Course/world/stage/unit: `courseid`, `worldid`, `stageid`, `unitid`
- Time range: `timerangestart`, `timerangeend`

The `scopelevel` value records the intended coverage level: `system`, `course`, `world`, `stage`, `unit`, or `learner`.

## Required Facts

Each coverage record can carry the H1B-required coverage facts:

- `capturestartedat`
- `backfillstartedat`
- `backfillcompletedat`
- `earliestreliableeventat`
- `latestreconciledat`
- `sourceavailable`
- `sourcefamily`
- `coveragestatus`
- `eventavailability`
- `eventcount`
- `reasoncode`
- `detailsjson`

## Inactivity Guard

Teacher inactivity analytics may only evaluate an interval when `coverage_service::can_evaluate_inactivity()` returns true. That requires COMPLETE coverage plus an earliest/latest reliable window that encloses the requested interval.

If coverage is missing or insufficient, the consumer must return a coverage notice instead of an inactivity finding.

## Learner UI Guard

Learner-facing pages must show a material coverage notice when missing or limited history changes the interpretation of progress. They may display available history, but must not describe source-limited or not-backfilled gaps as lack of work.

## Program 3 Adapter

`evidence_source_adapter::source_event_to_payload()` now includes:

- `sourcefactkey`
- `sourcefamily`
- `normpolicyversion`
- `coverage`

Program 3 must use `coverage.coveragestatus` and `coverage.eventavailability` before interpreting missing FLW history as a learning gap.

## Production Capture Boundary

H1B does not enable production capture. No active source observers or scheduled capture tasks are enabled by this gate. H2 may enable capture only after this coverage contract is treated as stable.

