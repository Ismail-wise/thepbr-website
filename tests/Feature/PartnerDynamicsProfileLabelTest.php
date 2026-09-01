<?php

use App\Support\PartnerDynamicsProfile;

test('the engine defines exactly eight profiles', function () {
    expect(PartnerDynamicsProfile::keys())->toHaveCount(8);
});

test('every profile has an english name, a burmese title and a description', function () {
    foreach (PartnerDynamicsProfile::keys() as $key) {
        expect(PartnerDynamicsProfile::label($key))->not->toBeEmpty();
        expect(PartnerDynamicsProfile::titleMm($key))->not->toBeEmpty();
        expect(PartnerDynamicsProfile::description($key))->not->toBeEmpty();
    }
});

test('content and visual configs cover exactly the engine profile keys', function () {
    $engineKeys = PartnerDynamicsProfile::keys();

    // The content config also carries shared copy at the top level —
    // 'example_disclaimer' is a string, not a profile — so compare only
    // its array entries.
    $contentKeys = array_keys(
        array_filter(config('partner_dynamics_content'), 'is_array')
    );

    $visualKeys = array_keys(config('partner_dynamics_visuals'));

    sort($engineKeys);
    sort($contentKeys);
    sort($visualKeys);

    expect($contentKeys)->toBe($engineKeys);
    expect($visualKeys)->toBe($engineKeys);
});

test('the shared example disclaimer is not mistaken for a profile', function () {
    $content = config('partner_dynamics_content');

    expect($content)->toHaveKey('example_disclaimer');
    expect($content['example_disclaimer'])->toBeString();
    expect(PartnerDynamicsProfile::keys())
        ->not->toContain('example_disclaimer');
});

test('the visuals config no longer carries its own profile naming', function () {
    foreach (config('partner_dynamics_visuals') as $visual) {
        expect($visual)->not->toHaveKey('name');
        expect($visual)->not->toHaveKey('badge_mm');
        expect($visual)->toHaveKeys([
            'primary',
            'secondary',
            'soft',
            'light',
            'illustration',
        ]);
    }
});

test('describe returns all three labels together', function () {
    $profile = PartnerDynamicsProfile::describe('visionary');

    expect($profile['key'])->toBe('visionary');
    expect($profile['name'])->toBe('Visionary');
    expect($profile['title_mm'])->not->toBeEmpty();
    expect($profile['description'])->not->toBeEmpty();
});

test('labels returns one english name per profile key', function () {
    $labels = PartnerDynamicsProfile::labels();

    expect($labels)->toHaveCount(8);
    expect(array_keys($labels))->toBe(PartnerDynamicsProfile::keys());
    expect($labels['visionary'])->toBe('Visionary');
});

test('an unknown or empty profile key degrades safely', function () {
    expect(PartnerDynamicsProfile::label('nonexistent'))->toBe('Nonexistent');
    expect(PartnerDynamicsProfile::titleMm('nonexistent'))->toBe('');
    expect(PartnerDynamicsProfile::description('nonexistent'))->toBe('');
    expect(PartnerDynamicsProfile::label(null))->toBe('');
});
