<?php

test('PBR course catalog contains ten connected chapters and sixty four unique tools', function () {
    $chapters = config('pbr_course.chapters', []);
    $toolKeys = collect($chapters)
        ->flatMap(fn (array $chapter) => collect($chapter['tools'] ?? [])->pluck('key'))
        ->values();

    expect($chapters)->toHaveCount(10)
        ->and($toolKeys)->toHaveCount(64)
        ->and($toolKeys->unique())->toHaveCount(64)
        ->and(collect($chapters)->pluck('number')->all())->toBe(range(1, 10));

    $domains = \App\Services\PbrTools\PbrOperatingSystemService::DOMAINS;

    expect(array_keys($domains))->toBe(range(1, 10))
        ->and(array_values($domains))->toBe([
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
});
