# mod_flwvrroom - FLW VR Room

`mod_flwvrroom` is an experiential FLW Moodle activity for 2D/3D room practice, scenario tasks, hotspots, role-play speaking, AI-assisted speaking feedback, gradebook updates, and completion rules.

Component: `mod_flwvrroom`

Release: `0.1.0-alpha`

Requires: Moodle 4.1 or later

Status: alpha. Use for controlled immersive-learning pilots.

## What This Activity Does

- Adds an FLW VR Room activity type to Moodle courses.
- Supports scenario templates such as cafe, classroom, hotel, airport, and supermarket.
- Supports panorama, built-in 3D, and uploaded 3D model room modes.
- Lets teachers define mission text, quiz answers, hotspots, KP codes, role-play prompts, and completion rules.
- Lets learners explore a room, interact with hotspots, answer prompts, and complete speaking/role-play tasks.
- Saves learner attempts with scores, KP codes, hotspot JSON, role-turn JSON, speaking JSON, duration, and task-complete state.
- Updates Moodle gradebook.
- Updates Moodle activity completion based on teacher-configured rules.
- Provides teacher reports.

## Activity Modes

| Mode | Purpose |
| --- | --- |
| Panorama | Uses a photographic/panorama background for room exploration. |
| Built-in 3D | Uses the bundled 3D scene experience. |
| Uploaded 3D model | Uses a teacher-provided `.glb` or `.gltf` room model. |

Supported upload asset extensions:

- `.glb`
- `.gltf`
- `.bin`
- `.png`
- `.jpg`
- `.jpeg`
- `.webp`

## Scenario Templates

Common scenarios include:

- Cafe.
- Classroom.
- Hotel.
- Airport.
- Supermarket.

Teachers can override the template with custom mission text, background, answers, hotspots, role-play settings, and KP codes.

## Custom Authoring Formats

### Answers

Format:

```text
answer text|score
```

Put `*` at the start of the answer text to mark the correct answer:

```text
*I'd like a coffee, please.|20
```

### Hotspots

Format:

```text
key|label|score|x|y|description|audio URL|objectX|objectY|objectZ|KP codes|object reference
```

Use stable hotspot keys so reports and later evidence mappings remain readable.

## Speaking Scoring

The activity can call a local FLW Speaking Scoring Service. Configure the base URL in the activity setting.

Expected endpoints:

- `POST /transcribe/audio`
- `POST /estimate/speaking`

Use HTTPS or a trusted local network path when recording audio in browsers. Browser microphone capture usually requires HTTPS or localhost.

## Role-Play Character

The role-play system can define:

- Character enabled/disabled.
- Character name and role.
- Scripted line.
- Expected learner answer.
- Role KP codes.
- Character position.
- Scripted role turns.
- AI role mode.
- AI personality.
- AI difficulty.
- Target response pattern.
- Maximum retries.
- Optional character model upload.

Role play is optional by default for completion, but teachers can require it in completion rules.

## Room Editor

The in-page room editor supports:

- Scenario template selection.
- Import/export JSON.
- Mission title and text.
- Quiz question and answers.
- Hotspot builder.
- 2D and 3D position fields.
- Object browser and object binding.
- KP picker/entry.
- Role-play settings.
- Completion rules.

Room editor saves through the AJAX service `mod_flwvrroom_save_room_editor`.

## Completion and Gradebook

Teacher-configurable completion rules:

- Require all hotspots.
- Require speaking answer.
- Require role play.
- Minimum score.

Default completion behavior:

- Hotspots required.
- Speaking required.
- Role play not required.
- Minimum score `70`.

The learner attempt saves `taskcomplete`. Reports and Moodle completion use the saved task-complete state so completion follows the teacher's rules.

## Pages

| Page | Purpose |
| --- | --- |
| `/mod/flwvrroom/index.php?id=COURSEID` | Course activity index. |
| `/mod/flwvrroom/view.php?id=CMID` | Learner room and teacher room editor. |
| `/mod/flwvrroom/report.php?id=CMID` | Teacher report. |

## Web Services

| Function | Purpose |
| --- | --- |
| `mod_flwvrroom_submit_attempt` | Save learner attempt, update grade, update completion. |
| `mod_flwvrroom_score_speaking` | Score/transcribe speaking input. |
| `mod_flwvrroom_save_room_editor` | Save room editor settings. |
| `mod_flwvrroom_role_waiter` | Run role-play response behavior. |

These services are intended for Moodle AJAX/session use, not public anonymous access.

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `mod/flwvrroom:addinstance` | Add the activity to a course | Editing teacher, manager |
| `mod/flwvrroom:view` | View the activity | Student, teacher, editing teacher, manager, guest |
| `mod/flwvrroom:submit` | Submit learner attempts | Student |
| `mod/flwvrroom:viewreports` | View teacher reports | Teacher, editing teacher, manager |

## Database

| Table | Purpose |
| --- | --- |
| `flwvrroom` | Activity settings, scenario configuration, role-play configuration, grade, and completion rules. |
| `flwvrroom_attempts` | Learner attempts, scores, hotspot/speaking/role JSON, duration, and completion state. |

## C-UP-KP Use

Use KP fields to connect room practice to C-UP-KP:

- Activity-level KP codes.
- Hotspot KP codes.
- Role-play KP codes.
- Speaking feedback KP data where available.

For mastery use, treat VR attempts as evidence only after scoring and teacher policy are clear. High-stakes KP mastery should use confirmed evidence or calibrated thresholds.

## Testing Checklist

1. Create an FLW VR Room activity.
2. Choose a scenario and room mode.
3. Add at least one hotspot and one KP code.
4. Set completion rules.
5. Log in as learner and complete hotspot, quiz, and speaking steps.
6. Submit the attempt.
7. Confirm gradebook updates.
8. Confirm Moodle activity completion follows the configured rules.
9. Open the teacher report.
10. Test room editor save/reload.
11. Test WebXR or desktop fallback depending on the device.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 3D room is blank | Browser console, uploaded model files, asset URLs, and WebGL support. |
| Microphone does not work | HTTPS trust, browser permission, and scoring service availability. |
| Attempt does not save | AJAX service response, session key, and `mod/flwvrroom:submit`. |
| Completion does not update | Completion enabled on the activity/course and teacher completion rules. |
| Teacher cannot edit room | Role permissions and course editing capability. |
| Report is empty | Learner attempt submission and `mod/flwvrroom:viewreports`. |

## Production Notes

VR Room can store learner speech text, role-play data, scores, and detailed interaction JSON. Keep assets licensed, validate uploaded models before use, and run a browser/device smoke test before assigning the activity to a class.
