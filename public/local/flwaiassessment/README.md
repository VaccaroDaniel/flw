# local_flwaiassessment - FLW AI Assessment

`local_flwaiassessment` is the Moodle-side review store for FLW AI writing and speaking assessment. It collects learner submissions, sends pending work to the local FLW AI service when processing is enabled, stores AI estimates, and lets teachers confirm or correct the final evaluation.

Component: `local_flwaiassessment`

Release: `0.1.0 alpha`

Requires: Moodle 5.1 or later

Status: alpha service plugin. Use in controlled FLW pilots before production-wide rollout.

## What This Plugin Does

- Stores writing and speaking assessment requests from FLW activities.
- Sends pending submissions to a local AI assessment API.
- Records CEFR level, total score, rubric JSON, weak KP JSON, recommendation JSON, and the raw AI response.
- Supports teacher review with confirmed CEFR level, score, note, reviewer, and confirmation time.
- Gives teachers a review list and per-result review page.
- Provides the assessment backend used by `mod_flwaispeaking`.

This plugin is not a standalone lesson player. It is the shared assessment layer that other FLW activities call.

## Main Pages

| Page | Purpose | Typical role |
| --- | --- | --- |
| `/local/flwaiassessment/index.php` | Review list for AI assessment results | Teacher, manager |
| `/local/flwaiassessment/submit.php?courseid=COURSEID` | Manual/course submission tool | Editing teacher, manager |
| `/local/flwaiassessment/view.php?id=RESULTID` | Single result with teacher confirmation fields | Teacher, manager |

The course navigation extension adds an AI assessment submission link for users who can manage assessment in the course.

## Standard Workflow

1. A learner submits writing or speaking work through an FLW activity.
2. The activity creates a pending row in `local_flwai_results`.
3. The scheduled task picks up pending rows if processing is enabled.
4. The task sends the work to the configured AI API endpoint.
5. The result is saved as `complete`, `failed`, or `needsinput`.
6. A teacher opens the result page, reviews the AI estimate, and confirms or corrects it.
7. Downstream FLW systems can use the confirmed result as learner evidence.

## Settings

Open:

`Site administration > Plugins > Local plugins > FLW AI Assessment`

| Setting | Meaning |
| --- | --- |
| `enableprocessing` | Enables the scheduled task to process pending submissions. Default is off. |
| `apiurl` | Base URL for the local FLW AI assessment server. Default: `http://127.0.0.1:8000`. |
| `modelname` | Model name sent in API payloads. Default: `local-cefr-estimator`. |
| `requesttimeout` | HTTP timeout in seconds. Default: `60`. |

Keep `enableprocessing` off when the AI service is unavailable, during API contract changes, or when you want to collect submissions without scoring them yet.

## AI API Contract

The scoring client calls these endpoints relative to `apiurl`.

### Writing

`POST /estimate/writing`

Payload:

```json
{
  "userid": 123,
  "courseid": 124,
  "cmid": 456,
  "submissionid": 789,
  "model": "local-cefr-estimator",
  "prompt": "Write about...",
  "text": "Learner response..."
}
```

### Speaking

`POST /estimate/speaking`

Payload:

```json
{
  "userid": 123,
  "courseid": 124,
  "cmid": 456,
  "submissionid": 789,
  "model": "local-cefr-estimator",
  "prompt": "Speak about...",
  "transcript": "Learner transcript...",
  "audio_path": "/path/or/url/to/audio"
}
```

Expected response fields:

| Field | Purpose |
| --- | --- |
| `cefr_level` | Estimated CEFR level. |
| `total_score` | Numeric score. |
| `rubric` | Skill/rubric breakdown. |
| `weak_kps` | KP codes or descriptions needing support. |
| `recommended_lessons` | Suggested follow-up lesson data. |

The full response is also stored as JSON for audit and troubleshooting.

## Scheduled Task

Task class:

`local_flwaiassessment\task\process_pending`

Default schedule: every 5 minutes.

Behavior:

- Exits immediately when `enableprocessing` is off.
- Processes up to 10 pending records per run.
- Marks rows as `processing` while active.
- Saves successful results as `complete`.
- Saves API or validation failures as `failed`.
- Uses `needsinput` when the submission does not contain enough usable learner input.

Run manually from the Moodle root:

```bash
php admin/cli/scheduled_task.php --execute='local_flwaiassessment\task\process_pending'
```

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `local/flwaiassessment:view` | View assessment results | Teacher, editing teacher, manager |
| `local/flwaiassessment:manage` | Submit work and confirm/correct assessment | Editing teacher, manager |

## Database

Primary table: `local_flwai_results`

Important fields:

| Field group | Fields |
| --- | --- |
| Source | `userid`, `courseid`, `cmid`, `activitytype`, `sourcecomponent`, `submissionid` |
| Input | `skilltype`, `rawtext`, `transcript`, `audiopath`, `prompttext` |
| Processing | `status`, `error`, `timecreated`, `timemodified` |
| AI result | `cefrlevel`, `totalscore`, `rubricjson`, `weakkpjson`, `recommendjson`, `airesponsejson` |
| Teacher review | `teachercefrlevel`, `teacherscore`, `teachernote`, `teacherconfirmed`, `confirmedby`, `timeconfirmed` |

## Integration Points

- `mod_flwaispeaking` creates AI assessment rows for speaking activities.
- C-UP-KP/Learner Evaluation can consume teacher-confirmed evidence when mapping speaking/writing outcomes to KP, UP, and competency states.
- The API server can be local, private network, or another controlled internal endpoint. Avoid sending learner audio/transcripts to unapproved external services.

## Operations Checklist

1. Install or upgrade the plugin through Moodle admin.
2. Configure `apiurl`, `modelname`, and timeout.
3. Keep `enableprocessing` off until the AI service health check passes.
4. Submit one test writing item and one test speaking item.
5. Run the scheduled task manually.
6. Confirm that rows move from `pending` to `complete`.
7. Open a result page and save a teacher confirmation.
8. Verify downstream evaluation pages use the confirmed result.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Rows stay pending | Confirm `enableprocessing` is on and the scheduled task is running. |
| Rows fail immediately | Check `apiurl`, firewall, HTTPS trust, and `requesttimeout`. |
| CEFR/score is blank | Inspect `airesponsejson`; the API may be returning unexpected field names. |
| Teacher cannot open pages | Check `local/flwaiassessment:view` or `local/flwaiassessment:manage`. |
| Speaking activity does not create results | Confirm `mod_flwaispeaking` is installed and points to this dependency. |

## Maintenance Notes

This plugin stores learner-generated text, transcripts, and AI feedback. Treat the database rows as personal learning records. Review retention, export, and deletion policies before using the plugin in production.
