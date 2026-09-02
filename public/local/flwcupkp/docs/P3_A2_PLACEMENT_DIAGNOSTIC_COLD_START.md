# Program 3 Gate A2 - Placement Diagnostic Cold Start

## Purpose

A2 treats placement as initial diagnostic evidence, not permanent truth. It
consumes only the frozen History V1 downstream evidence contract from
`local_flwhistory` and stores interpreted current placement state in
`flwcupkp_placement_state`.

## Supported States

- `NOT_TAKEN`
- `VALID`
- `STALE`
- `INCOMPLETE`
- `LOW_CONFIDENCE`
- `TEACHER_OVERRIDE`

## Policy Cases

- `no_placement`: no History V1 placement fact exists; no evidence is written.
- `partial`: only explicitly scored dimensions can become evidence.
- `abandoned`: no evidence unless a scored dimension and explicit mapping exist.
- `refused`: records the learner choice; no evidence is written.
- `imported_history`: valid unless stale or low confidence.
- `institutional_entry`: may enter the pipeline when explicitly mapped.
- `teacher_override`: remains labeled as an override and is never permanent truth.
- `stale_placement`: visible as state but not replayed as new evidence.

## Evidence Rule

A2 writes `history_v1_placement` evidence only when the History V1 placement
profile names an assessed dimension with a numeric score and that dimension
resolves to a C-UP-KP target by either:

- direct `targettype` and `targetid`;
- explicit C-UP-KP external ID; or
- a placement/diagnostic `flwcupkp_object` whose metadata names the dimension
  and whose object map points to the target.

An overall CEFR level or overall placement score alone can update the diagnostic
state, but it does not create KP, UP, or competency evidence.

## Entry Points

```text
/local/flwcupkp/placement_diagnostic.php
local/flwcupkp/cli/placement_diagnostic.php --action=status
local/flwcupkp/cli/placement_diagnostic.php --action=preview --courseid=COURSEID --unitcode=UNITCODE
local/flwcupkp/cli/placement_diagnostic.php --action=apply --courseid=COURSEID --unitcode=UNITCODE --confirm=1
```

## Boundary

A2 may write:

- `flwcupkp_placement_state`
- `flwcupkp_evidence`
- `flwcupkp_state`
- `flwcupkp_audit`

A2 does not:

- scrape raw Moodle logs;
- mutate History V1 source facts;
- treat placement as permanent truth;
- fabricate unassessed dimensions;
- select adaptive paths;
- change recommendation ranking.

## Next Gate

Program 3 Gate A3 - Adaptive Decision Policy V1.
