# Program 2 Gate H4 API Contract

## Scope

Gate H4 exposes trusted learning and grade history through secure Moodle external functions and internal summary services. It does not build C-UP-KP mastery logic, adaptive path selection, recommendation logic, or dashboard UI.

## External Functions

All functions are registered in `local/flwhistory/db/services.php` and implemented by `local_flwhistory\external\api`.

| Function | Internal Service | Purpose |
| --- | --- | --- |
| `local_flwhistory_get_present_summary` | `PresentSummaryCore` | Current learner/course summary from trusted H0-H3 data. |
| `local_flwhistory_get_learning_history` | `LearningHistoryQuery` | Paginated normalized source event history. |
| `local_flwhistory_get_attempt_history` | `AttemptHistoryQuery` | Paginated quiz/assessment attempt history. |
| `local_flwhistory_get_grade_history` | `GradeHistoryQuery` | Paginated grade version history, with audit redaction by default. |
| `local_flwhistory_get_recent_activity` | `RecentActivityQuery` | Bounded recent activity from normalized source events. |
| `local_flwhistory_get_learning_journey` | `LearningJourneyCore` | Course/unit journey state from Program 1 structure, Moodle completion, and Program 2 history. |

## Shared Parameters

Most functions accept:

| Parameter | Meaning |
| --- | --- |
| `courseid` | Required Moodle course id. |
| `userid` | Learner id. `0` means current user and is the default learner route. |
| `limit` | Page size. Service clamps to 1..100. |
| `offset` | Page offset. Negative values normalize to `0`. |
| `timestart` | Optional lower timestamp bound. |
| `timeend` | Optional upper timestamp bound. |

Function-specific filters:

| Function | Extra Filters |
| --- | --- |
| Learning history | `sourcefamily` |
| Attempt history | `cmid`, `unitid` |
| Grade history | `cmid`, `gradeitemid`, `includeaudit` |
| Recent activity | `sourcefamily`; defaults to the last 30 days when no time window is supplied. |

## Response Shape

External functions return:

```json
{
  "status": "ok",
  "datajson": "{...stable JSON payload...}"
}
```

`datajson` contains the service DTO. Internal PHP callers should use `local_flwhistory\local\history_api_service` directly when possible.

## DTO Safety

Learner-facing DTOs exclude raw source keys and grade audit internals. Grade audit details are only included when `includeaudit=true` and the caller has an explicit audit capability.

## Pagination

History query services return:

```json
{
  "type": "LearningHistoryQuery",
  "userid": 123,
  "courseid": 456,
  "pagination": {
    "limit": 50,
    "offset": 0,
    "total": 10,
    "hasmore": false
  },
  "records": []
}
```

The service performs bounded reads and avoids unbounded learner-history scans for API callers.
