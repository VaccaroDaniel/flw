# Program 3 Gate A5 Report

## Gate

`P3_A5` - Continuous Adaptive Path Engine

## Completion Criteria

- A4B is the sole activity eligibility input.
- All eight frozen adaptive actions are represented.
- Preview and controlled single/class apply surfaces exist.
- Recommendation writes are deterministic and idempotent.
- Previous A5 decisions are retained as superseded history.
- Full goal/curriculum/state/policy/candidate snapshots are persisted.
- Legacy recommendation ownership is preserved.
- Moodle availability cannot be bypassed.
- Privacy metadata, API contract, dashboard links, CLI, and tests are present.
- The gate stops at A5B.

## Write Boundary

```text
flwcupkp_recommend
flwcupkp_audit
```

All source-state inputs remain read-only during A5 recommendation apply.
