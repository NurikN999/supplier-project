<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->organization('Acme Trading', 'acme', 'acme@example.com', [
            'tags' => ['Reliable', 'Slow shipping'],
            'suppliers' => ['Nordwind GmbH', 'Steel & Co'],
            'products' => [['ACM-001', 'Steel bolt M8', 'pcs'], ['ACM-002', 'Copper wire 2mm', 'm']],
        ]);

        $this->organization('Beta Foods', 'beta', 'beta@example.com', [
            'tags' => ['Organic', 'Bulk only'],
            'suppliers' => ['Green Valley Farms', 'Sunrise Dairy'],
            'products' => [['BF-100', 'Oat flour', 'kg'], ['BF-200', 'Sunflower oil', 'l']],
        ]);
    }

    private function organization(string $name, string $slug, string $email, array $data): void
    {
        $org = Organization::create(['name' => $name, 'slug' => $slug]);

        User::create([
            'organization_id' => $org->id,
            'name' => $name.' Admin',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $tags = collect($data['tags'])->map(
            fn (string $tag) => Tag::create(['organization_id' => $org->id, 'name' => $tag])
        );

        $products = collect($data['products'])->map(
            fn (array $p) => Product::create([
                'organization_id' => $org->id,
                'sku' => $p[0],
                'name' => $p[1],
                'unit' => $p[2],
            ])
        );

        foreach ($data['suppliers'] as $i => $supplierName) {
            $supplier = Supplier::create([
                'organization_id' => $org->id,
                'name' => $supplierName,
                'email' => str($supplierName)->slug().'@example.com',
                'phone' => '+7 700 000 00'.$i,
            ]);

            $supplier->tags()->attach($tags[$i]);

            foreach ($products as $j => $product) {
                $supplier->products()->attach($product, ['price' => 100 + ($i * 25) + ($j * 10)]);
            }
        }
    }
}
