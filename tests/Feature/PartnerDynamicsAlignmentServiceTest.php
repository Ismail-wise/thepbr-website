<?php

use App\Services\PartnerDynamics\PartnerDynamicsAlignmentService;

function pdAlignmentParticipant(
    int $userId,
    string $name,
    string $primary,
    array $scores
): array {
    return [
        'user_id' => $userId,
        'name' => $name,
        'primary_profile' => $primary,
        'secondary_profile' => null,
        'dimension_scores' => $scores,
    ];
}

function pdBaseDimensions(float $score = 70): array
{
    return [
        'vision' => $score,
        'execution' => $score,
        'people' => $score,
        'analysis' => $score,
        'structure' => $score,
        'risk' => $score,
        'decision' => $score,
        'adaptability' => $score,
    ];
}

test('alignment returns all report sections', function () {

    $service = app(
        PartnerDynamicsAlignmentService::class
    );

    $result = $service->analyze([
        pdAlignmentParticipant(
            1,
            'Partner A',
            'guardian',
            pdBaseDimensions(80)
        ),
        pdAlignmentParticipant(
            2,
            'Partner B',
            'visionary',
            pdBaseDimensions(75)
        ),
    ]);

    expect($result)->toHaveKeys([
        'alignment_summary',
        'shared_strengths',
        'complementary_areas',
        'important_differences',
        'shared_blind_spots',
        'role_suggestions',
        'decision_recommendations',
        'discussion_priorities',
    ]);

    expect(
        $result['alignment_summary']['participant_count']
    )->toBe(2);

    expect($result['role_suggestions'])
        ->toHaveCount(2);
});


test('alignment detects shared strengths and important differences', function () {

    $service = app(
        PartnerDynamicsAlignmentService::class
    );

    $partnerA = pdBaseDimensions(80);
    $partnerA['vision'] = 90;

    $partnerB = pdBaseDimensions(75);
    $partnerB['vision'] = 30;

    $result = $service->analyze([
        pdAlignmentParticipant(
            1,
            'Partner A',
            'guardian',
            $partnerA
        ),
        pdAlignmentParticipant(
            2,
            'Partner B',
            'visionary',
            $partnerB
        ),
    ]);

    $differenceDimensions = collect(
        $result['important_differences']
    )->pluck('dimension')->all();

    $sharedDimensions = collect(
        $result['shared_strengths']
    )->pluck('dimension')->all();

    expect($differenceDimensions)
        ->toContain('vision');

    expect($sharedDimensions)
        ->toContain('structure');
});


test('alignment supports more than two participants', function () {

    $service = app(
        PartnerDynamicsAlignmentService::class
    );

    $result = $service->analyze([
        pdAlignmentParticipant(
            1,
            'Partner A',
            'guardian',
            pdBaseDimensions(80)
        ),
        pdAlignmentParticipant(
            2,
            'Partner B',
            'visionary',
            pdBaseDimensions(75)
        ),
        pdAlignmentParticipant(
            3,
            'Partner C',
            'connector',
            pdBaseDimensions(70)
        ),
    ]);

    expect(
        $result['alignment_summary']['participant_count']
    )->toBe(3);

    expect($result['role_suggestions'])
        ->toHaveCount(3);
});


test('alignment requires at least two participants', function () {

    $service = app(
        PartnerDynamicsAlignmentService::class
    );

    expect(fn () => $service->analyze([
        pdAlignmentParticipant(
            1,
            'Partner A',
            'guardian',
            pdBaseDimensions(80)
        ),
    ]))->toThrow(
        InvalidArgumentException::class
    );
});


test('alignment rejects dimension scores outside zero to one hundred', function () {

    $service = app(
        PartnerDynamicsAlignmentService::class
    );

    $invalid = pdBaseDimensions(80);
    $invalid['risk'] = 120;

    expect(fn () => $service->analyze([
        pdAlignmentParticipant(
            1,
            'Partner A',
            'guardian',
            $invalid
        ),
        pdAlignmentParticipant(
            2,
            'Partner B',
            'visionary',
            pdBaseDimensions(70)
        ),
    ]))->toThrow(
        InvalidArgumentException::class
    );
});
