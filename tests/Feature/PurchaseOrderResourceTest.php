<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $acme;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->acme = Organization::where('slug', 'acme')->firstOrFail();
        $this->supplier = $this->acme->suppliers()->firstOrFail();

        $this->actingAs(User::where('email', 'acme@example.com')->firstOrFail());
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->acme);
        Filament::bootCurrentPanel();
    }

    public function test_an_order_can_be_created_with_lines_priced_from_the_supplier(): void
    {
        $product = $this->supplier->products()->firstOrFail();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'supplier_id' => $this->supplier->id,
                'number' => 'PO-0001',
                'items' => [
                    ['product_id' => $product->id, 'qty' => 3, 'unit_price' => $product->pivot->price],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = PurchaseOrder::where('number', 'PO-0001')->firstOrFail();

        $this->assertSame($this->acme->id, $order->organization_id);
        $this->assertEquals($product->pivot->price, $order->items->first()->unit_price);
        $this->assertEquals($product->pivot->price * 3, $order->total());
    }

    public function test_the_receive_action_moves_stock_and_the_place_action_disappears(): void
    {
        $product = $this->supplier->products()->firstOrFail();

        $order = $this->acme->purchaseOrders()->create([
            'supplier_id' => $this->supplier->id,
            'number' => 'PO-0009',
        ]);
        $order->items()->create(['product_id' => $product->id, 'qty' => 7, 'unit_price' => 100]);

        Livewire::test(ListPurchaseOrders::class)
            ->callAction(TestAction::make('place')->table($order))
            ->assertActionHidden(TestAction::make('place')->table($order))
            ->callAction(TestAction::make('receive')->table($order));

        $this->assertSame(PurchaseOrderStatus::Received, $order->refresh()->status);
        $this->assertEquals(7, Stock::where('product_id', $product->id)->value('qty_on_hand'));
    }

    public function test_the_receive_action_is_hidden_on_a_draft_order(): void
    {
        $order = $this->acme->purchaseOrders()->create([
            'supplier_id' => $this->supplier->id,
            'number' => 'PO-0010',
        ]);

        Livewire::test(ListPurchaseOrders::class)
            ->assertActionVisible(TestAction::make('place')->table($order))
            ->assertActionHidden(TestAction::make('receive')->table($order));
    }
}
