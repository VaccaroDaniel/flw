# FLW VR Room Moodle Activity Plugin MVP

Plugin component: `mod_flwvrroom`  
Moodle folder: `mod/flwvrroom`  
Version: `0.1.0-alpha`

This is a first Moodle activity plugin MVP for Foreign Language World (FLW). It creates a browser-based VR-style 3D café room for A1 English practice.

## What this MVP does

- Adds a new Moodle activity type: **FLW VR Room**
- Creates a built-in **A1 café room**
- Lets learners click objects: waiter, menu, cup, table, cashier
- Gives vocabulary/listening messages
- Includes a simple mission quiz
- Calculates a score out of 100
- Saves attempts through Moodle AJAX
- Updates the Moodle gradebook
- Marks completion when the mission is complete
- Stores CEFR level and FLW Knowledge Point codes

## What this MVP does not do yet

- It does not use headset WebXR yet.
- It does not upload custom 3D rooms yet.
- It does not include AI speaking assessment yet.
- It does not include full backup/restore code yet.
- It includes privacy metadata, but not full export/delete request handling yet.

## Installation

1. Extract the ZIP.
2. Copy the `flwvrroom` folder into:

```text
moodle/mod/flwvrroom
```

3. Visit:

```text
Site administration → Notifications
```

4. Let Moodle install the plugin.
5. Go to a course.
6. Turn editing on.
7. Add activity or resource.
8. Choose **FLW VR Room**.

## Recommended activity settings

```text
Name: FLW A1 VR Room - At the Café
CEFR level: A1
Scenario: At the Café
Knowledge points:
A1-VOC-FOOD-001
A1-FUNC-ORDER-001
A1-LIS-QUESTION-001
A1-SPK-REPLY-001
Passing grade: 70
Maximum grade: 100
Completion: Student must view this activity
```

## Testing

Log in as a student, enter the activity, complete all object clicks and the quiz. At the end, the score should save to Moodle.

## Next development steps

1. Add file upload support for custom scene assets.
2. Add reusable scene JSON so teachers can create café, classroom, hotel, airport, and supermarket rooms.
3. Connect with `local_flwkp` Knowledge Point plugin.
4. Add speaking recording and offline STT/TTS.
5. Add real WebXR/A-Frame room mode.
