<?php

namespace App\Services\Portal;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PartnerAiGateway
{
    private function privateRequest(string $path, array $data): Response
    {
        $url = rtrim((string) config('pbr_ai.base_url'), '/');
        $secret = (string) config('pbr_ai.internal_secret');
        if ($url === '' || strlen($secret) < 32) throw new RuntimeException('PRIVATE_AI_NOT_CONFIGURED');
        $health = Http::connectTimeout(3)->timeout(5)->get($url.'/health');
        if (! $health->successful() || $health->json('service') !== 'pbr-ai-advisor'
            || $health->json('knowledgeReady') !== true || (int) $health->json('vectors') < 1 || $health->json('privateKnowledgeProtocol') !== 1) {
            throw new RuntimeException('PRIVATE_AI_PROTOCOL_NOT_READY');
        }
        $request = Http::withHeaders(['X-PBR-Internal-Secret' => $secret])
            ->connectTimeout((int) config('pbr_ai.connect_timeout', 5))->timeout((int) config('pbr_ai.timeout', 180));
        if ($path === '/internal/pbr/chat') $request->withHeaders(['Accept' => 'text/event-stream'])->withOptions(['stream' => true]);
        $response = $request->post($url.$path, $data);
        if (! $response->successful()) throw new RuntimeException('PRIVATE_AI_REQUEST_FAILED');

        return $response;
    }

    public function extractPrivate(string $bytes, string $mime): array
    {
        $result = $this->privateRequest('/internal/pbr/private/extract', ['data' => base64_encode($bytes), 'mimeType' => $mime])->json();
        if (! is_array($result) || ! is_string($result['text'] ?? null) || mb_strlen($result['text']) > 60000) {
            throw new RuntimeException('PRIVATE_EXTRACTION_INVALID');
        }

        return ['text' => $result['text'], 'partial' => (bool) ($result['partial'] ?? false)];
    }

    public function embedPrivate(array $texts, string $mode = 'document'): array
    {
        $result = $this->privateRequest('/internal/pbr/private/embed', ['texts' => array_values($texts), 'mode' => $mode])->json();
        $vectors = $result['vectors'] ?? null;
        $key = $result['embeddingKey'] ?? null;
        if (! is_array($vectors) || count($vectors) !== count($texts) || ! is_string($key) || trim($key) === '' || strlen($key) > 160) {
            throw new RuntimeException('PRIVATE_EMBEDDING_INVALID');
        }
        $dimensions = null;
        foreach ($vectors as $vector) {
            if (! is_array($vector) || count($vector) < 128 || count($vector) > 3072) throw new RuntimeException('PRIVATE_VECTOR_INVALID');
            $dimensions ??= count($vector);
            if (count($vector) !== $dimensions) throw new RuntimeException('PRIVATE_VECTOR_DIMENSION_MISMATCH');
            foreach ($vector as $value) {
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) throw new RuntimeException('PRIVATE_VECTOR_INVALID');
            }
        }

        return ['vectors' => $vectors, 'key' => $key];
    }

    public function openWorkspace(string $message, array $history, array $context): Response
    {
        return $this->privateRequest('/internal/pbr/chat', ['message' => $message, 'history' => $history, 'workspaceContext' => $context]);
    }

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
