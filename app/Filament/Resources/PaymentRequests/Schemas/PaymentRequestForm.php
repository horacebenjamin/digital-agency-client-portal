<?php

namespace App\Filament\Resources\PaymentRequests\Schemas;

use App\Models\PaymentRequest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'company_name')
                    ->searchable()
                    ->preload(),
                Select::make('project_id')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload(),
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
