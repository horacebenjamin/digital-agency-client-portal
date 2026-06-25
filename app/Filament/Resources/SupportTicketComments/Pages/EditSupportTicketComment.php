<?php

namespace App\Filament\Resources\SupportTicketComments\Pages;

use App\Filament\Resources\SupportTicketComments\SupportTicketCommentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicketComment extends EditRecord
{
    protected static string $resource = SupportTicketCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
