<?php

use App\Support\PartnerDynamicsExample;
use App\Support\PartnerDynamicsProfile;

test('every example key referenced by a profile exists in the shared config', function () {
    $defined = PartnerDynamicsExample::keys();

    expect($defined)->not->toBeEmpty();

    foreach (PartnerDynamicsProfile::keys() as $profile) {

        $referenced = config("partner_dynamics_content.{$profile}.examples", []);

        expect($referenced)->not->toBeEmpty();

        foreach ($referenced as $key) {
            expect($defined)->toContain($key);
        }
    }
});

test('every example carries a name and a note', function () {
    foreach (config('partner_dynamics_examples') as $key => $record) {
        expect($record['name'] ?? '')->not->toBeEmpty();
        expect($record['note'] ?? '')->not->toBeEmpty();
    }
});

test('resolve returns name, note and initials for each key', function () {
    $resolved = PartnerDynamicsExample::resolve([
        'steve_jobs',
        'walt_disney',
    ]);

    expect($resolved)->toHaveCount(2);
    expect($resolved[0]['name'])->toBe('Steve Jobs');
    expect($resolved[0]['initials'])->toBe('SJ');
    expect($resolved[0]['note'])->not->toBeEmpty();
    expect($resolved[1]['initials'])->toBe('WD');
});

test('resolve drops unknown keys instead of rendering an empty card', function () {
    $resolved = PartnerDynamicsExample::resolve([
        'steve_jobs',
        'nobody_at_all',
    ]);

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]['key'])->toBe('steve_jobs');
});

test('initials handle one word, many words and empty input', function () {
    expect(PartnerDynamicsExample::initials('Oprah Winfrey'))->toBe('OW');
    expect(PartnerDynamicsExample::initials('Prince'))->toBe('P');
    expect(PartnerDynamicsExample::initials('Jean  Claude  Dubois'))->toBe('JD');
    expect(PartnerDynamicsExample::initials('  '))->toBe('');
    expect(PartnerDynamicsExample::initials(null))->toBe('');
});

test('no example note makes a psychological claim about the person', function () {
    // The disclaimer says these are not assessment results. A note that
    // reads a person's mind would contradict it.
    $banned = ['မြင်တတ်', 'စိတ်', 'အမြင်ရှိ', 'သဘော'];

    foreach (config('partner_dynamics_examples') as $key => $record) {
        foreach ($banned as $word) {
            expect($record['note'])->not->toContain($word);
        }
    }
});

test('the examples section is shown rather than folded away', function () {
    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    expect($partial)->toContain('pd-ref-example-grid');
    expect($partial)->toContain('pd-ref-example-avatar');
    expect($partial)->not->toContain('pd-ref-example-chips');
    expect($partial)->not->toContain('<details class="pd-ref-examples"');
});

test('the disclaimer is still shown alongside the examples', function () {
    $partial = file_get_contents(
        resource_path(
            'views/partner-dynamics/partials/result-reference-top.blade.php'
        )
    );

    expect($partial)->toContain("example_disclaimer");
    expect(config('partner_dynamics_content.example_disclaimer'))
        ->not->toBeEmpty();
});
