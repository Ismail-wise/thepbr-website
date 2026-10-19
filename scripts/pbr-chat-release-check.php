<?php

// Synthetic-only provider checks. Never loads Owner sources or creates records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/pbr-chat-check-support.php';

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
        ['PERSONAL_AUTO_EN', 'personal', 'auto', 'What should business partners agree before starting? Give one short sentence.', 'en'],
        ['PERSONAL_SELECTED_MY', 'personal', 'my', 'What should business partners agree before starting? Give one short sentence.', 'my'],
        ['WORKSPACE_AUTO_MY', 'workspace', 'auto', 'ဒီလုပ်ငန်းရဲ့ ရင်းနှီးငွေ ဘယ်လောက်လဲ။ စာကြောင်းတို တစ်ကြောင်းနဲ့ ဖြေပါ။', 'my'],
        ['WORKSPACE_SELECTED_EN', 'workspace', 'en', 'ဒီလုပ်ငန်းရဲ့ ရင်းနှီးငွေ ဘယ်လောက်လဲ။ စာကြောင်းတို တစ်ကြောင်းနဲ့ ဖြေပါ။', 'en'],
    ];
    foreach ($checks as [$case, $scope, $language, $question, $expected]) {
        echo 'CHAT_CHECK_CASE='.$case."\nCHAT_CHECK_EXPECTED=".strtoupper($expected)."\n";
        $response = $scope === 'personal' ? $gateway->open($question, [], $language) : $gateway->openWorkspace($question, [], $context, $language);
        $body = $response->toPsrResponse()->getBody();
        try {
            $text = Pbr\Deployment\ChatReleaseProbe::readAnswer($body, (int) config('pbr_ai.timeout', 180));
            $letters = Pbr\Deployment\ChatReleaseProbe::letterCounts($text);
            echo 'CHAT_CHECK_MYANMAR_LETTERS='.$letters['myanmar']."\nCHAT_CHECK_LATIN_LETTERS=".$letters['latin']."\n";
            Pbr\Deployment\ChatReleaseProbe::assertReply($text, $expected, $scope === 'workspace');
            echo "CHAT_CHECK_RESULT=OK\n";
        } finally { $body->close(); }
    }
    echo "LIVE_BURMESE_AND_ENGLISH_REPLIES=OK\nAUTOMATIC_AND_SELECTED_REPLY_LANGUAGE=OK\nPRIVATE_SOURCE_CITATIONS=OK\nLIVE_USER_DATA_SENT_FOR_TESTING=NONE\n";
} catch (Throwable $error) {
    $code = preg_match('/^[A-Z_]+$/D', $error->getMessage()) ? $error->getMessage() : 'UNEXPECTED_ERROR';
    echo 'CHAT_CHECK_FAILED='.$code."\n";
    fwrite(STDERR, 'CHAT_CHECK_FAILED='.$code."\n");exit(2);
}
