<?php

namespace Tests\Feature;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\RelationManagers\ProductsRelationManager;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $acme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->acme = Organization::where('slug', 'acme')->firstOrFail();

        $this->actingAs(User::where('email', 'acme@example.com')->firstOrFail());

        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->acme);
        // HTTP middleware does this; Livewire unit tests have to boot the panel
        // themselves, and the tenancy global scopes are registered in Panel::boot().
        Filament::bootCurrentPanel();
    }

    public function test_the_supplier_list_shows_only_the_current_tenants_suppliers(): void
    {
        Livewire::test(ListSuppliers::class)
            ->assertCanSeeTableRecords($this->acme->suppliers)
            ->assertCanNotSeeTableRecords(Supplier::whereNot('organization_id', $this->acme->id)->get());
    }

    public function test_suppliers_can_be_filtered_by_tag(): void
    {
        $tag = $this->acme->tags()->where('name', 'Reliable')->firstOrFail();
        $tagged = $tag->suppliers;

        $this->assertCount(1, $tagged, 'seeder gives this tag exactly one supplier');

        Livewire::test(ListSuppliers::class)
            ->filterTable('tags', [$tag->id])
            ->assertCanSeeTableRecords($tagged)
            ->assertCanNotSeeTableRecords($this->acme->suppliers()->whereKeyNot($tagged->first()->id)->get());
    }

    public function test_a_product_can_be_attached_to_a_supplier_with_its_own_price(): void
    {
        $supplier = $this->acme->suppliers()->firstOrFail();
        $product = Product::create([
            'organization_id' => $this->acme->id,
            'sku' => 'ACM-999',
            'name' => 'Brass nut',
        ]);

        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $supplier,
            'pageClass' => EditSupplier::class,
        ])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $product->id, 'price' => 42.50])
            ->assertHasNoActionErrors();

        $this->assertEquals(
            42.50,
            $supplier->products()->whereKey($product->id)->firstOrFail()->pivot->price,
        );
    }

    public function test_another_organizations_product_cannot_be_attached(): void
    {
        $supplier = $this->acme->suppliers()->firstOrFail();
        // The tenancy global scope hides it, so reach past it deliberately.
        $foreign = Product::withoutGlobalScopes()->where('sku', 'BF-100')->firstOrFail();

        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $supplier,
            'pageClass' => EditSupplier::class,
        ])
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $foreign->id, 'price' => 1])
            ->assertHasActionErrors(['recordId']);

        $this->assertEmpty($supplier->products()->whereKey($foreign->id)->get());
    }

    public function test_a_supplier_is_created_inside_the_current_tenant(): void
    {
        Livewire::test(CreateSupplier::class)
            ->fillForm(['name' => 'Fresh Supplier', 'email' => 'fresh@example.com'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $this->acme->id,
            Supplier::where('name', 'Fresh Supplier')->firstOrFail()->organization_id,
        );
    }
}
