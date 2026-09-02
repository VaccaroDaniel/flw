# Program 3 Gate A0 - History Consumption Audit

Status: complete

Date: 2026-08-28

## Objective

Consume `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` as the trusted Program 2
output surface and plan C-UP-KP/adaptive learner intelligence without scraping
raw Moodle logs as the normal evidence source.

## Inputs Reviewed

- Program 3 revised gated master prompt v3.0.
- Integrated Three-Target Implementation Program v2.0.
- `local_flwhistory` downstream evidence contract V1.
- Current `local_flwcupkp` plugin source, schema, observers, adapters, mastery,
  recommendations, learner evaluation, teacher UI, and README.

## History V1 Contract

Provider:

```text
local_flwhistory\local\evidence_source_adapter
```

Required contract:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

Required fact families:

- `source_events`
- `attempts`
- `grades`
- `completion`
- `placement`
- `content_identities`

Required guarantees:

- read-only access
- bounded queries
- stable source keys
- coverage state included
- no adaptive policy
- no C-UP-KP mastery mutation

## Current C-UP-KP Source Coupling

The existing plugin can already record useful evidence, but it does so through
direct Moodle observers and module-table reads:

- `db/events.php` observes Moodle completion, quiz, assignment, H5P, SCORM, and
  optional FLW VR Room events.
- `classes/observer.php` routes those events directly to evidence adapters.
- `classes/local/quiz_evidence_adapter.php` reads `quiz_attempts`, `quiz`,
  `course_modules`, and quiz question tables.
- `classes/local/activity_evidence_adapter.php` reads
  `course_modules_completion`.
- `classes/local/specialized_evidence_adapter.php` reads assignment and
  module-specific event payloads.
- `classes/local/flwvrroom_evidence_adapter.php` reads optional FLW VR Room
  attempt data when installed.
- `classes/local/evidence_sync_repair.php` finds missing quiz evidence by
  joining Moodle quiz tables directly.

These paths are classified as KEEP for the current deployed pilot and EXTEND or
REFACTOR for Program 3. They must not become the normal source of future
adaptive learner intelligence.

## Gate A0 Decision

Program 3 normal source-history input is History V1:

```text
use_history_v1_adapter_not_raw_moodle_logs
```

Raw Moodle log/table access is allowed only for exceptional diagnostics,
repair, or reconciliation until the relevant Program 3 gate replaces those paths
with History V1 consumers.

## A0 Implementation

Added:

```text
local_flwcupkp\local\history_v1_consumer_contract
```

The class is intentionally read-only. It verifies the frozen contract, reports
planned consumption lanes, and can perform bounded sample reads for a course
without writing C-UP-KP evidence, states, recommendations, or audit rows.

## C0 Starting Classification

KEEP:

- Existing C-UP-KP schema and generic unit pages.
- Unit Setup Wizard.
- Curriculum Manager.
- Learner Evaluation profile.
- Teacher Review and override/approval audit behavior.
- Native Moodle competency sync readiness and dry-run controls.

EXTEND:

- Evidence schema with History V1 source keys, quality dimensions, result
  states, and policy versions.
- Object mappings with explicit evidence roles and Program 1 identity fields.
- Learner state with clearer separation of evidence-derived state, teacher
  decision state, and adaptive readiness.
- Health pages with History V1 freshness and reprocessing status.

REFACTOR:

- Direct quiz/completion/module evidence adapters into History V1 fact consumers.
- Evidence repair queue into History V1 reprocessing.
- Recommendation engine into a versioned adaptive decision policy.

DEPRECATE:

- Normal production dependence on Moodle raw log/event scraping.
- Treating completion as evidence unless mapping rules explicitly permit it.

UNKNOWN:

- Backup/restore coverage must be audited in C0.
- Large-scale performance constraints must be measured after History V1 source
  counts are known.

## A0 Stop Boundary

This gate does not implement:

- canonical domain model changes
- History-to-C-UP-KP evidence writes
- mastery policy changes
- adaptive decision policy
- student path generation
- teacher override changes
- raw Moodle log scraping

