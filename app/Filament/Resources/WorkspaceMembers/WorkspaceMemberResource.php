<?php

namespace App\Filament\Resources\WorkspaceMembers;

use App\Filament\Resources\WorkspaceMembers\Pages\ListWorkspaceMembers;
use App\Filament\Resources\WorkspaceMembers\Tables\WorkspaceMembersTable;
use App\Models\WorkspaceMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkspaceMemberResource extends Resource
{
    protected static ?string $model = WorkspaceMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Partner Memberships';

    protected static ?string $modelLabel = 'Partner Membership';

    protected static ?string $pluralModelLabel = 'Partner Memberships';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return WorkspaceMembersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('member_role', 'partner')
            ->with(['workspace', 'user', 'invitedBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaceMembers::route('/'),
        ];
    }
}
