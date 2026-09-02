# Program 2 Gate H6 Teacher Analytics Contract

## Scope

Gate H6 implements history-specific teacher analytics for `local_flwhistory`.

The page is a class-level and learner-row view over trusted History V1 data from H0-H5. It is descriptive only. It does not own C-UP-KP mastery, adaptive policy, placement decisions, or intervention routing.

## Route

```text
/local/flwhistory/teacher.php?courseid={courseid}
```

Optional parameters:

| Parameter | Meaning |
| --- | --- |
| `limit` | Learner rows per page. Clamped to 1..100. |
| `offset` | Learner row offset. |

## Access

The page requires teacher/admin history access:

| Capability | Use |
| --- | --- |
| `local/flwhistory:viewcourse` | View course-level teacher analytics. |
| `local/flwhistory:viewall` | System-level admin access. |
| `local/flwhistory:viewgradeaudit` | Include grade audit details. |

Grade audit data is omitted unless the current user has audit permission.

## Service Model

`local_flwhistory\local\teacher_analytics_service::teacher_dashboard_core()` returns:

| Key | Meaning |
| --- | --- |
| `type` | `TeacherHistoryAnalyticsCore` |
| `course` | Course id, fullname, and shortname. |
| `pagination` | Limit, offset, total, and hasmore. |
| `class_summary` | Completion, activity, official grade, attempt, and attention count summaries. |
| `learners` | Current page learner rows. |
| `attention_definitions` | Descriptive definitions for allowed attention signals. |
| `checkpoint_placement_summary` | Checkpoint and placement counts from visible learner rows. |
| `grade_audit` | Recent grade-version audit records, or capability-required state. |
| `program3_boundary` | Explicit marker that adaptive policy is not in H6 scope. |
| `generatedat` | DTO generation timestamp. |
| `normpolicyversion` | Frozen normalization policy version from H1B. |

## Teacher View

The renderer shows:

- class current completion summary
- last meaningful activity summary
- official Moodle grade summary
- attempt trend summary
- inactivity and evidence attention counts
- repeated unsuccessful attempt signals
- checkpoint and placement history summary
- grade audit panel when authorized
- individual learner drill-down links to the H5 learner dashboard

## Allowed Attention Signals

H6 emits only evidence-based descriptive signals:

- `inactive`
- `repeated_unsuccessful_attempts`
- `grade_decline_with_enough_comparable_data`
- `stalled_completion`
- `missing_activity_evidence`

## Performance Guardrails

Learner rows are paginated. Class-level counts use aggregate SQL. The implementation does not run full-lifetime per-learner query loops.
