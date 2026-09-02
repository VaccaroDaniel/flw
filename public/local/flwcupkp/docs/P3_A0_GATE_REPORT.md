# Program 3 Gate A0 Report

Status: complete

Date: 2026-08-28

## Completed

- Reviewed Program 3 gate sequence and downstream prerequisites.
- Consumed `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`.
- Audited current `local_flwcupkp` source-history coupling.
- Added read-only Program 3 History V1 contract preflight service.
- Added PHPUnit coverage for the preflight service.
- Updated plugin README with the History V1 boundary.
- Added no-schema version checkpoint `2026082801`.
- Upgraded live Moodle and purged caches.

## Files

Plugin:

- `classes/local/history_v1_consumer_contract.php`
- `tests/history_v1_consumer_contract_test.php`
- `README.md`
- `version.php`
- `db/upgrade.php`

Documentation:

- `docs/cupkp/P3_A0_HISTORY_CONSUMPTION_AUDIT.md`
- `docs/cupkp/P3_A0_ADAPTIVE_INTELLIGENCE_PLAN.md`
- `docs/cupkp/P3_A0_GATE_REPORT.md`
- `docs/cupkp/P3_A0_MANIFEST.json`

## Gate Decision

Program 3 may proceed to C0 only with this source-history rule:

```text
use_history_v1_adapter_not_raw_moodle_logs
```

History V1 is the normal source-history input. Raw Moodle logs are
diagnostic-only.

## Validation

PHP lint:

- `classes/local/history_v1_consumer_contract.php`: pass
- `tests/history_v1_consumer_contract_test.php`: pass

PHPUnit:

- Focused A0 test: OK, 3 tests, 32 assertions.
- Full `local_flwcupkp_testsuite`: OK, 24 tests, 128 assertions.
- Full `local_flwhistory_testsuite`: OK, 51 tests, 384 assertions.

Live smoke:

- `local_flwcupkp` upgraded to `2026082801`.
- Moodle caches purged.
- `history_v1_consumer_contract::contract_status(126, 1)` returned `ready`.
- Contract version: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`.
- Normalization policy: `H1B-20260827.1`.
- Findings: none.

## Next Gate

Program 3 Gate C0: perform the integrated repository audit for C-UP-KP,
adaptive-learning, and learner UX using History V1 as the only normal
source-history input.
