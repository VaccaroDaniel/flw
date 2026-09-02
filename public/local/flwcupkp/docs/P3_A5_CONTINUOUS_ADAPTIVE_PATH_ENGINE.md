# Program 3 Gate A5 - Continuous Adaptive Path Engine

## Outcome

Gate A5 implements controlled continuous adaptation using only frozen upstream
contracts. The runtime pipeline is:

```text
Program 2 event -> Program 3 evidence -> learner state -> retention -> goal gap
-> adaptive decision -> A4B eligibility -> A5 recommendation
```

The engine supports `ADVANCE`, `SKIP`, `EXTRA_PRACTICE`, `REMEDIATION`,
`REVIEW`, `RETRY`, `REASSESS`, and `REPRIORITIZE`.

## Runtime Surfaces

- Service: `local_flwcupkp\local\adaptive_path_engine_service`
- Page: `/local/flwcupkp/adaptive_path.php`
- CLI: `local/flwcupkp/cli/adaptive_path.php`
- Contract: `FLW_CUPKP_CONTINUOUS_ADAPTIVE_PATH_ENGINE_V1`
- Policy: `cupkp-continuous-adaptive-path-engine-v1`

Preview calls are read-only. Controlled apply is available for one learner or
a bounded class scope. The same policy/source hash is idempotent. When the hash
changes, only the current A5-owned row in that learner/course/unit scope is
superseded; legacy recommendations are untouched.

## Persisted Decision Snapshot

Each A5 recommendation stores:

- goal ID, version, active version, checksum, policy, and status;
- curriculum framework/course/unit and Foundation/Management/mapping contracts;
- mastery, confidence, retention, and upstream source snapshots;
- History V1, evidence, mastery, retention, adaptive, A4, A4B, and A5 policies;
- selected target and eligible Moodle activity;
- candidate summary, resolution/path hashes, reason codes, and timestamp.

## Safety Boundary

The only A5 writes are `flwcupkp_recommend` and `flwcupkp_audit`. A5 does not
mutate History V1, evidence, mastery, retention, placement, goals, curriculum,
mappings, Moodle availability, or activity completion. If no activity is
eligible, it stores a diagnostic action with no `objectid` or `cmid`.

## Next Gate

The next allowed gate is A5B: Trajectory Simulation and Invariant Testing.
