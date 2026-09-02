# Program 3 Gate E3 - Retention / Retrieval / Review

E3 adds a separate retention layer on top of E2 current mastery state.

## Contract

Version: `FLW_CUPKP_RETENTION_RETRIEVAL_REVIEW_V1`

The service consumes:

- `FLW_CUPKP_MANAGEMENT_V1`
- `FLW_CUPKP_MASTERY_CONFIDENCE_STATE_V1`
- `FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1`
- `FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1`
- `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`

History V1 remains the only normal source-history input. E3 reads E1-derived
C-UP-KP evidence and E2 current-state rows. It does not scrape Moodle logs.

## Retention States

- `NEW`
- `LEARNING`
- `CONSOLIDATING`
- `RETAINED`
- `REVIEW_DUE`
- `RETENTION_UNCERTAIN`
- `RELEARNING`

States are stored in lowercase on `flwcupkp_state` and exposed with canonical
uppercase labels in the contract.

## Core Rules

- Mastery and retrievability are separate.
- Time passing can make review due, but it does not lower mastery.
- A failed review can set retention to `RELEARNING`, but it does not erase
  the E2 mastery state.
- KP and UP targets may have different retention states for the same evidence
  because their preferred retrieval modes and intervals differ.
- Review quality favors retrieval, independent production, interaction, and
  transfer evidence according to target type.
- All snapshots record `retention_policy_version`.

## Snapshot Fields

E3 extends `flwcupkp_state` with:

- `retentionstate`
- `retentionconfidence`
- `retentionnextreview`
- `retentionlastretrieval`
- `retentionretrievalcount`
- `retentionpolicyversion`
- `retentionevidencehash`
- `retentionevidenceidsjson`
- `retentioncalculatedtime`

## Rebuild

Admins can preview and then apply a controlled retention rebuild. Preview is
read-only. Apply writes only the retention snapshot fields on existing E2
state rows and records an audit event.

Entry points:

- `/local/flwcupkp/retention_review.php`
- `local/flwcupkp/cli/retention_review.php`
- `local_flwcupkp_get_retention_review_status`
- `local_flwcupkp_get_current_retention_state`
- `local_flwcupkp_get_class_retention_summary`
- `local_flwcupkp_preview_retention_review_rebuild`
- `local_flwcupkp_apply_retention_review_rebuild`

## Out Of Scope

- Adaptive path selection
- Competency-centered goal modeling
- Mastery decay
- Grade/mastery collapse
- Raw Moodle log scraping
- History V1 source mutation

