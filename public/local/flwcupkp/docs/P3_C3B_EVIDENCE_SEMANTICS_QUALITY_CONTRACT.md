# Program 3 Gate C3B - Evidence Semantics and Quality Contract

Status: complete

Date: 2026-08-28

Frozen contract:

```text
FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1
```

Evidence policy version:

```text
cupkp-evidence-quality-v1
```

## Purpose

C3B defines what a C-UP-KP evidence event means before mastery, retention, or
adaptive path policies consume it. It preserves the C1/C1B/C2/C3 foundation
contracts and keeps History V1 as the only normal source-history input.

Normal source boundary:

```text
local_flwhistory\local\evidence_source_adapter
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
use_history_v1_adapter_not_raw_moodle_logs
```

Existing direct Moodle observers remain legacy capture paths until the later E1
History V1 evidence adapter and reprocessing gate replaces normal production
evidence ingestion.

## Evidence Event Metadata

C3B stores the conceptual evidence event in
`flwcupkp_evidence.rubricjson.cupkp_c3b_semantics`.

The metadata includes:

- History V1 contract and normal source rule.
- Source key, source type, source attempt ID, source reference, provenance, and
  legacy direct-capture flag.
- Target entity type and ID.
- Evidence role.
- Performance mode.
- Result state.
- Raw and normalized scores.
- Occurred and recorded timestamps when available.
- Curriculum version when available.
- Evidence policy version.
- Quality dimensions.
- Advisory evidence ceiling.
- Attempt/retry semantics.
- Inference path for inferred evidence.

## Result States

C3B freezes four result states:

- `positive`: supports the target claim.
- `negative`: shows unsuccessful or incorrect target performance.
- `partial`: gives mixed or incomplete support.
- `inconclusive`: cannot responsibly support or reduce the target claim.

Invariant:

```text
inconclusive evidence must not directly reduce mastery
```

The current mastery engine now gives explicit C3B `inconclusive` rows zero score
weight. This is a guardrail only; quality weighting and threshold policy remain
future E2 work.

## Performance Modes

C3B freezes these normalized performance modes:

- `passive_exposure`
- `recognition`
- `comprehension`
- `selection`
- `controlled_recall`
- `guided_production`
- `independent_production`
- `interaction`
- `transfer`

Existing evidence-strength labels such as `guided_performance`,
`controlled_production`, `independent_performance`, and
`transfer_performance` are translated into these conceptual modes without
renaming stored legacy fields.

## Direct and Inferred Evidence

C3B preserves:

- `direct`
- `inferred`

Inferred evidence stores an inference path. Completion, passive exposure, and
recognition signals default to inferred evidence unless explicit metadata says
otherwise.

## Quality Dimensions

C3B stores deterministic normalized values from `0` to `1` for:

- `validity`
- `reliability`
- `independence`
- `authenticity`
- `production_demand`
- `contextual_transfer`
- `support_level`
- `difficulty`
- `recency`
- `confidence`

`support_level` is normalized so `1` means unsupported independent performance
and `0` means fully modeled or heavily supported performance.

## Retry Semantics

C3B preserves attempts instead of collapsing wrong-hint-correct sequences into
perfect evidence. If metadata indicates hints, answer exposure, or retry after
support, independence, support level, and confidence are lowered.

## Evidence Ceilings

C3B records an advisory ceiling hint:

- passive completion and recognition cannot justify higher-order productive
  mastery by themselves;
- independent production may support productive mastery when the future mastery
  policy agrees;
- interaction and transfer may support higher-order mastery when the future
  mastery policy agrees.

C3B does not implement full mastery ceiling enforcement. That belongs to E2.

## API

```text
local_flwcupkp\local\evidence_semantics_quality_contract::contract()
local_flwcupkp\local\evidence_semantics_quality_contract::semantics_for_evidence()
local_flwcupkp\local\evidence_semantics_quality_contract::validate_evidence_payload()
local_flwcupkp\local\evidence_semantics_quality_contract::augment_evidence_payload()
local_flwcupkp\local\evidence_semantics_quality_contract::evidence_semantics_status()
```

## Stop Boundary

C3B does not implement:

- adaptive path selection;
- History V1 evidence reprocessing;
- raw Moodle log scraping;
- teacher override workflow;
- evidence-quality mastery weighting or new mastery thresholds.

Next gate:

```text
Program 3 Gate C4 - Lifecycle + Versioning + Governance
```
