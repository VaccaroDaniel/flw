# mod_flwaispeaking - FLW AI Speaking

`mod_flwaispeaking` is a Moodle activity module for FLW speaking practice and assessment. It lets teachers create transcript/audio speaking tasks, sends learner work to `local_flwaiassessment`, and syncs AI results back into the activity, reports, and gradebook.

Component: `mod_flwaispeaking`

Release: `0.2.0-alpha`

Requires: Moodle 5.1 or later

Dependency: `local_flwaiassessment` version `2026061400` or later

Status: alpha. Use with a trusted local FLW AI assessment server.

## What This Activity Does

- Adds an FLW AI Speaking activity type to Moodle courses.
- Supports topic speaking and read-aloud tasks.
- Supports transcript-only, audio-only, or transcript-plus-audio submission modes.
- Sends speaking submissions to `local_flwaiassessment`.
- Can transcribe browser-recorded audio through the local FLW AI server.
- Stores each learner attempt with linked assessment result ID.
- Lets students review their own submissions.
- Lets teachers view reports and AI result links.
- Updates Moodle gradebook from synced assessment scores.

## Required Backend

This module depends on `local_flwaiassessment`. Configure that plugin first:

1. Install/upgrade `local_flwaiassessment`.
2. Set its `apiurl`, `modelname`, and `requesttimeout`.
3. Confirm the local AI service supports:
   - `POST /transcribe/audio`
   - `POST /estimate/speaking`
4. Enable processing when ready.

Without `local_flwaiassessment`, the module cannot create or sync assessment results.

## Activity Settings

Teachers configure these fields when adding or editing the activity:

| Setting | Meaning |
| --- | --- |
| Name/intro | Normal Moodle activity name and introduction. |
| Task type | Topic speaking or read aloud. |
| Prompt text | Learner prompt sent to the AI scorer. |
| Target text | Read-aloud/reference text where relevant. |
| Reference audio URL | Optional listening/read-aloud support. |
| CEFR level | Target level from A1 to C2. |
| KP codes | One KP code per line. |
| Submission mode | Transcript, audio, or both. |
| Maximum attempts | `0` means unlimited. |
| Instant processing | Run the assessment task immediately after submission when processing is enabled. |
| Maximum grade | Gradebook maximum. |

## Learner Workflow

1. Open the activity from the course page.
2. Read the prompt and any target text/audio.
3. Type a transcript, record audio, or provide both depending on the activity mode.
4. Submit the attempt.
5. Moodle creates a linked AI assessment result.
6. If instant processing is enabled, Moodle tries to process the result immediately.
7. The learner can see their own previous submissions and latest status.

## Teacher Workflow

1. Add the activity to a course.
2. Set CEFR, prompt, KP codes, submission mode, and grade.
3. Ask a test learner to submit.
4. Open the activity report.
5. Use the AI result link to review teacher confirmation in `local_flwaiassessment`.
6. Refresh/sync submissions if assessment rows have been processed after the activity page loaded.
7. Confirm gradebook values.

## Pages

| Page | Purpose |
| --- | --- |
| `/mod/flwaispeaking/index.php?id=COURSEID` | Course activity index. |
| `/mod/flwaispeaking/view.php?id=CMID` | Learner/teacher activity page. |
| `/mod/flwaispeaking/report.php?id=CMID` | Teacher report. |
| `/mod/flwaispeaking/delete.php?id=CMID&submissionid=ID` | Delete a learner submission when permitted. |

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `mod/flwaispeaking:addinstance` | Add the activity to a course | Editing teacher, manager |
| `mod/flwaispeaking:view` | View the activity | Student, teacher, editing teacher, manager |
| `mod/flwaispeaking:submit` | Submit speaking work | Student |
| `mod/flwaispeaking:viewreports` | View class/submission reports | Teacher, editing teacher, manager |

## Database

| Table | Purpose |
| --- | --- |
| `flwaispeaking` | Activity settings. |
| `flwaispeaking_submissions` | Learner attempts linked to AI assessment rows. |

Important submission fields:

- `flwaispeakingid`
- `userid`
- `attemptnumber`
- `submissiontype`
- `transcript`
- `audiofilename`
- `audiomimetype`
- `assessmentid`
- `status`
- `cefrlevel`
- `totalscore`
- `rubricjson`
- `weakkpjson`
- `recommendjson`

## Gradebook Behavior

The module creates a Moodle grade item for the activity. After a linked AI assessment result is complete, activity submission data can be synced and gradebook values updated from the saved score.

If grades appear stale:

1. Confirm the linked `local_flwai_results` row is complete.
2. Open the activity/report to trigger sync behavior where available.
3. Confirm the grade item exists.
4. Confirm the activity maximum grade is set as expected.

## C-UP-KP Use

KP codes entered on the activity are the bridge from speaking tasks to C-UP-KP. For mastery use:

- Use stable KP codes.
- Keep one code per line.
- Confirm the AI result or teacher review before treating the submission as evidence.
- Prefer teacher-confirmed speaking evidence for high-impact mastery updates.

## Testing Checklist

1. Configure `local_flwaiassessment`.
2. Create an FLW AI Speaking activity.
3. Add at least one KP code.
4. Submit a transcript-only attempt as a student.
5. Confirm a row exists in `local_flwai_results`.
6. Run or wait for AI assessment processing.
7. Open the activity report.
8. Confirm the result link opens the AI assessment page.
9. Confirm score/status sync and gradebook update.
10. Test audio recording/transcription if browser microphone access is required.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Activity cannot be added | Plugin installed, dependency installed, and `mod/flwaispeaking:addinstance`. |
| Audio transcription fails | Browser microphone permission, HTTPS trust, AI service `/transcribe/audio`, and timeout. |
| Submission stays queued | `local_flwaiassessment/enableprocessing` and scheduled task. |
| Report does not show AI result | `assessmentid` link and `local_flwaiassessment:view` capability. |
| Grade is missing | Assessment completion, score field, grade item, and activity max grade. |

## Production Notes

Speaking submissions may include learner voice recordings, transcripts, scores, and AI feedback. Use a trusted local service, avoid unnecessary external transfer, and define retention/review policy before production deployment.
