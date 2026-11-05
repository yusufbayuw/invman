# Evidence retained for the operational UAT

The final report and `latest-ci.json` identify the canonical candidate, tested source SHA, workflow run and artifact. Browser result JSON links screenshots by filename. MySQL concurrency, SQLite JUnit, service probes and recovery evidence are separate from browser results.

Before/after regressions for disabled sessions, stale Work Order state, clipped header actions and PDF actor attribution are retained beside the canonical CI folder. Actual application-generated PDFs were downloaded through normal browser-authenticated sessions, extracted and visually inspected. No PDF content or screenshot was fabricated or edited to show a successful result.

Historical CI attempts are investigation evidence, not current passes. Their run/artifact URLs and reasons are indexed in `historical-ci.json`; the full local investigation folders remain available in this workspace. Canonical evidence and selected before/after files are committed for durable access. GitHub artifacts have finite retention (30 days); use the committed canonical copy after expiry.

Excluded: environment files, passwords, application keys, credentials, cookies, signed QR payloads, database dumps and private runtime archives. The backup/restore JSON contains counts and digests rather than dump contents. A SHA-256 manifest covers the published evidence; hashing detects later changes and does not replace access control or prove production readiness.
