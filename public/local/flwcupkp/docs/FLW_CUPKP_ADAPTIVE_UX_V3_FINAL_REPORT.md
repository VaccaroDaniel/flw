# FLW C-UP-KP Adaptive Learner UX V3 Final Report

Date: 2026-08-31  
Gate: Program 3 F1  
Contract: `FLW_CUPKP_ADAPTIVE_UX_V3_PRODUCTION_VALIDATION_V1`  
Plugin version: `2026083102` (`0.1.3-alpha`)

## Decision

Program 3 F1 implementation is complete and the final read-only validator is
deployed. The isolated integrated scenario passes the complete 13-step pipeline,
historical reproducibility, ownership, security/privacy, invariants, and all
performance budgets.

The current live scope is **not production-ready**. Course `125`, unit `U038`
has no deployed C-UP-KP objects, no resolved Program 1 content identities, no
learner History V1 activity facts, and no adaptive recommendation history.
Imported C-UP-KP objects also remain attached to deleted courses `124` and `174`.
F1 correctly returns `not_production_ready`; no production data was fabricated
to turn this into a pass.

## Integrated Pipeline Proof

The isolated Moodle PHPUnit scenario uses a real Moodle course, enrolled
learner, quiz and page course modules, resolved Program 1 content identities,
published C/UP/KP curriculum, a versioned goal, placement state, two trusted
History V1 source-event/attempt facts, controlled E1 reprocessing, E2 mastery,
E3 retention, A4B eligibility, two A5 recommendation versions, UX2 learner
composition, and UX3 staff composition.

All 13 required steps pass:

1. Content published.
2. Moodle activities deployed.
3. Learner acted.
4. History V1 captured the fact.
5. C-UP-KP evidence interpreted.
6. Mastery/current state updated.
7. Retention/review state updated.
8. Adaptive decision composed.
9. Eligible activity resolved.
10. Recommendation persisted.
11. Dashboard/timeline composed.
12. New History V1 fact captured.
13. Previous recommendation superseded and path adapted.

The separate full `local_flwhistory` suite verifies History V1 observers,
capture services, attempts, grades, completion, content identity, reconciliation,
privacy, and downstream contract behavior.

## Historical Reproducibility

Each F1 recommendation snapshot is required to preserve:

- Goal version and checksum.
- Curriculum/Foundation/Management versions.
- Evidence policy.
- Mastery policy.
- Retention policy.
- Adaptive decision and adaptive path policies.
- Progress/readiness policy.
- Learner-state snapshot.
- Candidate eligibility context and selected activity.

The isolated integrated test preserves all nine fields across two distinct
source hashes and at least one superseded recommendation. F1 added the missing
progress policy version to newly persisted A5 recommendation snapshots.

## Ownership Regression

All six ownership checks pass:

- Program 1 identity remains represented through History V1 content identities.
- `local_flwhistory` remains the source-history owner.
- Moodle core remains the gradebook owner.
- No duplicate C-UP-KP content registry or history store was introduced.
- Existing C-UP-KP services remain the semantic/mastery owner.
- A3/A4/A4B/A5 services remain the adaptive policy owner.

Normal source-history consumption remains
`FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`; raw Moodle logs remain diagnostic
only.

## Security And Privacy

All six static and runtime boundary checks pass:

- Learner path, teacher report, curriculum management, and override capabilities
  are registered.
- UX3 read and write web services declare the correct Moodle capabilities.
- The staff page requires report access and override capability for writes.
- The learner renderer exposes no staff intervention controls.
- The Moodle privacy provider is registered.
- The F1 validator has an empty write boundary and mutation counts are unchanged
  before and after validation.

## Invariants

- Detector self-test: `9/9` passed.
- Deterministic trajectory suite: `64` trajectories, `1,536` simulated steps,
  `0` incidents.

Production-ready status is impossible when any invariant fails.

## Performance

Live course `125` measurements, all within budget:

| Operation | Measured | Budget |
|---|---:|---:|
| History queries | 1.741 ms | 2,000 ms |
| Evidence normalization preview | 629.010 ms | 3,000 ms |
| State calculation | 0.947 ms | 1,000 ms |
| Graph traversal | 6.257 ms | 2,000 ms |
| Activity eligibility | 40.310 ms | 5,000 ms |
| Recommendation composition | 45.227 ms | 7,000 ms |
| Timeline render composition | 88.545 ms | 12,000 ms |
| Teacher view composition | 154.028 ms | 15,000 ms |

These timings prove the operations can be measured safely on the current live
dataset. They do not compensate for missing deployment/evidence data.

## Regression Evidence

- F1 focused: `3` tests, `37` assertions.
- Repository/Foundation freeze: `9` tests, `263` assertions.
- Program 3 cross-gate selection: `60` tests, `1,003` assertions.
- Full History V1 plugin: `51` tests, `384` assertions.
- Full C-UP-KP plugin: `177` tests, `2,078` assertions.
- PHP syntax checks: pass.
- `db/install.xml` parse: pass.
- Moodle upgrade to `2026083102`: pass.
- Moodle cache purge: pass.

## Live Validation

Validated scope:

- Course: `125`, `FLW-PLACEMENT-EN`, English Placement Test.
- Unit: `U038`.
- Auto-selected enrolled learner: user `3`.
- Pipeline result: `2/13` steps demonstrated.
- Findings: `16 BLOCKER`, `6 HIGH`, `0 MEDIUM`, `0 LOW`.
- Mutation check: unchanged.

Primary unresolved deployment findings:

- No C-UP-KP objects or mappings for course `125` / `U038`.
- No Program 1 content identities for that scope.
- Orphan object scopes still reference deleted courses `124` (`U038`) and `174`
  (`U037`).
- No History V1 learner activity/attempt/completion facts in the selected scope.
- No History V1-backed interpreted C-UP-KP evidence.
- No scoped mastery or retention states.
- No eligible next activity or persisted A5 recommendation.
- No two-version recommendation history to prove live path adaptation.

Ownership, security/privacy, invariants, performance, UX composition, and
read-only mutation checks pass on the live system.

## Required Production Remediation

This is remediation of the final gate, not a new build gate:

1. Publish/import a Program 1 unit into an active Moodle course and resolve its
   History V1 content identities.
2. Link the unit's C-UP-KP objects to active course modules and mappings.
3. Remove or explicitly migrate the orphan scopes for deleted courses `124` and
   `174` through the governed setup/import workflow.
4. Enrol a real learner and complete at least two mapped learner actions so
   History V1 contains separate facts.
5. Run controlled E1 evidence reprocessing, E2 mastery rebuild, E3 retention
   rebuild, and A5 path application after each action.
6. Rerun F1 and require `13/13`, zero `BLOCKER`, zero `HIGH`, no invariant
   incidents, and unchanged mutation counts.

```bash
php local/flwcupkp/cli/production_validation.php --action=discover
php local/flwcupkp/cli/production_validation.php --action=validate --courseid=COURSE_ID --unitcode=UNIT --userid=USER_ID
```

## Final Gate Freeze

F1 is the final Program 3 gate. Repository status is
`f1_validation_available`; this means the validator is implemented, not that
every deployment scope is production-ready. `next_allowed_gate` is `null`, the
F1 validator is read-only, and its write boundary is empty.

