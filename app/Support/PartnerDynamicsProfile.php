<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| Partner Dynamics — Profile Naming
|--------------------------------------------------------------------------
|
| Single source of truth for what each of the eight profiles is CALLED.
|
|   English name  ->  config/partner_dynamics.php  ('profiles.*.name')
|                     Already read by PartnerMatchRecommendationService,
|                     so the engine and the views cannot drift apart.
|
|   Burmese label ->  config/partner_dynamics_content.php ('*.title_mm')
|   Description   ->  config/partner_dynamics_content.php ('*.who_you_are')
|
| Scoring logic is untouched. This class only resolves labels.
|
*/

class PartnerDynamicsProfile
{
    /**
     * Every profile key the assessment engine can return.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(
            config('partner_dynamics.profiles', [])
        );
    }

    /**
     * profile key => English name.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (
            config('partner_dynamics.profiles', [])
            as $key => $profile
        ) {
            $labels[$key] =
                $profile['name']
                ?? ucfirst((string) $key);
        }

        return $labels;
    }

    /**
     * English name for one profile.
     */
    public static function label(?string $key): string
    {
        if (blank($key)) {
            return '';
        }

        return (string) (
            config("partner_dynamics.profiles.{$key}.name")
            ?? ucfirst($key)
        );
    }

    /**
     * Burmese label for one profile.
     */
    public static function titleMm(?string $key): string
    {
        if (blank($key)) {
            return '';
        }

        return (string) config(
            "partner_dynamics_content.{$key}.title_mm",
            ''
        );
    }

    /**
     * Burmese description ("who you are") for one profile.
     */
    public static function description(?string $key): string
    {
        if (blank($key)) {
            return '';
        }

        return (string) config(
            "partner_dynamics_content.{$key}.who_you_are",
            ''
        );
    }

    /**
     * All three labels for one profile, resolved together.
     *
     * @return array{key: string, name: string, title_mm: string, description: string}
     */
    public static function describe(?string $key): array
    {
        return [
            'key' => (string) $key,
            'name' => self::label($key),
            'title_mm' => self::titleMm($key),
            'description' => self::description($key),
        ];
    }
}
