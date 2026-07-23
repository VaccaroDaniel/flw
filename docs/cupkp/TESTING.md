# Testing

Test coverage targets:

- XMLDB schema installation.
- Stable ID uniqueness.
- Mapping integrity.
- Mandatory prerequisite cycle rejection.
- Import validation and idempotency.
- Evidence normalization.
- KP, UP, and competency state separation.
- Competency direct-evidence requirement.
- Recommendation prioritization.
- Capability checks.

Run from a Moodle root after installing the plugin:

```bash
php admin/cli/upgrade.php
vendor/bin/phpunit local_flwcupkp_testsuite
```
