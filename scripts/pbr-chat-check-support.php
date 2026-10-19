<?php

namespace Pbr\Deployment;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** Synthetic release probes only; never logs prompts, answers or source text. */
final class ChatReleaseProbe
{
    public static function readAnswer(StreamInterface $body, int $timeout): string
    {
        $buffer = '';
        $deadline = microtime(true) + $timeout;
        while (! $body->eof()) {
            if (microtime(true) > $deadline) throw new RuntimeException('LANGUAGE_CHECK_TIMEOUT');
            $buffer .= $body->read(4096);
            if (strlen($buffer) > 1048576) throw new RuntimeException('LANGUAGE_CHECK_RESPONSE_TOO_LARGE');
        }
        $text = '';
        $done = false;
        foreach (explode("\n", $buffer) as $line) {
            if (! str_starts_with($line, 'data:')) continue;
            $event = json_decode(trim(substr($line, 5)), true);
            if (! is_array($event)) throw new RuntimeException('LANGUAGE_CHECK_INVALID_STREAM');
            if (($event['type'] ?? '') === 'error') throw new RuntimeException('LANGUAGE_GENERATION_FAILED');
            if (($event['type'] ?? '') === 'delta') {
                if ($done || ! is_string($event['text'] ?? null)) throw new RuntimeException('LANGUAGE_CHECK_INVALID_STREAM');
                $text .= $event['text'];
            }
            if (($event['type'] ?? '') === 'done') $done = true;
        }
        if (! $done || trim($text) === '') throw new RuntimeException('LANGUAGE_GENERATION_FAILED');

        return $text;
    }

    public static function letterCounts(string $text): array
    {
        // Myanmar digits, punctuation and combining marks alone do not make
        // an English explanation Burmese (for example: "Capital is ၄၂ dollars").
        // Citation labels themselves are not English prose either.
        $prose = preg_replace('/\[S[1-9][0-9]*\]/u', '', $text);
        if ($prose === null) throw new RuntimeException('LANGUAGE_CHECK_INVALID_TEXT');
        $myanmar = preg_match_all('/(?=\p{L})[\x{1000}-\x{109F}\x{AA60}-\x{AA7F}\x{A9E0}-\x{A9FF}]/u', $prose);
        $latin = preg_match_all('/(?=\p{L})\p{Latin}/u', $prose);
        if ($myanmar === false || $latin === false) throw new RuntimeException('LANGUAGE_CHECK_INVALID_TEXT');

        return ['myanmar' => $myanmar, 'latin' => $latin];
    }

    public static function assertReply(string $text, string $expected, bool $workspace): void
    {
        if (! in_array($expected, ['my', 'en'], true)) throw new RuntimeException('LANGUAGE_CHECK_INVALID_EXPECTATION');
        $letters = self::letterCounts($text);
        if (($expected === 'my' && $letters['myanmar'] === 0)
            || ($expected === 'en' && ($letters['myanmar'] > 0 || $letters['latin'] === 0))) {
            throw new RuntimeException('REPLY_LANGUAGE_MISMATCH');
        }
        $numericText = strtr($text, ['၀'=>'0','၁'=>'1','၂'=>'2','၃'=>'3','၄'=>'4','၅'=>'5','၆'=>'6','၇'=>'7','၈'=>'8','၉'=>'9']);
        if ($workspace && (! preg_match('/(?<![0-9])42(?![0-9])/', $numericText) || ! str_contains($text, '[S1]'))) {
            throw new RuntimeException('WORKSPACE_CITATION_FAILED');
        }
    }
}
