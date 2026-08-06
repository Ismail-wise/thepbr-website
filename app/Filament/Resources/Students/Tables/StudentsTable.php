<?php

namespace App\Filament\Resources\Students\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('classSession.title')
                    ->label('Class Batch')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('usedAccessCode.code_last4')
                    ->label('Access Code')
                    ->formatStateUsing(fn (?string $state): string => $state ? '•••• '.$state : '—'),
                TextColumn::make('account_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('portal_access_expires_at')
                    ->label('Portal Expiry')
                    ->dateTime()
                    ->placeholder('No expiry')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registered At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('account_status')
                    ->label('Account Status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),
                SelectFilter::make('class_session_id')
                    ->label('Class Batch')
                    ->relationship('classSession', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
