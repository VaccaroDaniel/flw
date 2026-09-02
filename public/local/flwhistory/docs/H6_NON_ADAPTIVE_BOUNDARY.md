# Program 2 Gate H6 Non-Adaptive Boundary

## Boundary

Gate H6 is a History V1 teacher analytics layer. It reports what has happened in Moodle history and grades. It does not decide what a learner should do next.

## Explicitly Excluded

H6 does not implement:

- C-UP-KP mastery calculation
- C-UP-KP weakness diagnosis
- adaptive recommendation logic
- retention or risk scoring
- adaptive priority ranking
- teacher override of adaptive paths
- learner path policy ownership

Those responsibilities belong to Program 3.

## Program 3 Placeholder

The service returns:

```text
program3_boundary.status = not_in_scope
program3_boundary.reason = PROGRAM_3_OWNS_ADAPTIVE_POLICY_AND_CUPKP_MASTERY
```

This gives future Program 3 code a clear integration boundary without allowing H6 to become an adaptive-policy source.

## Teacher Notes

H6 does not add a teacher intervention-note subsystem. If teacher notes are added later, they must remain bounded, privacy-controlled, and separate from adaptive path overrides.
