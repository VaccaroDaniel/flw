# Program 2 Gate H5 Non-Adaptive Boundary

## Owned By H5

H5 owns:

- learner dashboard page
- learner access checks for that page
- responsive dashboard layout
- present summary rendering
- non-adaptive learning journey rendering
- attempt details
- grade history
- detailed learning history
- recent activity
- basic evidence trends where supported

## Reserved For Program 3

The following remain unavailable in H5:

- C-UP-KP Mastery
- Adaptive Next
- Goal Readiness
- Projected Future Roadmap
- mastery-based Skill Progress

These are rendered as `Not available yet` placeholders with reason code:

```text
PROGRAM_3_OWNS_CUPKP_AND_ADAPTIVE_LOGIC
```

## No Fabricated Data

H5 does not synthesize missing mastery, skill, or adaptive values. If the trusted H0-H4 data does not support a panel, the dashboard returns an insufficient-data or not-available state.

## Security

Current learner access requires:

```text
local/flwhistory:viewown
```

Viewing another learner requires:

```text
local/flwhistory:viewcourse
```

or system-level:

```text
local/flwhistory:viewall
```

The H5 learner dashboard never requests grade audit fields from H4.
