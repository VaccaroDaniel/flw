# Program 3 Gate E1: History Evidence Adapter

Contract: `FLW_CUPKP_HISTORY_EVIDENCE_ADAPTER_V1`

## Purpose

Gate E1 converts trusted Program 2 History V1 facts into derived C-UP-KP
evidence events through the frozen Management V1 mapping surface.

Normal source-history input remains:

```text
FLW_HISTORY_DOWNSTREAM_EVIDENCE_CONTRACT_V1
```

Raw Moodle logs are not scraped by this adapter.

## Conversion Chain

```text
History V1 source fact
-> Program 1 content identity
-> C-UP-KP learning object and object-map
-> C-UP-KP evidence event
```

The first E1 writer path supports:

- `attempts`
- eligible `completion`

Grade versions, placement facts, source events, and content identities remain
available as preserved read-only History V1 facts. They are not collapsed into
mastery evidence by E1.

## Missing Mapping Rule

If a source fact cannot be resolved to a C-UP-KP object and object-map:

- the History V1 source fact remains unchanged;
- no C-UP-KP evidence is fabricated;
- the preview/apply result marks the row as unresolved.

## Reprocessing

Reprocessing has two modes:

- `preview`: read-only; reports planned, existing, unresolved, skipped, and
  rejected rows.
- `apply`: controlled write; creates missing derived evidence rows through
  `mastery_engine::record_evidence()` and writes audit records.

Derived evidence is idempotent. Its `sourceattempt` key includes the History
source key, fact type, object, target, mapping evidence-meaning fingerprint, E1
adapter contract, and C3B evidence policy version.

## Admin Surfaces

Admin page:

```text
/local/flwcupkp/history_evidence.php
```

CLI:

```text
php local/flwcupkp/cli/history_evidence.php --action=preview --courseid=124 --unitcode=U038
php local/flwcupkp/cli/history_evidence.php --action=apply --courseid=124 --unitcode=U038 --confirm=1
```

Web services:

```text
local_flwcupkp_get_history_evidence_status
local_flwcupkp_preview_history_evidence_reprocess
local_flwcupkp_apply_history_evidence_reprocess
```

## Handoff

E1 does not add adaptive policy, change mastery thresholds, mutate History V1,
or match content by title.

Next gate:

```text
E2 - Mastery + Confidence + Current Learner State
```
