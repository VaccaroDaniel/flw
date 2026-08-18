# local_flwmedia - FLW Media Practice

`local_flwmedia` is the FLW media practice hub for Moodle. It stores media practice metadata, learner progress, and learner attempts for watch, listen, speak, read, and dictation practice while the actual audio/video/media files can remain on an external media server.

Component: `local_flwmedia`

Release: `0.1.0 alpha`

Requires: Moodle 4.5 or later

Status: alpha. Suitable for controlled FLW practice pilots with local review of media URLs and learner attempt storage.

## What This Plugin Does

- Provides learner practice modes: Watch, Listen, Speak, Read, and Dictate.
- Stores media item metadata by course, unit, lesson, language, category, CEFR, and KP tags.
- Stores learner progress per item/mode.
- Stores learner attempts for speaking, reading, and dictation.
- Provides a management page for teachers/admins.
- Provides AJAX web services for loading items and saving progress/attempts.
- Can be embedded into other Moodle content with a small HTML hub placeholder or render helper.

## Main Pages

| Page | Purpose |
| --- | --- |
| `/local/flwmedia/index.php?language=en` | Learner media practice hub. |
| `/local/flwmedia/manage.php?language=en` | Media item management. |
| `/local/flwmedia/seed_sample_data.php?language=en&unitcode=REW2_U001` | Admin-only sample data seeding for testing. |

## Practice Modes

| Mode | Purpose | Data saved |
| --- | --- | --- |
| Watch | Video/media viewing practice | Progress percent, completion, optional score. |
| Listen | Listening practice | Progress percent, completion, optional score. |
| Speak | Oral response practice | Transcript/audio URL/score/feedback as available. |
| Read | Reading aloud or reading response practice | Response/transcript/score/feedback. |
| Dictate | Dictation practice | Learner response, score, feedback. |

## Settings

Open:

`Site administration > Plugins > Local plugins > FLW Media`

| Setting | Meaning |
| --- | --- |
| `mediaserverbase` | Base URL for the external FLW media server. Default: `https://media.example.com/flw`. |
| `defaultperpage` | Default item count per page. Default: `12`. |
| `enablespeak` | Enables speaking practice features. |
| `enableread` | Enables reading practice features. |
| `enabledictate` | Enables dictation practice features. |
| `securemedia` | Reserved for stricter media URL/security behavior. |

## Embedding the Hub

You can place a hub placeholder in Moodle HTML content:

```html
<div class="flwmedia-hub" data-language="en" data-defaultmode="watch"></div>
```

PHP render helper:

```php
echo local_flwmedia_render_hub('en', '', 'watch');
```

Use the direct page for normal learner navigation and embedding when a course page needs a compact media practice block.

## Web Services

| Function | Type | Purpose |
| --- | --- | --- |
| `local_flwmedia_get_items` | Read/AJAX | Load visible practice items by language/filter. |
| `local_flwmedia_save_progress` | Write/AJAX | Save watch/listen/read progress. |
| `local_flwmedia_save_speaking_attempt` | Write/AJAX | Save speaking attempt details. |
| `local_flwmedia_save_reading_attempt` | Write/AJAX | Save reading attempt details. |
| `local_flwmedia_save_dictation_attempt` | Write/AJAX | Save dictation attempt details. |

Keep service access limited to authenticated Moodle sessions or trusted integrations.

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `local/flwmedia:view` | View learner media practice | User, student, teacher, editing teacher, manager |
| `local/flwmedia:manage` | Manage media items | Editing teacher, manager |
| `local/flwmedia:viewreports` | View progress/attempt reports | Teacher, editing teacher, manager |
| `local/flwmedia:seedtestdata` | Seed sample test data | Manager |

## Database

| Table | Purpose |
| --- | --- |
| `local_flwmedia_items` | Media practice records and metadata. |
| `local_flwmedia_categories` | Language-level practice category registry. |
| `local_flwmedia_progress` | Learner progress by item and mode. |
| `local_flwmedia_attempts` | Learner practice attempts and feedback. |

Important item metadata:

- `courseid`
- `unitcode`
- `lessoncode`
- `mode`
- `category`
- `title`
- `mediaurl`
- `posterurl`
- `subtitleurl`
- `transcript`
- `readtext`
- `expectedtext`
- `lang`
- `cefr`
- `kptags`
- `visible`

## Standard Setup

1. Install or upgrade the plugin.
2. Configure `mediaserverbase`.
3. Confirm media URLs are reachable from learner browsers.
4. Add or import media items for the target language/unit.
5. Open `/local/flwmedia/index.php?language=en` as a learner.
6. Complete one item in each enabled mode.
7. Confirm progress and attempts are saved.
8. Open the management/report view as teacher/admin.

## C-UP-KP and Learning Path Use

Media practice can contribute to the FLW learning path when item metadata includes:

- Stable `unitcode`.
- Optional `lessoncode`.
- CEFR level.
- KP tags in `kptags`.
- Practice mode.

For evidence-grade mastery updates, connect media attempt scoring to the C-UP-KP evidence adapter or review workflow before using attempts as confirmed mastery evidence.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No items appear | Language filter, item visibility, category visibility, and course/unit metadata. |
| Media does not play | `mediaurl`, external media server reachability, HTTPS/CORS, and browser console errors. |
| Progress does not save | Login state, web service availability, session key, and AJAX errors. |
| Speaking/read/dictation controls missing | Relevant enable setting and browser microphone permissions. |
| Teacher cannot manage items | `local/flwmedia:manage` role assignment. |

## Production Notes

This plugin stores learner progress and practice attempts. Actual media files can remain outside Moodle, but any transcripts, responses, scores, and feedback saved in Moodle should be treated as learner records. Confirm storage, retention, and privacy rules before broad deployment.
