<?php

namespace App\Filament\Resources\ProjectFiles\Pages;

use App\Filament\Resources\ProjectFiles\ProjectFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectFiles extends ListRecords
{
    protected static string $resource = ProjectFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
