# Backup, restore and rollback runbook

## Scope and executed rehearsal

The operational UAT runner executes a real MySQL dump and import into a separate **new** database, compares row counts and canonical SHA-256 table digests, checks foreign-key orphans, archives private files and verifies their restored hashes. See the latest CI evidence and exact tested revision linked in [the UAT report](OPERATIONAL-UAT-REPORT.md). The published rehearsal is synthetic; inspect its private-file manifest before assuming coverage of real attachments. Database dumps, credentials, cookies, application keys and runtime environment files are deliberately excluded from published evidence.

QA success is not a production recovery rehearsal. Actual server access, backup topology, encryption/key custody, consistency window, off-host retention, restore permissions, RPO and RTO remain unverified.

## Preconditions

- Name the source environment, exact application SHA, database/schema, private-volume root, backup owner and **new, empty** restore target.
- Confirm non-production status before rehearsal; for production, obtain explicit change approval and a controlled write-freeze window. No command below is authorization to access production.
- Verify resolved absolute filesystem paths and DB identity. Abort if the restore target already exists or contains data. Never use DROP DATABASE, migrate:fresh or overwrite an existing volume in the recovery procedure.
- Keep credentials in a mode-0600 MySQL client option file or an approved secret mechanism, not command-line passwords, shell history, repository files or exported logs.
- Stop or quiesce writers, including queue workers/scheduler, at the agreed consistency boundary. `--single-transaction` protects InnoDB snapshots but does not make an independently changing private volume consistent with the database.

## Backup

1. Record release SHA, lockfile hashes, migration status, DB server/version/storage engines, table counts and inventory/reservation/approval integrity checks. Preserve the existing APP_KEY securely; rotating it can make encrypted data unreadable.
2. With writes frozen, dump the named database using a client configuration file. Example for an approved environment:

   ```sh
   mysqldump --defaults-extra-file=/secure/mysql-backup.cnf \
     --single-transaction --routines --events --triggers --hex-blob \
     --no-tablespaces --set-gtid-purged=OFF APPROVED_SOURCE_DB > backup.sql
   ```

3. Archive the private disk from its verified absolute root, preserving relative paths, permissions and metadata. Include all referenced ticket/loan attachments and receipts, not just public images. Keep a file manifest and SHA-256 checksums.
4. Securely retain the matching code artifact, dependency lockfiles and encrypted configuration/key escrow. Encrypt the backup, copy it off-host and verify checksums after transfer. Record completion and restore-test dates; file existence alone is not a successful backup.

## Restore into a fresh target

1. Provision a new non-production schema and private-volume directory with distinct credentials/hostnames and outbound mail/push/payment integrations isolated. Abort on an existing target.
2. Import the dump into that new schema with `mysql --defaults-extra-file=... NEW_EMPTY_DB < backup.sql`. Do not import into the source schema.
3. Extract files into the new verified root. Validate archive paths before extraction for an untrusted archive. Restore ownership and keep private files outside public web access.
4. Configure the matching code and original required encryption key through the secret mechanism. Rebuild environment-specific caches and publish matching frontend/Livewire/Filament assets. Keep background writers stopped until validation finishes.
5. Compare table counts and canonical row digests; examine inventory totals, allocated serials, open/overdue reservations, return receipts, immutable approval/revision/estimate history, user-role/title assignments and workflows. Run anti-joins for foreign-key orphans. Hash-compare every restored private file and confirm referenced attachments exist.
6. Use normal browser login to complete a loan and Work Order with distinct actors. Confirm a damaged asset remains held, private documents require authorization, queue/scheduler work and notifications reach only the isolated destination.
7. Record restore duration against agreed RTO, data timestamp against RPO, evidence paths, exceptions and named acceptance. Tear down only explicitly disposable rehearsal targets after retaining evidence.

## Deployment failure and rollback

- Before writes reopen, choose the reviewed forward fix or restore the **matching code, database and private-volume snapshot together** under the approved recovery plan.
- After new transactions/history exist, avoid code-only rollback to an older approval model. Historical readers may interpret revision/approval gates incorrectly.
- Do not force down migrations that deliberately refuse to remove used revision/estimate history. Do not delete history merely to make rollback succeed.
- If restoration is required, freeze writers again, preserve forensic snapshots and reconcile transactions created after the recovery point with the operational owner. Validate integrity and normal browser flows before reopening.
- Restart supervised workers with the restored/released code, verify the single active scheduler, inspect failed jobs and alerting, and record the incident and recovery decision.

Production rehearsal status: **BLOCKED** until an authorized production-equivalent environment and actual backup/restore controls can be inspected. This audit did not overwrite or delete any real database or storage.
