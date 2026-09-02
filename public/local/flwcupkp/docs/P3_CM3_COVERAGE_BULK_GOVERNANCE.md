# Program 3 Gate CM3 Coverage + Bulk Governance

Date: 2026-08-29

Status: complete

## Purpose

CM3 makes C-UP-KP management practical at FLW scale. It adds a bounded
coverage matrix, governance findings, bulk import/export controls, and import
rollback-request auditing over the frozen Foundation V1, CM1, and CM2 surfaces.

## Runtime Surface

- Service: `local_flwcupkp\local\coverage_bulk_governance_manager`
- Admin page: `/local/flwcupkp/governance.php`
- Home navigation: C-UP-KP Home -> Coverage Governance
- Curriculum navigation: Curriculum Manager -> Coverage Governance

Example U038 URL:

```text
https://main.flw.com/local/flwcupkp/governance.php?courseid=124&unitcode=U038
```

## What CM3 Shows

- CM3 readiness status and dependency status for Foundation V1, CM1, and CM2.
- Scope filters for framework, Moodle course, and unit code.
- Coverage cards for:
  competency coverage, KP teaching coverage, UP practice coverage, UP
  assessment coverage, evidence-quality coverage, and production/interaction
  coverage.
- Findings for:
  orphans, taught-not-assessed, assessed-not-taught, recognition-only
  interaction evidence, missing prerequisites, deprecated references, evidence
  ceilings, and coverage imbalance.
- Lifecycle governance counts across draft, review, approved, published,
  deprecated, and archived states.
- Replacement-edge and impact summaries.
- Recent import batches with validation and rollback status.

## Bulk Import Workflow

1. Open `/local/flwcupkp/governance.php`.
2. Choose JSON or CSV.
3. For CSV, choose Activity mappings or Quiz-KP mappings.
4. Paste package content or provide a safe plugin-relative server file path.
5. Click Preview import.
6. Review validation errors, warnings, row counts, checksum, and duplicate
   status.
7. Click Confirm import only after the preview is valid.

Confirmed imports delegate to the existing `import_service`, so transaction
handling, validation, and duplicate checksum behavior remain centralized.

## Rollback Workflow

Existing import batches record checksum and validation state, but they do not
own row-level import metadata. CM3 therefore does not delete rows as a blind
rollback. Instead it provides:

- rollback preview;
- controlled rollback request;
- `flwcupkp_import.rollbackstatus = rollback_requested`;
- `cm3_import_rollback_requested` audit rows.

This is safe for production because it records the decision and impact without
damaging curriculum rows that may have been edited after import.

## Boundaries

- History V1 remains the only normal source-history input.
- CM3 reads curriculum, mapping, evidence, import, and audit tables through the
  established plugin services and bounded aggregate queries.
- CM3 does not scrape raw Moodle logs.
- CM3 does not recalculate mastery or mutate learner states.
- CM3 does not change recommendation or adaptive path policy.

## Next Gate

CM4 should freeze Management V1 for production consumers using CM1 operational
authoring, CM2 guarded relationship editing, and CM3 bulk coverage governance.
