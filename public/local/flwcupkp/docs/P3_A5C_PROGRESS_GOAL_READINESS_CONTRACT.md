# FLW C-UP-KP Progress and Goal Readiness Contract V1

## Gate

- Gate: `P3_A5C`
- Contract: `FLW_CUPKP_PROGRESS_GOAL_READINESS_CONTRACT_V1`
- Policy: `cupkp-progress-goal-readiness-v1`
- Normal source-history input: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`
- State changes: none
- Next allowed gate: `UX1`

## Metric separation

The four metrics are not interchangeable.

| Metric | Numerator | Denominator | Meaning |
| --- | --- | --- | --- |
| `completion_progress` | Distinct goal-relevant mapped Moodle activities completed in trusted History V1 facts | Distinct goal-relevant mapped activities in the current course/unit | Activity completion only |
| `mastery_progress` | Weighted mastery score capped by evidence sufficiency | Total weight of unique current goal/path requirements | Evidence-supported mastery only |
| `goal_readiness` | Weighted minimum of mastery, confidence, evidence, and retention attainment | Total weight of mandatory requirements in a defensible current goal | Readiness to confirm the semantic goal |
| `path_progress` | Current A4 requirements classified satisfied | Unique current A4 requirements | Progress through the mandatory path |

Target weights are KP `1`, UP `2`, and competency `3`. Every metric response
exposes numerator, denominator, weights, mandatory gaps, confidence, retention,
evidence ceiling, missing evidence, and policy version.

## Readiness ceilings

Evidence counts cap readiness at `0.00`, `0.55`, `0.80`, and `1.00` for zero,
one, two, and at least three evidence items. Retention caps are `0.55` new,
`0.65` learning, `0.90` consolidating, `1.00` retained, `0.70` review due,
`0.60` uncertain, `0.45` relearning, and `0.75` when retention is missing.

## Percentage rule

Goal Readiness is displayed as a percentage only when there is a current,
versioned active or completed goal, at least one semantic destination target,
and a non-empty requirement denominator. Otherwise the preferred learner
measure is a qualitative milestone without a percentage.

Qualitative milestones are:

- `GOAL_NOT_SET`
- `GOAL_PAUSED`
- `GOAL_SCOPE_INCOMPLETE`
- `PREREQUISITES_NEEDED`
- `EVIDENCE_NEEDED`
- `RETENTION_CHECK_NEEDED`
- `BUILDING_TOWARD_GOAL`
- `READY_FOR_GOAL_CONFIRMATION`
- `GOAL_ACHIEVED`

## Goal achievement

A displayed 100% does not achieve a goal. Achievement requires all conditions:

- the current goal is semantically defensible;
- every mandatory requirement is satisfied;
- no hard prerequisite is blocked;
- every requirement meets mastery and confidence thresholds of `0.70`;
- every requirement has at least three evidence items;
- every requirement is retained;
- no required evidence is missing.

## Boundary

A5C is read-only. It does not change goals, learner state, mastery, retention,
recommendations, mappings, completion, or History V1. UX1 may compose these
frozen outputs into a Past-Present-Future dashboard but must not redefine them.
