# H0 Reuse Map

## Reuse Principles

- Reuse Moodle source tables as authoritative source records.
- Reuse FLW plugin APIs and tables as upstream sources where they already exist.
- Reuse Program 1 content deployment contract for stable source identity.
- Reuse Program 3 C-UP-KP services for mastery, evaluation, recommendations, competency sync, and repair UX.
- Do not fork or reimplement existing FLW plugin responsibilities.

## Reused as Source

| Existing Area | Reuse Type | Notes |
| --- | --- | --- |
| Moodle quiz and question engine | Read source and observe events | Attempt and question-step history source. |
| Moodle assignment | Read source and observe events | Submission and grading history source. |
| Moodle SCORM | Read source and observe events | Requires Program 1 SCO identifier resolution. |
| Moodle gradebook | Read source and observe events | Official grade source. |
| Moodle completion | Read source and observe events | Course module and course completion transitions. |
| Program 1 deployment contract | Resolve stable content identity | Required for course, unit, section, activity, SCO, revision, and freshness. |
| `local_flwexam` | Read service/table outputs and quiz observers | Source for FLW exam attempts, results, skill scores, KP exam results. |
| `local_flwplacement` | Read placement attempts/profile | Source for placement level/profile history. |
| `local_flwmedia` | Read attempts/progress and services | Source for media practice history. |
| `local_flwaiassessment` | Read finalized results | Source for AI assessment estimates and confirmations. |
| `mod_flwaispeaking` | Read finalized submissions | Source for speaking activity attempt history. |
| `mod_flwvrroom` | Read attempt event/table | Source for VR attempt history. |
| `theme_flwacademy` | Preserve visual shell | Program 2 H1 must not change theme output. |

## Not Reused as Program 2 Owner

| Area | Existing Owner | Program 2 Position |
| --- | --- | --- |
| C-UP-KP framework/UP/KP CRUD | `local_flwcupkp` | Do not duplicate. |
| C-UP-KP evidence interpretation | `local_flwcupkp` | Program 2 can later feed source facts, not decide evidence strength. |
| Mastery and rollups | `local_flwcupkp` | Do not duplicate. |
| Learner evaluation snapshots/diagnostics | `local_flwcupkp` | Preserve owner; later consume Program 2 facts. |
| Adaptive path and recommendations | `local_flwcupkp` | Preserve owner. |
| Moodle competency writer | `local_flwcupkp` | Preserve owner. |
| Existing evidence sync repair pages | `local_flwcupkp` | Program 2 may add source-history reconciliation pages later, separate from C-UP-KP evidence repair. |

## New Program 2 Assets Planned for H1

- New plugin: `local/flwhistory`
- Schema: source events, attempts, question coverage, grade versions, completion transitions, source mappings, reconciliation runs.
- Services: repository, normalizer, source identity, Program 1 resolver boundary, history read APIs.
- Privacy/capability layer for learner-owned history and teacher/admin reporting.

