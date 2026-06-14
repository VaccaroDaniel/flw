# FLW VR Room Moodle Activity Plugin MVP

`mod_flwvrroom` is an alpha Moodle activity module for FLW language situation practice.

The first room is a browser-based A1 cafe mission. Learners click objects, answer a simple waiter prompt, receive a score, and save the result to Moodle.

## Current MVP features

- Moodle activity module structure.
- Teacher settings for CEFR level, scenario, FLW knowledge point codes, passing grade, and maximum grade.
- Interactive cafe room with waiter, menu, cup, table, and cashier hotspots.
- Mission quiz for the expression "I would like a coffee, please."
- Attempt saving through Moodle AJAX external service.
- Gradebook update using the learner's saved score.
- Activity completion when the learner reaches the passing grade.
- Privacy metadata and delete/export hooks for attempt data.

## Install

Copy this folder to:

```text
moodle/mod/flwvrroom
```

Then log in as a Moodle administrator and open:

```text
Site administration -> Notifications
```

Moodle should detect and install the plugin.

## Suggested first activity

- Name: FLW A1 VR Room - At the Cafe
- CEFR level: A1
- Scenario: At the Cafe
- Passing grade: 70
- Maximum grade: 100
- Knowledge points:

```text
A1-VOC-FOOD-001
A1-FUNC-ORDER-001
A1-LIS-QUESTION-001
A1-SPK-REPLY-001
```

## Notes

This is not a final WebXR headset room. It is the first stable learning-logic layer: Moodle activity, CEFR/KP mapping, scoring, attempts, gradebook, and completion.

The next version should add teacher-editable rooms and hotspots before adding full VR/AR headset support.
