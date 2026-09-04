<?php

use App\Services\PbrTools\PbrDomainRegistryService;

test('v1 registry remains unchanged while v2 master registry is parallel', function () {
    $registry = app(PbrDomainRegistryService::class);

    expect($registry->keys())->toBe([
        'capital',
        'ownership',
        'contribution',
        'distribution',
        'financial_controls',
        'governance',
        'exit',
        'continuity',
        'share_transfer',
        'dispute_resolution',
    ]);

    expect(
        config('pbr_canonical_data.domains')
    )->toHaveCount(10);

    expect($registry->masterKeys())->toBe([
        'capital',
        'contribution',
        'ownership',
        'governance',
        'operations',
        'financial_controls',
        'distribution',
        'risk',
        'exit',
        'continuity',
        'share_transfer',
        'dispute_resolution',
        'implementation_review',
        'dissolution',
    ]);
});

test('v2 master registry exposes the approved fourteen chapter order', function () {
    $registry = app(PbrDomainRegistryService::class);

    $domains = $registry->masterAll();

    expect($domains)->toHaveCount(14);

    $expectedChapter = 1;

    foreach ($domains as $domainKey => $domain) {
        expect($domain['domain_key'])
            ->toBe($domainKey)
            ->and($domain['chapter_number'])
            ->toBe($expectedChapter);

        $expectedChapter++;
    }
});

test('v2 canonical contract matches the v2 master registry', function () {
    $registry = app(PbrDomainRegistryService::class);

    $domains = config(
        'pbr_canonical_data.domains_v2',
        []
    );

    expect($domains)
        ->toBeArray()
        ->toHaveCount(14);

    expect(array_keys($domains))
        ->toBe($registry->masterKeys());

    foreach ($domains as $domainKey => $contract) {
        $masterDomain = $registry->masterDomain(
            $domainKey
        );

        expect($contract['chapter'])
            ->toBe($masterDomain['chapter_number'])
            ->and($contract['name'])
            ->toBe($masterDomain['name_en']);
    }
});

test('v2 canonical dependencies match the approved architecture', function () {
    $domains = config(
        'pbr_canonical_data.domains_v2',
        []
    );

    $expected = [
        'capital' => [],
        'contribution' => [
            'capital',
        ],
        'ownership' => [
            'contribution',
        ],
        'governance' => [
            'ownership',
        ],
        'operations' => [
            'governance',
        ],
        'financial_controls' => [
            'capital',
            'governance',
            'operations',
        ],
        'distribution' => [
            'ownership',
            'governance',
            'operations',
            'financial_controls',
        ],
        'risk' => [
            'governance',
            'operations',
            'financial_controls',
        ],
        'exit' => [
            'ownership',
            'governance',
            'operations',
            'financial_controls',
        ],
        'continuity' => [
            'ownership',
            'governance',
            'operations',
            'financial_controls',
            'exit',
        ],
        'share_transfer' => [
            'ownership',
            'governance',
            'exit',
        ],
        'dispute_resolution' => [
            'governance',
        ],
        'implementation_review' => [
            'capital',
            'contribution',
            'ownership',
            'governance',
            'operations',
            'financial_controls',
            'distribution',
            'risk',
            'exit',
            'continuity',
            'share_transfer',
            'dispute_resolution',
        ],
        'dissolution' => [
            'ownership',
            'governance',
            'operations',
            'financial_controls',
            'risk',
            'exit',
            'continuity',
            'dispute_resolution',
        ],
    ];

    foreach ($expected as $domainKey => $readsFrom) {
        expect(
            $domains[$domainKey]['reads_from']
        )->toBe($readsFrom);
    }
});

test('every v2 dependency references a valid different v2 domain', function () {
    $domains = config(
        'pbr_canonical_data.domains_v2',
        []
    );

    $keys = array_keys($domains);

    foreach ($domains as $domainKey => $contract) {
        foreach ($contract['reads_from'] ?? [] as $dependency) {
            expect(
                in_array(
                    $dependency,
                    $keys,
                    true
                )
            )->toBeTrue(
                $domainKey.' references unknown V2 dependency '.$dependency
            );

            expect($dependency)
                ->not->toBe($domainKey);
        }
    }
});
