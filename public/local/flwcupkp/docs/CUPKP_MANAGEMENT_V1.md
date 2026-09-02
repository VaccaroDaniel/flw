# C-UP-KP Management V1

Program 3 Gate CM4 freezes the C-UP-KP management surface for production consumers.

## Contract

- Version: `FLW_CUPKP_MANAGEMENT_V1`
- Gate: `P3_CM4`
- Normal source-history input: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`
- Normal source rule: `use_history_v1_adapter_not_raw_moodle_logs`
- Next allowed gate: `E1`

## Frozen Inputs

Management V1 depends on these frozen or ready surfaces:

- C1 canonical domain model
- C1B ontology boundary
- C2 relationship and prerequisite graph semantics
- C3 content/evidence mapping contract
- C3B evidence semantics and quality model
- C4 lifecycle/versioning/governance
- C5 Foundation V1
- CM1 operational curriculum authoring
- CM2 guarded relationship editing and where-used impact previews
- CM3 coverage, bulk management, and governance
- Program 2 History V1 downstream evidence contract

## Required Pass Criteria

CM4 passes only when:

- ontology boundaries are frozen/guarded
- graph semantics are frozen
- management CRUD surfaces are present
- permissions are registered at the expected context levels
- where-used impact previews are available
- coverage validation runs through the six CM3 bounded checks
- bulk management has dry-run, transactional import, duplicate detection, export, and controlled rollback
- lifecycle governance is frozen
- Program-1 imported content mappings resolve to stable object identities
- Program-2 History V1 contract is ready

## Production Consumer Surface

Consumers should read:

- `management_v1_contract::contract`
- `management_v1_contract::management_status`
- `management_v1_contract::consumer_snapshot`
- web service `local_flwcupkp_get_management_v1_status`
- admin page `/local/flwcupkp/management.php`

Consumers may use CM1/CM2/CM3 read surfaces for navigation, selected entity detail, where-used impact, coverage matrix, and governance dashboard summaries.

Management writes remain limited to the existing guarded authoring and governance methods. Production consumers treat this contract as read-only.

## Explicit Non-Scope

CM4 does not add:

- adaptive path selection
- History V1 evidence reprocessing writes
- mastery state recalculation
- learner state mutation
- recommendation policy changes
- raw Moodle log scraping

Those concerns start at E1 or later gates.
