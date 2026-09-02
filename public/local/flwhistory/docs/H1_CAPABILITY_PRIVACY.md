# Program 2 Gate H1 Capability and Privacy

## Capabilities

| Capability | Context | Archetypes | Purpose |
| --- | --- | --- | --- |
| `local/flwhistory:viewown` | Course | manager, editingteacher, teacher, student | View own learning history in a course. |
| `local/flwhistory:viewcourse` | Course | manager, editingteacher, teacher | View learner/class history in a course. |
| `local/flwhistory:viewall` | System | manager | View site-wide history. |
| `local/flwhistory:viewgradeaudit` | Course | manager, editingteacher, teacher | View grade history/audit data. |
| `local/flwhistory:export` | Course | manager, editingteacher, teacher | Export history data when later UI/API exists. |
| `local/flwhistory:reconcile` | System | manager | Run repair/backfill/reconciliation actions in later gates. |
| `local/flwhistory:manage` | System | manager | Manage Program 2 history settings in later gates. |

Authorization rule for later API/UI work: a `userid` parameter is never authorization. Course/system context and capability checks must control access.

## Privacy Provider

Path: `local/flwhistory/classes/privacy/provider.php`

The provider declares metadata for:

- `flwhist_source_event`
- `flwhist_attempt`
- `flwhist_question_attempt`
- `flwhist_grade_version`
- `flwhist_completion`
- `flwhist_placement`
- `flwhist_reconcile_run`
- `flwhist_correction`

Learner-owned tables are exported and deleted by `userid`.

Actor fields are anonymized when the actor is deleted:

- `flwhist_source_event.usermodified`
- `flwhist_grade_version.graderid`
- `flwhist_completion.overrideby`
- `flwhist_reconcile_run.userid`
- `flwhist_correction.userid`

`flwhist_content_link` is a Program 1 content identity cache and has no direct learner `userid`; it is not exported as learner personal history.

## H1 Privacy Limitation

The provider structure is implemented for the H1 schema. Full privacy/security audit remains a later production-hardening requirement (`P2-PRV-001`, `P2-SEC-001`) because H1 has no user-facing pages or production capture behavior.

