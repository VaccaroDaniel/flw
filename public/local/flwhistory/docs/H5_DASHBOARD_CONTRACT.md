# Program 2 Gate H5 Dashboard Contract

## Scope

Gate H5 implements the learner history and grade history dashboard core for `local_flwhistory`.

The dashboard is a trustworthy Past plus non-adaptive Present view. It uses H0-H4 trusted services and H3 grade-summary data. It does not calculate C-UP-KP mastery, adaptive recommendations, goal readiness, future roadmap predictions, or mastery-based skill progress.

## Route

```text
/local/flwhistory/dashboard.php?courseid={courseid}
```

Optional parameters:

| Parameter | Meaning |
| --- | --- |
| `userid` | Learner id. Omit or pass `0` for the current user. |
| `limit` | Page size for history tables. Clamped to 1..100. |
| `attemptoffset` | Offset for attempt details. |
| `gradeoffset` | Offset for grade history. |
| `historyoffset` | Offset for detailed learning history. |
| `activityoffset` | Offset for recent activity. |

## Dashboard Model

`local_flwhistory\local\dashboard_service::learner_dashboard_core()` returns:

| Key | Source |
| --- | --- |
| `present` | H4 `PresentSummaryCore` |
| `journey` | H4 `LearningJourneyCore` |
| `standard_next_action` | Non-adaptive course-order journey state |
| `grade_distinctions` | Trusted H3 `flwhist_grade_summary` plus H4 grade history |
| `trend` | Basic evidence trend from H4 attempt and grade records |
| `attempt_history` | H4 `AttemptHistoryQuery` |
| `grade_history` | H4 `GradeHistoryQuery`, audit excluded |
| `learning_history` | H4 `LearningHistoryQuery` |
| `recent_activity` | H4 `RecentActivityQuery` |
| `program3_placeholders` | Explicit unavailable placeholders |

## Standard Next Action

The dashboard may show:

- Continue current unit
- Next standard available activity

This is labelled as standard course progression only. It is not adaptive or personalized.

## Grade Distinctions

H5 keeps these separate:

- latest attempt score
- best attempt score
- official Moodle grade
- latest grade version

The dashboard does not conflate attempts with official Moodle grades.

## Trends

H5 shows only basic evidence trends when at least two trusted points exist:

- attempt score trend
- official grade trend

Skill trend is marked insufficient in H5 because H4 history does not contain a reliable skill taxonomy.
