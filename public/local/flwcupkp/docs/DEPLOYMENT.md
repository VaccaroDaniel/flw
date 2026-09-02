# Deployment

1. Copy `local/flwcupkp` into the Moodle root.
2. Run Moodle upgrade.
3. Assign capabilities to administrator, teacher, and learner roles.
4. Import the pilot fixture with validation enabled.
5. Review coverage and orphan reports.
6. Run `php local/flwcupkp/cli/health_check.php`.
7. Run Moodle competency sync in dry-run mode.
8. Enable scheduled recalculation tasks, including `local_flwcupkp\task\recalculate_states`, `local_flwcupkp\task\sync_competencies`, and `local_flwcupkp\task\calibration_recalculation`.

Current live deployment note:

- Live `local_flwcupkp` version verified as `2026081101`.
- The 2026080700 upgrade creates `flwcupkp_calrecalc` for controlled calibration recalculation run history.
- The 2026081100 upgrade registers CSV import external-service functions and ships `local/flwcupkp/openapi.json`.
- The 2026081101 upgrade exposes audited admin bulk status changes and controlled framework version cloning. Cloned draft framework versions copy only curriculum topology; native Moodle competency links and live Moodle activity links are intentionally cleared until the clone is explicitly linked.
- On the Windows Moodle installer used for verification, CLI upgrade, cache purge, health checks, and scheduled-task execution must run with permissions that can write to `moodledata`.
- Composer dev dependencies were installed in the Moodle root to restore `vendor/bin/phpunit` and `vendor/bin/behat`.
- `config.php` now includes isolated PHPUnit and Behat settings (`phpu_` and `bht_` table prefixes with separate workspace dataroots). These settings do not affect normal live requests unless Moodle is bootstrapped in PHPUnit mode or the Behat test environment marker is enabled.
- PHPUnit and Behat were both initialized and executed successfully after using workspace-based isolated dataroots. After testing, the Behat environment was dropped and disabled, PHPUnit was dropped, and final isolated table counts were `phpu=0` and `bht=0`.
- If the Behat dataroot path contains spaces, Moodle's printed `vendor\bin\behat --config ...` command may fail because the generated path is unquoted. Use the Windows short path to the generated `behat.yml`, or configure a no-space Behat dataroot before initialization.
- After the CSV/privacy/admin hardening patches, Moodle upgrade, CSV validation/import idempotency checks, full plugin PHPUnit, Behat UI/accessibility smoke, isolated test cleanup, and strict live health all completed successfully. The final full plugin PHPUnit result was `17 tests`, `70 assertions`; the final Behat result was `4 scenarios`, `21 steps`; final isolated table counts were `phpu=0` and `bht=0`.

Before enabling Moodle competency write mode:

- run a fresh `health_check.php`;
- confirm `sync_readiness.readyforwrites` is true;
- confirm every C-UP-KP framework has `moodleframeworkid`;
- confirm every C-UP-KP competency has `moodlecompetencyid`;
- export a backup package with `php local/flwcupkp/cli/export_package.php --output=/path/flw-cupkp-export.json`.

Rollback:

- disable external service access;
- disable Moodle competency sync write mode;
- restore curriculum definitions from the latest JSON export if needed;
- mark import batches as rolled back;
- uninstall the plugin only after exporting any needed audit reports.
