<?php

namespace App\Filament\Resources\SupportTicketComments\Pages;

use App\Filament\Resources\SupportTicketComments\SupportTicketCommentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicketComment extends CreateRecord
{
    protected static string $resource = SupportTicketCommentResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
