<?php

namespace App\Filament\Resources\ProjectFiles\Pages;

use App\Filament\Resources\ProjectFiles\ProjectFileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectFile extends EditRecord
{
    protected static string $resource = ProjectFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
