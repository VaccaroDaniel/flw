# Program 3 Gate A4B - Candidate Eligibility + Activity Resolution

## Contract

`FLW_CUPKP_CANDIDATE_ACTIVITY_RESOLUTION_V1`

## Purpose

A4B resolves the A4 target-level path into Moodle activities the learner can
actually open now.

The hard invariant is:

```text
inaccessible_activity_can_never_become_next
```

## Eligibility Pipeline

```text
target
curriculum_validity
prerequisite
world_stage_course
enrollment
moodle_availability
visibility
dates
attempts
teacher_restrictions
device_capability
diversity
eligible_activities
```

## Runtime Surface

- Service: `local_flwcupkp\local\candidate_activity_resolution_service`
- Admin/teacher/student page: `/local/flwcupkp/activity_resolution.php`
- CLI: `php local/flwcupkp/cli/activity_resolution.php --action=status`
- Web services:
  - `local_flwcupkp_get_candidate_activity_resolution_status`
  - `local_flwcupkp_get_learner_activity_resolution`
  - `local_flwcupkp_get_class_activity_resolution_summary`

## Boundary

A4B is read-only. It does not write recommendation rows, persist learner paths,
mutate mastery/retention/placement state, mutate History V1, or unlock Moodle
activities. If the first A4 target is not accessible, A4B selects the next-best
eligible target. If none is eligible, it returns a diagnostic.

## Handoff

Next allowed gate: `A5 - Continuous Adaptive Path Engine`.
