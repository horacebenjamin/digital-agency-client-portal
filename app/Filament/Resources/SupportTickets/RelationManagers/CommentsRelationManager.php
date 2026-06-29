<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Replies';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeftRight;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Reply')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Toggle::make('is_internal')
                    ->label('Internal reply')
                    ->helperText('Internal replies are hidden from clients and do not notify them.')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('creator.name')
                    ->label('Author')
                    ->placeholder('Support team')
                    ->searchable(),
                TextColumn::make('body')
                    ->label('Reply')
                    ->wrap()
                    ->limit(160)
                    ->searchable(),
                IconColumn::make('is_internal')
                    ->boolean()
                    ->label('Internal'),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add reply')
                    ->modalHeading('Add support ticket reply')
                    ->createAnother(false)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
