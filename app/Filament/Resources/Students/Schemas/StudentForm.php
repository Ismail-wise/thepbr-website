<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Phone Number')
                    ->tel()
                    ->maxLength(30),
                Select::make('class_session_id')
                    ->label('Class Batch')
                    ->relationship('classSession', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('account_status')
                    ->label('Account Status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ])
                    ->required(),
                DateTimePicker::make('portal_access_expires_at')
                    ->label('Portal Access Expiry')
                    ->helperText('Leave empty to keep portal access without an expiry date.'),
            ]);
    }
}
