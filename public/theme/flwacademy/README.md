# theme_flwacademy - FLW Academy

`theme_flwacademy` is the main FLW Moodle theme. It is a Boost child theme that adds FLW Academy layouts, dashboard pages, language-world navigation, placement/exam/media entry points, course cover cards, student-friendly course pages, and FLW visual styling.

Component: `theme_flwacademy`

Release: `1.3.0 - FLW Clean Theme v3`

Requires: Moodle 5.1 or later

Status: beta FLW theme. Use as the main FLW site theme after visual QA on the target Moodle version.

## What This Theme Does

- Provides an FLW front page and dashboard experience.
- Adds language-world, self-study, school, demo, activity, and exam browsing patterns.
- Adds a student-focused course layout with FLW course covers and simplified learning actions.
- Adds reading tools such as course contents, dictionary link, learning language, and unit map.
- Integrates with FLW placement, media practice, exams, and C-UP-KP dashboard fragments when those plugins are installed.
- Keeps teacher/admin editing flows close to normal Moodle behavior.
- Provides palette settings and extra SCSS support.

## Parent Theme

Parent:

`boost`

The theme uses Moodle's standard Boost base and overrides selected layouts/templates/renderers for FLW.

## Main Layouts

| Moodle layout | File | Purpose |
| --- | --- | --- |
| `frontpage` | `frontpage_flw.php` | FLW Academy landing/home experience. |
| `mydashboard` | `dashboard_flw.php` | FLW learner dashboard. |
| `course` | `course.php` | FLW course page layout. |
| `coursecategory` | `school_category_flw.php` | FLW category browsing. |
| `standard`, `admin`, `report` | `drawers.php` | Boost-like standard pages. |
| `login` | `login.php` | FLW login layout. |

## Settings

Open:

`Site administration > Appearance > Themes > FLW Academy`

| Setting | Default | Purpose |
| --- | --- | --- |
| Primary blue color | `#0a4be8` | Main FLW brand/action color. |
| Orange color | `#FF8A00` | Accent color. |
| Purple color | `#7B4DFF` | Accent color. |
| Pink color | `#E05280` | Accent color. |
| Cream background | `#FFFDF8` | Light page background. |
| Corner radius | `1.1rem` | Theme-level rounded corner value. |
| Extra SCSS | Empty | Custom SCSS appended after theme styles. |

Color/radius changes reset theme caches automatically.

## FLW Navigation

The top navigation includes FLW entries such as:

- Dashboard.
- Self Study.
- Practice.
- Dictionary.
- Exam.
- Teacher.
- Collaboration.

Active states are calculated from the current path/page. For example:

- `/local/flwmedia/` activates Practice.
- `/local/flwexam/` activates Exam.
- `/local/flwplacement/` can activate placement/self-study context.
- Dictionary links use `local_mldict` when installed; otherwise they fall back to dashboard anchors.

## Student Clean Course Behavior

FLW student course pages are simplified when the user is a learner in a real course and does not have course update capability. Teachers/admins keep editing controls and normal Moodle management behavior.

This means:

- Students get a quieter course experience.
- Teachers/admins can still edit, manage blocks, and use normal Moodle course tools.
- Clean behavior should be tested with both student and teacher accounts.

## Optional Plugin Integrations

The theme checks for optional FLW plugins before linking to their data:

| Plugin | Theme use |
| --- | --- |
| `local_flwplacement` | Placement test links, placement profile, CEFR journey, confidence, start unit. |
| `local_flwmedia` | Watch/listen/speak/read/dictate practice links. |
| `local_flwexam` | Exam center links, available exams, result signals. |
| `local_flwcupkp` | Dashboard control-center fragments and learning-progress cards. |
| `local_mldict` | Dictionary link and headword counts when available. |

The theme should not fail if these optional plugins are absent. It falls back to generic links or empty states where possible.

## Learner Dashboard

The dashboard can show:

- Language selection.
- Continue learning.
- Placement level/status.
- CEFR progress.
- Skill progress.
- Unit map.
- Vocabulary review.
- Exam/placement signals.
- Portfolio highlights.
- C-UP-KP control-center fragments when available.

Dashboard data is partly cached and partly enriched at runtime for user-specific fragments.

## Placement Links

The theme can route placement buttons to:

- `/local/flwplacement/index.php?language=CODE&flwplacement=1`
- A direct linked Moodle Quiz attempt URL when the placement plugin has a ready quiz configured.

Supported language codes include:

- `en`
- `ru`
- `zh`
- `de`
- `ja`
- `fr`
- `es`

## Media Practice Links

Practice cards can link to:

```text
/local/flwmedia/index.php?language=CODE&mode=watch
/local/flwmedia/index.php?language=CODE&mode=listen
/local/flwmedia/index.php?language=CODE&mode=speak
/local/flwmedia/index.php?language=CODE&mode=read
/local/flwmedia/index.php?language=CODE&mode=dictate
```

## Developer Commands

Run from the Moodle root.

Build theme CSS:

```bash
php admin/cli/build_theme_css.php --themes=flwacademy
```

Build AMD JavaScript after editing `amd/src`:

```bash
npx grunt amd --component=theme_flwacademy
```

Purge Moodle caches:

```bash
php admin/cli/purge_caches.php
```

## Main Files

| Path | Purpose |
| --- | --- |
| `config.php` | Parent theme, layouts, callbacks, favicon. |
| `settings.php` | Admin palette/radius/extra SCSS settings. |
| `lib.php` | Theme data exporters, nav helpers, plugin integrations, SCSS callbacks. |
| `layout/` | Moodle layout files. |
| `templates/` | Mustache templates for FLW UI. |
| `scss/` | Theme SCSS source. |
| `amd/src/` | Theme JavaScript source. |
| `pix/` | Images, icons, and dashboard assets. |

## Testing Checklist

1. Set FLW Academy as the active theme.
2. Purge caches.
3. Open the front page as guest/logged-out user.
4. Open `/my/` as student.
5. Open a course as student and confirm the simplified course layout.
6. Open the same course as teacher/admin and confirm editing controls remain available.
7. Open placement, media, exam, dictionary, and C-UP-KP pages if installed.
8. Confirm top navigation active states.
9. Test desktop and mobile widths.
10. Rebuild CSS/AMD after source changes.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Old styling remains | Purge caches and rebuild theme CSS. |
| JavaScript changes do not appear | Run `npx grunt amd --component=theme_flwacademy` and purge caches. |
| Placement button opens fallback page | Placement plugin quiz setting/readiness. |
| Dictionary link is not direct | `local_mldict` installation and readable index page. |
| Teacher sees student-clean course page | Course role/capability `moodle/course:update`. |
| Student sees editing UI | Role assignment or course update capability override. |

## Production Notes

Before production rollout, check all primary FLW pages with student, teacher, and admin accounts. Theme issues are often role-specific because the theme intentionally changes the experience based on capability and current page.
