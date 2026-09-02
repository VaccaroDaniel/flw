# FLW VR/AR to C-UP-KP Integration

This document records the current bridge between `mod_flwvrroom` and `local_flwcupkp`.

## Implemented receiving side

`local_flwcupkp` now accepts structured FLW VR Room attempts through two paths:

- Moodle event observer: `\mod_flwvrroom\event\attempt_submitted`
- External service: `local_flwcupkp_record_flwvrroom_attempt`

Both paths resolve the Moodle course module ID (`cmid`) to `flwcupkp_object`, then write evidence only through existing `flwcupkp_object_map` rows. Raw KP-code strings can filter KP-level maps, but they no longer create evidence by themselves.

## Payload contract

The machine-readable schema is:

`local/flwcupkp/schemas/flwvrroom_attempt.schema.json`

Minimum payload:

```json
{
  "cmid": 123,
  "courseid": 45,
  "userid": 67,
  "attemptid": 890,
  "xrmode": "builtin3d",
  "scenario": "restaurant_waiter",
  "score": 82,
  "maxscore": 100
}
```

Richer payload:

```json
{
  "cmid": 123,
  "courseid": 45,
  "userid": 67,
  "attemptid": 890,
  "xrmode": "builtin3d",
  "scenario": "restaurant_waiter",
  "kpcodes": ["KP-ORDER-REQUEST", "KP-POLITE-QUESTION"],
  "score": 82,
  "maxscore": 100,
  "durationseconds": 245,
  "hotspots": [
    {
      "id": "menu_board",
      "kind": "info",
      "completed": true,
      "kpcodes": ["KP-MENU-VOCAB"],
      "position": {"x": 1.2, "y": 1.6, "z": -2.4}
    }
  ],
  "roleturns": [
    {
      "role": "waiter",
      "prompt": "Ask the customer if they are ready to order.",
      "learnerresponse": "Are you ready to order?",
      "normalizedscore": 0.86,
      "kpcodes": ["KP-POLITE-QUESTION"]
    }
  ],
  "speaking": [
    {
      "prompt": "Take a food order.",
      "recognizedresponse": "What would you like to order?",
      "similarity": 0.84,
      "taskcompletion": 0.90,
      "intelligibility": 0.78,
      "feedback": "Clear question form. Improve final rising intonation.",
      "kpcodes": ["KP-ORDER-REQUEST"]
    }
  ]
}
```

Do not pass audio blobs to C-UP-KP. `mod_flwvrroom` should send text and scoring dimensions returned by the local Speaking Scoring Service.

## Evidence signals

The bridge writes separate evidence records for:

- `vr_room_attempt`: overall attempt score
- `vr_hotspot_interaction`: completed or scored hotspot/object interaction
- `vr_roleplay_turn`: AI character or role-play turn score
- `vr_speaking_ai`: local Speaking Scoring Service result

Each signal records a short stable `sourceattempt` key, so retrying the same attempt does not duplicate evidence.

## Teacher editing target

The next `mod_flwvrroom` editor should store scenario data with these sections:

- Scene: `xrmode`, panorama URL, built-in 3D room preset, or uploaded GLB/GLTF model file.
- Hotspots: ID, label, type, 3D position, linked KP codes, task text, media, and scoring rule.
- Role characters: character name, role, prompt bank, target KP codes, expected learner functions.
- Speaking tasks: prompt, expected response, rubric dimensions, and warm no-speech message.

The editor should preview and save the same JSON that is sent to `local_flwcupkp_record_flwvrroom_attempt`.

## XR roadmap

Use one shared scenario model and switch only the renderer/runtime:

- `panorama`: stable 360 sphere navigation and 2D/3D hotspots.
- `builtin3d`: simple Three.js room with built-in objects and walk navigation.
- `uploaded3d`: teacher-uploaded GLB/GLTF room or objects.
- `webxr`: optional immersive VR entry when browser/device supports WebXR.
- `ar`: optional object placement / camera overlay later.

The C-UP-KP evidence bridge is renderer-independent, so WebXR/AR should not require a new mastery data model.

## Current limitation

The current machine no longer contains the `mod/flwvrroom` source folder, so this change implements the receiving C-UP-KP side and the integration contract. When `mod/flwvrroom` is restored under Moodle, add either the event trigger or external-service call using the payload above.
