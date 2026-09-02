# H0 Architecture Decision Records

## ADR-H0-001: Create a New Program 2 Component

Decision: Program 2 will be implemented as `local_flwhistory`.

Reason: `local_flwcupkp` already owns C-UP-KP curriculum, evidence interpretation, mastery, learner evaluation, recommendations, competency sync, calibration, and repair UI. A separate history component prevents duplicate ownership while giving all programs a stable historical fact layer.

Consequence: H1 creates schema/service contracts in `local_flwhistory`. Program 3 integrations come later through explicit APIs or adapters.

## ADR-H0-002: Use Append-Oriented History

Decision: Program 2 records immutable source facts and correction/version rows rather than overwriting history.

Reason: Learning and grade history must explain what happened, when it changed, and what source produced it.

Consequence: Current state views should be derived from event/version rows, not be the only persisted truth.

## ADR-H0-003: Moodle Gradebook Remains Authoritative for Official Grades

Decision: Program 2 does not replace Moodle gradebook. It records grade history and interpretation metadata around Moodle grade records.

Reason: Moodle gradebook is the source of official course grade values.

Consequence: Program 2 stores grade versions and reconciliation state, then links back to `grade_items`, `grade_grades`, and `grade_grades_history`.

## ADR-H0-004: Program 1 Owns Content Deployment Identity

Decision: Program 2 consumes Program 1 stable mappings for world, stage, unit, section, course module, SCORM SCO, component activity, micro-activity, revision, and freshness.

Reason: Recreating content mapping in Program 2 would violate the three-program contract and create drift.

Consequence: H1 must define a Program 1 resolver boundary. H2+ can implement a PHP facade, cached import, or CLI-generated map reader.

## ADR-H0-005: Program 3 Owns C-UP-KP Mastery and Adaptive Decisions

Decision: Program 2 stores learning and grade history only. It does not calculate C-UP-KP mastery, adaptive recommendations, or Moodle competency ratings.

Reason: `local_flwcupkp` already owns those workflows and has existing production-hardening work.

Consequence: Program 3 can later consume Program 2 normalized facts, but the direction of ownership remains Program 2 history -> Program 3 intelligence.

## ADR-H0-006: Source Identity Must Be Replay-Safe

Decision: Every captured fact must have a stable idempotency key based on source component, source table/entity id, source revision/time, and event type when needed.

Reason: Observers, scheduled tasks, repairs, and backfills can replay the same source event.

Consequence: H1 must include unique constraints and repository upsert behavior for source events.

## ADR-H0-007: Store References Before Heavy Payloads

Decision: H1 should store normalized values and source references. Large raw payloads should be avoided or stored only when required for traceability.

Reason: SCORM and question-step data can be high-volume. Program 2 needs durable history without becoming a raw log dump.

Consequence: H1 schema should include compact JSON summary/payload fields with size discipline and source pointers.

## ADR-H0-008: Preserve Visual Baseline Through H1

Decision: H1 is backend-only. It does not change course pages, dashboard cards, learner pages, teacher verification, or theme layout.

Reason: The current C-UP-KP/Learner Evaluation pages are active work and should not be destabilized by schema creation.

Consequence: Visual and UX changes belong to later Program 2/3 gates after backend contracts are stable.

## ADR-H0-009: No Moodle Core Changes

Decision: Program 2 must use Moodle plugin APIs, observers, scheduled tasks, external functions, privacy providers, and capability checks.

Reason: Core changes increase upgrade risk and violate package safety constraints.

Consequence: All H1 files belong under `local/flwhistory`.

