<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = App\Models\User::first();
    $svc = app(App\Services\DailyCollectionService::class);
    $data = [
        'dealer_id' => 1,
        'supply_date' => '2026-11-02',
        'items' => [
            ['product_id' => 1, 'quantity' => 349, 'unit' => 'Can', 'custom_price' => 11]
        ],
        'payment_amount' => 3901,
        'payment_method' => 'upi',
        'payment_date' => '2026-11-02',
        'payment_reference' => 'gpay',
        'payment_notes' => null,
        'include_in_ledger_amount' => true,
        'idempotency_key' => 'TEST-SCRATCH-'.time()
    ];
    $res = $svc->createSupply($data, $user);
    echo "SUCCESS: Supply ID = " . $res->id . PHP_EOL;
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . PHP_EOL;
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
