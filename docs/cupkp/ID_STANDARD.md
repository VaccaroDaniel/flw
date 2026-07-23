# ID Standard

Competency:

```text
FLW-{COURSE}-{CEFR}-C-{NUMBER}
```

Use Point:

```text
FLW-{COURSE}-{CEFR}-UP-{UNIT}-{NUMBER}
```

Knowledge Point:

```text
FLW-{LANGUAGE}-{CEFR}-{DOMAIN}-{NUMBER}
```

Rules:

- IDs are externally stable.
- Titles and descriptions can change without changing IDs.
- Unit-derived IDs should include course, level, unit, and sequence.
- Imports must reject duplicate external IDs inside the same entity type unless the import is an idempotent update.
