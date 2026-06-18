# Teacher Guide

## Purpose

FLW VR Room gives learners a simple situation practice room. The MVP includes five built-in scenario presets for A1 speaking/listening readiness.

## Scenario presets

- At the Cafe
- In the Classroom
- At the Hotel
- At the Airport
- At the Supermarket

Each preset changes the room style, hotspot labels, mission prompt, answer choices, and default knowledge point codes.

Each preset also uses a photographic 360-style panorama background. Learners can rotate left and right in the scene, then click the floating object labels.

## Room modes

FLW VR Room now has three room modes:

- **360 panorama**: the original photographic room image with rotating navigation.
- **3D built-in room**: a simple Three.js room made from built-in objects. The first version is designed for the A1 cafe scenario and includes clickable 3D cafe objects such as the waiter, menu, cup, table, and cashier.
- **Uploaded 3D model**: a teacher-uploaded `.glb` or `.gltf` model is loaded into the Three.js room surface.

Both modes use the same mission, quiz, score, CEFR, KP, gradebook, and completion behavior.

For uploaded models, upload one `.glb` file when possible. If you use `.gltf`, also upload its related `.bin` and texture files in the same file area. The first `.glb` or `.gltf` file is used as the room model.

In this first uploaded-model version, the 3D model is loaded, centered, and scaled automatically. Hotspot scoring still uses the activity hotspot labels. Precise object-to-hotspot mapping for uploaded GLB/GLTF models belongs to the next visual editing step.

## Learner task

In each scenario, the learner should:

1. Click the five scene objects.
2. Read the situation prompt.
3. Choose the best English reply.
4. Save the attempt.

## Score

The MVP score is:

- Waiter: 20
- Menu: 15
- Cup: 15
- Table: 15
- Cashier: 15
- Correct mission answer: 20

Total: 100

The default passing grade is 70.

## FLW mapping

Recommended A1 cafe knowledge points:

```text
A1-VOC-FOOD-001
A1-FUNC-ORDER-001
A1-LIS-QUESTION-001
A1-SPK-REPLY-001
```

If a teacher leaves the knowledge point box empty, FLW VR Room shows the default KP codes for the selected scenario.

## Classroom use

Use this as a five-minute practice activity after teaching the scenario vocabulary and target function.

For offline or LAN classrooms, keep all media local and test with the same browser students will use.

## Custom rooms

To create a custom room, open the activity settings and enable **Use custom room content**.

You can override:

- Background image URL
- Mission title
- Mission text
- Quiz question
- Quiz answers
- Hotspots

Custom quiz answer format:

```text
answer text|score
```

Use `*` before the best answer if you do not want to think about the score:

```text
*Yes, please.|20
No, I am a student.|0
The gate is blue.|0
```

Custom hotspot format:

```text
key|label|score|x|y
```

`x` and `y` are percentages across the panorama.

Example:

```text
passport|Passport|15|25|68
ticket|Ticket|15|39|66
staff|Staff|20|68|31
```
