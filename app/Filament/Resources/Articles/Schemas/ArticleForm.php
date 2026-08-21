<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ArticleForm
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

                // Optional: GeneratesSlug fills this from the English words in
                // the title when left blank. Kept editable so an existing
                // article's URL can still be seen and, if genuinely needed,
                // corrected before it is published and shared.
                TextInput::make('slug')
                    ->label('Slug (URL) — ဗလာထားနိုင်ပါသည်')
                    ->helperText('ဗလာထားပါက ခေါင်းစဉ်ထဲက အင်္ဂလိပ်စာလုံးများမှ အလိုအလျောက် ဖန်တီးပါမည်။')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('category')
                    ->label('အမျိုးအစား')
                    ->options([
                        'Agreement' => 'Agreement — စာချုပ်',
                        'Profit split' => 'Profit split — အမြတ်ခွဲဝေမှု',
                        'Exit' => 'Exit — ထွက်ခွာမှု',
                        'Structure' => 'Structure — ဖွဲ့စည်းပုံ',
                        'Decisions' => 'Decisions — ဆုံးဖြတ်ချက်',
                    ])
                    ->required()
                    ->native(false),

                // Bangkok time in, UTC in the database — see VideoForm.
                DateTimePicker::make('published_at')
                    ->label('ဖော်ပြမည့် ရက်စွဲ (ထိုင်းအချိန်)')
                    ->helperText('ဗလာထားပါက မဖော်ပြသေးပါ (draft)။')
                    ->timezone(config('app.display_timezone'))
                    ->seconds(false),

                Textarea::make('excerpt')
                    ->label('အကျဉ်းချုပ်')
                    ->helperText('ကတ်ပေါ်တွင် ပြမည့် စာကြောင်း တစ်ကြောင်း နှစ်ကြောင်း။')
                    ->required()
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),

                FileUpload::make('cover_image')
                    ->label('ပုံ')
                    ->image()
                    ->disk('public')
                    ->directory('articles')
                    ->imageEditor()
                    ->maxSize(3072)
                    ->helperText('မထည့်လည်း ရပါသည်။ အများဆုံး 3MB။')
                    ->columnSpanFull(),

                Textarea::make('body')
                    ->label('စာကိုယ်')
                    ->helperText('စာကြောင်း တစ်ကြောင်း အလွတ်ခုန်ပါက အပိုဒ်အသစ် ဖြစ်ပါမည်။')
                    ->required()
                    ->rows(20)
                    ->columnSpanFull(),
            ]);
    }
}
