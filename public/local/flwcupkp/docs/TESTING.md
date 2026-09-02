# Testing

Test coverage targets:

- XMLDB schema installation.
- Stable ID uniqueness.
- Mapping integrity.
- Mandatory prerequisite cycle rejection.
- Import validation and idempotency.
- CSV activity mapping and quiz-KP mapping import idempotency.
- Evidence normalization.
- KP, UP, and competency state separation.
- Competency direct-evidence requirement.
- Recommendation prioritization.
- Capability checks.
- External service write rate limiting.
- Calibration proposal simulation, queued recalculation, run history, and scheduled-task processing.
- Specialized assignment, H5P, SCORM, and trusted STT adapter registration.
- Traceability and curriculum graph rendering.
- Privacy API export/delete/user-list coverage.

Run from a Moodle root after installing the plugin:

```bash
php admin/cli/upgrade.php
php vendor/bin/phpunit --configuration phpunit.xml.dist public/local/flwcupkp/tests/mastery_engine_test.php ...
php local/flwcupkp/cli/health_check.php --strict
php admin/cli/scheduled_task.php --execute='\\local_flwcupkp\\task\\calibration_recalculation'
```

Current workspace verification:

- `php -l` passed for all PHP files under `local/flwcupkp`.
- JSON fixtures, JSON templates, JSON schema, and `db/install.xml` parse successfully.
- U037 and U038 fixture cross-references resolve across competencies, Use Points, Knowledge Points, prerequisites, learning objects, and activity mappings.
- Source PHPUnit coverage includes `calibration_recalculation_test.php` for queued calibrated recalculation and `specialized_evidence_adapter_test.php` for trusted STT evidence without raw audio storage.
- Source Behat coverage includes `tests/behat/admin_pages.feature` and `behat_local_flwcupkp.php` for the admin landing page, curriculum relationship view, traceability report, calibration page, curriculum-page Axe accessibility, and keyboard focus navigation.

Current live Moodle smoke verification:

- Live plugin version: `2026081101`.
- Strict health check: `status: ok`, no warnings, 100% Comp->UP, UP->KP, KP->activity, and direct competency evidence coverage.
- CSV validation passed for both shipped templates:
  - `local/flwcupkp/templates/activity_cupkp_mapping.csv`
  - `local/flwcupkp/templates/quiz_kp_mapping.csv`
- CSV import wrote live import batches `3` and `4`; repeat imports returned `already_imported` with the same import IDs.
- `flwcupkp_calrecalc` contains a completed queued recalculation smoke run.
- The calibration recalculation scheduled task executes successfully.
- Traceability and curriculum pages render the expected U038 graph/evidence/state markers.
- Expanded external API methods return live data under an admin Moodle session.
- Assignment, H5P, and SCORM event classes plus observer callbacks are present in the installed Moodle code.
- Composer dev dependencies were restored in the Moodle root with `composer install --no-interaction --prefer-dist`; `vendor/bin/phpunit` and `vendor/bin/behat` now exist.
- Moodle `config.php` was given isolated PHPUnit settings: `phpunit_prefix = phpu_`, `phpunit_dataroot = C:\Users\com\Documents\Estimation Speaking\moodledata_phpunit`. A timestamped backup was created before editing.
- Moodle `config.php` was given isolated Behat settings: `behat_wwwroot = http://192.168.129.79`, `behat_prefix = bht_`, `behat_dataroot = C:\Users\com\Documents\Estimation Speaking\moodledata_behat`, and `behat_faildump_path = C:\Users\com\Documents\Estimation Speaking\moodledata_behat_faildump`.

Automated Moodle tests executed in the Windows installer:

- PHPUnit was initialized successfully after moving the isolated dataroot out of the Moodle installer tree. After CSV import, expanded privacy coverage, bulk curriculum/version-clone coverage, and Unit Control Packet alias import coverage were added, the full explicit `local_flwcupkp` PHPUnit file set passed: `17 tests`, `70 assertions`. The run emitted only unrelated Moodle deprecation notices from `mod_contentdesigner`.
- The privacy-provider hardening test now verifies expanded privacy deletion/anonymization and PostgreSQL-safe context ID comparison.
- Behat was initialized with isolated `bht_` tables. The plugin context uses Moodle's standard `component > page type` page syntax. The browser-backed plugin feature passed `4 scenarios` and `21 steps` in `0m29.01s` using ChromeDriver with Axe enabled, including the admin Bulk operations section, scoped main-region accessibility, and keyboard navigation from Search to Filter.
- To rerun the `@javascript @accessibility` scenario locally, start Apache and provide a browser WebDriver at `http://localhost:4444/wd/hub`. The final run used a temporary matching ChromeDriver, which was removed after the test cleanup.
- The successful Behat rerun used Moodle's `--disable-composer` initialization option because dev dependencies were already installed and a Composer self-update attempt had hit a transient network reset.
- After testing, `admin/tool/behat/cli/util_single_run.php --drop`, `admin/tool/behat/cli/util_single_run.php --disable`, and `admin/tool/phpunit/cli/util.php --drop` were run. Final isolated table counts were `phpu=0` and `bht=0`.
- Final strict live health after cleanup returned `status: ok`, no warnings or errors, sync write readiness true, and 100% Comp->UP, UP->KP, KP->activity, and direct competency evidence coverage.
