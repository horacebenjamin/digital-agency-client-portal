<?php

namespace App\Filament\Resources\PaymentRequests\Schemas;

use App\Models\PaymentRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PaymentRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'company_name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('project_id', null)),
                Select::make('project_id')
                    ->relationship(
                        'project',
                        'title',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => filled($get('client_id'))
                            ? $query->where('client_id', $get('client_id'))
                            : $query->whereRaw('1 = 0'),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('client_id'))),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->prefix('£')
                    ->numeric()
                    ->step('0.01')
                    ->minValue(0)
                    ->required()
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? number_format($state / 100, 2, '.', '') : null)
                    ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) round(((float) $state) * 100) : null),
                Select::make('currency')
                    ->options([
                        'gbp' => 'GBP',
                    ])
                    ->default('gbp')
                    ->required(),
                Select::make('status')
                    ->options(PaymentRequest::STATUSES)
                    ->default('draft')
                    ->required(),
                DatePicker::make('due_date'),
            ]);
    }
}
