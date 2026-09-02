# Program 3 Gate C3 - Content + Evidence Mapping Contract

Status: complete

Date: 2026-08-28

Frozen contract:

```text
FLW_CUPKP_CONTENT_EVIDENCE_MAPPING_CONTRACT_V1
```

Implementation service:

```text
local_flwcupkp\local\content_evidence_mapping_contract
```

## Boundary

C3 connects C-UP-KP curriculum targets to Program 1 content identities and
Program 2 source evidence facts. It does not decide mastery, choose adaptive
paths, or scrape raw Moodle logs.

Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
local_flwhistory\local\evidence_source_adapter
```

Raw Moodle logs remain diagnostic-only.

## Stable Identity Rule

Mappings must use stable object IDs and Program 1 identity fields, not human
titles. Accepted identity fields are:

```text
sourcekey
unitid
lessonid
componentid
activityid
assessmentid
questionid
cmid
```

`courseid` and `cmid` are Moodle links. They can help locate a course module,
but they are not a substitute for stable Program 1 identity. Unresolved Program
1 identity facts stay unresolved; the plugin must not fabricate mappings from
titles.

Learning-object metadata now preserves:

```text
program1_identity
content_evidence_mapping_contract
source_history_contract
completion_counts_as_evidence
```

## Pedagogical Roles

C3 freezes four canonical mapping roles:

| Role | Meaning | Can Create Evidence |
| --- | --- | --- |
| `TEACHES` | Introduces or explains content. | No |
| `PRACTICES` | Gives learner practice. Attempts can count; plain completion normally cannot. | Yes |
| `ASSESSES` | Measures target performance or knowledge. | Yes |
| `EVIDENCE_FOR` | Directly declares that the source can produce evidence for the target. | Yes |

Legacy labels such as `lesson`, `practice`, `assessment`, `checkpoint`,
`placement`, `teacher_observation`, and `external_assessment` are normalized to
these canonical roles.

## Evidence Source Types

C3 accepts these source categories:

```text
program2_attempt
grade_linked_assessment
completion
teacher_observation
placement
checkpoint
external_assessment
```

Completion is never equivalent to mastery. Completion evidence is accepted only
for `ASSESSES` and `EVIDENCE_FOR` mappings by default. `PRACTICES` mappings can
accept completion only when the object purpose or metadata explicitly marks
completion as pedagogically valid evidence.

Mapping-level `completion_counts_as_evidence` import flags are stored as
`completion_evidence_map_overrides` inside the learning-object metadata, keyed
by target type and target ID. This keeps C3 deployable without a schema change
while preserving the mapping decision for runtime evidence guards.

## Enforcement Points

C3 is enforced at:

- JSON package validation through `validator::validate_package()`.
- CSV mapping validation through `import_service::validate_csv()`.
- JSON/CSV import writes before `flwcupkp_object_map` storage.
- Admin curriculum object and mapping saves.
- All evidence writes through `evidence_guard::normalize_evidence()`.
- Quiz, activity completion, assignment, H5P, SCORM, STT, and FLW VR Room
  evidence adapters before evidence is recorded.

Stored evidence rubric JSON is augmented with:

```text
cupkp_c3_mapping.contract
cupkp_c3_mapping.history_contract
cupkp_c3_mapping.source_type
cupkp_c3_mapping.completion_is_mastery
cupkp_c3_mapping.content_identity
cupkp_c3_mapping.object_externalid
cupkp_c3_mapping.pedagogical_role
cupkp_c3_mapping.target_type
cupkp_c3_mapping.target_id
```

## Stop Boundary

C3 did not implement:

- evidence quality scoring;
- result-state semantics;
- evidence-policy versions;
- mastery-policy changes;
- adaptive path selection;
- History V1 evidence reprocessing;
- raw Moodle log scraping.

Those belong to later gates, starting with C3B and E1.
