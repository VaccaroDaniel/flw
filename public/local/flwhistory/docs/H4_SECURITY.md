# Program 2 Gate H4 Security Notes

## Access Model

Gate H4 uses Moodle course context checks before returning history data.

| Route | Capability |
| --- | --- |
| Current learner, `userid=0` or own user id | `local/flwhistory:viewown` in the course context. |
| Teacher/admin viewing another learner in a course | `local/flwhistory:viewcourse` in the course context, or `local/flwhistory:viewall` in system context. |
| Grade audit fields | `local/flwhistory:viewgradeaudit` in the course context, or `local/flwhistory:viewall` in system context. |

## Default Learner Route

The default external API route is learner-safe:

- If `userid` is omitted or `0`, H4 resolves the current Moodle user.
- Learners can view their own history only.
- Another learner's history is blocked unless the caller has explicit course or system scope.

## Grade Audit Redaction

`GradeHistoryQuery` returns learner-safe grade records by default. It does not expose:

- source event ids
- grade history ids
- grader ids
- correction and supersession links
- teacher correction reasons

Those fields are returned under `audit` only when the caller passes `includeaudit=true` and has audit permission.

## Non-Goals

H4 deliberately does not:

- calculate C-UP-KP mastery
- rate Moodle competencies
- generate adaptive recommendations
- expose raw source identity keys to learner DTOs
- add dashboards or navigation pages

## Test Coverage

The H4 external API tests verify:

- current-user resolution
- blocking one student from viewing another student's data
- teacher access to a learner in the same course
- audit field inclusion only for authorized teacher audit reads
