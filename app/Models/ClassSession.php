<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    protected $fillable = [
        'title', 'starts_on', 'ends_on', 'mode', 'location',
        'time_note', 'fee', 'capacity', 'enrolled', 'is_visible',
    ];

    protected $casts = [
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'is_visible' => 'boolean',
    ];

    public const MODES = [
        'in_person' => 'အခန်းတွင်း',
        'online'    => 'Online',
    ];

    /* ---------- helpers ---------- */

    public function getIsUpcomingAttribute(): bool
    {
        return $this->starts_on->gte(now()->startOfDay());
    }

    public function getSeatsLeftAttribute(): int
    {
        return max(0, $this->capacity - $this->enrolled);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->capacity > 0 && $this->seats_left === 0;
    }

    /** Percentage of seats taken — drives the little meter bar. */
    public function getFilledPercentAttribute(): int
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return (int) round($this->enrolled / $this->capacity * 100);
    }

    public function getModeLabelAttribute(): string
    {
        $label = self::MODES[$this->mode] ?? $this->mode;

        return $this->mode === 'online'
            ? "{$this->location} — Online"
            : "{$this->location} — {$label}";
    }

    /* ---------- Burmese dates ---------- */

    private const MONTHS = [
        1 => 'ဇန်နဝါရီ', 2 => 'ဖေဖော်ဝါရီ', 3 => 'မတ်', 4 => 'ဧပြီ',
        5 => 'မေ', 6 => 'ဇွန်', 7 => 'ဇူလိုင်', 8 => 'ဩဂုတ်',
        9 => 'စက်တင်ဘာ', 10 => 'အောက်တိုဘာ', 11 => 'နိုဝင်ဘာ', 12 => 'ဒီဇင်ဘာ',
    ];

    private const DIGITS = ['0'=>'၀','1'=>'၁','2'=>'၂','3'=>'၃','4'=>'၄','5'=>'၅','6'=>'၆','7'=>'၇','8'=>'၈','9'=>'၉'];

    public static function mmNumber(int|string $n): string
    {
        return strtr((string) $n, self::DIGITS);
    }

    public function getMmMonthAttribute(): string
    {
        return self::MONTHS[$this->starts_on->month];
    }

    public function getMmDayAttribute(): string
    {
        return self::mmNumber(str_pad((string) $this->starts_on->day, 2, '0', STR_PAD_LEFT));
    }

    /** "ဇူလိုင် ၁၉–၂၀" or "ဇူလိုင် ၁၉" */
    public function getMmRangeAttribute(): string
    {
        $out = $this->mm_month . ' ' . self::mmNumber($this->starts_on->day);

        if ($this->ends_on && ! $this->ends_on->isSameDay($this->starts_on)) {
            $out .= '–' . self::mmNumber($this->ends_on->day);
        }

        return $out;
    }

    /** "၂ ရက်" or "၁ ရက်" */
    public function getMmDurationAttribute(): string
    {
        $days = $this->ends_on
            ? $this->starts_on->diffInDays($this->ends_on) + 1
            : 1;

        return self::mmNumber($days) . ' ရက်';
    }
}
