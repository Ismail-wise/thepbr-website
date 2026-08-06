<?php

namespace App\Filament\Resources\StudentAccessCodes;

use App\Filament\Resources\StudentAccessCodes\Pages\ListStudentAccessCodes;
use App\Filament\Resources\StudentAccessCodes\Tables\StudentAccessCodesTable;
use App\Models\StudentAccessCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentAccessCodeResource extends Resource
{
    protected static ?string $model = StudentAccessCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Access Codes';

    protected static ?string $modelLabel = 'Access Code';

    protected static ?string $pluralModelLabel = 'Access Codes';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return StudentAccessCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentAccessCodes::route('/'),
        ];
    }
}
