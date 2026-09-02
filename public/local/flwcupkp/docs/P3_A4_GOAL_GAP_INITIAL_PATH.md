# Program 3 Gate A4 - Goal-Gap + Initial Personalized Path

## Scope

A4 computes the first explainable target-level route for a learner.

Trusted inputs:

- A1 learner goal
- E2 current learner state
- C-UP-KP requirements through C2 relationship traversal
- C2 prerequisites
- E3 retention/review state
- A3 adaptive decision policy

## Contract

Runtime contract: `FLW_CUPKP_GOAL_GAP_INITIAL_PATH_V1`

Policy version: `cupkp-goal-gap-initial-path-v1`

Service: `local_flwcupkp\local\goal_gap_path_service`

Surfaces:

- `initial_path.php`
- `cli/initial_path.php`
- `local_flwcupkp_get_goal_gap_path_status`
- `local_flwcupkp_get_learner_initial_path`
- `local_flwcupkp_get_class_initial_path_summary`

## Output Shape

Each learner path returns:

- `goal_gap_analysis`
- missing KP/UP/competency buckets
- satisfied KP/UP/competency buckets
- blocked-by-prerequisite buckets
- `candidate_next_targets`
- `initial_personalized_path`
- `next_target`
- `projected_roadmap`
- `destination`
- explainability hashes and source snapshots

## A4 Boundary

A4 is read-only.

It does not:

- scrape raw Moodle logs
- mutate History V1 facts
- change goals, placement, mastery, or retention state
- write recommendation rows
- persist path rows
- resolve Moodle activities
- run continuous adaptation

Activity resolution starts at A4B. Continuous adaptation starts at A5.
