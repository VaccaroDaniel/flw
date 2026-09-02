# C-UP-KP M4 Governance

Status: complete

Date: 2026-08-28

M4 governance is implemented as Program 3 Gate C4:

```text
FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1
local_flwcupkp\local\lifecycle_governance_contract
```

Authoritative documents:

- `P3_C4_LIFECYCLE_GOVERNANCE_CONTRACT.md`
- `P3_C4_VALIDATION_MATRIX.md`
- `P3_C4_GATE_REPORT.md`
- `P3_C4_MANIFEST.json`

The implemented governance layer freezes lifecycle states, status transitions,
published-row immutability, framework clone/revision behavior, `REPLACED_BY`
replacement rules, evidence-bearing object-map deletion protection, and runtime
validation for duplicate codes, invalid relationships, orphan rows, missing
evidence routes, invalid replacements, and invalid published states.

Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
use_history_v1_adapter_not_raw_moodle_logs
```
