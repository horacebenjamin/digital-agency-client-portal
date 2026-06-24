<?php

namespace App\Filament\Resources\ProjectFiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                TextInput::make('name')
                    ->required(),
                TextInput::make('path')
                    ->required(),
                TextInput::make('disk')
                    ->required()
                    ->default('public'),
                TextInput::make('mime_type'),
                TextInput::make('size')
                    ->numeric(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
