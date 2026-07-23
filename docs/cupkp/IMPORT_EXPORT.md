# Import and Export

Imports use JSON as the canonical package format. CSV unit artifacts are converted into the canonical package shape before import.

Required import behavior:

- schema validation;
- checksum tracking;
- idempotent re-import;
- transactional writes;
- warnings and errors;
- import batch audit;
- rollback status marker.

Example packages are stored in `local/flwcupkp/fixtures`.
