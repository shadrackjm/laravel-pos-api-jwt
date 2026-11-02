<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan pos:nplusone` — the on-camera demo for episode 22.
 *
 * Counts the actual queries fired by the naive and eager-loaded versions of the
 * same listing, then shows what `preventLazyLoading` does about it.
 */
class DemonstrateNPlusOne extends Command
{
    protected $signature = 'pos:nplusone {--products=50 : How many products to seed for the demo}';

    protected $description = 'Count the queries an N+1 costs, and show the fix';

    public function handle(): int
    {
        $this->seed((int) $this->option('products'));

        $count = Product::count();

        $this->newLine();
        $this->components->info("Listing {$count} products, each with its category name");
        $this->newLine();

        // We have to switch the guard off to demonstrate the bug at all — which
        // is itself the point of the guard. With preventLazyLoading enabled,
        // the loop below throws instead of quietly firing 50 queries.
        Model::preventLazyLoading(false);

        $naive = $this->countQueries(function () {
            foreach (Product::all() as $product) {
                $ignored = $product->category->name;   // one query per product
            }
        });

        Model::preventLazyLoading(! app()->isProduction());

        $eager = $this->countQueries(function () {
            foreach (Product::with('category')->get() as $product) {
                $ignored = $product->category->name;   // already loaded
            }
        });

        $this->components->twoColumnDetail('Without with()', "{$naive} queries");
        $this->components->twoColumnDetail('With with()', "{$eager} queries");
        $this->newLine();

        $this->components->error(
            "The naive version ran {$naive} queries to show {$count} products. "
            .'That is the N+1 problem: 1 query for the list, plus 1 per row.'
        );

        $this->newLine();
        $this->components->info('Aggregating in the database instead of in PHP');
        $this->newLine();

        // The wrong way: pull every row into PHP and add it up there.
        $inPhp = $this->countQueries(function () {
            $total = 0;
            foreach (Product::all() as $product) {
                $total += $product->stock;
            }
        });

        // The right way: let the database do the arithmetic.
        $inDb = $this->countQueries(fn () => Product::sum('stock'));

        $this->components->twoColumnDetail('Summing stock in PHP', "{$inPhp} query, ".$count.' models hydrated');
        $this->components->twoColumnDetail('Summing stock in SQL', "{$inDb} query, 0 models hydrated");
        $this->newLine();

        $this->line('  Same query count — but the PHP version built '.$count.' objects to add up '.$count.' integers.');
        $this->line('  At 100,000 products that is the difference between fine and out of memory.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    private function seed(int $target): void
    {
        if (Product::count() >= $target) {
            return;
        }

        $categories = collect(['Drinks', 'Stationery', 'Snacks', 'Cleaning'])
            ->map(fn ($name) => Category::firstOrCreate(['name' => $name]));

        for ($i = Product::count(); $i < $target; $i++) {
            Product::create([
                'category_id' => $categories->random()->id,
                'name' => "Demo Product {$i}",
                'sku' => "DEMO-{$i}",
                'price' => random_int(100, 5000) / 100,
                'stock' => random_int(0, 200),
            ]);
        }
    }
}
