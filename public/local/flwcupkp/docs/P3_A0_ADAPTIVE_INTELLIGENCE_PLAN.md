# Program 3 Gate A0 - Adaptive Learner Intelligence Plan

Status: complete

Date: 2026-08-28

## Principle

Program 3 owns interpretation. Program 2 owns source history.

That means C-UP-KP and adaptive-learning services consume History V1 facts, then
apply explicit C-UP-KP mapping, evidence, mastery, retention, and adaptive
policies. They do not scrape raw Moodle logs as their normal input.

## Planned Consumption Lanes

| History V1 fact | Future Program 3 use | First gate |
| --- | --- | --- |
| `source_events` | provenance, source identity, coverage, unresolved mapping state | E1 |
| `attempts` | mapped attempt-to-evidence conversion | E1 |
| `grades` | grade-linked evidence summaries and learner evaluation views | E1/E2 |
| `completion` | completion evidence only when mappings say it is pedagogically valid | E1 |
| `placement` | cold-start learner profile and starting path | A2 |
| `content_identities` | Program 1 course/section/cmid/activity/assessment/question resolution | C3/A4B |

## Foundation Gates After A0

1. C0: integrated repository audit.
2. C1: canonical C-UP-KP domain model.
3. C1B: ontology boundary and validation.
4. C2: relationships and prerequisites.
5. C3: content and evidence mapping contracts.
6. C3B: evidence semantics and quality model.
7. C4: lifecycle, versioning, and governance.
8. C5: foundation freeze V1.
9. C5B: read-only foundation inspector.

Only after those foundation gates should Program 3 enable production
History-to-C-UP-KP evidence conversion, mastery, retention, adaptive pathing, and
learner UX simplification.

## Production Boundary

Normal:

- Read bounded payloads from `local_flwhistory\local\evidence_source_adapter`.
- Store C-UP-KP evidence only through a future E1 adapter that records source
  contract, source keys, curriculum version, evidence policy version, quality,
  and mapping state.
- Preserve latest attempt, best attempt, and official Moodle grade separately.

Diagnostic-only:

- Raw Moodle logs.
- Direct Moodle module table scraping outside a History V1 reconciliation path.
- One-off repair reads that bypass History V1.

## UX Direction

Learner-facing pages should continue moving from tables toward clear next-action
cards, progress visuals, unit maps, retrieval/review prompts, and concise
explanations. Teacher/admin pages should expose freshness, evidence quality,
coverage gaps, and override reason trails without asking users to inspect raw
database rows.

