# H3 Reconciliation

Gate: H3 - Grade-Version History + Attempt Semantics + Reconciliation

Status: PASS

## Purpose

H3 reconciliation keeps Program 2 current grade summaries aligned with Moodle Gradebook while preserving historical source facts.

The reconciliation target is:

```text
local flwhist_grade_summary <-> Moodle grade_grades / grade_items
```

## What Reconciliation May Update

Reconciliation may update only local derived summary rows in `flwhist_grade_summary` and local diagnostic rows in `flwhist_reconcile_run`.

It may repair:

- stale official grade values in the local summary
- latest attempt pointer
- best attempt pointer
- latest grade version pointer
- current or missing official grade status
- summary JSON

## What Reconciliation Must Not Update

Reconciliation must not silently rewrite:

- Moodle core grade tables
- source events
- attempt facts
- grade-version source facts
- correction or supersession history

If source facts are wrong, a controlled correction path must create an auditable correction instead of overwriting history.

## Status Values

Current H3 statuses:

- `current`: Moodle official grade exists and the summary reflects current derived state.
- `official_grade_missing`: the Moodle grade item exists, but the learner's official grade row is missing.

## Operational Checks

The H3 live upgrade confirmed:

- `local_flwhistory` version: `2026082801`
- `flwhist_grade_summary` table exists
- grade history service autoloads
- 12 observer definitions are present
- scheduled coverage refresh remains enabled

The live site had no existing grade version or grade summary rows at the time of the H3 check, so the upgrade was schema/registration only and did not alter learner grade facts.

