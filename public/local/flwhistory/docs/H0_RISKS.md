# H0 Risks

## Risk Register

| Risk | Severity | Evidence | Mitigation |
| --- | --- | --- | --- |
| Program 1 contract is available as docs/Python artifacts, not yet as a Moodle PHP service. | Medium | H0 found `P1_CONTENT_DEPLOYMENT_CONTRACT_V1.md` and handoff docs in `adventure_scorm_gui`. | H1 defines a resolver boundary; H2 implements PHP map reader or bridge. |
| Earlier Program 1 S9 manifest had a conditional scope note. | Low | S9 artifacts exist; user stated Program 1 is finished and out of scope. | Record release-authority acceptance in H0; do not block H1. |
| `local_flwcupkp` already captures evidence from many same events Program 2 will capture. | High | `local_flwcupkp\db\events.php` observes quiz, assignment, SCORM, H5P, VR, and completion. | Program 2 stores normalized source history only. Program 3 remains evidence/mastery owner. |
| SCORM and question-step volume may grow quickly. | Medium | SCORM `scorm_scoes_value` and question step data can be high-volume. | H1 should store normalized summaries and source references, not indiscriminate raw logs. |
| Gradebook history has both current and historical tables. | Medium | `grade_grades`, `grade_grades_history`, and grade events exist. | H1 defines grade version semantics and H2 backfill reconciliation. |
| Multiple FLW plugins have separate privacy and capability surfaces. | Medium | Existing plugins each define their own access/privacy files. | H1 adds `local_flwhistory` privacy provider and clear capabilities. |
| Live Moodle worktree is dirty with unrelated theme/config changes. | Low | `git status` in Moodle public root reports existing modified/untracked files. | H0 ignored them. Future edits must stay scoped. |
| H5P payload semantics are xAPI-like and may require careful filtering. | Medium | H5P `statement_received` exists and is already observed by C-UP-KP. | Program 2 should normalize minimal statement summaries first. |
| Repair/replay actions could double-write without idempotency. | High | Existing repair flows replay evidence. Program 2 will add reconciliation. | H1 must define unique source keys and replay-safe upserts. |

## No Current H0 Blockers

No H0 blocker prevents H1. The only high risks are ownership/idempotency risks, and both are addressable in H1 design.

