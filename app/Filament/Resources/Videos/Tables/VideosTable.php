<?php

namespace App\Filament\Resources\Videos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Pulled live from YouTube's CDN. If a thumbnail is blank in
                // this list, the stored ID is wrong — which makes the table
                // itself the check that the link was pasted correctly.
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->height(44),

                TextColumn::make('title')
                    ->label('ခေါင်းစဉ်')
                    ->searchable()
                    ->limit(48)
                    ->wrap(),

                TextColumn::make('category')
                    ->label('အမျိုးအစား')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('duration_minutes')
                    ->label('မိနစ်')
                    ->alignEnd()
                    ->toggleable(),

                // Draft, Scheduled and Published are three different states and
                // a bare date hides the difference: a future date looks
                // published until someone checks the calendar.
                TextColumn::make('published_at')
                    ->label('အခြေအနေ')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match (true) {
                        $state === null => 'Draft',
                        $state->isFuture() => 'Scheduled',
                        default => 'Published',
                    })
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        $state->isFuture() => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('youtube_id')
                    ->label('YouTube ID')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('အမျိုးအစား')
                    ->options([
                        'Getting started' => 'Getting started',
                        'Capital' => 'Capital',
                        'Ownership' => 'Ownership',
                        'Governance' => 'Governance',
                        'Exit' => 'Exit',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
