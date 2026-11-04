<?php
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Support\Facades\DB;

define('INVMAN_AUTHORITY_FIXTURES_ONLY', true);
require __DIR__.'/authority-concurrency.php';

if (($argv[1] ?? '') === 'counter-worker') {
    $p = json_decode(file_get_contents($argv[2]), true);
    DB::transaction(function () use ($p): void {
        G002M015ItemInstance::where('g002_m007_item_id', $p['item'])->count();
        barrier($p['prefix']);
        G002M015ItemInstance::findOrFail($p['instance'])->update(['is_available' => true]);
    });
    echo json_encode(['committed' => true]);
    exit;
}
$unit = G001M001Unit::create(['name' => 'QA counter snapshot '.bin2hex(random_bytes(4))]);
$item = G002M007Item::create(['name' => 'Counter snapshot', 'quantity' => 2, 'is_borrowable' => true, 'g001_m001_unit_id' => $unit->id]);
$instances = $item->item_instance()->orderBy('id')->get();
foreach ($instances as $instance) $instance->update(['is_available' => false]);
$prefix = $dir.'/counter-snapshot';
file_put_contents($prefix.'.json', json_encode(['item' => $item->id, 'instance' => $instances[1]->id, 'prefix' => $prefix]));
$worker = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'counter-worker', $prefix.'.json'], [1 => ['file', $prefix.'.out', 'w'], 2 => ['file', $prefix.'.err', 'w']], $pipes);
waitFile($prefix.'.ready');
$instances[0]->update(['is_available' => true]);
file_put_contents($prefix.'.release', 'go');
$code = proc_close($worker);
$item->refresh();
$result = ['interleaving' => 'B opens older repeatable-read snapshot; A makes instance A available and commits; B makes instance B available in its older transaction',
    'worker_exit' => $code, 'quantity' => (int) $item->quantity, 'available_quantity' => (int) $item->available_quantity,
    'actual_ready_instances' => $item->item_instance()->where('is_available', true)->where('is_borrowable', true)->count()];
$result['pass'] = $code === 0 && $result['quantity'] === 2 && $result['available_quantity'] === 2 && $result['actual_ready_instances'] === 2;
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit($result['pass'] ? 0 : 1);
