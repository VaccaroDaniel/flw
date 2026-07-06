# FLW SCORM Clean Viewer v1

This viewer keeps Moodle core intact and uses the `flwacademy` theme to make learner SCORM pages feel like a clean coursebook/video lesson. The cleanup is presentation-only.

## What the theme changes

For non-editing learner SCORM pages, the theme hides only Moodle chrome around the player:

- Page header and breadcrumbs
- Secondary activity navigation
- Activity header and completion/info panels
- SCORM player exit activity button
- SCORM top bar
- Additional activities / blocks drawer

The theme also supplies optional styling for an internal FLW package navigation bar:

- `.flw-scorm-bar`
- `.flw-back`
- `.flw-unit-title`
- `.flw-progress`

The theme does not hide Moodle's internal SCORM player structure by CSS. Moodle's SCORM JavaScript depends on the table-of-contents and player containers to create and size the content iframe. Use SCORM activity settings to hide Moodle SCORM navigation safely.

## Recommended SCORM defaults

Path:

`Site administration -> Plugins -> Activity modules -> SCORM package -> Default value settings`

Set these defaults for new FLW SCORM activities:

- Display course structure on entry page: `No`
- Student skip content structure page: `Always`
- Disable preview mode: `Yes`
- Display course structure in player: `Disabled`
- Display attempt status: `My home page only`
- Display package: `Current window`
- Show navigation: `No`

For individual SCORM activities, also prefer hiding the activity name/description on the course page when the package already contains its own title screen.

## Existing SCORM activities

Default settings affect new activities. Existing activities keep their saved values until updated manually or with a reviewed migration helper.

This repository includes a dry-run helper:

```powershell
& C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe scripts\flw_scorm_clean_defaults.php
```

Apply only the global defaults:

```powershell
& C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe scripts\flw_scorm_clean_defaults.php --apply
```

Apply defaults and update all existing SCORM instances:

```powershell
& C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe scripts\flw_scorm_clean_defaults.php --apply --update-existing --yes
```

## Optional package navigation bar

Add this inside the SCORM package HTML when the package needs its own coursebook navigation. Keep the link relative to the package location.

```html
<nav class="flw-scorm-bar">
  <a class="flw-back" href="../../course/view.php">&larr; Course</a>
  <div class="flw-unit-title">FLW Unit Title</div>
  <div class="flw-progress">Lesson 1 / 7</div>
</nav>
```

The Moodle theme supplies the styling for `.flw-scorm-bar`, `.flw-back`, `.flw-unit-title`, and `.flw-progress`.

## Verification checklist

- Learner SCORM player opens directly into the package content.
- Moodle SCORM TOC/sidebar and activity header are hidden for learner playback.
- Teacher/admin editing pages still show Moodle controls.
- Browser console has no new JavaScript errors from the theme.
- Front page, login page, and unauthenticated course redirect still respond normally after rebuilding theme CSS and purging caches.
