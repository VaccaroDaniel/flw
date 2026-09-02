# Program 3 UX3 Staff Intelligence Contract

## Contract

`FLW_CUPKP_STAFF_INTELLIGENCE_V1` gives authorized teachers and administrators
detailed learner intelligence and controlled interventions. It consumes the
frozen UX2 learner experience, A5 path, A4B activity resolution, E2 mastery,
E3 retention, A1 goals, and History V1-derived evidence.

History V1 remains owned by `local_flwhistory`. UX3 does not read raw Moodle
logs, rebuild History, or write source-history facts. A5 continues to own the
normal adaptive recommendation. UX3 applies an explicit staff governance layer
after A5 has composed its normal recommendation.

## Staff Detail

The staff view may expose:

- competency, KP, and UP identity
- mastery and confidence
- retention and review state
- evidence and provenance
- prerequisites
- recommendation reasons
- policy versions
- current path decisions and bounded decision history

The learner-facing UX2 page remains unchanged and does not receive staff forms,
policy detail, audit history, or intervention controls.

## Six Explanations

Every current recommendation answers:

1. Why this target?
2. Why this activity?
3. Why extra practice?
4. Why review?
5. Why skip?
6. Why did the path change?

## Controlled Interventions

The supported controls are:

- assign a target and currently eligible Moodle activity
- force review
- hold advancement
- override the recommendation using the frozen A5 action vocabulary
- adjust the learner goal through `learning_goal_service::save_goal`
- record direct teacher evidence through `mastery_engine::record_evidence`

All writes require `local/flwcupkp:override` in the course context and a
non-empty staff reason. Goal, evidence, recommendation, and audit changes use
existing audited services. Path controls use the append-only
`flwcupkp_intervention` ledger.

## Versioning And Precedence

An intervention series is identified by learner, course, unit, intervention
type, and target. Every change appends a version. Releasing an active path
intervention appends a `released` version linked by `supersedesid`; it never
updates or deletes the active version in place.

Active path controls use this precedence:

1. hold advancement
2. assigned target/activity
3. recommendation override
4. forced review

Hold intent is represented in UX3 metadata while the effective recommendation
uses A5 `REPRIORITIZE`, preserving the frozen A5 action vocabulary. Assigned
activities use A5 `ADVANCE`; forced review uses A5 `REVIEW`.

## Eligibility Invariant

A staff-selected activity must be present in the current A4B eligible activity
set for the same learner, course, unit, and target. Eligibility is checked when
the intervention is created and whenever a recommendation is composed. If the
activity later becomes hidden, unavailable, closed, exhausted, or otherwise
ineligible, UX3 reports `blocked_by_current_eligibility` and falls back to the
normal A5 result. It does not unlock or expose the activity.

## Privacy And Audit

Learner intervention rows are included in Moodle privacy export and deletion.
When a user is only the staff actor, `createdby` is anonymized to `0` so the
learner's immutable operational history can remain intact. Creation and release
events are also written to `flwcupkp_audit`.

## Surfaces

- Page: `/local/flwcupkp/staff_intelligence.php`
- Read-only CLI: `cli/staff_intelligence.php`
- Status API: `local_flwcupkp_get_staff_intelligence_status`
- Learner detail API: `local_flwcupkp_get_staff_intelligence`
- Apply API: `local_flwcupkp_apply_staff_intervention`
- Release API: `local_flwcupkp_release_staff_intervention`

The next allowed gate is F1 Full Integrated Production Validation.
