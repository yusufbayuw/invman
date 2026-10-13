# October 2026 npm build dependency hardening

## Verified results

InvMan's npm dependency tree was previously flagged by `npm audit` with **8 advisories (5 high, 3 moderate)** after the Laravel 12 update. The project's production dependency audit `npm audit --omit=dev --audit-level=high` passed: these npm dependencies are installed for building assets, not executed as production Node services.

This change adds a targeted npm `overrides` entry to use **`postcss-selector-parser@7.1.6`** everywhere. That version fixes the published selector CPU-exhaustion issue ([GHSA-rj75-hqrm-r3gf](https://github.com/advisories/GHSA-rj75-hqrm-r3gf)), including old transitive versions used by Tailwind 3, PostCSS Nesting and Typography.

The dependency lockfile was regenerated with npm, not hand-edited. A one-time CI job installed the existing Laravel/Filament dependencies, built the updated assets with `npm ci && npm run build`, and separately built a fresh `main` checkout with the original lockfile. It compared the compiled stylesheet **byte-for-byte** and confirmed matching SHA-256 sums (`b7d77ecbec26321a37c3bc70fcb4618c2fb992219a6f8e82401f9d9b60ae49d3`). No application PHP, JavaScript, or CSS source was changed.

After resolution, the audit reports **6 high advisories**, all related to a single upstream `braces@3.0.3` chain (its dependents `micromatch`, `chokidar`, `fast-glob`, `tailwindcss`, and `@tailwindcss/typography`). This total counts dependent packages rather than six independent exploit paths.

## Why the remaining audit is not automatically fixed

- GitHub's current [braces advisory GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) lists no patched `braces` release.
- Filament **3** uses Tailwind CSS **3** for its preset/theme integration. `npm audit fix --force` proposes a **Tailwind 4** major upgrade that is not compatible with existing Filament 3 styling without migrating the associated theming architecture.
- These are **build-time dependencies**, not Node services shipped as application runtime dependencies. The build handles repository-controlled glob patterns and CSS, not untrusted online requests. This lowers practical exposure but does not remove the published advisories.

**Do not** silence npm audit, add fictitious patches, use `--force` on production, or upgrade Tailwind independently of Filament. Keep tracking [issue #21](https://github.com/yusufbayuw/invman/issues/21), verify `npm audit` when new patched versions arrive, and plan the Filament 4+/Tailwind 4 migration separately with screenshot-level UI verification.

## Reproduction

```bash
npm ci
npm audit --omit=dev --audit-level=high
npm audit --audit-level=high
npm run build
```

The second audit continues to return non-zero while the `braces` upstream advisory remains. CI intentionally **flags** the development audit while **blocking** on production Node audit, build failures, Composer advisories, Laravel tests, and MySQL smoke tests. Deployment uses the tested `package-lock.json` with `npm ci`, not `npm update`.
