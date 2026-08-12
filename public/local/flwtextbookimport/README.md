# FLW Textbook Importer

`local_flwtextbookimport` imports reviewed textbook conversion packages into FLW Moodle.

The pilot implementation consumes the CKLA dry-run JSON, creates the hidden Moodle course shell, imports reviewed Page/Assignment placeholders, and keeps all activity review metadata in Moodle for teacher and FLW handoff review.

## Dry Run

```powershell
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json"
```

## Execute Course Shell Import

The course is created hidden unless `--visible` is provided.

```powershell
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json" --execute
```

If the course already exists, add `--reuse-course` to refresh the course fields and section summaries.

## Execute Lesson Activity Import

The phase-2 importer creates hidden Page/Assignment modules only. Reader candidates, files, and quizzes stay in the review package.

```powershell
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json" --create-activities --section=1 --types=page,assign --review-statuses=needs_teacher_review,needs_activity_review
```

Use `--reuse-modules` for reruns. New modules are hidden unless `--visible` is provided.

To import or refresh every CKLA pilot lesson section:

```powershell
$php = "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe"
$cli = "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php"
$input = "C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json"
foreach ($section in 1..16) {
    & $php $cli --input="$input" --create-activities --section=$section --types=page,assign --review-statuses=needs_teacher_review,needs_activity_review --reuse-modules
}
```

## Review Dashboard

Open `/local/flwtextbookimport/index.php` as a site admin to sync the dry-run package into the review table and edit approval, CEFR, skill, KP tags, and notes for each planned activity.

The generated course navigation also includes a `Textbook import review` link when review rows exist for that course.

## FLW Handoff

- The course is created under `FLW / English / K-12 / Grade 2`.
- Review rows store editable `cefr`, `skill`, and `kptags` metadata for later `local_flwcupkp` mapping.
- The dashboard links to the generated course and to the FLW C-UP-KP manager.
- Imported modules remain hidden until teacher review is complete.

## Current Import Boundary

- Creates missing category path from package metadata.
- Creates or updates the target Moodle course shell.
- Creates missing topic sections.
- Writes section summaries with source page ranges and activity review counts.
- Creates reviewed Page and Assignment placeholders section-by-section.
- Stores review metadata in `{flwtbi_review}`.
- Does not create File, Quiz, H5P, or reader-candidate activities yet.

## Safety

- Default mode is read-only dry-run.
- `--execute` is required for Moodle writes.
- Existing courses are not modified unless `--reuse-course` is supplied.
- Existing generated activities are not skipped unless `--reuse-modules` is supplied.
- Courses are hidden by default.
- Activities are hidden by default.

## CKLA Pilot Smoke Result

- Installed component: `local_flwtextbookimport`
- Created course: `FLW-EN-G2-CKLA-U2`
- Course ID: 175
- Visibility: hidden
- Sections: 17
- Imported hidden modules: 62
- Page modules: 16
- Assignment modules: 46
- Review rows: 112
- Approved review rows: 62
- KP metadata rows: 112
