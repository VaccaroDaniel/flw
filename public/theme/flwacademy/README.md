# FLW Academy / FLW Clean Theme v3

FLW Clean Theme v3 is implemented inside the existing `theme_flwacademy` Moodle theme. It keeps FLW Academy branding and Boost compatibility while giving student course pages a clean app-like learning interface.

## What Clean Mode Does

Clean mode is added only on course view pages when all of these are true:

- The page is inside a real course, not the site front page.
- The user is not editing.
- The user does not have `moodle/course:update` in the course context.

Teachers, managers, and admins keep normal Moodle navigation, editing controls, blocks, reports, settings, and tabs.

## Installation

1. Copy or deploy `theme/flwacademy` into Moodle.
2. Visit `Site administration > Notifications` to let Moodle detect the version update.
3. Enable the theme from `Site administration > Appearance > Theme selector`.
4. Purge caches after deployment.

Windows installer CLI example:

```powershell
C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe C:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\admin\cli\purge_caches.php
```

## Rebuild SCSS

This theme appends `scss/flwclean.scss` after the existing FLW Academy SCSS.

Windows installer CLI example:

```powershell
C:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe C:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\admin\cli\build_theme_css.php --themes=flwacademy
```

## Rebuild AMD JavaScript

Source files live in `amd/src/`.

Build files are included in `amd/build/` so the modules can load immediately. When editing AMD source, rebuild with Moodle's normal Grunt pipeline from the Moodle root:

```bash
npx grunt amd --component=theme_flwacademy
```

If your Moodle checkout uses the standard full build, this is also acceptable:

```bash
npx grunt amd
```

## Sample Course HTML

Paste this into a Moodle page/label/book chapter inside a course. The theme handles styling, lazy video loading, pagination, and accordion behavior for student clean mode.

```html
<header class="flw-unit-header">
  <p class="flw-kicker">Real English World</p>
  <h1>Unit 1: First Conversations</h1>
  <p>Your goal: greet people, introduce yourself, and ask simple classroom questions.</p>
</header>

<nav class="flw-mini-nav" aria-label="FLW lesson navigation">
  <a href="#flw-lessons">Lessons</a>
  <a href="#flw-watch">Watch</a>
  <a href="#flw-practice">Practice</a>
  <a href="#flw-project">Project</a>
</nav>

<div class="flw-unit-map">
  <a class="flw-unit-node completed" href="#">Unit 1</a>
  <a class="flw-unit-node current" href="#">Unit 2</a>
  <a class="flw-unit-node locked" href="#">Unit 3</a>
</div>

<section id="flw-lessons">
  <article class="flw-lesson-card">
    <button class="flw-lesson-toggle" type="button" aria-expanded="true">Lesson 1: Say Hello</button>
    <div class="flw-lesson-body">
      <p>Read, listen, and practice a short greeting exchange.</p>
    </div>
  </article>
  <article class="flw-lesson-card">
    <button class="flw-lesson-toggle" type="button" aria-expanded="false">Lesson 2: Ask a Name</button>
    <div class="flw-lesson-body" hidden>
      <p>Practice asking and answering names with a partner.</p>
    </div>
  </article>
</section>

<section id="flw-watch" class="flw-video-hub" data-page-size="6">
  <h2>Watch</h2>
  <div class="flw-video-grid">
    <article class="flw-video-card">
      <video controls preload="metadata" poster="https://media.example.com/flw/unit001/watch/poster.jpg">
        <source data-src="https://media.example.com/flw/unit001/watch/Video.mp4" type="video/mp4">
        <track src="https://media.example.com/flw/unit001/watch/Video.vtt" kind="subtitles" srclang="en" label="English">
      </video>
      <h3>Unit 1 Watch</h3>
    </article>
  </div>
</section>

<section id="flw-practice" class="flw-practice-panel">
  <h2>Practice</h2>
  <p>Add HTML practice tasks or Moodle activities in the page flow.</p>
</section>

<section id="flw-project" class="flw-project-panel">
  <h2>Project</h2>
  <p>Create a short self-introduction and submit it to your teacher.</p>
</section>
```

## Manual Acceptance Tests

### Student Test

- Log in as a student.
- Open a course view page.
- Confirm the body has `flw-clean-mode`.
- Confirm Moodle tabs/sidebar/course index/blocks are hidden.
- Confirm lesson content remains visible.
- Confirm `.flw-video-hub` paginates when it has more cards than `data-page-size`.
- Confirm video `<source data-src>` values are not copied to `src` until the videos are near the viewport.
- Confirm mobile width has a single-column coursebook layout.

### Teacher Test

- Log in as a teacher.
- Open the same course.
- Turn editing on.
- Confirm Moodle editing controls, course settings, blocks, tabs, and activity menus remain visible.
- Confirm activities can still be edited.

### Admin Test

- Log in as an admin.
- Open Site administration.
- Open `Appearance > Themes > FLW Academy` settings.
- Confirm no PHP fatal errors.
- Confirm browser console has no errors from `theme_flwacademy/flw_video_lazy`, `flw_pagination`, or `flw_accordion`.

## Known Limitations

- The unit map is visual only. It does not calculate adaptive progress.
- The mini navigation uses ordinary page anchors and does not replace Moodle permissions or availability rules.
- The video hub expects MP4/VTT URLs that the browser is allowed to request.
- Clean mode is intentionally scoped to course view pages, not mod activity pages, admin pages, reports, or editing views.
