# FLW Academy v1.0 Clean Release

Date: 2026-08-14

This baseline stabilizes the current FLW Academy Moodle build after the home/dashboard/theme, Exam, Placement, KP, K-12, course view, SCORM, and dictionary iterations.

## Release Checks

- FLW PHP lint: passed for 225 PHP files.
- FLW XMLDB install schemas: parsed successfully.
- Moodle database schema: `Database structure is ok.`
- FLW KP health check: `status: ok`, `readyforwrites: true`, no integrity errors.
- Moodle cache purge: completed.
- Moodle upgrade: completed successfully from the updated FLW plugin versions.

## Stabilization Fixes Included

- K-12 hero title restored as `School courses by institution level.`
- K-12 multilingual category descriptions now show only the selected FLW learning language.
- VR Room schema migration added for legacy field drift:
  - migrates old `knowledgepoints` data into `kpcodes`
  - normalizes CEFR/scenario values
  - removes stale legacy attempt columns after preserving completion/time data
  - aligns defaults, field lengths, and indexes with `install.xml`
- Exam session schema migration added:
  - updates `sessiontype` default to `teacher`
  - safely drops and restores the dependent session index during the default change

## Backup

A focused JSON backup of the tables touched by the schema migration was created before upgrade:

`D:/Dev/MoodleWindowsInstaller-latest-501/server/moodle/plugin_backups/flw_academy_v1_schema_backup_20260814_063015.json`

## Known Environment Note

Moodle's broad admin check still reports a non-FLW scheduled-task issue:

- `\core\task\h5p_get_content_types_task` has a max fail delay.
- Cron had not run recently before this stabilization pass.

This is a core H5P/content-type sync environment issue, not an FLW code/schema failure.
