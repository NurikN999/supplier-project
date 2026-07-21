<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_user_only_belongs_to_their_own_organization(): void
    {
        $user = User::where('email', 'acme@example.com')->firstOrFail();
        $acme = Organization::where('slug', 'acme')->firstOrFail();
        $beta = Organization::where('slug', 'beta')->firstOrFail();

        $this->assertTrue($user->canAccessTenant($acme));
        $this->assertFalse($user->canAccessTenant($beta));
        $this->assertEquals(['Acme Trading'], collect($user->getTenants(filament()->getPanel('admin')))->pluck('name')->all());
    }

    public function test_supplier_lists_do_not_leak_between_organizations(): void
    {
        $acme = Organization::where('slug', 'acme')->firstOrFail();
        $beta = Organization::where('slug', 'beta')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['Nordwind GmbH', 'Steel & Co'],
            $acme->suppliers()->pluck('name')->all(),
        );
        $this->assertSame(4, Supplier::count(), 'both orgs seeded');
        $this->assertEmpty($beta->suppliers()->whereIn('name', ['Nordwind GmbH', 'Steel & Co'])->get());
    }

    public function test_the_same_product_has_a_different_price_per_supplier(): void
    {
        $acme = Organization::where('slug', 'acme')->firstOrFail();
        $product = $acme->products()->where('sku', 'ACM-001')->firstOrFail();

        $prices = $product->suppliers->pluck('pivot.price')->map(fn ($p) => (float) $p);

        $this->assertCount(2, $prices);
        $this->assertNotEquals($prices[0], $prices[1]);
    }
}
