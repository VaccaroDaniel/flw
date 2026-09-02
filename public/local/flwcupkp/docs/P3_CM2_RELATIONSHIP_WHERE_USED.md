# Program 3 Gate CM2 Relationship Editor + Where Used

Date: 2026-08-29

Status: complete

## Purpose

CM2 makes curriculum graph maintenance safer. It adds controlled relationship
and prerequisite editing over the frozen Foundation V1 contracts, with
where-used impact previews before substantial changes.

## Runtime Surface

- Service: `local_flwcupkp\local\relationship_where_used_manager`
- Editor page: `/local/flwcupkp/mappings.php`
- Entity impact panel: `/local/flwcupkp/entity.php`

## What CM2 Does

- previews relationship saves before writing;
- previews relationship deletes before writing;
- shows C2 semantic labels such as REQUIRES, SUPPORTS, TRAINS, EVIDENCE_FOR,
  REVIEW_OF, ALTERNATIVE_TO, EXTENDS, and REPLACED_BY;
- validates edited rows through C1B ontology, C2 graph semantics, C3 object
  mapping rules, and C4 lifecycle governance;
- blocks protected object-map deletion when learner evidence already exists;
- records confirmed relationship writes in the audit log;
- shows where-used impact counts for competencies, UPs, KPs, learning objects,
  courses, units, lessons, activities, questions, checkpoints, evidence rows,
  and learner-state references;
- shows coverage governance counts for objects without targets, protected
  object maps, published targets without routes, hard prerequisite edges, and
  replacement edges.

## Boundaries

- History V1 remains the only normal source-history input.
- Foundation V1 remains the authoritative frozen dependency surface.
- CM2 does not scrape raw Moodle logs.
- CM2 does not recalculate mastery.
- CM2 does not change recommendations or adaptive path selection.
- CM2 does not create learner goals.
- CM2 uses cached or aggregated counts for expensive impact signals.

## Admin Workflow

1. Open `/local/flwcupkp/mappings.php`.
2. Filter by framework, course, and unit when needed.
3. Choose a mapping type.
4. Add or edit endpoints and metadata.
5. Click Preview relationship change.
6. Review validation, semantic label, where-used counts, and impacted objects.
7. Confirm only after the preview is valid.

For delete operations, use Preview delete from the existing mappings table. CM2
will show impact first and block deletion of object maps that already have
learner evidence.

## Entity Workflow

Open `/local/flwcupkp/entity.php?type=kp&id=...` or the matching competency,
UP, object, or framework detail page. The entity detail now includes a CM2
where-used impact panel before workflow actions. Deprecation actions carry an
explicit impact acknowledgement from that panel.

## Next Gate

CM3 may add bulk coverage management and governance UI at FLW scale. It must
preserve the CM2 preview/confirm contract and still avoid adaptive policy.
