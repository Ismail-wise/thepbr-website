<?php

namespace App\Filament\Resources\UserAccess;

use App\Filament\Resources\UserAccess\Pages\ListUserAccess;
use App\Filament\Resources\UserAccess\Tables\UserAccessTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserAccessResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Accounts & Access';

    protected static ?string $modelLabel = 'User Account';

    protected static ?string $pluralModelLabel = 'Accounts & Access';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return UserAccessTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('studentEnrollments');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserAccess::route('/'),
        ];
    }
}
