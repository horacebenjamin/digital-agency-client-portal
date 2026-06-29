<?php

namespace App\Filament\Resources\SupportTicketComments\Pages;

use App\Filament\Resources\SupportTicketComments\SupportTicketCommentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupportTicketComment extends CreateRecord
{
    protected static string $resource = SupportTicketCommentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
