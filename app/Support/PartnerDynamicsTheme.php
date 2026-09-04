<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| Partner Dynamics — Visual Theme
|--------------------------------------------------------------------------
|
| Resolves a profile key into the CSS custom properties the Partner
| Dynamics pages are drawn with.
|
| Before an assessment is taken there is no profile, so the brand palette
| is used. Afterwards every Partner Dynamics surface takes the reader's
| own colour.
|
| The boundary is deliberate. These variables are set on the Partner
| Dynamics section only, never on the layout, because green, orange and
| red already carry meaning in the Business OS — healthy, attention,
| danger — and a Visionary's red must not turn the dashboard into a
| warning.
|
*/

class PartnerDynamicsTheme
{
    /**
     * The palette used before an assessment exists, and whenever a
     * profile key cannot be resolved. Matches --forest / --sage in
     * student-portal.css.
     */
    public const BRAND = [
        'primary' => '#157f09',
        'secondary' => '#3da32f',
        'soft' => '#eaf5e4',
        'light' => '#cfe0c7',
    ];

    /**
     * The four colours for one profile, or the brand palette.
     *
     * @return array{primary: string, secondary: string, soft: string, light: string}
     */
    public static function palette(?string $profileKey): array
    {
        if (blank($profileKey)) {
            return self::BRAND;
        }

        $visual = config("partner_dynamics_visuals.{$profileKey}");

        if (! is_array($visual)) {
            return self::BRAND;
        }

        return [
            'primary' => $visual['primary'] ?? self::BRAND['primary'],
            'secondary' => $visual['secondary'] ?? self::BRAND['secondary'],
            'soft' => $visual['soft'] ?? self::BRAND['soft'],
            'light' => $visual['light'] ?? self::BRAND['light'],
        ];
    }

    /**
     * The palette as a style attribute value.
     *
     * Pass a secondary profile to expose --pd-secondary-profile as well,
     * which the result page uses for the secondary score.
     */
    public static function variables(
        ?string $profileKey,
        ?string $secondaryProfileKey = null
    ): string {
        $palette = self::palette($profileKey);

        $declarations = [
            '--pd-primary: ' . $palette['primary'],
            '--pd-secondary: ' . $palette['secondary'],
            '--pd-soft: ' . $palette['soft'],
            '--pd-light: ' . $palette['light'],
        ];

        if (filled($secondaryProfileKey)) {
            $declarations[] =
                '--pd-secondary-profile: '
                . self::palette($secondaryProfileKey)['primary'];
        }

        return implode('; ', $declarations) . ';';
    }

    /**
     * Whether a real profile palette was resolved, rather than the brand
     * fallback. Useful for deciding whether to show themed chrome.
     */
    public static function isThemed(?string $profileKey): bool
    {
        return self::palette($profileKey) !== self::BRAND;
    }
}
