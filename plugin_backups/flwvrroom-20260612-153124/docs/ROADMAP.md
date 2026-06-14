# FLW VR Room Roadmap

## Phase 1: Moodle MVP

Current plugin:

- Static café room
- Clickable learning objects
- Quiz
- Score saving
- Gradebook update
- Completion support

## Phase 2: Teacher-authored rooms

Add a scene editor or JSON-based room configuration:

```json
{
  "scenario": "classroom",
  "cefr": "A1",
  "mission": "Name five classroom objects",
  "objects": [
    {"id": "desk", "label": "Desk", "kp": "A1-VOC-CLASS-001"}
  ]
}
```

## Phase 3: Knowledge Point integration

Connect with:

```text
local_flwkp
local_flwpath
```

## Phase 4: Speaking and AI

Add:

- microphone recording
- local STT
- local TTS
- rubric scoring
- pronunciation / grammar feedback

## Phase 5: Real WebXR / A-Frame

Add a switch:

```text
Render mode: Canvas MVP / A-Frame WebXR
```
