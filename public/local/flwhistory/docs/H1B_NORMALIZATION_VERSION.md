# Program 2 Gate H1B Normalization Version Contract

## Frozen Version

H1B freezes the first Program 2 normalization policy version:

`H1B-20260827.1`

The Moodle database field is named `normpolicyversion`. It is the Moodle-safe equivalent of the prompt term `history_normalization_policy_version`.

## Source Fact Versus Normalized Meaning

H1B separates immutable source facts from normalized interpretations:

- `sourcekey` identifies one recorded normalized event row.
- `sourcefactkey` links rows that interpret the same underlying source fact.
- `payloadhash` records the normalized summary payload stored for that row.
- `normpolicyversion` records which policy produced that normalized meaning.

When a rule changes, the source fact remains stable. The old normalized row remains auditable, and the new interpretation is stored as a new version-linked row.

## Supersession Flow

`history_service::record_normalization_supersession()` creates a new source event for a changed normalization policy:

1. Load the existing source event.
2. Preserve its `sourcefactkey`.
3. Create a new deterministic `sourcekey` containing the new normalization policy.
4. Store the new normalized summary and `payloadhash`.
5. Set `correctionof` to the previous source event id.
6. Write a `flwhist_correction` row with `correctiontype = normalization_supersession`.

Repeated calls with the same old event and new policy are idempotent.

## Tables With Frozen Policy Version

H1B stores `normpolicyversion` on:

- `flwhist_source_event`
- `flwhist_attempt`
- `flwhist_placement`
- `flwhist_question_attempt`
- `flwhist_grade_version`
- `flwhist_completion`
- `flwhist_coverage`
- `flwhist_reconcile_run`

H1B also adds `sourcefamily` and `sourcefactkey` to the normalized fact tables where source identity matters.

## Consumer Rules

Consumers must not reinterpret old rows in place after policy changes. They must either:

- read rows under their stored `normpolicyversion`, or
- request a corrected/superseded interpretation and keep the previous row auditable.

This rule protects teacher reports, learner timelines, and Program 3 C-UP-KP evidence from silent historical drift.

## Production Capture Boundary

H1B only freezes semantics and schema. H2 may enable production capture once all source adapters write the frozen `normpolicyversion` and retain `sourcefactkey`.

