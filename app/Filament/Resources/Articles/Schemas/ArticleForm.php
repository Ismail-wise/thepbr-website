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

                TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->helperText('အင်္ဂလိပ်စာလုံး၊ နံပါတ်နှင့် - သာ သုံးပါ။ ဥပမာ — profit-split-guide')
                    ->required()
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

                DateTimePicker::make('published_at')
                    ->label('ဖော်ပြမည့် ရက်စွဲ')
                    ->helperText('ဗလာထားပါက မဖော်ပြသေးပါ (draft)။')
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