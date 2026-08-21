<?php

namespace App\Models;

use App\Models\Concerns\GeneratesSlug;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use GeneratesSlug;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'category',
        'youtube_id',
        'duration_minutes',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    /** Use the slug in URLs instead of the id, matching Article. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Accept any YouTube link shape and store only the ID.
     *
     * Whoever adds a video will paste whatever the YouTube UI gave them —
     * a watch link, a Share short link, a Shorts link, sometimes with a
     * ?t= timestamp or a ?si= share token attached. Normalising on write
     * means the rest of the app never has to think about it, and a saved
     * record cannot carry a tracking parameter into an embed.
     */
    public function setYoutubeIdAttribute(?string $value): void
    {
        $this->attributes['youtube_id'] = self::extractYoutubeId($value);
    }

    /**
     * Pull the 11-character video ID out of any common YouTube URL form.
     *
     * Returns the trimmed input unchanged when nothing matches, so a bad
     * value surfaces as a broken embed during review rather than being
     * silently discarded and saved as an empty string.
     */
    public static function extractYoutubeId(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $patterns = [
            '~youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~i',
            '~youtu\.be/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/live/([A-Za-z0-9_-]{11})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $m) === 1) {
                return $m[1];
            }
        }

        // Already a bare ID.
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $value) === 1) {
            return $value;
        }

        return $value;
    }

    /**
     * Embed URL on the no-cookie host.
     *
     * youtube-nocookie.com does not set tracking cookies until the viewer
     * actually plays the video, which keeps the site's cookie promise in the
     * privacy policy honest: only cookies needed for login and security.
     */
    public function getEmbedUrlAttribute(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'
            .$this->youtube_id
            .'?rel=0&modestbranding=1';
    }

    /**
     * Poster image.
     *
     * hqdefault is used rather than maxresdefault because maxresdefault only
     * exists for videos uploaded above a certain resolution and 404s
     * otherwise, which would leave a broken thumbnail with no fallback.
     */
    public function getThumbnailUrlAttribute(): string
    {
        return 'https://i.ytimg.com/vi/'.$this->youtube_id.'/hqdefault.jpg';
    }

    public function getWatchUrlAttribute(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtube_id;
    }

    /** "၂၀၂၆ ဩဂုတ် ၂၁" with Burmese digits, matching Article. */
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
        $day = strtr((string) $this->published_at->day, $digits);

        return $year.' '.$months[$this->published_at->month].' '.$day;
    }
}
