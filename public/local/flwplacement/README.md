# local_flwplacement - FLW Placement

`local_flwplacement` is the FLW placement and learner starting-point plugin for Moodle. It can run the built-in adaptive placement test or redirect learners into a linked Moodle Quiz placement test, then stores the latest placement profile used by FLW learning paths.

Component: `local_flwplacement`

Release: `0.1.0 alpha`

Requires: Moodle 5.1 or later

Status: alpha. Use for controlled placement pilots and validate quiz mappings before using for official learner routing.

## What This Plugin Does

- Provides a built-in adaptive FLW placement test.
- Can use one linked Moodle Quiz per supported language.
- Saves placement attempts/results.
- Maintains a latest learner placement profile.
- Builds skill profiles, weak-skill lists, recommended start unit, confidence, and learning path JSON.
- Syncs completed linked Moodle Quiz attempts into placement records.
- Provides staff reports and CSV/question-bank export support.

## Supported Languages

- English: `en`
- Russian: `ru`
- Chinese: `zh`
- German: `de`
- Japanese: `ja`
- French: `fr`
- Spanish: `es`

## Main Pages

| Page | Purpose |
| --- | --- |
| `/local/flwplacement/index.php?language=en` | Learner placement test entry. |
| `/local/flwplacement/view.php?id=RESULTID` | Learner/staff placement result report. |
| `/local/flwplacement/reports.php` | Staff placement reports. |
| `/local/flwplacement/export.php` | Download built-in question bank CSV. |
| `/local/flwplacement/save.php` | Save endpoint for built-in adaptive placement payloads. |

Autostart parameters supported by the learner entry page:

- `autostart=1`
- `flwautostart=1`
- `flwplacement=1`

When a linked Moodle Quiz is configured and ready, autostart can redirect directly to the quiz attempt flow.

## Settings

Open:

`Site administration > Plugins > Local plugins > FLW Placement`

For each supported language, choose either:

- Built-in FLW adaptive placement.
- A specific Moodle Quiz.

Settings use keys like:

- `local_flwplacement/quizid_en`
- `local_flwplacement/quizid_ru`
- `local_flwplacement/quizid_zh`
- `local_flwplacement/quizid_de`
- `local_flwplacement/quizid_ja`
- `local_flwplacement/quizid_fr`
- `local_flwplacement/quizid_es`

## Learner Workflow

1. Learner opens `/local/flwplacement/index.php?language=en`.
2. Moodle checks placement access.
3. If no linked quiz is configured, the built-in adaptive test loads.
4. If a linked quiz is configured and ready, the learner can start the Moodle Quiz placement test.
5. The final result stores CEFR level, recommended course/start unit, confidence, skill profile, and learning path data.
6. FLW dashboards can use the latest placement profile to recommend the next lesson or unit.

## Teacher/Admin Workflow

1. Configure the language-specific quiz selector if using Moodle Quiz placement.
2. Confirm the quiz has enough random slots/source-bank questions.
3. Ask a test learner to complete placement.
4. Open placement reports.
5. Confirm `local_flwplacement_profile` has the latest profile for the learner/course key.
6. Use the profile as input for FLW Academy, C-UP-KP learning path, and next-action cards.

## Quiz Readiness

The quiz readiness tile/checks look for:

- Linked quiz availability.
- Course module readiness.
- Source bank/question readiness.
- Expected random slots, commonly 30 for placement.
- Last automatic sync state.

If the learner should use Moodle Quiz placement, verify readiness before publishing the placement path.

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `local/flwplacement:take` | Take placement | User, student, teacher, editing teacher, manager |
| `local/flwplacement:viewreports` | View placement reports | Teacher, editing teacher, manager |
| `local/flwplacement:manage` | Manage placement settings/workflows | Editing teacher, manager |

## Database

| Table | Purpose |
| --- | --- |
| `local_flwplacement` | Placement attempts/results. |
| `local_flwplacement_profile` | Latest placement profile per learner/course key. |

Important result fields:

- `userid`
- `courseid`
- `cefrlevel`
- `recommendedcourse`
- `startingunit`
- `confidencescore`
- `weightedscore`
- `skillprofilejson`
- `skillpercentjson`
- `weakskillsjson`
- `resultjson`
- `attemptjson`

Important profile fields:

- `latestresultid`
- `overallcefr`
- `recommendedstartunit`
- `nextcheckpointunit`
- `placementconfidence`
- `placementstatus`
- `skilllevelsjson`
- `kpmasteryjson`
- `supportflagsjson`
- `speakingprofilejson`
- `learningpathjson`
- `profilejson`
- `placementhistoryjson`

## Integration Points

- `theme_flwacademy`: Find My Level and learner dashboard entry points.
- `local_flwcupkp`: Uses placement and learning path signals to drive next action and gap guidance.
- `local_flwexam`: Can share exam/placement source-course infrastructure and result history.
- Moodle Quiz: Optional delivery engine for language-specific placement tests.

## Testing Checklist

1. Set English placement to built-in adaptive mode.
2. Log in as a learner and complete `/local/flwplacement/index.php?language=en`.
3. Confirm a row appears in `local_flwplacement`.
4. Confirm the latest learner row appears in `local_flwplacement_profile`.
5. Configure an English Moodle Quiz placement.
6. Use `autostart=1` and confirm the learner redirects to the quiz.
7. Submit the quiz.
8. Confirm the quiz attempt sync updates placement result/profile data.
9. Open reports as teacher/admin.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Learner cannot access placement | Login state and `local/flwplacement:take`. |
| Linked quiz does not start | Quiz ID setting, quiz visibility, course module availability, and autostart parameters. |
| Built-in test does not save | Browser console, session key, and `/local/flwplacement/save.php`. |
| Result exists but dashboard is stale | Latest profile row and downstream cache/rollup behavior. |
| Staff cannot see reports | `local/flwplacement:viewreports` or `local/flwplacement:manage`. |

## Production Notes

Placement affects the learner's starting path. Keep quiz mappings stable, validate level thresholds with real learner samples, and keep a manual override process available for teachers.
