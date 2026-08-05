<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'category',
        'cover_image',
        'body',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /** Use the slug in URLs instead of the id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** "2026 ဇူလိုင် 15" with Burmese digits. */
    public function getBurmeseDateAttribute(): string
    {
        if (! $this->published_at) {
            return 'မဖော်ပြသေး';
        }

        $months = [
            1 => 'ဇန်နဝါရီ', 2 => 'ဖေဖော်ဝါရီ', 3 => 'မတ်', 4 => 'ဧပြီ',
            5 => 'မေ', 6 => 'ဇွန်', 7 => 'ဇူလိုင်', 8 => 'ဩဂုတ်',
            9 => 'စက်တင်ဘာ', 10 => 'အောက်တိုဘာ', 11 => 'နိုဝင်ဘာ', 12 => 'ဒီဇင်ဘာ',
        ];

        $digits = ['0'=>'၀','1'=>'၁','2'=>'၂','3'=>'၃','4'=>'၄','5'=>'၅','6'=>'၆','7'=>'၇','8'=>'၈','9'=>'၉'];

        $year = strtr((string) $this->published_at->year, $digits);
        $day  = strtr(str_pad((string) $this->published_at->day, 2, '0', STR_PAD_LEFT), $digits);

        return "{$year} {$months[$this->published_at->month]} {$day}";
    }

    /** Split the body on blank lines so each becomes a paragraph. */
    public function getParagraphsAttribute(): array
    {
        $parts = preg_split('/\R\s*\R/u', trim((string) $this->body));

        return array_values(array_filter(array_map(
            fn ($p) => trim(preg_replace('/\R/u', ' ', $p)),
            $parts ?: []
        )));
    }
}
