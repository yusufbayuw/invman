# Inventory identity rollout — 5 November 2026

This patch never rewrites existing item/instance codes. Master edits preserve issued physical instance names and codes. New uncoded masters generate instance codes `ITEM-{master-id}-{sequence}`; coded masters use `{master-code}-{sequence}` and skip already issued labels. Inventory saves and observer counters run in the same transaction. The database unique indexes are the final concurrent-write guard; form validation alone is insufficient.

## Required upgrade procedure

1. Use a consistent backup restored to an isolated clone, including physical-label mappings. No production seeder is part of this patch.
2. Run `php artisan invman:audit-inventory-identities`. This command reads both tables, returns JSON with conflicting codes and row IDs, and exits nonzero on conflict. Grouping uses the database's collation. Null codes remain nullable; duplicate non-null codes (including empty strings) block upgrade.
3. For each conflict, obtain the asset custodian's mapping of physical asset, old code, reservations, checklists, repair tickets and existing QR label. Choose the retained identity; retain a signed reconciliation record and issue replacement labels only when explicitly agreed. Do not delete, merge or automatically renumber rows.
4. Re-run the read-only audit until clean. Stop application writes in the clone, run the migration, compare asset and historical row counts/hashes, and exercise checkout/return and label lookup. Review differences in database collation between SQLite and MySQL. This is a prerequisite to planning a separate production release; this work does not deploy.
5. The migration checks BOTH tables before DDL. Conflicts leave both tables and all labels unchanged. If MySQL commits the first index before an infrastructure interruption, retry recognizes the existing index. The unique-index build rejects writes racing with DDL rather than allowing duplicate committed identities.

`down()` removes only these indexes, preserving data. This is not a rollback of custody or printed labels. Full rollback of unrelated historical migrations is not certified (the SQLite historical named-FK rollback is unsupported); the new migration's own down/up/retry is exercised independently.

## Evidence and limits

`InventoryIdentityIntegrityTest` tests duplicate master/instance rejection, unchanged totals after failed writes, global generation, duplicate-legacy preflight, unchanged labels on conflict and migration retry. `tests/Support/Readiness/inventory-identities.php` runs two real MySQL workers for generated and explicit duplicate codes and verifies persisted counters. The CI job must pass before merge. Existing-data production conflict counts remain BLOCKED until an authorized disposable clone is available. No production code/label is assumed conflict-free.
