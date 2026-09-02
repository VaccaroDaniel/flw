# Program 3 Gate E2 - Mastery + Confidence + Current Learner State

E2 derives a reproducible present-state view from normalized C-UP-KP evidence.

## Contract

Version: `FLW_CUPKP_MASTERY_CONFIDENCE_STATE_V1`

The service consumes:

- `FLW_CUPKP_MANAGEMENT_V1`
- `FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1`
- `FLW_CUPKP_EVIDENCE_SEMANTICS_QUALITY_V1`
- `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`

History V1 remains the only normal source-history input. E2 reads derived
C-UP-KP evidence rows produced through E1; it does not scrape raw Moodle logs.

## Learner State Types

- `LearnerKPState`
- `LearnerUPState`
- `LearnerCompetencyState`

Each current-state row exposes mastery, confidence, cache status, trend, policy
version, rule version, evidence IDs/hash, calculated time, and manual override
status.

## Confidence

Mastery and confidence are separate values. The E2 confidence model considers:

- evidence quality;
- independence;
- performance mode;
- bounded recency;
- source/strength diversity;
- advisory evidence ceilings;
- minimum evidence sufficiency.

Grades remain History V1 grade facts unless a controlled evidence adapter
explicitly maps a trusted attempt/completion fact into C-UP-KP evidence.

## Rebuild

The current-state cache is `flwcupkp_state`. Admins can preview and then apply a
controlled rebuild from Program-3 evidence rows. Manual overrides are preserved
and never overwritten by automated rebuild.

Entry points:

- `/local/flwcupkp/mastery_state.php`
- `local/flwcupkp/cli/mastery_state.php`
- `local_flwcupkp_get_mastery_state_status`
- `local_flwcupkp_get_current_learner_state`
- `local_flwcupkp_get_class_current_state_summary`
- `local_flwcupkp_preview_mastery_state_rebuild`
- `local_flwcupkp_apply_mastery_state_rebuild`

## Out Of Scope

- Retention/retrieval/review states
- Adaptive path selection
- Recommendation policy changes
- Raw Moodle log scraping
- History V1 source mutation

