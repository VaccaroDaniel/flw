# Program 3 Gate E1 Report

Status: implemented

## Completed

- Added `local_flwcupkp\local\history_evidence_adapter`.
- Added read-only adapter status with Management V1 and History V1 dependency
  checks.
- Added read-only preview for History V1 attempts and eligible completion facts.
- Added controlled apply reprocessing with audit records.
- Added idempotent, versioned derived evidence keys.
- Added mapping evidence-meaning fingerprints so corrected mappings can
  regenerate derived evidence without overwriting old rows.
- Added unresolved mapping reporting without fabricated evidence.
- Added Moodle admin page, CLI, web-service functions, and OpenAPI entries.
- Updated CM4/Foundation/CM1/CM2/CM3 handoff metadata to E2.

## Preserved Boundaries

- History V1 is the only normal source-history input.
- Raw Moodle log scraping is not used.
- Grade versions remain separate History facts and are not treated as mastery
  evidence by E1.
- Adaptive path selection and recommendation policy are unchanged.
- Derived evidence is written only through `mastery_engine::record_evidence()`.

## Tests

Primary coverage:

```text
local_flwcupkp\history_evidence_adapter_test
```

Expected checks:

- contract consumes `FLW_CUPKP_MANAGEMENT_V1`;
- contract consumes `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`;
- preview is read-only;
- unresolved mapping creates no evidence;
- apply creates History-backed evidence;
- repeated apply is idempotent;
- completion evidence respects C3 completion guardrails.

## Next Gate

```text
Program 3 Gate E2 - Mastery + Confidence + Current Learner State
```
