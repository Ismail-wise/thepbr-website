<?php

namespace App\Filament\Resources\PortalAccessEvents;

use App\Filament\Resources\PortalAccessEvents\Pages\ListPortalAccessEvents;
use App\Models\PortalAccessEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PortalAccessEventResource extends Resource
{
    protected static ?string $model = PortalAccessEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?string $navigationLabel = 'Access History';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return config('partner_portal.enabled') && auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'actor']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label('Time')->dateTime('d M Y H:i')
                ->timezone(config('app.display_timezone'))->sortable(),
            TextColumn::make('user.email')->label('Account')->searchable()->wrap(),
            TextColumn::make('actor.name')->label('Admin')->searchable(),
            TextColumn::make('action')->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))->badge(),
            TextColumn::make('days')->label('Days Added')->placeholder('—'),
            TextColumn::make('before_state.status')->label('Before')->badge(),
            TextColumn::make('after_state.status')->label('After')->badge(),
            TextColumn::make('after_state.expires_at')->label('Expiry After')->dateTime('d M Y H:i')
                ->timezone(config('app.display_timezone'))->placeholder('—'),
        ])->filters([
            SelectFilter::make('user')->label('Account')->relationship('user', 'email')->searchable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPortalAccessEvents::route('/')];
    }
}
