<?php

namespace App\Filament\Resources\Articles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('ပုံ')
                    ->square(),

                TextColumn::make('title')
                    ->label('ခေါင်းစဉ်')
                    ->searchable()
                    ->wrap()
                    ->limit(70),

                TextColumn::make('category')
                    ->label('အမျိုးအစား')
                    ->badge()
                    ->searchable(),

                TextColumn::make('published_at')
                    ->label('ဖော်ပြသည့် ရက်')
                    ->dateTime('Y-m-d')
                    ->placeholder('မဖော်ပြသေး')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('အမျိုးအစား')
                    ->options([
                        'Agreement' => 'Agreement',
                        'Profit split' => 'Profit split',
                        'Exit' => 'Exit',
                        'Structure' => 'Structure',
                        'Decisions' => 'Decisions',
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