# InvMan: Asset Tracking, Relations and Shield Access (2026-11-05)

## Asset lifecycle

The existing `Barang` and `Barang Satuan` resources are retained. Existing rows use `tracking_mode=individual` by default, and the old reservations and QR/checklists still identify their physical units.

New `Barang.tracking_mode` supports:
- **individual**: physical instances are created with their own codes, condition, history and allocation.
- **bulk**: the initial quantity is a physical stock balance and DOES NOT create fake physical instances. Audited changes use the **Koreksi Stok Bulk** action; concurrent reservations continue to use the half-open time-window stock availability engine.

Bulk checkout and return capture one quantity-level condition record per reservation. They still require proper two-party confirmation. A damaged return blocks further loans and creates an item-level damage ticket when the category is configured. Manual stock changes reject outstanding reservations and cannot create a negative physical balance.

The `Barang Satuan` detail screen exposes **Hubungan dan Komponen Aset**. Asset relations:
- `component`: a physical component of its parent (child must not be independently borrowable);
- `contained`: an asset placed inside another asset; original inventory identity retained;
- `installed`: equipment installed in another asset (child must not be independently borrowable);
- `bundle`: membership of a declared kit.

A relationship checks access to the parent and child, cross-unit/group scope, duplicate installation and cycles. A kit parent must not be borrowable: **atomic reservation/checkout of a kit and its members is not implemented**. Do not advertise a kit as reservable or falsely count its constituent assets as reserved. Normal room placement remains a separate relation.

### Ruangan Tanpa Unit (NOT Ruangan Bersama)

`g003_m006_rooms.g001_m001_unit_id` already exists and is nullable. Null means **Ruangan Tanpa Unit** (room without assigned organizational unit), **not** a universally available/shared room. Facility manages this room's master data and reservation decisions. An optional legacy management-group link does not grant the group approval authority for no-unit rooms.

A unit-owned item may be physically located in a room assigned to its own unit or in a no-unit room, **without changing the catalog unit ownership**. Sarpras users cannot place assets into another unit's room. Room loanability continues to depend on explicit `is_borrowable` and existing reservation rules, never on the nullable unit field. On the room form, group manager selection is optional for no-unit rooms only.

## Access model

- `admin`: super-admin existing Shield Gate.
- `fasilitas`: Staff, Supervisor and Manager; master catalog/physical-unit and location/vehicle resources are available through Shield permissions; Work Order-specific titles/workflows remain separate.
- `pengelola_barang`: only `Barang` and `Barang Satuan`; rows are restricted by `itemManagements` memberships, including list, search and guarded save.
- `sarpras`: may view/create/update **Barang** and **Barang Satuan** only when the catalog owner is their own non-null unit. List, global search, record policy and form writes are scoped to the authenticated unit; even a second `pengelola_barang` role does not expand Sarpras beyond this unit.

The asset-manager role is provisioned on existing group users by role migration/RoleSeeder and when a group is managed through the Filament resource; API / direct pivot writes should also explicitly assign the role. Group membership is *never* a grant for unrelated master modules.

**Important:** Don't run `RoleSeeder` to force-reset permissions. It now bootstraps only roles without permissions. The one-time migration grants additional master permissions on an existing database while preserving current role grants. Future Shield UI edits remain authoritative.

## Deployment and rollout

1. Back up application, current database and audit tables.
2. Review PR and run its Quality Guard + Operational UAT. Do not merge on a green status alone: inspect branch-specific MySQL/SQLite security and transaction tests.
3. Run `php artisan migrate --force` only after CI; validate that legacy items receive the `individual` default and that no old item instance/reservation count changes.
4. For old installs, confirm Facility, Sarpras and asset-manager roles/permissions; verify Sarpras SD cannot read/edit SMP assets or forge foreign units, and that IT/Perpustakaan users are in assigned groups.
5. Manually test a bulk loan with two-person checkout, on-time/damaged return, and concurrent booking; test individual and cross-group URL/payload denial.
6. New bundle definitions do not support whole-kit loan and must remain non-borrowable pending a dedicated atomic bundle allocation feature.

## Known deliberate boundaries

- No automatic legacy-to-bulk conversion: changing a tracking mode with physical instances or reservation history is rejected.
- No automatic composite repair/availability propagation for parent components; do not treat a graph relation alone as a maintenance safety guarantee.
- No new consumable-stock ledger; bulk remains returnable stock. Consumables need an additional issue/consumption workflow.
