<?php

use App\Models\{G002M007Item, G002M015ItemInstance};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || getenv('INVMAN_FINAL_QA') !== '1'
    || DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'invman_ci') {
    throw new RuntimeException('Only isolated opt-in invman_ci MySQL testing is permitted.');
}
if (($argv[1] ?? '') === 'worker') {
    $data = json_decode(file_get_contents($argv[2]), true);
    file_put_contents($data['ready'], 'ready');
    $deadline = microtime(true) + 30;
    while (! is_file($data['go'])) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Barrier timeout'); }
        usleep(10000);
    }
    try {
        G002M015ItemInstance::create(['g002_m007_item_id' => $data['item'], 'code' => $data['code']]);
        echo json_encode(['outcome' => 'committed']);
    } catch (QueryException $e) {
        if (($e->errorInfo[1] ?? null) !== 1062) { throw $e; }
        echo json_encode(['outcome' => 'duplicate_rejected']);
    }
    exit;
}
$tag = 'QA-IDENTITY-'.bin2hex(random_bytes(6));
$runtime = storage_path('framework/testing/'.$tag);
mkdir($runtime, 0700, true);
$race = function (array $payloads) use ($runtime): array {
    $prefix = $runtime.'/'.bin2hex(random_bytes(4));
    $workers = [];
    foreach ($payloads as $index => $payload) {
        $payload += ['ready' => $prefix.'-'.$index.'.ready', 'go' => $prefix.'.go'];
        $file = $prefix.'-'.$index.'.json';
        file_put_contents($file, json_encode($payload));
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $file], [1 => ['pipe', 'w'], 2 => ['file', $prefix.'-'.$index.'.err', 'w']], $pipes);
        $workers[] = [$process, $pipes[1], $payload['ready']];
    }
    $deadline = microtime(true) + 30;
    foreach ($workers as [, , $ready]) {
        while (! is_file($ready)) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Worker startup timeout'); }
            usleep(10000);
        }
    }
    file_put_contents($prefix.'.go', 'go');
    $results = [];
    foreach ($workers as [$process, $pipe]) {
        $result = json_decode(stream_get_contents($pipe), true);
        fclose($pipe);
        if (proc_close($process) !== 0 || ! $result) { throw new RuntimeException('Worker failed'); }
        $results[] = $result['outcome'];
    }
    sort($results);
    return $results;
};
$first = G002M007Item::create(['name' => $tag, 'code' => $tag, 'quantity' => 0, 'is_borrowable' => true]);
$generated = $race([['item' => $first->id, 'code' => null], ['item' => $first->id, 'code' => null]]);
$second = G002M007Item::create(['name' => $tag.'-second', 'quantity' => 0, 'is_borrowable' => true]);
$duplicate = $race([['item' => $first->id, 'code' => $tag.'-FIXED'], ['item' => $second->id, 'code' => $tag.'-FIXED']]);
$totals = G002M007Item::whereKey([$first->id, $second->id])->get()->every(fn ($item) =>
    (int) $item->quantity === $item->item_instance()->count()
    && (int) $item->available_quantity === $item->item_instance()->where('is_available', true)->where('is_borrowable', true)->count());
$result = ['mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
    'generated_outcomes' => $generated, 'explicit_duplicate_outcomes' => $duplicate,
    'unique_generated' => $first->item_instance()->distinct()->count('code') === $first->item_instance()->count(),
    'totals_match' => $totals];
$result['pass'] = $generated === ['committed', 'committed'] && $duplicate === ['committed', 'duplicate_rejected']
    && $result['unique_generated'] && $totals;
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit($result['pass'] ? 0 : 1);
