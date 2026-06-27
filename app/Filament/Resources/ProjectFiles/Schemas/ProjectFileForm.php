<?php

namespace App\Filament\Resources\ProjectFiles\Schemas;

use App\Models\ProjectFile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProjectFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->relationship('project', 'title')
                    ->required(),
                FileUpload::make('path')
                    ->label('File')
                    ->directory('project-files')
                    ->disk('public')
                    ->required()
                    ->storeFileNamesIn('name')
                    ->downloadable()
                    ->openable(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ProjectFile::STATUSES)
                    ->default(ProjectFile::STATUS_AVAILABLE)
                    ->required(),
            ]);
    }
}
