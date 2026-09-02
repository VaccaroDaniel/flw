# Program 3 Gate A5B: Trajectory Simulation and Invariants

## Frozen contract

- Gate: `P3_A5B`
- Contract: `FLW_CUPKP_TRAJECTORY_SIMULATION_INVARIANTS_V1`
- Simulation policy: `cupkp-trajectory-invariants-v1`
- Source policy: frozen A5 continuous adaptive path policy
- Normal history input: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`
- Write boundary: none
- Next allowed gate: `A5C`

## Deterministic scenarios

1. Success, failure, and remediation
2. Retention review
3. Mastery uncertainty
4. Modality diversity
5. Goal change
6. Hidden activity fallback
7. Hard prerequisite
8. Deterministic replay

## Global invariants

The service detects loops, oscillation, repetitive modality, impossible paths,
unavailable NEXT activities, hard-prerequisite skips, mastery collapse after a
successful outcome, retention flooding, and nondeterministic replay.

Every detector has an adversarial self-test that mutates one otherwise clean
trajectory and proves that the detector fails it. Normal simulations use only
counterfactual in-memory rows and never persist projections or change A5
recommendations.

## Surfaces

- Moodle page: `/local/flwcupkp/trajectory_simulation.php`
- CLI: `php local/flwcupkp/cli/trajectory_simulation.php --action=simulate`
- Status API: `local_flwcupkp_get_trajectory_simulation_status`
- Simulation API: `local_flwcupkp_run_trajectory_simulation`
- Learner projection API: `local_flwcupkp_get_learner_trajectory_projection`

The browser and suite APIs require `local/flwcupkp:viewreports`. Learner
projection uses the same self-or-teacher access rule as the learner evaluation
services.

## Operational limits

- Trajectories: 1 to 2,000
- Steps per trajectory: 4 to 100
- Returned samples: 1 to 20
- Default suite: 512 trajectories x 24 steps = 12,288 simulated steps

The same seed, selected scenarios, trajectory count, step count, source policy,
and simulation policy must reproduce the same suite hash.
