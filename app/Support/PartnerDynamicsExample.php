<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| Partner Dynamics — Illustrative Examples
|--------------------------------------------------------------------------
|
| Resolves the example keys listed against each profile in
| config/partner_dynamics_content.php into the shared records in
| config/partner_dynamics_examples.php.
|
| Initials are derived rather than stored so a name and its mark can never
| disagree.
|
*/

class PartnerDynamicsExample
{
    /**
     * Resolve a list of example keys.
     *
     * Unknown keys are dropped rather than rendered as an empty card.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array{key: string, name: string, note: string, initials: string}>
     */
    public static function resolve(array $keys): array
    {
        $records = config('partner_dynamics_examples', []);
        $resolved = [];

        foreach ($keys as $key) {

            if (! isset($records[$key]['name'])) {
                continue;
            }

            $name = (string) $records[$key]['name'];

            $resolved[] = [
                'key' => (string) $key,
                'name' => $name,
                'note' => (string) ($records[$key]['note'] ?? ''),
                'initials' => self::initials($name),
            ];
        }

        return $resolved;
    }

    /**
     * First letter of the first and last word: "Steve Jobs" -> "SJ".
     */
    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return '';
        }

        $first = mb_substr($words[0], 0, 1);

        if (count($words) === 1) {
            return mb_strtoupper($first);
        }

        $last = mb_substr($words[count($words) - 1], 0, 1);

        return mb_strtoupper($first . $last);
    }

    /**
     * Every example key defined in the shared config.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(
            config('partner_dynamics_examples', [])
        );
    }
}
