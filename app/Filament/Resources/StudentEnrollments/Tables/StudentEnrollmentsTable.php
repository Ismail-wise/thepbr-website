<?php

namespace App\Filament\Resources\StudentEnrollments\Tables;

use App\Models\StudentEnrollment;
use App\Services\AccessAdministrationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('classSession.title')
                    ->label('Class Batch')
                    ->placeholder('No class'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'revoked' => 'danger',
                        'expired' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('access_expires_at')
                    ->label('Access Expires')
                    ->dateTime()
                    ->placeholder('Lifetime')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->emptyStateHeading('No authoritative Student entitlements')
            ->emptyStateDescription('Legacy Student fallback access appears in Accounts & Access. Use its Manage action to create an authoritative entitlement.')
            ->recordActions([
                ActionGroup::make([
                    Action::make('revoke')
                        ->label('Revoke Access')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (StudentEnrollment $record): bool => $record->isActive())
                        ->action(function (StudentEnrollment $record): void {
                            app(AccessAdministrationService::class)
                                ->revokeStudentEntitlement(auth()->user(), $record);

                            Notification::make()
                                ->title('Student entitlement revoked')
                                ->success()
                                ->send();
                        }),
                    Action::make('renewYear')
                        ->label('Renew 1 Year')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (StudentEnrollment $record): bool =>
                            ! $record->isActive()
                            || $record->access_expires_at !== null)
                        ->action(function (StudentEnrollment $record): void {
                            app(AccessAdministrationService::class)
                                ->renewStudentEntitlement(auth()->user(), $record);

                            Notification::make()
                                ->title('Student entitlement renewed for one year')
                                ->success()
                                ->send();
                        }),
                    Action::make('grantLifetime')
                        ->label('Grant Lifetime')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (StudentEnrollment $record): bool =>
                            $record->status !== 'active'
                            || $record->access_expires_at !== null)
                        ->action(function (StudentEnrollment $record): void {
                            app(AccessAdministrationService::class)
                                ->grantLifetimeStudentEntitlement(auth()->user(), $record);

                            Notification::make()
                                ->title('Lifetime Student entitlement granted')
                                ->success()
                                ->send();
                        }),
                ])->label('Manage'),
            ]);
    }
}
