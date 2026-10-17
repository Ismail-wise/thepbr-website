<?php

namespace App\Services\Portal;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PartnerAiGateway
{
    public function open(string $message, array $history): Response
    {
        $url = rtrim((string) config('pbr_ai.base_url'), '/');
        $secret = (string) config('pbr_ai.internal_secret');
        if ($url === '' || strlen($secret) < 32) {
            throw new RuntimeException('Partner AI configuration is incomplete.');
        }

        // This integration calls the user's partner-ai service, never a replacement chatbot.
        $health = Http::connectTimeout(3)->timeout(5)->get($url.'/health');
        if (! $health->successful() || $health->json('service') !== 'pbr-ai-advisor'
            || $health->json('knowledgeReady') !== true || (int) $health->json('vectors') < 1) {
            throw new RuntimeException('Partner AI knowledge base is not ready.');
        }

        $response = Http::withHeaders([
            'Accept' => 'text/event-stream',
            'X-PBR-Internal-Secret' => $secret,
        ])->connectTimeout((int) config('pbr_ai.connect_timeout', 5))
            ->timeout((int) config('pbr_ai.timeout', 180))
            ->withOptions(['stream' => true])
            ->post($url.'/internal/pbr/chat', [
                'message' => $message,
                'history' => $history,
                // No business is manufactured and no private workspace data is included.
                'workspaceContext' => ['scope' => 'personal_partnership_advice'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Partner AI request failed.');
        }

        return $response;
    }
}
