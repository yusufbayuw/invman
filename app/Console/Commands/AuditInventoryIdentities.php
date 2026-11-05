<?php
namespace App\Console\Commands;

use App\Services\InventoryIdentityService;
use Illuminate\Console\Command;

final class AuditInventoryIdentities extends Command
{
    protected $signature = 'invman:audit-inventory-identities';
    protected $description = 'Pemeriksaan read-only duplikasi kode inventaris sebelum migration unique index.';

    public function handle(InventoryIdentityService $identities): int
    {
        $conflicts = $identities->conflicts();
        $this->line(json_encode(['read_only' => true, 'conflicts' => $conflicts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $conflicts === [] ? self::SUCCESS : self::FAILURE;
    }
}
