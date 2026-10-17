<?php

namespace App\Filament\Resources\UserAccess\Tables;

use App\Filament\Resources\PortalAccessEvents\PortalAccessEventResource;
use App\Models\User;
use App\Services\AccessAdministrationService;
use App\Services\Portal\StudentPortalAccess;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('portal_status')
                    ->label('Portal Access')
                    ->state(fn (User $record): string => app(StudentPortalAccess::class)->summary($record)['status'])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'info',
                        'expired' => 'warning',
                        default => 'danger',
                    })
                    ->visible(fn (): bool => (bool) config('partner_portal.enabled')),
                TextColumn::make('portal_expires_at')
                    ->label('Portal Expires')
                    ->state(fn (User $record) => app(StudentPortalAccess::class)->summary($record)['expires_at'])
                    ->dateTime('d M Y H:i')
                    ->timezone(config('app.display_timezone'))
                    ->placeholder(fn (User $record): string => app(StudentPortalAccess::class)->summary($record)['status'] === 'active' ? 'Lifetime' : '—')
                    ->visible(fn (): bool => (bool) config('partner_portal.enabled')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('portal_status')
                    ->label('Portal Access')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'suspended' => 'Suspended',
                        'expiring' => 'Expiring in 7 days',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? app(StudentPortalAccess::class)->queryForStatus($query, $data['value'])
                        : $query)
                    ->visible(fn (): bool => (bool) config('partner_portal.enabled')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('grantDays')
                        ->label(fn (User $record): string => app(StudentPortalAccess::class)->summary($record)['status'] === 'active'
                            ? 'Extend Portal Access' : 'Activate Portal Access')
                        ->color('success')
                        ->modalHeading(fn (User $record): string => 'Portal Access — '.$record->name)
                        ->modalDescription(fn (User $record): string => $record->email.' · ဝင်ခွင့်ရနေပါက ကျန်သက်တမ်းပေါ် ထပ်ပေါင်းမည်။ အခြားအခြေအနေတွင် ယနေ့မှ စတင်မည်။')
                        ->schema([
                            TextInput::make('days')->label('ဝင်ခွင့်ပေးမည့် ရက်အရေအတွက်')
                                ->integer()->required()->minValue(1)->maxValue(3650)->default(30),
                        ])
                        ->visible(fn (User $record): bool => config('partner_portal.enabled')
                            && ! $record->isAdmin() && $record->hasActiveAccount()
                            && ! (app(StudentPortalAccess::class)->summary($record)['status'] === 'active'
                                && app(StudentPortalAccess::class)->summary($record)['expires_at'] === null))
                        ->action(function (User $record, array $data): void {
                            app(StudentPortalAccess::class)->grantDays(auth()->user(), $record, (int) $data['days']);
                            Notification::make()->title('Portal access updated')->success()->send();
                        }),
                    Action::make('suspendPortal')
                        ->label('Suspend Portal Access')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Portal ဝင်ခွင့်ကို ယာယီရပ်မည်။ သိမ်းထားသော ရလဒ်များနှင့် AI ဆွေးနွေးမှုများ ဆက်ရှိမည်။')
                        ->visible(fn (User $record): bool => config('partner_portal.enabled') && ! $record->isAdmin()
                            && app(StudentPortalAccess::class)->summary($record)['status'] === 'active')
                        ->action(function (User $record): void {
                            app(StudentPortalAccess::class)->suspend(auth()->user(), $record);
                            Notification::make()->title('Portal access suspended')->success()->send();
                        }),
                    Action::make('accessHistory')
                        ->label('Access History')
                        ->visible(fn (): bool => (bool) config('partner_portal.enabled'))
                        ->url(fn (User $record): string => PortalAccessEventResource::getUrl('index', [
                            'tableFilters' => ['user' => ['value' => $record->id]],
                        ], panel: 'admin')),
                    Action::make('grantYear')
                        ->label('Grant Student 1 Year')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool =>
                            $record->hasActiveAccount()
                            && $record->studentEnrollments->isEmpty()
                            && (! config('partner_portal.enabled') || ! $record->isStudent()
                                || $record->portal_access_expires_at !== null))
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
