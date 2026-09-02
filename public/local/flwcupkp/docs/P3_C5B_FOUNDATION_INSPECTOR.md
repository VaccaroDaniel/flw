# Program 3 Gate C5B Foundation Inspector

Status: complete

Date: 2026-08-29

## Purpose

C5B provides a read-only admin page for human validation of the frozen
Foundation V1 before operational curriculum authoring begins.

Admin page:

```text
/local/flwcupkp/foundation.php
```

Example pilot URL:

```text
https://192.168.129.79/local/flwcupkp/foundation.php?courseid=124&unitcode=U038
```

## Shows

- Foundation V1 status and next gate.
- Unresolved blocker/high finding count.
- History V1 boundary and dependency contract statuses.
- Recorded curriculum, relationship, evidence, foundation, and history
  versions.
- Migration readiness checks.
- Non-blocking findings.
- Competency, Use Point, and Knowledge Point rows.
- Relationship and prerequisite graph rows.
- Learning-object content/evidence mappings and Moodle activity links.
- Authoritative implementation classes.
- Allowed read APIs and forbidden adaptive operations.
- Raw Foundation V1 contract JSON for verification.

## Boundary

C5B does not:

- write learner state;
- change mastery policy;
- replay or reprocess History V1 evidence;
- rank or select adaptive paths;
- create learner goals;
- scrape raw Moodle logs as normal learner-intelligence input.

## Next Gate

The next build gate is CM1, the Core C-UP-KP Curriculum Manager.
