<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Only drafts are editable — placed and received orders are history.
            ->disabled(fn (?PurchaseOrder $record) => $record?->status !== null && $record->status !== PurchaseOrderStatus::Draft)
            ->components([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name', fn (Builder $query) => $query->whereBelongsTo(Filament::getTenant()))
                    ->required()
                    ->searchable()
                    ->preload()
                    // Prices belong to a supplier, so changing it invalidates the lines.
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('items', [])),
                TextInput::make('number')
                    ->required()
                    ->default(fn () => PurchaseOrder::nextNumber())
                    ->maxLength(255),
                Repeater::make('items')
                    ->relationship()
                    ->label('Items')
                    ->addActionLabel('Add product')
                    ->columns(3)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->required()
                            ->options(fn (Get $get) => static::priceList($get('../../supplier_id'))->pluck('name', 'id'))
                            // One line per product; the unique index says so too. Compare as
                            // strings — repeater state is int on fill, string in the browser.
                            ->disableOptionWhen(fn (string $value, Get $get) => $value !== (string) $get('product_id')
                                && in_array($value, array_map('strval', array_column($get('../../items') ?? [], 'product_id')), true))
                            ->live()
                            // The price always comes from that supplier's price list.
                            ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => $set(
                                'unit_price',
                                static::priceList($get('../../supplier_id'))->firstWhere('id', $state)?->pivot->price,
                            )),
                        TextInput::make('qty')
                            ->numeric()
                            ->minValue(0.001)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Prefilled from the supplier price list.'),
                    ]),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    /** @return Collection<int, Product> */
    private static function priceList(mixed $supplierId)
    {
        return Supplier::whereBelongsTo(Filament::getTenant())
            ->find($supplierId)
            ?->products()
            ->wherePivot('is_active', true)
            ->get()
            ?? new Collection;
    }
}
