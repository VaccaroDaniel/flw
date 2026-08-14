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

## Compose Learner-Ready Lesson Content

The first composer template updates CKLA Grade 2 Unit 2 Lesson 1 placeholders with learner-ready Page and Assignment wording while keeping modules hidden unless `--visible` is supplied.

```powershell
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json" --compose-lesson --section=1
```

Current Lesson 1 composed modules:

- `Lesson 1: Magic e, Tricky Words, and Mike's Bedtime`
- `Lesson 1.1 Family Spelling Practice`
- `Lesson 1.2 a_e and i_e Sentence Practice`
- `Lesson 1.3 Magic e Word Builder`
- `Lesson 1.4 Mike's Bedtime Reading Check`

## Preview And Publish Lesson 1

The review dashboard includes preview links for the five Lesson 1 modules and two protected actions:

- `Publish only Lesson 1 for students` makes the course visible and publishes only Lesson 1's composed Page/Assignment modules.
- `Return Lesson 1 to review` hides Lesson 1 again and hides the course shell when no generated modules remain visible.

CLI equivalents:

```powershell
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json" --publish-lesson --section=1
& "D:\Dev\MoodleWindowsInstaller-latest-501\server\php\php.exe" "D:\Dev\MoodleWindowsInstaller-latest-501\server\moodle\public\local\flwtextbookimport\cli\import_course.php" --input="C:\Users\com\Documents\Estimation Speaking\flw-moodle-importer-pilot\output\moodle_dry_run\ckla_g2_u2_moodle_dry_run.json" --unpublish-lesson --section=1
```

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
- Lesson 1 learner-ready modules: 5
- Lesson 1 visible modules: 0
- Publish switch: Lesson 1 only
