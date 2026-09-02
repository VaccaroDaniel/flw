# Program 3 UX2 Learner Experience Contract

## Identity

- Gate: `P3_UX2`
- Contract: `FLW_CUPKP_LEARNER_EXPERIENCE_V1`
- UX policy: `cupkp-learner-experience-v1`
- Source view: `FLW_CUPKP_STUDENT_LEARNING_TIMELINE_VIEW_V1`
- Source history: `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`
- Mode: read-only

## Learner Information Order

Level 1 always contains exactly these six sections, in order:

1. My History
2. Where I Am Now
3. What I Should Do Next
4. Coming Up
5. My Milestone
6. My Goal

The presentation rule is **History compressed, Current expanded, Future
summarized**. Coming Up is bounded to three items.

## Progressive Disclosure

- Level 2: Show History and Show Roadmap
- Level 3: Why This Activity? and More Details

Level 2 History links to and summarizes Program 2 History V1; it does not copy,
rebuild, or write History records. Level 3 explains the existing adaptive
decision without owning or changing the adaptive policy.

## Learner Terminology

| Internal term | Learner term |
| --- | --- |
| Competency | Skill |
| KP | Learning Point |
| UP | Practice Target |
| Mastery | Ability or Progress |
| Prerequisite | Needed First |
| Remediation | Extra Practice |
| Evidence | Learning Results |

Internal table, API, ontology, and policy identifiers remain stable. Internal
IDs and policy metadata are not rendered in the learner disclosures.

## Continue Learning Guard

The primary action is emitted only when the frozen UX1/A5/A4B result is
`next_activity_ready`, the activity is available, its course-module ID is
positive, and its URL is a same-site Moodle `/mod/.../view.php?id=...` URL for
that exact ID. Hidden, blocked, expired, external, malformed, or mismatched
activities never produce a Continue Learning link. The service does not invent
an activity when no eligible result exists.

## Mobile and Accessibility

The main flow remains vertical in the order History, Current, Future, Goal and
does not require horizontal scrolling. Progressive disclosure uses native
`details`/`summary` controls with visible keyboard focus. The page has one
primary action and no learner-facing data table.

## Ownership and Write Boundary

- Program 2 owns source History and its detailed History page.
- UX1 owns the frozen Past/Present/Future composition DTO.
- A4B/A5 own candidate eligibility and adaptive decisions.
- UX2 owns presentation simplification only.
- UX2 writes no evidence, state, recommendation, goal, placement, History, or
  audit records.

The only next allowed gate is UX3 Teacher/Admin Explainability and Override.
