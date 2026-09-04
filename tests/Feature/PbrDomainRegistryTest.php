<?php

use App\Services\PbrTools\PbrDomainRegistryService;
use App\Services\PbrTools\PbrOperatingSystemService;

test('business domains resolve through stable domain keys', function () {
    $registry = app(PbrDomainRegistryService::class);

    $domains = $registry->all();

    expect($domains)->not->toBeEmpty();

    foreach ($domains as $domainKey => $domain) {
        expect($domain['domain_key'])
            ->toBe($domainKey)
            ->and($domain['domain'])
            ->toBe($domainKey)
            ->and(
                $registry->domainForLegacyChapter(
                    (int) $domain['legacy_chapter_number']
                )
            )
            ->toBe($domainKey)
            ->and(
                $registry->legacyChapterForDomain(
                    $domainKey
                )
            )
            ->toBe(
                (int) $domain['legacy_chapter_number']
            );
    }
});

test('legacy chapter map remains compatible with the existing v1 runtime', function () {
    $registry = app(PbrDomainRegistryService::class);

    expect(
        $registry->legacyChapterMap()
    )->toBe(
        PbrOperatingSystemService::DOMAINS
    );
});

test('operating system chapter compatibility delegates to the domain registry', function () {
    $operatingSystem =
        app(PbrOperatingSystemService::class);

    foreach (
        PbrOperatingSystemService::DOMAINS
        as $chapterNumber => $domainKey
    ) {
        expect(
            $operatingSystem->domainForChapter(
                $chapterNumber
            )
        )->toBe($domainKey);
    }
});

test('business registry and canonical contract expose the same current domain identities', function () {
    $registry = app(PbrDomainRegistryService::class);

    $canonicalDomains = config(
        'pbr_canonical_data.domains',
        []
    );

    expect(array_keys($canonicalDomains))
        ->toBe($registry->keys());
});

test('legacy area compatibility resolves metadata through the stable registry', function () {
    $registry = app(PbrDomainRegistryService::class);

    foreach (
        $registry->legacyChapterMap()
        as $chapterNumber => $domainKey
    ) {
        $area = $registry->areaForLegacyChapter(
            $chapterNumber
        );

        expect($area['domain_key'])
            ->toBe($domainKey)
            ->and(
                $registry->findByLegacyChapter(
                    $chapterNumber
                )
            )
            ->toBe($area);
    }

    expect(
        $registry->findByLegacyChapter(999)
    )->toBeNull();
});
