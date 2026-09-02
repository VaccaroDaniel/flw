# Program 3 Gate C5 Validation Matrix

Status: complete

Date: 2026-08-28

| Requirement | Implementation | Validation |
| --- | --- | --- |
| Freeze one authoritative C implementation | `canonical_domain_model`, `flwcupkp_comp` | `foundation_v1_contract_test::test_authoritative_implementation_status_has_no_missing_services` |
| Freeze one authoritative KP implementation | `canonical_domain_model`, `flwcupkp_kp` | `foundation_v1_contract_test::test_authoritative_implementation_status_has_no_missing_services` |
| Freeze one authoritative UP implementation | `canonical_domain_model`, `flwcupkp_up` | `foundation_v1_contract_test::test_authoritative_implementation_status_has_no_missing_services` |
| Freeze ontology rules | `ontology_boundary` | Covered through `foundation_status()` dependency checks |
| Freeze relationships and prerequisites | `relationship_graph_contract` | Allowed API and dependency checks |
| Freeze content mappings | `content_evidence_mapping_contract` | Allowed API and migration-readiness checks |
| Freeze evidence mappings, semantics, provenance, and policy | `evidence_semantics_quality_contract`, `evidence_guard` | Version and authoritative implementation checks |
| Freeze lifecycle and versioning | `lifecycle_governance_contract` | Dependency and high-severity blocking test |
| Record curriculum contract version | `foundation_v1_contract::version_record()` | Required-version test |
| Record relationship contract version | `foundation_v1_contract::version_record()` | Required-version test |
| Record evidence policy version | `foundation_v1_contract::version_record()` | Required-version test |
| No unresolved BLOCKER/HIGH defects | `foundation_v1_contract::foundation_status()` | Blocking dependency test |
| Keep History V1 as only normal source-history input | `history_v1_consumer_contract` | Required-version and boundary test |
| Do not add adaptive logic | `foundation_v1_contract::adaptive_api_contract()` | Forbidden-work assertions |

## Severity Rule

Dependency findings are normalized for C5:

- `blocker` becomes `BLOCKER`.
- `error` becomes `HIGH`.
- `warning` becomes `MEDIUM`.
- all other severities become `INFO`.

Foundation V1 status is `blocked` if any `BLOCKER` or `HIGH` finding remains.
`MEDIUM` warnings are reported but do not block the freeze.
