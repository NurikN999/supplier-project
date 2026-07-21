<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('items_count')
                    ->label('Lines')
                    ->counts('items'),
                TextColumn::make('total')
                    ->state(fn (PurchaseOrder $record) => $record->total())
                    ->money('KZT'),
                TextColumn::make('placed_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('received_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PurchaseOrderStatus::class),
            ])
            ->recordActions([
                static::transition('place', PurchaseOrderStatus::Placed, 'heroicon-o-paper-airplane'),
                static::transition('receive', PurchaseOrderStatus::Received, 'heroicon-o-inbox-arrow-down'),
                static::transition('cancel', PurchaseOrderStatus::Cancelled, 'heroicon-o-x-circle'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The model owns the rules; the button only asks whether the move is legal
     * and reports back what happened.
     */
    private static function transition(string $name, PurchaseOrderStatus $to, string $icon): Action
    {
        return Action::make($name)
            ->icon($icon)
            ->color($to->getColor())
            ->requiresConfirmation()
            ->visible(fn (PurchaseOrder $record) => $record->canTransitionTo($to))
            ->action(function (PurchaseOrder $record) use ($name, $to): void {
                try {
                    $record->{$name}();
                } catch (\DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Order {$record->number} is now {$to->value}.")
                    ->body($to === PurchaseOrderStatus::Received ? 'Stock has been updated.' : null)
                    ->success()
                    ->send();
            });
    }
}
