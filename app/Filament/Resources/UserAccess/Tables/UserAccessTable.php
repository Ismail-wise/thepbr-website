<?php

namespace App\Filament\Resources\UserAccess\Tables;

use App\Models\User;
use App\Services\AccessAdministrationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserAccessTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                TextColumn::make('effective_access')
                    ->label('Effective Access')
                    ->state(fn (User $record): string => self::effectiveAccess($record))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Inactive' => 'danger',
                        str_contains($state, 'Admin') => 'warning',
                        str_contains($state, 'Student') => 'success',
                        str_contains($state, 'Partner') => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('role')
                    ->label('Legacy Role')
                    ->badge(),
                TextColumn::make('account_status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                ActionGroup::make([
                    Action::make('grantYear')
                        ->label('Grant Student 1 Year')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool =>
                            $record->hasActiveAccount()
                            && $record->studentEnrollments->isEmpty())
                        ->action(function (User $record): void {
                            app(AccessAdministrationService::class)
                                ->grantOneYearStudentAccess(auth()->user(), $record);

                            Notification::make()
                                ->title('One-year Student entitlement granted')
                                ->success()
                                ->send();
                        }),
                    Action::make('grantLifetime')
                        ->label('Grant Student Lifetime')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool =>
                            $record->hasActiveAccount()
                            && $record->studentEnrollments->isEmpty())
                        ->action(function (User $record): void {
                            app(AccessAdministrationService::class)
                                ->grantLifetimeStudentAccess(auth()->user(), $record);

                            Notification::make()
                                ->title('Lifetime Student entitlement granted')
                                ->success()
                                ->send();
                        }),
                    Action::make('deactivate')
                        ->label('Deactivate Account')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool =>
                            $record->account_status === 'active'
                            && (int) auth()->id() !== (int) $record->id)
                        ->action(function (User $record): void {
                            app(AccessAdministrationService::class)
                                ->deactivateAccount(auth()->user(), $record);

                            Notification::make()
                                ->title('Account deactivated')
                                ->success()
                                ->send();
                        }),
                    Action::make('activate')
                        ->label('Activate Account')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => $record->account_status !== 'active')
                        ->action(function (User $record): void {
                            app(AccessAdministrationService::class)
                                ->activateAccount(auth()->user(), $record);

                            Notification::make()
                                ->title('Account activated')
                                ->success()
                                ->send();
                        }),
                ])->label('Manage'),
            ]);
    }

    private static function effectiveAccess(User $user): string
    {
        if (! $user->hasActiveAccount()) {
            return 'Inactive';
        }

        $studentAccess = $user->isStudent()
            ? ($user->studentEnrollments->isEmpty() ? 'Student (legacy)' : 'Student')
            : null;

        $access = collect([
            $user->isAdmin() ? 'Admin' : null,
            $studentAccess,
            $user->isPartner() ? 'Partner' : null,
        ])->filter()->implode(' + ');

        return $access !== '' ? $access : 'Public';
    }
}
