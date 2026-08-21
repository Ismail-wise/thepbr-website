<?php

namespace App\Filament\Resources\Videos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class VideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('ခေါင်းစဉ်')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->helperText('အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာ သုံးပါ။ ဥပမာ — how-to-split-profit')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),

                // Accepts any YouTube link shape; the model normalises it to a
                // bare ID on save, so whoever adds a video can paste whatever
                // the YouTube Share button gave them.
                TextInput::make('youtube_id')
                    ->label('YouTube လင့်ခ် သို့မဟုတ် ID')
                    ->helperText('YouTube လင့်ခ် အပြည့် ကူးထည့်နိုင်ပါသည်။ ဥပမာ — https://youtu.be/dQw4w9WgXcQ')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('category')
                    ->label('အမျိုးအစား')
                    ->options([
                        'Getting started' => 'Getting started — စတင်ခြင်း',
                        'Capital' => 'Capital — မတည်ငွေ',
                        'Ownership' => 'Ownership — ပိုင်ဆိုင်မှု',
                        'Governance' => 'Governance — အုပ်ချုပ်မှု',
                        'Exit' => 'Exit — ထွက်ခွာမှု',
                    ])
                    ->native(false),

                TextInput::make('duration_minutes')
                    ->label('ကြာချိန် (မိနစ်)')
                    ->helperText('မထည့်လည်း ရပါသည်။')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(600),

                DateTimePicker::make('published_at')
                    ->label('ဖော်ပြမည့် ရက်စွဲ')
                    ->helperText('ဗလာထားပါက မဖော်ပြသေးပါ (draft)။ နောင်ရက်စွဲ ထားပါက ထိုအချိန်ရောက်မှ ပေါ်ပါမည်။')
                    ->seconds(false)
                    ->columnSpanFull(),

                Textarea::make('excerpt')
                    ->label('အကျဉ်းချုပ်')
                    ->helperText('ကတ်ပေါ်နှင့် share preview တွင် ပြမည့် စာကြောင်း တစ်ကြောင်း နှစ်ကြောင်း။')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }
}
