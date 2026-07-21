<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Select::make('tags')
                    // Filament scopes resource queries, not relationship options — scope it here.
                    ->relationship('tags', 'name', fn (Builder $query) => $query->whereBelongsTo(Filament::getTenant()))
                    ->multiple()
                    ->preload(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
