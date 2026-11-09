<?php

namespace Database\Seeders\Support;

use App\Models\AssetRelation;
use App\Models\G001M001Unit;
use App\Models\G002M002ItemType;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\User;
use App\Services\AssetRelationService;
use RuntimeException;

/**
 * Additive demo-only examples of individual/bulk modes and four asset
 * relationship types. Only guarded, transactional demo seeders call this.
 *
 * A bundle is an inventory definition, not an automatically loanable kit.
 */
final class DemoAssetStructure
{
    /** @var array<string, array{0: string, 1: int, 2: string, 3: bool}> */
    private const ITEMS = [
        'BULK' => ['Stok bulk - Kursi susun', 80, 'bulk', true],
        'COMP-PC' => ['Komposit - PC desktop rakitan', 1, 'individual', true],
        'COMP-SSD' => ['Komponen PC - SSD internal', 1, 'individual', false],
        'COMP-RAM' => ['Komponen PC - RAM internal', 1, 'individual', false],
        'BOX' => ['Contained - Lemari perlengkapan', 1, 'individual', false],
        'BOX-MIC' => ['Contained - Mikrofon tersimpan', 1, 'individual', true],
        'INST-RACK' => ['Installed - Rak jaringan tetap', 1, 'individual', false],
        'INST-SWITCH' => ['Installed - Switch pada rak', 1, 'individual', false],
        'BUNDLE' => ['Bundle - Paket dokumentasi (definisi)', 1, 'individual', false],
        'BUNDLE-CAM' => ['Bundle - Kamera anggota paket', 1, 'individual', true],
        'BUNDLE-LIGHT' => ['Bundle - Lampu anggota paket', 1, 'individual', true],
    ];

    /** @var list<array{0: string, 1: string, 2: string}> */
    private const RELATIONS = [
        ['COMP-PC', 'COMP-SSD', 'component'],
        ['COMP-PC', 'COMP-RAM', 'component'],
        ['BOX', 'BOX-MIC', 'contained'],
        ['INST-RACK', 'INST-SWITCH', 'installed'],
        ['BUNDLE', 'BUNDLE-CAM', 'bundle'],
        ['BUNDLE', 'BUNDLE-LIGHT', 'bundle'],
    ];

    /**
     * @return array<string, G002M007Item>
     */
    public function seed(
        string $prefix,
        string $codePrefix,
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        G002M002ItemType $type,
        ?G003M006Room $room,
        User $actor,
    ): array {
        if (! in_array($prefix, ['[DEMO-ORG]', '[DEMO-INVMAN]'], true)
            || ! str_starts_with($unit->name, $prefix.' ')
            || ! str_starts_with($management->name, $prefix.' ')
            || ! str_starts_with($type->name, $prefix.' ')
            || ! str_starts_with($codePrefix, $prefix === '[DEMO-ORG]' ? 'INVMAN-ORG-' : 'INVMAN-DEMO-')
            || ! str_ends_with($codePrefix, '-STRUCT-')
            || ($room && (int) $room->g001_m001_unit_id !== (int) $unit->id)) {
            throw new RuntimeException('Fixture struktur aset harus berada di unit dan pengelola demo yang sesuai.');
        }

        $items = [];
        $instances = [];
        foreach (self::ITEMS as $key => [$label, $quantity, $tracking, $borrowable]) {
            $code = $codePrefix.$key;
            $attributes = [
                'name' => $prefix.' '.$label,
                'g001_m001_unit_id' => $unit->id,
                'g002_m003_item_management_id' => $management->id,
                'g002_m002_item_type_id' => $type->id,
                'g003_m006_room_id' => $room?->id,
                'tracking_mode' => $tracking,
                'quantity' => $quantity,
                'is_borrowable' => $borrowable,
            ];
            $item = G002M007Item::query()->firstOrCreate(['code' => $code], $attributes);
            if (! $item->wasRecentlyCreated) {
                // Never reset inventory, group/room placement, or a user's edits.
                foreach ($attributes as $column => $expected) {
                    $actual = $item->getAttribute($column);
                    $matches = $column === 'is_borrowable'
                        ? (bool) $actual === $expected
                        : (string) $actual === (string) $expected;
                    if (! $matches) {
                        throw new RuntimeException("Aset demo {$code} telah berubah pada {$column}; tidak ditimpa.");
                    }
                }
            }

            $ownedInstances = $item->item_instance()->get();
            if ($tracking === 'bulk') {
                if ($ownedInstances->isNotEmpty()) {
                    throw new RuntimeException("Barang bulk {$code} tidak boleh memiliki Barang Satuan.");
                }
            } else {
                if ($ownedInstances->count() !== 1) {
                    throw new RuntimeException("Barang individual {$code} wajib memiliki satu identitas satuan.");
                }
                $instance = $ownedInstances->first();
                if ((int) $instance->g001_m001_unit_id !== (int) $unit->id
                    || (bool) $instance->is_borrowable !== $borrowable) {
                    throw new RuntimeException("Identitas atau status pinjam Barang Satuan {$code} telah berubah.");
                }
                $instances[$key] = $instance;
            }
            $items[$key] = $item;
        }

        foreach (self::RELATIONS as [$parentKey, $childKey, $relationType]) {
            $parent = $instances[$parentKey];
            $child = $instances[$childKey];
            $existing = AssetRelation::query()
                ->where('parent_instance_id', $parent->id)
                ->where('child_instance_id', $child->id)
                ->first();
            if ($existing) {
                if ($existing->relation_type !== $relationType) {
                    throw new RuntimeException("Relasi demo {$parentKey}/{$childKey} telah diubah; tidak ditimpa.");
                }
                continue;
            }
            // Uses existing permissions, unit/group scope, cycle and custody guards.
            app(AssetRelationService::class)->connect($parent->id, $child->id, $relationType, $actor);
        }

        return $items;
    }
}
