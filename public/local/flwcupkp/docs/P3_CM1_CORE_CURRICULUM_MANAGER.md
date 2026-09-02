# Program 3 Gate CM1 Core Curriculum Manager

Status: complete as of 2026-08-29.

CM1 adds operational curriculum authoring on top of the frozen Foundation V1
surface. It does not add adaptive path selection, mastery policy changes,
learning-goal ownership, History V1 reprocessing writes, or raw Moodle log
scraping.

## Contract

```text
local_flwcupkp\local\core_curriculum_manager
FLW_CUPKP_CORE_CURRICULUM_MANAGER_V1
```

The CM1 contract depends on:

- `FLW_CUPKP_FOUNDATION_V1`
- `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`
- C1/C1B/C2/C3/C3B/C4 semantics already exposed by Foundation V1

## Admin UI

Core page:

```text
/local/flwcupkp/curriculum.php
```

Selected entity detail page:

```text
/local/flwcupkp/entity.php?type=kp&id=ENTITYID
```

The curriculum page now includes CM1 navigation by:

- language
- CEFR level
- FLW stage
- domain or skill
- entity type: Framework, Competency, UP, KP, or Learning Object

The selected entity detail page shows:

- definition
- stable code
- revision/version
- lifecycle status
- relationships
- prerequisites
- where-used graph
- content usage
- evidence coverage
- validation checks
- audit history
- available workflow actions

## Workflow

Permission-controlled workflow actions are available for governed curriculum
rows:

- view
- create
- edit pre-publication rows
- send to review
- approve
- publish
- deprecate

Workflow actions call the frozen C4 lifecycle governance rules. Invalid
transitions, such as direct `draft` to `published`, are rejected. Published and
deprecated semantic rows cannot be edited through the direct edit form; admins
should clone/revision and then deprecate or replace old rows.

Learning objects remain governed by their framework version and content/evidence
mapping contracts.

## Boundary

CM1 is intentionally not an adaptive gate. It may display current evidence,
learner-state counts, validation findings, and graph dependencies, but it does
not recalculate mastery or generate/adapt learner paths.

Next allowed gate:

```text
CM2
```
