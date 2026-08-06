<?php

namespace App\Filament\Resources\StudentAccessCodes\Tables;

use App\Models\StudentAccessCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentAccessCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('plain_code')
                    ->label('Access Code')
                    ->copyable()
                    ->copyMessage('Code copied')
                    ->copyMessageDuration(1500),
                TextColumn::make('classSession.title')
                    ->label('Class Batch')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'used' => 'info',
                        'expired' => 'warning',
                        'disabled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime()
                    ->placeholder('No expiry')
                    ->sortable(),
                TextColumn::make('usedBy.name')
                    ->label('Used By')
                    ->placeholder('Not used')
                    ->searchable(),
                TextColumn::make('used_at')
                    ->label('Used At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('disable')
                    ->label('Disable')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StudentAccessCode $record): bool => $record->status === 'available')
                    ->action(function (StudentAccessCode $record): void {
                        $record->update(['status' => 'disabled']);

                        Notification::make()
                            ->title('Access code disabled')
                            ->success()
                            ->send();
                    }),
                Action::make('enable')
                    ->label('Enable')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (StudentAccessCode $record): bool => $record->status === 'disabled')
                    ->action(function (StudentAccessCode $record): void {
                        $record->update(['status' => 'available']);

                        Notification::make()
                            ->title('Access code enabled')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
