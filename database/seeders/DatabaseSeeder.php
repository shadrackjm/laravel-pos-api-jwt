<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $drinks = Category::firstOrCreate(['name' => 'Drinks']);
        $stationery = Category::firstOrCreate(['name' => 'Stationery']);

        Product::updateOrCreate(
            ['sku' => 'MUG-001'],
            ['category_id' => $drinks->id, 'name' => 'Coffee Mug', 'price' => 12.50, 'stock' => 40],
        );

        Product::updateOrCreate(
            ['sku' => 'TEA-001'],
            ['category_id' => $drinks->id, 'name' => 'Green Tea Box', 'price' => 8.25, 'stock' => 15],
        );

        Product::updateOrCreate(
            ['sku' => 'NOTE-001'],
            ['category_id' => $stationery->id, 'name' => 'Notebook', 'price' => 3.99, 'stock' => 100],
        );
    }
}
