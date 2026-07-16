# FLW Media local plugin

`local_flwmedia` provides a Moodle-hosted, language-specific practice hub for FLW Watch, Listen, Speak, Read, and Dictate activities. Moodle stores metadata, permissions, progress, and attempts. The actual video/audio files stay on the external secured FLW media server and are referenced by URL.

## Install

1. Copy the plugin to `local/flwmedia`.
2. Run `php admin/cli/upgrade.php`.
3. Run `php admin/cli/purge_caches.php`.
4. Visit `Site administration > Plugins > Local plugins > FLW Media`.
5. Set the media server base URL.
6. Open the Practice page:

```text
/local/flwmedia/index.php?language=en
```

7. Or add a hub to a Moodle Page, Label, Book, or FLW Practice HTML area:

```html
<div class="flwmedia-hub"
     data-language="en"
     data-defaultmode="watch">
</div>
```

The plugin queues a lightweight AMD initializer from the `core\hook\output\before_footer_html_generation` hook, so the hub auto-detects `.flwmedia-hub` containers without theme changes.

PHP render helper:

```php
echo local_flwmedia_render_hub('en', '', 'watch');
```

## Manage Practice

Users with `local/flwmedia:manage` can open:

```text
/local/flwmedia/manage.php?language=en
```

The management page supports language-level categories and entries. `unitcode` remains available as optional metadata on entries, but learner Practice links use language only.

## Sample Data

For development/testing only, a manager can seed sample records:

```text
/local/flwmedia/seed_sample_data.php?language=en&unitcode=REW2_U001
```

The sample media URLs are for development testing only. Production FLW courses must use the secured FLW media server. Replace all sample media URLs with real media server paths.

Production examples:

```text
https://media.yourdomain.com/flw/real/unit001/watch/video.mp4
https://media.yourdomain.com/flw/real/unit001/audio/dialogue_01.mp3
https://media.yourdomain.com/flw/real/unit001/captions/video.vtt
```

## Architecture Notes

- Media files are not uploaded to Moodle file storage.
- `local_flwmedia_items` stores metadata and external media URLs. Practice is selected by `lang`; `courseid` and `unitcode` are not learner URL parameters.
- `local_flwmedia_progress` stores learner watch/listen/read/practice progress.
- `local_flwmedia_attempts` stores Speak, Read, and Dictate attempt metadata.
- Pagination defaults to 12 records and caps web-service page size at 48.
- Cards use `data-src` and lazy loading so media is not loaded until visible or played.
- Future signed URL support can store a logical media path, ask a signing service for a short-lived URL after Moodle permission checks, and let the media server stream the file directly.

## Practice Modes

- Watch: video gallery with MP4/HLS-ready external URLs, poster images, optional captions, and 90 percent/ended completion.
- Listen: audio gallery with optional transcript and 90 percent/ended completion.
- Speak: prompt card with browser MediaRecorder controls. V1 stores attempt metadata only; audio upload can be added later through a safe Moodle File API or external media-server upload flow.
- Read: reading card with optional model audio, short response, and mark-as-read completion.
- Dictate: audio card with typed response, exact/normalized/word-overlap checking, attempt save, and score.

## Capabilities

- `local/flwmedia:view`: student, teacher, editingteacher, manager.
- `local/flwmedia:manage`: editingteacher, manager.
- `local/flwmedia:viewreports`: teacher, editingteacher, manager.
- `local/flwmedia:seedtestdata`: manager.

## Test Checklist

- Student can see Watch, Listen, Speak, Read, and Dictate tabs.
- Student can play video/audio.
- Speak recording controls work in a browser that supports MediaRecorder.
- Read can be marked complete.
- Dictate checks and saves a score.
- Progress saves through AJAX.
- Pagination, search, and category filtering work.
- Teacher/admin functions are not shown in the hub.
- Manager can seed sample data.
- Mobile layout uses one column and controls fit the screen.
- Page loads only the first page of records and lazy loads media.
