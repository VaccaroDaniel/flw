# FLW Learning and Grade History

`local_flwhistory` is Program 2 of the FLW three-target architecture. It stores source-grounded learning and grade history facts so downstream systems can answer what happened, when it changed, and where the source came from.

## H1 Scope

H1 creates the technical foundation only:

- XMLDB schema.
- Capabilities.
- Privacy provider scaffold.
- Source identity helpers.
- Repository and service contracts.
- Observer/task registration scaffold with no active observers or tasks.
- Unit tests for identity, repository idempotency, correction links, Program 1 reference round trips, and normalization.

## Ownership Boundary

This plugin stores history. It does not calculate C-UP-KP mastery, adaptive recommendations, or Moodle competency ratings. Those remain owned by `local_flwcupkp`.

Moodle Gradebook remains authoritative for official grades. This plugin records grade versions and links them back to Moodle grade records.

## Program 1 Identity

Program 1 owns stable content deployment identity. `local_flwhistory` keeps a `flwhist_content_link` cache and a `p1_resolver` boundary so later gates can connect Moodle course, section, cmid, SCORM SCO, activity, and revision identities without duplicating Program 1 logic.

## H1 Non-Behavior

No production capture was enabled in H1. H1B froze coverage and normalization-version semantics before capture was allowed.

## H2 Capture

H2 enables lightweight, production-safe capture for verified educational source events:

- Moodle quiz attempt lifecycle events.
- Moodle SCORM raw-score and lesson-status events.
- Moodle course module and course completion events.
- FLW VR Room attempt submitted events.
- A scheduled coverage-refresh task for captured source families.

Observers extract stable identifiers, call `local_flwhistory\local\capture_service`, and return quickly. Source facts are written before downstream processing, repeated attempts remain distinct, missing Program 1 mapping is preserved as `unresolved_mapping`, and every normalized row retains `normpolicyversion`.

SCORM attempts use the stable source identity `moodle:scorm_attempt:{attemptid}`. Both score and status callbacks reconcile into one idempotent History V1 attempt row, preserving raw score, scaled score, completion state, attempt number, Moodle course-module identity, and Program 1 mapping status.

An administrator can repair a historical SCORM attempt without reading Moodle logs:

`php local/flwhistory/cli/scorm_capture.php --action=repair --attemptid={attemptid} --confirm=1`

The repair reads the authoritative Moodle SCORM attempt and tracking tables, records an auditable repair source event, and runs through the same normalized capture path as the production observer.

## H3 Grade History

H3 adds source-linked grade version history and current grade-summary reconciliation:

- Moodle `user_graded` and `grade_deleted` events are captured as gradebook source facts.
- `flwhist_grade_version` records official grade changes where Moodle supplies reliable grade object or grade history data.
- `flwhist_grade_summary` stores the current derived read model for latest attempt, best attempt, official Moodle grade, and latest grade version as separate values.
- Reconciliation repairs only local derived summaries. It does not write Moodle core grade tables and does not rewrite historical source facts.

Trend, improvement, mastery, and adaptive recommendations remain out of scope for Program 2 H3.

## H4 Secure APIs

H4 exposes bounded read services for later learner and teacher UI work:

- Present summary core.
- Learning history query.
- Attempt history query.
- Grade history query.
- Recent activity query.
- Learning journey core.

External API calls default to the current user when `userid` is `0`. Viewing another learner requires `local/flwhistory:viewcourse` or system-level `local/flwhistory:viewall`. Grade audit details require `local/flwhistory:viewgradeaudit` or system-level `local/flwhistory:viewall`.

Learner DTOs omit grade audit fields unless audit access is explicitly requested and authorized. H4 still does not calculate C-UP-KP mastery, adaptive recommendations, trend, or improvement.

## H5 Learner Dashboard Core

H5 adds a learner-facing dashboard at:

`/local/flwhistory/dashboard.php?courseid={courseid}`

The dashboard composes H4 services and trusted H3 grade summaries into a present-and-past view:

- present summary
- non-adaptive learning journey
- standard next action from course order
- grade distinctions for latest attempt, best attempt, official Moodle grade, and latest grade version
- attempt details
- grade history
- detailed learning history
- recent activity
- basic evidence trends where at least two trusted points exist

The dashboard does not calculate C-UP-KP mastery, adaptive next steps, goal readiness, projected future roadmap, or mastery-based skill progress. Those areas are displayed only as "Not available yet" placeholders because Program 3 owns them.

## H6 Teacher History Analytics

H6 adds a teacher/admin class history page at:

`/local/flwhistory/teacher.php?courseid={courseid}`

The page provides descriptive history analytics only:

- class completion summary
- last meaningful activity
- official grade summary
- attempt trend
- inactivity
- repeated unsuccessful attempts
- checkpoint and placement history
- grade audit for users with `local/flwhistory:viewgradeaudit`
- individual learner drill-down links to the H5 dashboard

Attention signals are evidence-based and descriptive: inactive, repeated unsuccessful attempts, grade decline with enough comparable data, stalled completion, and missing activity evidence. H6 does not label C-UP-KP weakness, mastery deficit, retention risk, or adaptive priority.

## H7 History V1 Freeze

H7 completes the production-hardening layer and freezes the History V1 contract for Program 3 consumption.

Operator CLI:

`php local/flwhistory/cli/history_v1.php --action=freeze --courseid={courseid}`

Supported actions:

- `backfill`: dry-run by default; add `--execute` to write recoverable Moodle/FLW facts. Supports `--limit`, `--sources`, `--cursorjson`, `--source`, and `--idempotency`.
- `reconcile`: dry-run by default; add `--execute` to repair local History V1 summaries and completion facts from Moodle source state.
- `performance`: measures summary, journey, history pagination, grade detail, and teacher class-history paths.
- `freeze`: checks schema, active capture runtime, privacy/security implementation, downstream contract, reconciliation preview, and performance probes.
- `contract`: prints `FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1`.

H7 backfill never fabricates missing facts. Rows without reliable timestamps are skipped; unknown grade reasons remain `null`; C-UP-KP evidence, mastery, adaptive recommendations, and Moodle competency ratings are not created by Program 2.

Downstream systems should use `local_flwhistory\local\evidence_source_adapter` for History V1 facts instead of scraping raw Moodle logs as a normal evidence source.
