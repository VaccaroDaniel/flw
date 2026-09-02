# FLW C-UP-KP Implementation Plan

## Program 3 Gate A0 History V1 Boundary

Status: complete as of 2026-08-28.

Program 3 consumes `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1` through
`local_flwhistory\local\evidence_source_adapter` for normal learner-intelligence
source history. Raw Moodle logs are diagnostic-only. The preflight service is:

```text
local_flwcupkp\local\history_v1_consumer_contract
```

The next official Program 3 gate is C0, the integrated repository audit.

## Program 3 Gate C0 Integrated Repository Audit

Status: complete as of 2026-08-28.

The C0 audit verified Program 1 identity and Program 2 History V1 readiness
through `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`, inspected the current
`local_flwcupkp` plugin surface, classified every prompt-required subsystem, and
identified C1-C5 foundation gaps. The read-only audit service is:

```text
local_flwcupkp\local\program3_repository_audit
```

Live audit status on course `126` returned:

```text
ready_for_c1
```

The next Program 3 gate is C1, the canonical C-UP-KP domain model freeze.

## Program 3 Gate C1 Canonical Domain Model

Status: complete as of 2026-08-28.

The C1 gate froze Competency, Use Point, and Knowledge Point meanings in
`local_flwcupkp\local\canonical_domain_model`, preserved many-to-many C-UP-KP
relationships, separated official CEFR macro level from FLW stage, rejected
A2.1/A2.2-style pseudo-CEFR values, and prevented learner mastery fields from
being stored on curriculum definitions. The frozen contract is:

```text
FLW_CUPKP_CANONICAL_DOMAIN_MODEL_V1
```

Live smoke on courses `124` and `126` returned:

```text
frozen
```

The next Program 3 gate is C1B, ontology boundary validation to prevent
C/KP/UP category drift.

## Program 3 Gate C1B Ontology Boundary

Status: complete as of 2026-08-28.

The C1B gate froze ontology boundary validation in
`local_flwcupkp\local\ontology_boundary`. It detects overly narrow
competencies, KPs written as learner tasks, UPs containing unmodeled new
knowledge, and semantic duplicates across C/UP/KP types. It also defines the
current safe vocabulary for statuses, mapping roles, object roles, object
purposes, evidence-strength labels, and prerequisite labels. The frozen contract
is:

```text
FLW_CUPKP_ONTOLOGY_BOUNDARY_V1
```

Live smoke on courses `124` and `126` returned:

```text
guarded
```

The next Program 3 gate was C2, relationship and prerequisite graph semantics.

## Program 3 Gate C2 Relationship Graph

Status: complete as of 2026-08-28.

The C2 gate froze relationship and prerequisite graph semantics in
`local_flwcupkp\local\relationship_graph_contract`. It defines `SUPPORTS`,
`REQUIRES`, `EVIDENCE_FOR`, `TRAINS`, `EXTENDS`, `ALTERNATIVE_TO`,
`REVIEW_OF`, and `REPLACED_BY`, including source/target types, direction,
cardinality, symmetry, transitivity, cycle rules, inference behavior, version
behavior, and deprecation behavior. The frozen contract is:

```text
FLW_CUPKP_RELATIONSHIP_GRAPH_V1
```

The service also centralizes adjacency, dependency, and where-used graph
queries and prevents hard mandatory KP prerequisite cycles during package
validation, imports, and manual mapping saves.

Live smoke on courses `124` and `126` returned:

```text
frozen
```

The next Program 3 gate is C3, content and evidence mapping contracts.

## Program 3 Gate C3 Content + Evidence Mapping Contracts

Status: complete as of 2026-08-28.

The C3 gate froze content/evidence mapping contracts in
`local_flwcupkp\local\content_evidence_mapping_contract`. It connects stable
Program 1 content identities to C-UP-KP learning objects, defines canonical
pedagogical roles for object mappings, classifies accepted evidence source
types, and enforces that completion is not mastery. Completion can create
evidence only when the mapped role/purpose explicitly permits it. The frozen
contract is:

```text
FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1
```

C3 is wired into JSON/CSV validation, import, manual curriculum saves, evidence
guards, quiz/activity/specialized/VR evidence adapters, package schema, tests,
and live status checks. Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

Live smoke on course `124`, unit `U038`, returned:

```text
frozen
```

The next Program 3 gate was C3B, evidence semantics and quality model.

## Program 3 Gate C3B Evidence Semantics and Quality

Status: complete as of 2026-08-28.

The C3B gate froze evidence-event semantics in
`local_flwcupkp\local\evidence_semantics_quality_contract`. New C-UP-KP
evidence rows now carry C3B metadata in `flwcupkp_evidence.rubricjson`,
including History V1 source keys, result state, evidence role, performance
mode, direct/inferred flag, inference path, retry semantics, advisory evidence
ceiling, quality dimensions, and evidence policy version.

The frozen contract is:

```text
FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1
```

The evidence policy version is:

```text
cupkp-evidence-quality-v1
```

C3B keeps this evidence policy separate from the mastery rule version. Explicit
`inconclusive` C3B evidence rows do not directly reduce mastery score, but C3B
does not implement quality-weighted mastery, History V1 reprocessing, or
adaptive path selection.

The next Program 3 gate was C4, lifecycle, versioning, and governance.

## Program 3 Gate C4 Lifecycle, Versioning, and Governance

Status: complete as of 2026-08-28.

The C4 gate froze lifecycle governance in
`local_flwcupkp\local\lifecycle_governance_contract`. It defines canonical
states `draft`, `review`, `approved`, `published`, `deprecated`, and
`archived`, while preserving legacy aliases such as `validated -> approved`,
`active -> published`, and `reference -> published`.

The frozen contract is:

```text
FLW_CUPKP_LIFECYCLE_GOVERNANCE_V1
```

C4 prevents published semantic rows from being overwritten in place. Semantic
changes must be made by cloning/revisioning the framework graph, then
deprecating or replacing the old row. `REPLACED_BY` links require a
deprecated/archived source and an approved/published successor. Object mappings
with learner evidence cannot be physically deleted.

C4 is wired into package validation, JSON imports, CSV mapping imports, manual
curriculum saves, manual mapping saves/deletes, bulk status changes, framework
version cloning, runtime governance status, and the repository audit.

Live smoke on course `124`, unit `U038`, returned:

```text
frozen
```

The next Program 3 gate is C5, Foundation Freeze V1.

## Repository Findings

The writable working tree available to this Codex session is `C:\Users\com\Documents\Estimation Speaking`. It currently contains no Moodle core checkout or existing FLW source files beyond an incomplete `.git` directory. The wider FLW collection exists at `D:\Codex\Estimation Speaking`, but it is outside the writable root for this run. The C-UP-KP reference folder at `D:\WinPro.Delta\Projects\C-UP-KP` contains the master prompt and one completed unit package, `REW3_U038_V31_text_image_moodle_package.zip`.

Reference inputs inspected:

- `FLW_C_UP_KP_SCI_Paper_V3_Journal_Ready_Manuscript.docx`: journal-style Version 3 manuscript defining the C-UP-KP model, Moodle integration concept, and curriculum intelligence pipeline.
- `REW3_U038_V31_text_image_moodle_package.zip`: REW Unit 38, B1, "Problem Solving" package with `unit_profile.json`, `U038_KP_UP_Lesson_Map.csv`, `U038_Quiz_Corpus_Traceable.csv`, lesson plans, corpora, and image assets.
- `Master Prompt.txt`: operational implementation requirements for a Moodle-local plugin named `local_flwcupkp`.

The safest implementation target is therefore a portable Moodle local plugin source tree at:

```text
local/flwcupkp
```

It can be copied into an installed Moodle root as `local/flwcupkp`.

## Existing Reusable Components

The U038 package already provides usable curriculum traceability inputs:

- unit profile and unit aim;
- lesson-level KP/UP map;
- lesson plans generated from corpus sources;
- quiz items with source IDs and audit rules;
- vocabulary, grammar, reading, listening, speaking, writing, and project corpora.

These are sufficient for the first importer fixture and quality-audit workflow.

## Proposed Architecture

Implement `local_flwcupkp` as an isolated Moodle local plugin.

Primary layers:

- Domain tables for frameworks, competencies, Use Points, Knowledge Points, mappings, learning objects, evidence, learner states, rules, recommendations, imports, and audit logs.
- Repository services wrapping Moodle DML calls and transactions.
- Import/validation service for schema-checked JSON packages and validated CSV mapping artifacts.
- Mastery engine that calculates KP, UP, and competency states separately.
- Recommendation engine that stores explainable learning-path recommendations.
- External service endpoints for versioned API access.
- Admin pages for import, validation, coverage reports, and learner-path inspection.
- Scheduled tasks for recalculation and Moodle competency synchronization.

## Database Strategy

Use Moodle XMLDB tables with normalized names under the plugin namespace. Because Moodle table names are length-sensitive, table names use compact forms such as `flwcupkp_framework`, `flwcupkp_comp`, `flwcupkp_up`, and `flwcupkp_kp`.

Design requirements:

- stable external IDs;
- status/version fields;
- soft deletion through `status`;
- timestamps and user IDs for audit;
- unique indexes on stable IDs;
- mapping uniqueness indexes;
- learner/state indexes;
- import batch checksums for idempotency.

## Migration Strategy

Initial installation creates all base tables. Future upgrades should add nullable columns first, backfill through scheduled tasks, then enforce stricter validation in later versions. Existing Moodle grades, completions, competencies, and learner data must not be modified by install or upgrade scripts.

## API Strategy

Use Moodle external functions with capability checks and validated parameters. The first phase provides service functions for:

- listing frameworks;
- importing a C-UP-KP package;
- recording evidence;
- retrieving learner state;
- retrieving recommendations;
- coverage report generation;
- Moodle sync dry-run.

The implementation maps the prompt's REST-style endpoints to Moodle external services rather than custom public routes.

## Moodle Integration Strategy

Moodle remains the system of record for users, courses, enrolments, activities, grades, completion, and native competencies. `local_flwcupkp` owns Use Points, Knowledge Points, deeper mappings, evidence interpretation, mastery state, recommendations, and curriculum-quality reports.

Moodle competency sync is dry-run first. It links or proposes Moodle competencies only for the competency layer, never for UP or KP unless explicitly configured.

## Implementation Phases

1. Domain/database foundation, capabilities, privacy provider, and documentation.
2. JSON import with pilot fixtures and validation reports.
3. Evidence ingestion and explainable mastery calculation.
4. Recommendation engine and learner-path APIs.
5. Admin, teacher, and student dashboards.
6. Moodle competency synchronization.
7. Hardening: tests, accessibility, performance, backup/restore.

This pass implements Phase 1 plus functional Phase 2/3 foundations and clearly marks remaining UI breadth as deferred.

## Program 3 Foundation Gate Log

Current status on 2026-08-29:

- A0 complete: History V1 is the only normal source-history input.
- C0 complete: integrated repository audit is available.
- C1 complete: canonical C/UP/KP domain model is frozen.
- C1B complete: ontology boundary validation is frozen.
- C2 complete: relationship and prerequisite graph semantics are frozen.
- C3 complete: content and evidence mapping contracts are frozen.
- C3B complete: evidence semantics and quality model are frozen.
- C4 complete: lifecycle, versioning, and governance are frozen.
- C5 complete: `FLW_CUPKP_FOUNDATION_V1` is frozen for evidence, mastery,
  adaptive, and UX consumers.
- C5B complete: admins have a read-only Foundation Inspector for Foundation V1
  status, dependency contracts, migration readiness, C/UP/KP rows, graph
  relations, content/evidence mappings, implementation ownership, and
  non-blocking findings.
- CM1 complete: admins have the Core C-UP-KP Curriculum Manager over the
  frozen Foundation V1 surface. It supports language, CEFR, FLW stage,
  domain/skill, and entity-type navigation; selected entity detail pages;
  validation, content usage, evidence coverage, audit history; and governed
  review/approve/publish/deprecate workflow actions.
- CM2 complete: admins have controlled relationship/prerequisite editing with
  preview-before-write, where-used impact counts, semantic relationship labels,
  protected delete checks, coverage governance summaries, and audit logging for
  confirmed relationship writes.

C5 records:

```text
curriculum_contract_version = FLW_CUPKP_CURRICULUM_CONTRACT_V1
relationship_contract_version = FLW_CUPKP_RELATIONSHIP_GRAPH_V1
evidence_policy_version = cupkp-evidence-quality-v1
```

The next implementation gate is CM3, bulk coverage management and governance UI
at FLW scale. Adaptive path selection, learner goal creation, History V1
evidence reprocessing writes, and mastery-policy changes remain stopped until
later gates.

## Risks

- No live Moodle root is available in the writable workspace, so plugin install/upgrade cannot be executed against a real database in this run.
- Event adapter class names must be verified inside the target Moodle installation before enabling observers beyond conservative defaults.
- U038 is B1 Unit 38, while the master prompt's built-in demonstration asks for B2 Unit 37. Both are kept separate.
- Large-scale mastery thresholds require empirical calibration after real learner data is available.

## Assumptions

- The target Moodle version is 4.1 or later, matching recent FLW plugin references.
- The plugin will be installed at `local/flwcupkp`.
- JSON remains the canonical full-package format; supported CSV mapping templates are validated, idempotent, audited imports that write through the same import batch model.
- The offline STT endpoint remains an internal adapter input, not a public unauthenticated client endpoint.

## Rollback Strategy

- Install only creates plugin-owned tables.
- Imports are tracked by batch checksum and can be marked rolled back.
- Imported rows include source batch metadata where applicable.
- Moodle competency synchronization supports dry-run and logs proposed changes before write mode.
- Removing the plugin should not alter Moodle gradebook, completion, or native competency records.
