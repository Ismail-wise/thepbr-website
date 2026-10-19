<?php

use GuzzleHttp\Psr7\Utils;
use Pbr\Deployment\ChatReleaseProbe;

require_once __DIR__.'/../../scripts/pbr-chat-check-support.php';

test('Myanmar numerals and punctuation do not misclassify English Workspace replies', function (): void {
    $answer = 'The reviewed capital is ၄၂ dollars။ [S1]';
    expect(ChatReleaseProbe::letterCounts($answer)['myanmar'])->toBe(0);
    ChatReleaseProbe::assertReply($answer, 'en', true);
});

test('Burmese prose with familiar English terms and native numbers passes cited checks', function (): void {
    ChatReleaseProbe::assertReply('Capital က ၄၂ dollars ဖြစ်ပါတယ်။ [S1]', 'my', true);
    expect(ChatReleaseProbe::letterCounts('ဖြစ်ပါတယ်။')['myanmar'])->toBeGreaterThan(0);
});

test('genuinely wrong reply languages still fail', function (string $answer, string $expected): void {
    ChatReleaseProbe::assertReply($answer, $expected, false);
})->with([
    ['Partners should agree their responsibilities.', 'my'],
    ['တာဝန်ခွဲဝေမှုကို သဘောတူထားသင့်ပါတယ်။', 'en'],
    ['၄၂။ [S1]', 'en'],
    ['၄၂။ [S1]', 'my'],
])->throws(RuntimeException::class, 'REPLY_LANGUAGE_MISMATCH');

test('Workspace replies still require both the correct amount and the exact citation', function (string $answer): void {
    ChatReleaseProbe::assertReply($answer, 'en', true);
})->with(['Capital is 42 dollars.', 'Capital is 43 dollars. [S1]', 'Capital is 142 dollars. [S1]'])
    ->throws(RuntimeException::class, 'WORKSPACE_CITATION_FAILED');

test('the probe reads Unicode deltas across read boundaries and ignores SSE heartbeats', function (): void {
    $answer = str_repeat('Capital က ၄၂ dollars ဖြစ်ပါတယ်။ [S1] ', 70);
    $sse = ": heartbeat\r\n\r\ndata: {\"type\":\"meta\"}\r\n\r\n";
    foreach (mb_str_split($answer, 17) as $chunk) {
        $sse .= 'data: '.json_encode(['type' => 'delta', 'text' => $chunk], JSON_UNESCAPED_UNICODE)."\r\n\r\n";
    }
    $sse .= "data: {\"type\":\"done\"}\r\n\r\n";
    expect(ChatReleaseProbe::readAnswer(Utils::streamFor($sse), 5))->toBe($answer);
});

test('failed, empty and unfinished model streams cannot pass deployment', function (string $sse): void {
    ChatReleaseProbe::readAnswer(Utils::streamFor($sse), 5);
})->with([
    "data: {\"type\":\"delta\",\"text\":\"Answer\"}\n\n",
    "data: {\"type\":\"done\"}\n\n",
    "data: {\"type\":\"delta\",\"text\":\"Answer\"}\n\ndata: {\"type\":\"error\"}\n\ndata: {\"type\":\"done\"}\n\n",
])->throws(RuntimeException::class, 'LANGUAGE_GENERATION_FAILED');

test('malformed model event data fails without reflecting the answer', function (): void {
    ChatReleaseProbe::readAnswer(Utils::streamFor('data: PRIVATE SYNTHETIC ANSWER'), 5);
})->throws(RuntimeException::class, 'LANGUAGE_CHECK_INVALID_STREAM');
