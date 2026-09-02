# C-UP-KP Architecture

`local_flwcupkp` adds a curriculum-intelligence graph to Moodle while preserving Moodle as the record system for users, courses, activities, grades, completion, and competencies.

Core flow:

```text
Competency -> Use Point -> Knowledge Point -> Learning Object -> Evidence -> Learner State -> Recommendation
```

The graph is many-to-many. KP mastery can support readiness, but UP and competency achievement require direct performance evidence.

Main services:

- `repository`: transactional Moodle DML access.
- `import_service`: idempotent JSON import and validation.
- `mastery_engine`: configurable state calculation.
- `recommendation_engine`: explainable learning-path recommendation generation.
- `audit_service`: coverage, orphan, evidence-gap, and alignment reports.
- external API classes: Moodle web-service surface.
