# Program 3 UX1 Student Learning Timeline View Contract

## Identity

- Gate: `P3_UX1`
- Contract: `FLW_CUPKP_STUDENT_LEARNING_TIMELINE_VIEW_V1`
- View policy: `cupkp-past-present-future-view-v1`
- Next allowed gate: `UX2`

## Purpose

UX1 integrates trusted learner history and frozen learner intelligence into one
Past, Present, and Future presentation. It is a read-only composition contract,
not a new source-of-truth service.

## Past

Owner: `local_flwhistory`.

Source DTO: `LearnerHistoryDashboardCore`.

History retains ownership of:

- Grade History
- Detailed Learning History
- Recent Activity
- Attempt History
- historical Learning Journey

UX1 calls the approved History dashboard service and delegates rendering to the
History dashboard renderer. It does not query raw Moodle logs, copy History
tables, normalize source events, or rebuild a History panel.

## Present

Owner: `local_flwcupkp`.

Source: `FLW_CUPKP_PROGRESS_GOAL_READINESS_CONTRACT_V1`.

The compact Present view contains:

- History-owned current location as a read-only fact
- semantically separate mastery progress, goal readiness, and path progress
- qualitative milestones when a percentage is not defensible
- current versioned learning goal
- bounded current C-UP-KP skill/mastery, confidence, evidence, and retention states

## Future

Owner: `local_flwcupkp`.

Sources include the frozen A5 continuous adaptive path and A4/A4B route and
activity-resolution contracts.

The compact Future view contains:

- adaptive next action and resolved activity
- bounded projected roadmap
- bounded persisted A5 recommendation history
- version-aware `why_path_changed` dimensions covering goal, curriculum,
  learner state, policy, selected target, selected activity, and adaptive action

## Presentation Boundary

Templates receive either the approved History dashboard DTO or a compact
Program 3 presentation DTO. Program 3 Present and Future payloads exclude raw
relationship graphs, prerequisite collections, target-resolution internals,
and eligible/ineligible activity pools.

## Read-Only Boundary

UX1 writes no records. It does not:

- mutate or rebuild History V1
- scrape raw Moodle logs
- calculate or persist new evidence, mastery, retention, placement, or goals
- apply an adaptive recommendation
- unlock or complete a Moodle activity
- implement UX2 learner-experience simplification

History V1 remains the only normal source-history input.
