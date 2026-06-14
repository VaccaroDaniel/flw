# Install on Windows/XAMPP Moodle

1. Stop Apache.
2. Copy `flwvrroom` to:

```text
C:\xampp\htdocs\moodle\mod\flwvrroom
```

3. Start Apache.
4. Open Moodle as admin.
5. Go to:

```text
Site administration → Notifications
```

6. Confirm installation.
7. Create a course activity named:

```text
FLW A1 VR Room - At the Café
```

## If the plugin does not appear

Check that the folder is exactly:

```text
moodle/mod/flwvrroom/version.php
```

not:

```text
moodle/mod/flwvrroom/flwvrroom/version.php
```
