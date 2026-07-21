<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'supplier_id', 'number', 'status', 'notes'])]
class PurchaseOrder extends Model
{
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
}
