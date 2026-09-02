# Program 3 Gate A1 - Competency-Centered Learning Goal

A1 models where a learner is trying to go before later gates decide placement,
diagnostic meaning, or adaptive path selection.

## Contract

Contract version:

```text
FLW_CUPKP_COMPETENCY_CENTERED_LEARNING_GOAL_V1
```

Service:

```text
local_flwcupkp\local\learning_goal_service
```

Normal page:

```text
/local/flwcupkp/learning_goal.php
```

The preferred goal target is a desired competency or skill profile. The payload
may also include CEFR, FLW stage, purpose, priority skills, target date, weekly
target, and selected competency, UP, or KP targets.

## Sources

Supported goal sources are:

- `STUDENT`
- `TEACHER`
- `INSTITUTION`

The web page and external API enforce source-aware capability checks. Student
goal updates are learner-owned. Teacher and institution updates require teacher
or admin capability in the relevant scope.

## Storage

Current goals are stored in:

```text
flwcupkp_goal
```

Immutable goal versions are stored in:

```text
flwcupkp_goal_version
```

Every changed goal creates a new version. Duplicate saves with the same checksum
return `unchanged` and do not create another version.

## Boundary

A1 may write only:

- `flwcupkp_goal`
- `flwcupkp_goal_version`
- `flwcupkp_audit`

A1 does not mutate evidence, mastery state, retention state, recommendations,
placement, diagnostics, cold-start policy, or adaptive path ranking.

## Handoff

Next allowed gate:

```text
Program 3 Gate A2 - Placement + Diagnostic + Cold Start
```
