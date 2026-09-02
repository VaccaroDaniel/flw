# Program 2 Gate H1 Technical Architecture

## Resulting Component

- Component: `local_flwhistory`
- Path: `D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwhistory`
- Version: 2026082701
- Release: 0.1.0-alpha
- Moodle target: 5.1+

`local_flwhistory` is the Program 2 history foundation. It stores source-grounded facts about learner actions, attempts, grade versions, completion changes, placement states, and reconciliation runs.

## Ownership Boundary

Program 2 owns:

- Normalized learning source facts.
- Replay-safe source identity.
- Attempts and question/item attempt history.
- Grade version history.
- Completion history.
- Placement source fact history.
- Program 1 content identity cache.
- Correction/supersession links.
- Reconciliation run metadata.

Program 2 does not own:

- C-UP-KP evidence interpretation.
- KP/UP/competency mastery.
- Adaptive recommendations.
- Moodle competency rating writes.
- Existing C-UP-KP teacher/student pages.

Those remain owned by `local_flwcupkp` and Program 3.

## Data Flow

H1 installs the storage and service layer only.

Future H2+ flow:

1. Moodle/FLW source event occurs.
2. Lightweight observer or scheduled repair captures the source fact.
3. `source_identity` builds the replay-safe source key.
4. `normalizer` creates neutral Program 2 DTOs.
5. `repository` stores or updates rows by source key.
6. Program 3 may later consume source facts through `evidence_source_adapter`.

H1 registers no active source capture observers and no scheduled tasks.

## Stable References

The schema stores Moodle references where available:

- `userid`
- `courseid`
- `sectionid`
- `cmid`
- `gradeitemid`
- `sourceattemptid`
- `questionusageid`
- `questionattemptid`
- `gradegradeid`
- `gradehistoryid`

The schema stores Program 1 / FLW references where available:

- `worldid`
- `stageid`
- `unitid`
- `lessonid`
- `componentid`
- `activityid`
- `assessmentid`
- `questionid`
- `sourcerevision`
- `freshness`

Identity never depends on display titles.

## Source Identity

Every replayable family uses `sourcekey` as a unique source identity. The conceptual key parts are:

- `sourcesystem`
- `sourcetype`
- `sourceid`
- `sourceversion`
- `eventtype`

Long keys are shortened with a SHA-256 suffix while staying within Moodle index-safe `char(191)`.

## Correction Model

H1 supports explicit correction/supersession through:

- `correctionof`
- `supersededby`
- `flwhist_correction`

Correction rows preserve the audit trail. Privacy/legal deletion remains separate and is handled by the privacy provider.

## H2 Safety

`db/events.php` and `db/tasks.php` exist as empty scaffolds. They deliberately register nothing in H1. H1B must freeze coverage and normalization-version semantics before production event capture begins.

