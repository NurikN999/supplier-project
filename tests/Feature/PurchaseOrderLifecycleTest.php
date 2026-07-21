<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockMovement;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $acme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->acme = Organization::where('slug', 'acme')->firstOrFail();
    }

    public function test_receiving_an_order_increases_stock(): void
    {
        $order = $this->order();
        $product = $order->items->first()->product;

        $this->assertNull($product->stock);

        $order->place();
        $order->receive();

        $this->assertSame(PurchaseOrderStatus::Received, $order->refresh()->status);
        $this->assertNotNull($order->received_at);
        $this->assertEquals(10, Stock::where('product_id', $product->id)->value('qty_on_hand'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => 'purchase_received',
            'purchase_order_id' => $order->id,
        ]);
    }

    public function test_receiving_twice_does_not_double_the_stock(): void
    {
        $order = $this->order();
        $order->place();
        $order->receive();

        $this->expectException(DomainException::class);

        try {
            $order->receive();
        } finally {
            $this->assertEquals(10, Stock::sum('qty_on_hand'));
            $this->assertSame(1, StockMovement::count());
        }
    }

    public function test_an_unplaced_order_cannot_be_received(): void
    {
        $order = $this->order();

        $this->expectExceptionMessage('A draft order cannot become received.');

        try {
            $order->receive();
        } finally {
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_a_received_order_cannot_be_cancelled(): void
    {
        $order = $this->order();
        $order->place();
        $order->receive();

        $this->expectExceptionMessage('A received order cannot become cancelled.');

        $order->cancel();
    }

    public function test_an_empty_order_cannot_be_placed(): void
    {
        $order = $this->order();
        $order->items()->delete();

        $this->expectExceptionMessage('An order cannot be placed without items.');

        try {
            $order->place();
        } finally {
            $this->assertSame(PurchaseOrderStatus::Draft, $order->refresh()->status);
        }
    }

    public function test_a_draft_order_can_be_cancelled(): void
    {
        $order = $this->order();
        $order->cancel();

        $this->assertSame(PurchaseOrderStatus::Cancelled, $order->refresh()->status);
    }

    /** A draft order for one product at the supplier's own price. */
    private function order(): PurchaseOrder
    {
        $supplier = $this->acme->suppliers()->firstOrFail();
        $product = $supplier->products()->firstOrFail();

        $order = $this->acme->purchaseOrders()->create([
            'supplier_id' => $supplier->id,
            'number' => 'PO-1',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'qty' => 10,
            'unit_price' => $product->pivot->price,
        ]);

        return $order->load('items.product');
    }
}
