<?php

namespace App\Filament\Resources\SupportTicketComments;

use App\Filament\Resources\SupportTicketComments\Pages\CreateSupportTicketComment;
use App\Filament\Resources\SupportTicketComments\Pages\EditSupportTicketComment;
use App\Filament\Resources\SupportTicketComments\Pages\ListSupportTicketComments;
use App\Filament\Resources\SupportTicketComments\Schemas\SupportTicketCommentForm;
use App\Filament\Resources\SupportTicketComments\Tables\SupportTicketCommentsTable;
use App\Models\SupportTicketComment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketCommentResource extends Resource
{
    protected static ?string $model = SupportTicketComment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'body';

    public static function form(Schema $schema): Schema
    {
        return SupportTicketCommentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketCommentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTicketComments::route('/'),
            'create' => CreateSupportTicketComment::route('/create'),
            'edit' => EditSupportTicketComment::route('/{record}/edit'),
        ];
    }
}
