# H7 Migration and Reconciliation Runbook

## Dry Run

Backfill preview:

`php local/flwhistory/cli/history_v1.php --action=backfill --courseid={courseid} --limit=100`

Reconciliation preview:

`php local/flwhistory/cli/history_v1.php --action=reconcile --courseid={courseid} --limit=100`

## Execute

Backfill write:

`php local/flwhistory/cli/history_v1.php --action=backfill --courseid={courseid} --limit=100 --execute --idempotency=H7-YYYYMMDD`

Reconciliation repair:

`php local/flwhistory/cli/history_v1.php --action=reconcile --courseid={courseid} --limit=100 --execute --idempotency=H7-YYYYMMDD`

## Resume

Use the `nextcursors` object returned by the previous command:

`php local/flwhistory/cli/history_v1.php --action=backfill --courseid={courseid} --cursorjson='{"quiz_attempts":123}' --execute`

## Source Selection

Use comma-separated sources:

`php local/flwhistory/cli/history_v1.php --action=backfill --courseid={courseid} --sources=quiz_attempts,completion --limit=100`

Available sources:

- `quiz_attempts`
- `completion`
- `grade_history`
- `grade_current`
- `placement`

## Freeze Check

`php local/flwhistory/cli/history_v1.php --action=freeze --courseid={courseid} --limit=25`
