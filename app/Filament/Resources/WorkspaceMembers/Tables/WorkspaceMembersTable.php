<?php

namespace App\Filament\Resources\WorkspaceMembers\Tables;

use App\Models\WorkspaceMember;
use App\Services\AccessAdministrationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkspaceMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('workspace.business_name')
                    ->label('Workspace')
                    ->placeholder(fn (WorkspaceMember $record): string =>
                        $record->workspace?->name ?? 'Deleted workspace')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Partner')
                    ->placeholder('Not accepted'),
                TextColumn::make('invited_email')
                    ->label('Invited Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('invitation_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'pending' => 'warning',
                        'removed', 'revoked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('invitation_expires_at')
                    ->label('Invite Expires')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('accepted_at')
                    ->label('Accepted At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                ActionGroup::make([
                    Action::make('revokeInvitation')
                        ->label('Revoke Invitation')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (WorkspaceMember $record): bool => $record->isPending())
                        ->action(function (WorkspaceMember $record): void {
                            app(AccessAdministrationService::class)
                                ->revokePartnerInvitation(auth()->user(), $record);

                            Notification::make()
                                ->title('Partner invitation revoked')
                                ->success()
                                ->send();
                        }),
                    Action::make('removePartner')
                        ->label('Remove Partner Access')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (WorkspaceMember $record): bool => $record->isAccepted())
                        ->action(function (WorkspaceMember $record): void {
                            app(AccessAdministrationService::class)
                                ->removePartnerAccess(auth()->user(), $record);

                            Notification::make()
                                ->title('Partner access removed')
                                ->success()
                                ->send();
                        }),
                ])->label('Manage'),
            ]);
    }
}
