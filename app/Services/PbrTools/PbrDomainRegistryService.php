<?php

namespace App\Services\PbrTools;

use LogicException;

class PbrDomainRegistryService
{
    /**
     * Stable domain-key view of the current Business Operating Area registry.
     *
     * The existing numeric chapter key is retained only as legacy catalog
     * metadata. Application code should use domain_key as business identity.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $areas = config(
            'pbr_business_operating_system.areas',
            []
        );

        if (! is_array($areas)) {
            throw new LogicException(
                'PBR business operating area registry must be an array.'
            );
        }

        $domains = [];

        foreach ($areas as $legacyChapterNumber => $area) {
            if (! is_array($area)) {
                throw new LogicException(
                    'Every PBR business operating area must be an array.'
                );
            }

            $domainKey = trim(
                (string) ($area['domain'] ?? '')
            );

            if ($domainKey === '') {
                throw new LogicException(
                    'Every PBR business operating area must declare a stable domain key.'
                );
            }

            if (array_key_exists($domainKey, $domains)) {
                throw new LogicException(
                    'Duplicate PBR domain key: '.$domainKey
                );
            }

            $domains[$domainKey] = array_merge(
                $area,
                [
                    'domain_key' => $domainKey,
                    'legacy_chapter_number' =>
                        (int) $legacyChapterNumber,
                ]
            );
        }

        return $domains;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $domainKey): bool
    {
        return array_key_exists(
            $domainKey,
            $this->all()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $domainKey): ?array
    {
        return $this->all()[$domainKey] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function domain(string $domainKey): array
    {
        $domain = $this->find($domainKey);

        abort_unless(
            is_array($domain),
            404
        );

        return $domain;
    }

    /**
     * Compatibility resolver for the existing V1 catalog.
     *
     * Chapter number is not business identity. It is only a legacy catalog
     * mapping used while the runtime is migrated to stable domain keys.
     */
    public function domainForLegacyChapter(
        int $chapterNumber
    ): string {
        foreach ($this->all() as $domainKey => $domain) {
            if (
                (int) (
                    $domain[
                        'legacy_chapter_number'
                    ] ?? 0
                ) === $chapterNumber
            ) {
                return $domainKey;
            }
        }

        abort(404);
    }

    public function legacyChapterForDomain(
        string $domainKey
    ): int {
        $domain = $this->domain($domainKey);

        return (int) (
            $domain['legacy_chapter_number']
            ?? 0
        );
    }

    /**
     * @return array<int, string>
     */
    public function legacyChapterMap(): array
    {
        $map = [];

        foreach ($this->all() as $domainKey => $domain) {
            $chapterNumber = (int) (
                $domain[
                    'legacy_chapter_number'
                ] ?? 0
            );

            $map[$chapterNumber] = $domainKey;
        }

        ksort($map);

        return $map;
    }
}
