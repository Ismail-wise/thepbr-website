<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| Partner Dynamics — Dimension Naming
|--------------------------------------------------------------------------
|
| Single source of truth for what each of the eight dimensions is called.
| Everything reads config/partner_dynamics_dimensions.php through here.
|
| Scoring is untouched. This class only resolves labels.
|
*/

class PartnerDynamicsDimension
{
    /**
     * The eight dimension keys, in display order.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(
            config('partner_dynamics_dimensions', [])
        );
    }

    /**
     * key => English label.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (
            config('partner_dynamics_dimensions', [])
            as $key => $dimension
        ) {
            $labels[$key] = $dimension['name'] ?? ucfirst((string) $key);
        }

        return $labels;
    }

    /**
     * key => fuller Burmese label.
     *
     * @return array<string, string>
     */
    public static function labelsMm(): array
    {
        $labels = [];

        foreach (
            config('partner_dynamics_dimensions', [])
            as $key => $dimension
        ) {
            $labels[$key] =
                $dimension['name_mm']
                ?? $dimension['name']
                ?? ucfirst((string) $key);
        }

        return $labels;
    }

    /**
     * English label for one dimension.
     */
    public static function label(?string $key): string
    {
        if (blank($key)) {
            return '';
        }

        return (string) (
            config("partner_dynamics_dimensions.{$key}.name")
            ?? ucfirst($key)
        );
    }

    /**
     * Burmese label for one dimension.
     */
    public static function labelMm(?string $key): string
    {
        if (blank($key)) {
            return '';
        }

        return (string) (
            config("partner_dynamics_dimensions.{$key}.name_mm")
            ?? self::label($key)
        );
    }
}
