# Program 3 Gate A3 - Adaptive Decision Policy V1

## Purpose

A3 freezes deterministic C-UP-KP adaptive decision rules before personalized
path generation. It reads trusted A1, A2, E2, E3, C2, Management V1, and
History V1 contract surfaces, then explains the learner's current adaptive
decision without writing a path, recommendation, mastery state, retention
state, placement state, or Moodle activity resolution.

## Contract

```text
FLW_CUPKP_ADAPTIVE_DECISION_POLICY_V1
```

Runtime policy version:

```text
cupkp-adaptive-decision-policy-v1
```

## Inputs

- A1 current competency-centered learning goal.
- A2 placement diagnostic and cold-start state.
- E2 mastery, confidence, and current learner state.
- E3 retention, retrieval, and review state.
- C2 prerequisite and relationship graph semantics.
- History V1 only through frozen downstream services.

## Outputs

- `NEXT TARGET`
- `PROJECTED ROADMAP`
- `DESTINATION`

A3 does not map the next target to a Moodle activity. That remains stopped
until a later gate.

## Decision States

- `GOAL_REQUIRED`
- `GOAL_REVIEW`
- `PLACEMENT_REQUIRED`
- `DIAGNOSTIC_INCOMPLETE`
- `PLACEMENT_REVIEW`
- `REASSESSMENT_RECOMMENDED`
- `RELEARNING_REQUIRED`
- `REVIEW_REQUIRED`
- `PREREQUISITE_REQUIRED`
- `REMEDIATION_REQUIRED`
- `RETRY_RECOMMENDED`
- `INTRODUCE_TARGET`
- `ADVANCE_READY`
- `FALLBACK_TEACHER_REVIEW`

## Visible Policy Rules

A3 exposes its thresholds in `adaptive_decision_policy_service::policy()`:

- mastery advance, retry, and remediation thresholds by target type;
- confidence low/stable thresholds;
- placement low-confidence, stale, and teacher-override handling;
- hard and soft prerequisite readiness thresholds;
- retention review/relearning precedence.

Candidate priority and tie-breaking are explicit:

- decision priority ascending;
- explicit goal target before non-goal target;
- KP before UP before competency;
- target external ID ascending;
- target ID ascending;
- signal code ascending.

## Explainability

Each learner decision includes:

- selected rule and action;
- target reference, if available;
- destination snapshot from A1;
- source snapshots from A1/A2/E2/E3;
- thresholds used;
- projected policy roadmap;
- deterministic decision hash for later anti-loop checks.

## Entry Points

```text
/local/flwcupkp/adaptive_decision.php
local/flwcupkp/cli/adaptive_decision.php --action=status
local/flwcupkp/cli/adaptive_decision.php --action=policy
local/flwcupkp/cli/adaptive_decision.php --action=learner --userid=USERID --courseid=COURSEID --unitcode=UNITCODE
local/flwcupkp/cli/adaptive_decision.php --action=class --courseid=COURSEID --unitcode=UNITCODE
```

## Boundary

A3 is read-only.

A3 does not:

- scrape raw Moodle logs;
- mutate History V1 source facts;
- mutate learning goals;
- mutate placement state;
- mutate mastery or retention state;
- write recommendation rows;
- persist generated paths;
- resolve C-UP-KP targets to Moodle activities;
- hide thresholds in arbitrary PHP constants.

## Next Gate

Program 3 Gate A4 - Goal-Gap + Initial Personalized Path.
