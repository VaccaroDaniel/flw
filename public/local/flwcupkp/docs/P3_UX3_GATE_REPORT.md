# Program 3 Gate UX3 Report

## Result

Implementation and live deployment verification complete.

## Implemented

- Frozen `FLW_CUPKP_STAFF_INTELLIGENCE_V1` ownership and precedence contract
- Staff-only learner detail for C/KP/UP, mastery, confidence, retention,
  provenance, prerequisites, reasons, policy versions, and path decisions
- Six required recommendation explanations
- Six permission-controlled staff intervention types
- Append-only intervention ledger with immutable release versions
- Existing audited goal, evidence, recommendation, and audit writers
- Current A4B eligibility enforcement for every selected activity
- Role-aware page and course/home navigation
- Four secure external services, OpenAPI paths, and read-only CLI
- Moodle privacy export/deletion coverage
- Responsive staff dashboard without exposing complexity in UX2 learner UI
- Repository audit and Foundation V1 handoff to F1

## Frozen Boundary

UX3 does not rebuild or mutate History V1, scrape raw Moodle logs, change normal
A5 policy ownership, unlock unavailable activities, silently overwrite a staff
decision, or expose staff complexity in the learner UI.

## Verification

- Focused UX3: 6 tests, 85 assertions
- Cross-gate UX3/UX2/UX1/A5/A4B/goal/mastery/retention/Foundation/privacy:
  51 tests, 865 assertions
- Full `local_flwcupkp`: 174 tests, 2,034 assertions
- Live plugin version: `2026083101`
- Live UX3 readiness on existing course 125: 10/10 criteria
- Live repository status: `ready_for_f1`
- Intervention ledger: present
- External functions: 4/4 registered
- Valid-course unauthenticated route: canonical Moodle access redirect confirmed
- PHP syntax, OpenAPI JSON, install XML, and UX3 language keys: valid

The available browser session has guest access only. It followed the protected
route to course enrolment and confirmed that no staff intelligence is exposed
without authentication. An authenticated staff visual inspection was not
possible without fabricating a production account or changing enrolment. The
responsive renderer, staff/learner role split, and control visibility are
covered by the integrated PHPUnit fixture.
