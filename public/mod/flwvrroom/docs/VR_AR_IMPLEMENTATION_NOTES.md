# FLW VR Room VR/AR Implementation Notes

Last updated: 2026-08-15

## Scope

These notes summarize the recent `mod_flwvrroom` VR/AR implementation work, including 360 panorama stabilization, Three.js 3D room support, uploaded GLB/GLTF room support, teacher editing tools, role-character interaction, speaking scoring integration, C-UP-KP evidence tracking, and report UI improvements.

## Implemented Features

- Stabilized 360 panorama room navigation with wraparound rotation, drag/swipe, keyboard controls, and desktop fallback behavior.
- Added Three.js-based built-in 3D room mode with simple room objects, movement controls, role character support, and WebXR entry path.
- Added uploaded 3D model room mode for GLB/GLTF models, including model loading status, object discovery, and large-model warning.
- Added true WebXR session entry for panorama, built-in 3D, and uploaded 3D modes when browser/headset support is available.
- Added richer hotspot content: descriptions, optional audio URL, KP codes, 3D object coordinates, and 3D object reference.
- Added teacher-friendly room editor controls in the room page for mission text, quiz answers, hotspots, KP codes, role-play turns, AI waiter settings, completion rules, and scenario templates.
- Added scenario JSON import/export so teachers can reuse room configurations.
- Added visual hotspot authoring: create from scene, drag/update hotspots, bind hotspot to named GLB/GLTF object, update selected hotspot, and delete selected hotspot.
- Added role character and AI waiter flow using the local FLW AI/Speaking Scoring Service, not browser speech recognition.
- Added speaking recording and scoring through the local Speaking Scoring Service.
- Added per-KP result tracking through structured attempt payloads and C-UP-KP evidence synchronization.
- Added report UI for KP summary, learner summary, recent attempts, full attempt detail, structured evidence, C-UP-KP evidence debug, and recommended next practice.
- Added teacher-configurable completion rules: require all hotspots, require speaking, require role play, and minimum score.

## Important File Areas

- Main learner/editor JavaScript: `amd/src/room.js`
- Moodle AMD build copy: `amd/build/room.min.js`
- Learner/editor template: `templates/room.mustache`
- Activity view config: `view.php`
- Teacher report page: `report.php`
- Activity settings form: `mod_form.php`
- Room editor AJAX service: `classes/external/save_room_editor.php`
- Attempt submit AJAX service: `classes/external/submit_attempt.php`
- AI waiter AJAX service: `classes/external/role_waiter.php`
- Database schema and upgrades: `db/install.xml`, `db/upgrade.php`
- Language strings: `lang/en/flwvrroom.php`
- Styling: `styles.css`

## Custom Hotspot Format

Custom hotspots currently use one line per hotspot:

```text
key|label|score|x|y|description|audio URL|objectX|objectY|objectZ|KP codes|object reference
```

Notes:

- `x` and `y` are 2D panorama/world placement values.
- `objectX`, `objectY`, and `objectZ` are 3D scene coordinates.
- `KP codes` are comma-separated.
- `object reference` can bind a hotspot to a named object/mesh in an uploaded GLB/GLTF model.
- The room editor and position helper can generate this format automatically.

## Review Fixes Applied

- Fixed extra closing `div` in the full attempt detail report route.
- Changed report pass/fail display to use saved `taskcomplete`, so it follows teacher completion rules.
- Fixed panorama visual placement to save panorama-world coordinates instead of raw screen coordinates.
- Fixed 3D visual placement/drag to update `objectX`, `objectY`, `objectZ`, and object reference.
- Fixed update/delete/bind/append hotspot actions so the live hotspot buttons and mission checklist update immediately.
- Fixed AI waiter retry handling so `0` retries is preserved in JavaScript and PHP.

## Verification Performed

- Ran `node --check` for `amd/src/room.js`.
- Ran `node --check` for `amd/build/room.min.js`.
- Ran PHP lint for touched PHP files.
- Ran Moodle plugin upgrade after DB version changes.
- Purged Moodle caches after AMD/template/string changes.
- Confirmed new DB columns were installed.

## Known Limitations

- WebXR headset behavior cannot be fully validated from CLI; it still needs browser/headset testing.
- The AMD build file is currently a direct copy of source, not minified.
- Visual hotspot editing is intentionally lightweight and still text-format based under the hood.
- GLB/GLTF object binding depends on useful object or mesh names inside the model file.
- Large-model warning is heuristic and based on approximate triangle count after loading.

## Suggested Next Improvements

- Add automated browser smoke tests for panorama, built-in 3D, uploaded 3D, editor save, and report routes.
- Add a richer GLB object inspector with search, highlight-on-hover, and selected-object preview.
- Add a dedicated scenario JSON schema and validation messages before import.
- Add a separate teacher preview mode so unsaved edits can be tested without affecting learner attempts.
- Add model optimization guidance in the teacher guide, including texture size and mesh naming recommendations.
