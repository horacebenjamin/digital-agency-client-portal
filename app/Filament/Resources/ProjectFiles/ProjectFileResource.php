<?php

namespace App\Filament\Resources\ProjectFiles;

use App\Filament\Resources\ProjectFiles\Pages\CreateProjectFile;
use App\Filament\Resources\ProjectFiles\Pages\EditProjectFile;
use App\Filament\Resources\ProjectFiles\Pages\ListProjectFiles;
use App\Filament\Resources\ProjectFiles\Schemas\ProjectFileForm;
use App\Filament\Resources\ProjectFiles\Tables\ProjectFilesTable;
use App\Models\ProjectFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProjectFileResource extends Resource
{
    protected static ?string $model = ProjectFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProjectFileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectFilesTable::configure($table);
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
            'index' => ListProjectFiles::route('/'),
            'create' => CreateProjectFile::route('/create'),
            'edit' => EditProjectFile::route('/{record}/edit'),
        ];
    }
}
