<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['organization_id', 'supplier_id', 'number', 'status', 'notes'])]
class PurchaseOrder extends Model
{
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'placed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** Never stored — 3NF. */
    public function total(): string
    {
        return $this->items->sum(fn (PurchaseOrderItem $item) => $item->qty * $item->unit_price);
    }

    /** ponytail: sequential per tenant, gaps after deletes. The unique index is the real guard. */
    public static function nextNumber(): string
    {
        return 'PO-'.str_pad((string) (static::query()->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    public function canTransitionTo(PurchaseOrderStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    public function place(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Placed);

        if ($this->items()->doesntExist()) {
            throw new DomainException('An order cannot be placed without items.');
        }

        $this->forceFill(['status' => PurchaseOrderStatus::Placed, 'placed_at' => now()])->save();
    }

    public function cancel(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Cancelled);

        $this->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();
    }

    /**
     * Receiving is the event that moves stock: every item lands in the warehouse.
     */
    public function receive(): void
    {
        $this->transitionTo(PurchaseOrderStatus::Received);

        DB::transaction(function (): void {
            foreach ($this->items()->with('product')->get() as $item) {
                StockMovement::create([
                    'organization_id' => $this->organization_id,
                    'product_id' => $item->product_id,
                    'qty_delta' => $item->qty,
                    'reason' => 'purchase_received',
                    'purchase_order_id' => $this->id,
                ]);

                Stock::query()->updateOrCreate(
                    ['organization_id' => $this->organization_id, 'product_id' => $item->product_id],
                    [],
                )->increment('qty_on_hand', $item->qty);
            }

            $this->forceFill(['status' => PurchaseOrderStatus::Received, 'received_at' => now()])->save();
        });
    }

    private function transitionTo(PurchaseOrderStatus $status): void
    {
        if (! $this->canTransitionTo($status)) {
            throw new DomainException("A {$this->status->value} order cannot become {$status->value}.");
        }
    }
}
