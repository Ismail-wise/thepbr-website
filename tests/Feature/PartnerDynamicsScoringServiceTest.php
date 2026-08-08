<?php

use App\Services\PartnerDynamics\PartnerDynamicsScoringService;

function neutralPartnerDynamicsAnswers(): array
{
    $answers = [];

    for ($i = 1; $i <= 32; $i++) {
        $answers[$i] = 3;
    }

    for ($i = 33; $i <= 40; $i++) {
        $answers[$i] = 'A';
    }

    return $answers;
}

test('partner dynamics returns eight dimensions and eight profiles', function () {
    $result = app(PartnerDynamicsScoringService::class)
        ->calculate(neutralPartnerDynamicsAnswers());

    expect($result['dimension_scores'])->toHaveCount(8);
    expect($result['profile_scores'])->toHaveCount(8);
    expect($result['primary_profile'])->not->toBeEmpty();
    expect($result['secondary_profile'])->not->toBeEmpty();
});

test('partner dynamics validates missing answers', function () {
    app(PartnerDynamicsScoringService::class)->calculate([]);
})->throws(InvalidArgumentException::class);

test('partner dynamics validates behaviour values', function () {
    $answers = neutralPartnerDynamicsAnswers();
    $answers[1] = 6;

    app(PartnerDynamicsScoringService::class)->calculate($answers);
})->throws(InvalidArgumentException::class);

test('partner dynamics validates scenario choices', function () {
    $answers = neutralPartnerDynamicsAnswers();
    $answers[33] = 'X';

    app(PartnerDynamicsScoringService::class)->calculate($answers);
})->throws(InvalidArgumentException::class);

test('partner dynamics produces scores between zero and one hundred', function () {
    $result = app(PartnerDynamicsScoringService::class)
        ->calculate(neutralPartnerDynamicsAnswers());

    foreach ($result['dimension_scores'] as $score) {
        expect($score)->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    }

    foreach ($result['profile_scores'] as $score) {
        expect($score)->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(100);
    }
});
