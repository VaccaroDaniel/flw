# Deployment

1. Copy `local/flwcupkp` into the Moodle root.
2. Run Moodle upgrade.
3. Assign capabilities to administrator, teacher, and learner roles.
4. Import the pilot fixture with validation enabled.
5. Review coverage and orphan reports.
6. Run `php local/flwcupkp/cli/health_check.php`.
7. Run Moodle competency sync in dry-run mode.
8. Enable scheduled recalculation tasks.

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
