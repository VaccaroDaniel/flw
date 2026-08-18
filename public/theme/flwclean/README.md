# theme_flwclean - FLW Clean Mode v1

`theme_flwclean` is a lightweight child theme for distraction-free FLW course reading and video learning. It depends on `theme_flwacademy` and reuses FLW Academy SCSS callbacks while applying quieter course/activity presentation.

Component: `theme_flwclean`

Release: `FLW Clean Mode v1`

Requires: Moodle `2025041400` or later

Dependency: `theme_flwacademy`

Status: alpha. Use for focused reading/video pilots after confirming the parent FLW Academy theme is installed.

## What This Theme Does

- Provides a simplified FLW course-reading experience.
- Uses `theme_flwacademy` as its parent and `boost` as fallback through the parent chain.
- Reuses FLW Academy SCSS and extra SCSS callbacks.
- Adds `style/custom.css` for Clean Mode-specific styling.
- Hides or reduces activity header noise on course/incourse-style pages.
- Keeps Moodle drawers/course index support available.
- Includes a small video gallery AMD module and example snippet.
- Does not store personal data.

## Parent Themes

Configured parent chain:

```php
$THEME->parents = ['flwacademy', 'boost'];
```

Install and verify `theme_flwacademy` before enabling this theme.

## When To Use This Theme

Use `theme_flwclean` when the site/course should feel more like a clean reader or video-learning environment:

- Focused textbook reading.
- Video gallery lessons.
- Low-distraction learner course pages.
- Pilots where FLW Academy's broader dashboard/home experience is not needed.

Use `theme_flwacademy` when you need the full FLW dashboard, language worlds, placement/exam/media navigation, and branded academy home experience.

## Main Layout Behavior

| Layout | Behavior |
| --- | --- |
| `course` | Uses drawers layout with language menu and reduced activity header display. |
| `incourse` | Uses drawers layout with reduced activity header display. |
| `mydashboard` | Uses drawers layout with non-navbar and language menu options. |
| `login`, `popup`, `embedded`, `maintenance`, `secure` | Fall back to Boost-specific layout behavior where configured. |
| `report`, `admin`, `standard` | Keeps drawer-based Moodle structure. |

The theme sets:

- `usescourseindex = true`
- FontAwesome icon system.
- Edit switch enabled.
- Activity header config with title/completion/description hidden where supported.

## Files

| Path | Purpose |
| --- | --- |
| `config.php` | Parent theme, layouts, callbacks, icon system, course index behavior. |
| `style/custom.css` | Clean Mode CSS loaded by the theme. |
| `layout/drawers.php` | Main drawer layout. |
| `layout/course.php` | Course layout support. |
| `templates/course.mustache` | Course template customization. |
| `amd/src/video_gallery.js` | Video gallery source module. |
| `amd/build/video_gallery.min.js` | Built AMD output. |
| `examples/video-gallery-snippet.html` | Example HTML snippet. |

## Developer Commands

Run from the Moodle root.

Build theme CSS:

```bash
php admin/cli/build_theme_css.php --themes=flwclean
```

Build AMD JavaScript after editing `amd/src/video_gallery.js`:

```bash
npx grunt amd --component=theme_flwclean
```

Purge caches:

```bash
php admin/cli/purge_caches.php
```

## Setup Checklist

1. Install or verify `theme_flwacademy`.
2. Install `theme_flwclean`.
3. Run Moodle upgrade if this is a new install.
4. Select FLW Clean Mode v1 as the active theme or assign it where needed.
5. Purge caches.
6. Open a course as student.
7. Open the same course as teacher/admin.
8. Confirm course reading/video pages are quieter but admin/editing controls still work for staff.

## Video Gallery

The theme includes an example video gallery snippet:

`examples/video-gallery-snippet.html`

Use it as a starting point for content pages that need a clean course video gallery. After changing gallery JavaScript, rebuild AMD and purge caches.

## Privacy

`theme_flwclean` does not store personal data. It changes page presentation only. Personal data shown on a page still belongs to the underlying Moodle page/plugin.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Theme cannot be selected | Confirm `theme_flwacademy` is installed and upgraded. |
| Styling looks like Boost | Purge caches and build `flwclean` theme CSS. |
| Video gallery script does not update | Run the AMD build command and purge caches. |
| Course page is too minimal for teachers | Confirm role/capability expectations and switch to `theme_flwacademy` for full Academy navigation. |
| Missing favicon/assets | Confirm parent theme assets are present under `theme/flwacademy/pix`. |

## Production Notes

Clean Mode is intentionally narrower than FLW Academy. It is best for focused course consumption, not as the complete FLW portal theme. Validate with real course content, especially activities that rely on Moodle's normal activity header, completion display, or course index.
