# H7 Security, Privacy, Performance, and Freeze

## Security

History V1 keeps learner/teacher separation in the H4-H6 services and pages:

- learners can read their own history
- teacher/admin access requires course or system capabilities
- grade audit data requires grade audit capability
- external functions validate parameters and context

History V1 does not add state-changing web forms.

## Privacy

The privacy provider exports and deletes:

- source events
- attempts
- question attempts
- grade versions
- grade summaries
- completion rows
- placement rows
- coverage rows
- correction rows involving the learner

## Performance

H7 measures five read paths:

- summary
- journey
- history pagination
- grade detail
- class history view

Use:

`php local/flwhistory/cli/history_v1.php --action=performance --courseid={courseid} --limit=25`

## Freeze

The freeze check verifies implementation readiness and returns the downstream contract.

Use:

`php local/flwhistory/cli/history_v1.php --action=freeze --courseid={courseid} --limit=25`
