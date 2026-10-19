<?php

// Synthetic-only provider checks. Never loads Owner sources or creates records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

try {
    [$script, $mode, $root] = array_pad($argv, 3, '');
    if ($mode !== 'language-check' || ! is_file($root.'/vendor/autoload.php')) throw new RuntimeException('APP_AND_MODE_REQUIRED');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config('app.debug') !== false || ! config('partner_portal.enabled') || parse_url((string) config('app.url'), PHP_URL_HOST) !== 'thepbr.io') throw new RuntimeException('PRODUCTION_CONFIG_REQUIRED');
    $gateway = $app->make(App\Services\Portal\PartnerAiGateway::class);
    $context = [
        'scope' => 'owner_private_knowledge_v1', 'workspace' => ['name' => 'Synthetic language check'],
        'sources' => [['reference' => 'S1', 'title' => 'Synthetic number', 'business_status' => 'current']],
        'excerpts' => [['reference' => 'S1', 'business_status' => 'current', 'text' => 'The reviewed synthetic capital is 42 dollars.']],
    ];
    $checks = [
        ['personal', 'auto', 'What should business partners agree before starting? Give one short sentence.', false],
        ['personal', 'my', 'What should business partners agree before starting? Give one short sentence.', true],
        ['workspace', 'auto', 'ဒီလုပ်ငန်းရဲ့ ရင်းနှီးငွေ ဘယ်လောက်လဲ။ စာကြောင်းတို တစ်ကြောင်းနဲ့ ဖြေပါ။', true],
        ['workspace', 'en', 'ဒီလုပ်ငန်းရဲ့ ရင်းနှီးငွေ ဘယ်လောက်လဲ။ စာကြောင်းတို တစ်ကြောင်းနဲ့ ဖြေပါ။', false],
    ];
    foreach ($checks as [$scope, $language, $question, $myanmar]) {
        $response = $scope === 'personal' ? $gateway->open($question, [], $language) : $gateway->openWorkspace($question, [], $context, $language);
        $body = $response->toPsrResponse()->getBody();$text = '';$buffer = '';$done = false;$failed = false;
        $deadline = microtime(true) + (int) config('pbr_ai.timeout', 180);
        try {
            while (! $body->eof()) {
                if (microtime(true) > $deadline || strlen($buffer) > 1048576) throw new RuntimeException('LANGUAGE_CHECK_TIMEOUT');
                $buffer .= $body->read(4096);
            }
            foreach (explode("\n", $buffer) as $line) {
                if (! str_starts_with($line, 'data:')) continue;
                $event = json_decode(trim(substr($line, 5)), true);
                if (($event['type'] ?? '') === 'delta') $text .= $event['text'] ?? '';
                if (($event['type'] ?? '') === 'done') $done = true;
                if (($event['type'] ?? '') === 'error') $failed = true;
            }
            if (! $done || $failed || trim($text) === '') throw new RuntimeException('LANGUAGE_GENERATION_FAILED');
            $hasMyanmar = preg_match('/[\x{1000}-\x{109F}\x{AA60}-\x{AA7F}\x{A9E0}-\x{A9FF}]/u', $text) === 1;
            if ($hasMyanmar !== $myanmar) throw new RuntimeException('REPLY_LANGUAGE_MISMATCH');
            if ($scope === 'workspace' && (! str_contains($text, '42') || ! str_contains($text, '[S1]'))) throw new RuntimeException('WORKSPACE_CITATION_FAILED');
        } finally { $body->close(); }
    }
    echo "LIVE_BURMESE_AND_ENGLISH_REPLIES=OK\nAUTOMATIC_AND_SELECTED_REPLY_LANGUAGE=OK\nPRIVATE_SOURCE_CITATIONS=OK\nLIVE_USER_DATA_SENT_FOR_TESTING=NONE\n";
} catch (Throwable $error) {
    $code = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UNEXPECTED_ERROR';
    fwrite(STDERR, 'CHAT_CHECK_FAILED='.$code."\n");exit(2);
}
