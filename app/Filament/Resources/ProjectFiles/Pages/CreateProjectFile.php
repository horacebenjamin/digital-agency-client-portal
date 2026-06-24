<?php

namespace App\Filament\Resources\ProjectFiles\Pages;

use App\Filament\Resources\ProjectFiles\ProjectFileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectFile extends CreateRecord
{
    protected static string $resource = ProjectFileResource::class;
}
