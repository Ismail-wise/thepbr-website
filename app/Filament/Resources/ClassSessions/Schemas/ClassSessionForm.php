<?php

namespace App\Filament\Resources\ClassSessions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClassSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                DatePicker::make('starts_on')
                    ->required(),
                DatePicker::make('ends_on'),
                TextInput::make('mode')
                    ->required()
                    ->default('in_person'),
                TextInput::make('location')
                    ->required(),
                TextInput::make('time_note'),
                TextInput::make('fee'),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('enrolled')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_visible')
                    ->required(),
            ]);
    }
}
