# Install on Windows XAMPP Moodle

1. Stop Apache in the XAMPP control panel.
2. Copy the plugin folder to:

```text
C:\xampp\htdocs\moodle\mod\flwvrroom
```

3. Start Apache again.
4. Open Moodle as an administrator.
5. Go to:

```text
Site administration -> Notifications
```

6. Confirm the plugin installation.
7. Open a course, turn editing on, and add:

```text
FLW VR Room
```

## First test settings

- Name: FLW A1 VR Room - At the Cafe
- CEFR level: A1
- Scenario: At the Cafe
- Passing grade: 70
- Maximum grade: 100

Knowledge points:

```text
A1-VOC-FOOD-001
A1-FUNC-ORDER-001
A1-LIS-QUESTION-001
A1-SPK-REPLY-001
```

## Troubleshooting

- If Moodle does not detect the plugin, check that the path is exactly `moodle/mod/flwvrroom`.
- If the room does not save scores, purge Moodle caches from `Site administration -> Development -> Purge caches`.
- If JavaScript changes are not visible, purge caches again because Moodle caches AMD modules.
