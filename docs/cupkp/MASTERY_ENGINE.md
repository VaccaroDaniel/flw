# Mastery Engine

The engine calculates direct KP, UP, and competency states from evidence, then rolls child states up through the C-UP-KP graph.

KP states:

```text
not_introduced, introduced, practiced, controlled_use, independent_use, mastered, review_due
```

UP states:

```text
not_observed, emerging, developing, demonstrated, stable, transfer_ready
```

Competency states:

```text
not_started, developing, provisionally_achieved, achieved, sustained
```

Roll-up behavior:

- KP evidence updates the KP state, then recalculates every mapped parent UP and competency.
- UP evidence updates the UP state, then recalculates every mapped parent competency.
- Teacher KP overrides and cleared overrides also trigger parent recalculation.
- Scheduled state recalculation sweeps direct evidence and roll-up parents.

UP roll-up uses `flwcupkp_up_kp.weight` and `minreadiness`; required child KPs default to `0.70` readiness when no explicit minimum is configured.

Competency roll-up uses `flwcupkp_comp_up.weight` and `minmastery`; required child UPs default to `0.70` mastery when no explicit minimum is configured.

Competency states remain conservative. Child KP/UP mastery can create `provisionally_achieved`, but `achieved` or `sustained` requires a direct competency performance event or sufficient mapped UP performance evidence that satisfies the competency evidence rule, such as `minimum_direct_events`, `required_strength`, and `minimum_score`.

Operational command:

```bash
php local/flwcupkp/cli/recalculate_rollups.php
php local/flwcupkp/cli/recalculate_rollups.php --userid=5
php local/flwcupkp/cli/recalculate_rollups.php --userid=5 --no-sync
```
