<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * `php artisan pos:race` — the on-camera demo for episode 17.
 *
 * Spawns two real, parallel PHP processes that both try to sell the last item.
 * Run with --unsafe to see the naive check-then-write oversell; run without it
 * to see lockForUpdate serialise them correctly.
 */
class DemonstrateRaceCondition extends Command
{
    protected $signature = 'pos:race
                            {--unsafe : Use the naive check-then-write (no row lock)}
                            {--worker= : Internal: run as one of the two competing workers}';

    protected $description = 'Demonstrate the overselling race condition and its fix';

    public function handle(): int
    {
        return $this->option('worker') !== null
            ? $this->runWorker()
            : $this->runDemo();
    }

    private function runDemo(): int
    {
        $category = Category::firstOrCreate(['name' => 'Race Demo']);

        $product = Product::updateOrCreate(
            ['sku' => 'RACE-001'],
            ['category_id' => $category->id, 'name' => 'Last Coffee Mug', 'price' => 12.50, 'stock' => 1],
        );

        $mode = $this->option('unsafe') ? 'UNSAFE (no lock)' : 'SAFE (lockForUpdate)';

        $this->newLine();
        $this->components->info("Mode: {$mode}");
        $this->components->twoColumnDetail('Product', $product->name);
        $this->components->twoColumnDetail('Stock before', (string) $product->stock);
        $this->newLine();
        $this->line('  Two cashiers tap Pay at the same instant...');
        $this->newLine();

        // Two genuinely parallel OS processes — not two loops in one script.
        $flag = $this->option('unsafe') ? ' --unsafe' : '';
        $binary = PHP_BINARY;
        $artisan = base_path('artisan');

        @unlink($this->resultsPath());

        $pool = Process::pool(fn ($pool) => [
            $pool->command("\"{$binary}\" \"{$artisan}\" pos:race --worker=1{$flag}"),
            $pool->command("\"{$binary}\" \"{$artisan}\" pos:race --worker=2{$flag}"),
        ])->start();

        $pool->wait();

        foreach (file($this->resultsPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $this->line('  '.$line);
        }

        $finalStock = (int) Product::where('sku', 'RACE-001')->value('stock');

        $this->newLine();
        $this->components->twoColumnDetail('Stock after', (string) $finalStock);
        $this->newLine();

        if ($finalStock < 0) {
            $this->components->error("Stock is {$finalStock}. We sold an item that did not exist.");
        } elseif ($finalStock === 0) {
            $this->components->info('Stock is 0. Exactly one cashier won, which is correct.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function runWorker(): int
    {
        $worker = $this->option('worker');

        try {
            DB::transaction(function () use ($worker) {
                if ($this->option('unsafe')) {
                    // THE BUG. Read stock...
                    $product = Product::where('sku', 'RACE-001')->first();

                    // ...pause, exactly as real work between a check and a write
                    // would. Both processes are now holding the same value: 1.
                    usleep(150_000);

                    if ($product->stock < 1) {
                        $this->say("Cashier {$worker}: refused — out of stock");

                        return;
                    }

                    $product->decrement('stock', 1);
                } else {
                    // THE FIX. lockForUpdate() makes the second process WAIT here
                    // until the first transaction commits, so it reads the
                    // already-decremented value instead of the stale one.
                    $product = Product::where('sku', 'RACE-001')->lockForUpdate()->first();

                    usleep(150_000);

                    if ($product->stock < 1) {
                        $this->say("Cashier {$worker}: refused — out of stock");

                        return;
                    }

                    $product->decrement('stock', 1);
                }

                $this->say("Cashier {$worker}: SOLD");
            }, 3);
        } catch (\Throwable $e) {
            $this->say("Cashier {$worker}: failed — ".class_basename($e));
        }

        return self::SUCCESS;
    }

    /**
     * Append this worker's outcome to a shared file the parent reads afterwards.
     * More reliable than piping stdout back from a child process on Windows.
     */
    private function say(string $line): void
    {
        file_put_contents($this->resultsPath(), $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function resultsPath(): string
    {
        return storage_path('app/race-demo.txt');
    }
}
