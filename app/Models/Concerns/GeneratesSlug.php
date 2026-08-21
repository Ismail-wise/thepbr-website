<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Generates a URL slug from a title, so nobody has to type one.
 *
 * The titles on this site are Burmese with English terms embedded —
 * "Partnership Business မစခင် ... အချက် (၇) ချက်". Burmese characters
 * percent-encode into unreadable URLs
 * (/articles/%E1%80%99%E1%80%AD%E1%80%90...), which defeats the point of
 * having a slug at all: Google reads the words in a URL, and a person
 * deciding whether to click a shared link reads them too.
 *
 * So the slug is built from the ASCII words in the title only. When a title
 * carries no English at all, it falls back to a stable "article-12" form
 * rather than producing an empty slug.
 */
trait GeneratesSlug
{
    protected static function bootGeneratesSlug(): void
    {
        static::saving(function (Model $model): void {
            // Only generate when empty. An existing slug is never regenerated
            // from an edited title: the old URL may already be shared on
            // Facebook or indexed by Google, and silently changing it breaks
            // every one of those links.
            if (filled($model->slug)) {
                return;
            }

            $model->slug = $model->generateUniqueSlug((string) $model->title);
        });
    }

    public function generateUniqueSlug(string $title): string
    {
        $base = static::slugFromTitle($title);

        if ($base === '') {
            $base = 'post';
        }

        $slug = $base;
        $suffix = 2;

        // "Partnership Business" is a phrase that will recur across articles,
        // so collisions are expected rather than exceptional. The suffix keeps
        // the readable stem and stays stable once saved.
        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Reduce a mixed Burmese/English title to lowercase ASCII words.
     */
    public static function slugFromTitle(string $title): string
    {
        // Everything that is not a Latin letter or digit becomes a separator.
        // This drops Burmese script, punctuation, em dashes and bracketed
        // Burmese numerals in one pass.
        $ascii = preg_replace('/[^A-Za-z0-9]+/', ' ', $title) ?? '';

        $words = array_filter(
            explode(' ', strtolower($ascii)),
            // Single characters are usually initials or stray letters left
            // behind by stripping Burmese, and add nothing to a URL.
            static fn (string $word): bool => mb_strlen($word) > 1
        );

        if ($words === []) {
            return '';
        }

        // 60 characters keeps the URL readable in a Facebook share preview,
        // which truncates well before a browser address bar does.
        return trim(mb_substr(implode('-', $words), 0, 60), '-');
    }
}
