# local_flwkp - FLW Knowledge Point Registry

`local_flwkp` is the Moodle-side curriculum graph for FLW. It stores languages, CEFR/equivalent levels, units, domains, atomic knowledge points, prerequisites, and mappings from Moodle items to knowledge points.

Component: `local_flwkp`

Release: `0.1.0 alpha`

Requires: Moodle 5.1 or later

Status: alpha curriculum registry. It is mostly a backend/data plugin, not a learner-facing UI.

## What This Plugin Does

- Defines the FLW curriculum spine by language and level.
- Stores unit records and learner-facing unit outcomes.
- Stores knowledge point records with domain, threshold, description, and outcome.
- Stores prerequisite relationships between knowledge points.
- Stores mappings from Moodle activities/questions/resources to knowledge points.
- Provides small read/write helper methods for other FLW plugins.

Use this plugin when a Moodle object needs to answer questions like:

- Which KPs belong to this unit?
- Which Moodle quiz question or activity demonstrates this KP?
- What prerequisite KPs should be checked before recommending this item?

## Current Scope

This plugin is the first Moodle-side curriculum brain for FLW. It is intentionally small in the current alpha. It does not yet provide a full visual curriculum editor. Admins normally manage higher-level operational workflows through C-UP-KP setup/evaluation pages, placement, exam, or importer tooling, while this plugin stores the shared KP reference layer.

## Seeded Curriculum

The install script seeds an English MVP curriculum:

- Language: English.
- CEFR levels: A1 through C2.
- Domains: `VOC`, `GRA`, `REA`, `LIS`, `SPK`, `WRI`, `PRO`, `FUN`, `STU`, `EXA`.
- Example unit: `EN-A1-U01`.
- Example knowledge points for the initial English A1 unit.

The seed data is useful for smoke testing. Production FLW courses should import or define complete unit/KP packages through the operational C-UP-KP workflow.

## Data Model

| Table | Purpose |
| --- | --- |
| `local_flwkp_languages` | Supported languages and curriculum framework name. |
| `local_flwkp_levels` | CEFR or equivalent levels per language. |
| `local_flwkp_units` | Course units inside a level. |
| `local_flwkp_domains` | Skill and knowledge domains used by FLW. |
| `local_flwkp_points` | Atomic knowledge point records. |
| `local_flwkp_prereqs` | Prerequisite relationships between KPs. |
| `local_flwkp_mappings` | Links from Moodle components/items to KP records. |

## Important Fields

### Units

| Field | Meaning |
| --- | --- |
| `code` | Stable unit code, for example `EN-A1-U01`. |
| `name` | Human-readable unit title. |
| `canstatement` | Unit-level learner outcome. |
| `estimatedhours` | Expected learning time. |
| `sortorder` | Display/order position. |

### Knowledge Points

| Field | Meaning |
| --- | --- |
| `code` | Stable KP code. |
| `domainid` | Domain such as vocabulary, grammar, reading, or speaking. |
| `name` | Short KP label. |
| `description` | Teacher/developer description. |
| `outcome` | Learner achievement outcome. |
| `masterythreshold` | Score threshold for mastery, default `80`. |

### Mappings

| Field | Meaning |
| --- | --- |
| `pointid` | Target KP. |
| `component` | Moodle component such as `mod_quiz`. |
| `itemtype` | Item type such as `question`, `activity`, or `resource`. |
| `itemid` | Moodle object ID. |
| `weight` | Contribution weight for evidence calculations. |

## Helper API

Class:

`local_flwkp\local\curriculum`

Available helper methods:

| Method | Purpose |
| --- | --- |
| `get_points_for_unit($unitcode)` | Return all KPs for a unit with joined domain/unit/level/language data. |
| `get_point_by_code($code)` | Return one KP by stable code. |
| `add_mapping($pointcode, $component, $itemtype, $itemid, $weight)` | Link a Moodle item to a KP. |

Example:

```php
$points = \local_flwkp\local\curriculum::get_points_for_unit('EN-A1-U01');
```

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `local/flwkp:view` | View curriculum/KP data | Authenticated user |
| `local/flwkp:manage` | Manage curriculum/KP data | Manager |

The plugin currently exposes the data layer and helper class more than a full admin UI. Capability checks should still be used by any page or service that reads or writes KP registry data.

## Integration Points

- `local_flwcupkp`: Uses imported KP/UP/competency relationships and Moodle activity/question mappings for mastery state.
- `local_flwplacement`: Can use KP and unit data to recommend starting points and learning paths.
- `local_flwexam`: Can use critical KP outcomes for exam readiness and certification gates.
- `local_flwtextbookimport`: Can tag imported textbook activities with KP metadata.
- `mod_flwvrroom` and `mod_flwaispeaking`: Can attach KP codes to experiential and speaking activities.

## Recommended Operating Pattern

1. Keep KP codes stable. Do not rename codes after learner evidence has been collected.
2. Treat `local_flwkp_points.code` as the integration key.
3. Use mappings instead of hardcoding activity/question IDs in external systems.
4. Put display names and teacher descriptions in Moodle, but keep canonical curriculum package files under version control.
5. Let C-UP-KP manage operational activation, evidence repair, thresholds, and learner state.

## Testing Checklist

1. Install or upgrade the plugin.
2. Confirm the seed tables are created.
3. Confirm `EN-A1-U01` returns records through `get_points_for_unit`.
4. Add a test mapping to a Moodle question or activity.
5. Confirm the mapping appears in `local_flwkp_mappings`.
6. Confirm downstream C-UP-KP/evaluation pages can resolve the KP code.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| A unit has no KPs | Confirm the unit code matches exactly and exists in `local_flwkp_units`. |
| A KP cannot be mapped | Confirm the KP code exists in `local_flwkp_points`. |
| Evidence does not roll up | Confirm the Moodle item mapping points to the correct `component`, `itemtype`, and `itemid`. |
| Duplicate curriculum records | Confirm imports use stable codes and do not recreate already-seeded units. |

## Production Notes

The curriculum graph is foundational data. Before production use, define ownership for KP code naming, import review, versioning, and deprecation. Avoid deleting KPs that have learner evidence; deactivate or supersede them in the operational layer instead.
