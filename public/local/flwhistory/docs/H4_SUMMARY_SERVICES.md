# Program 2 Gate H4 Summary Services

## PresentSummaryCore

`PresentSummaryCore` builds a current learner summary from trusted Program 2 and Moodle data:

- course identity
- current unit/activity identity from latest normalized source events
- Moodle completion progress for completion-tracked course modules
- active learning days from recent normalized events
- average official Moodle grade from `flwhist_grade_summary`
- average assessment attempt score from `flwhist_attempt`
- normalization policy version

Study time is returned as an explicit insufficient-data state in H4:

```json
{
  "status": "insufficient_data",
  "seconds": null,
  "reason": "NO_RELIABLE_STUDY_TIME_SOURCE_H4"
}
```

## LearningJourneyCore

`LearningJourneyCore` builds a course journey using:

- Program 1 course module and unit identity cache
- Moodle course module order and completion state
- Program 2 normalized source events
- Program 2 attempt history

Journey item states are:

| State | Meaning |
| --- | --- |
| `completed` | Moodle completion state is complete/pass/fail. |
| `current` | The item has evidence or attempts, but is not completed. |
| `future` | No completion, source event, or attempt evidence yet. |

Checkpoint is a separate boolean flag. It is true when an assessment identity is present or when the module is a quiz.

## Query Services

The query services provide paginated read access:

- `LearningHistoryQuery`
- `AttemptHistoryQuery`
- `GradeHistoryQuery`
- `RecentActivityQuery`

These services are history readers only. They do not infer C-UP-KP mastery or adaptive next steps.

## Performance Guardrails

H4 applies:

- page limits clamped to 100
- offset normalization
- default recent-activity window of 30 days
- batched content-link, completion, event-count, and attempt-count lookups
- compact DTOs for API payloads
