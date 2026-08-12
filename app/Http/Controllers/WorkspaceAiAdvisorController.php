<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PartnershipWorkspace;
use App\Services\Ai\PbrAiContextBuilder;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkspaceAiAdvisorController extends Controller
{
    public function index(Request $request, PartnershipWorkspace $workspace): View
    {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $conversations = AiConversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->latest('id')
            ->limit(30)
            ->get();

        $selected = null;

        if (! $request->boolean('new')) {
            $requestedId = $request->integer('conversation');

            if ($requestedId > 0) {
                $selected = $conversations->firstWhere('id', $requestedId);
            }

            $selected ??= $conversations->first();
        }

        if ($selected) {
            $selected->load([
                'messages' => fn ($query) => $query
                    ->oldest('id')
                    ->limit(120),
            ]);
        }

        return view('workspaces.ai-advisor', [
            'workspace' => $workspace,
            'conversations' => $conversations,
            'selectedConversation' => $selected,
            'businessName' => $workspace->business_name ?: $workspace->name,
            'isManager' => $request->user()->isAdmin()
                || (int) $workspace->owner_user_id === (int) $request->user()->id,
        ]);
    }

    public function chat(
        Request $request,
        PartnershipWorkspace $workspace,
        PbrAiContextBuilder $contextBuilder
    ): StreamedResponse|JsonResponse {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $baseUrl = rtrim((string) config('pbr_ai.base_url'), '/');
        $secret = (string) config('pbr_ai.internal_secret');

        if ($baseUrl === '' || $secret === '') {
            return response()->json([
                'message' => 'PBR AI Advisor ကို Server မှာ configure မလုပ်ရသေးပါ။',
            ], 503);
        }

        $conversation = null;

        if (! empty($validated['conversation_id'])) {
            $conversation = AiConversation::query()
                ->whereKey((int) $validated['conversation_id'])
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $conversation ??= AiConversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'title' => Str::limit(trim($validated['message']), 72, '…'),
        ]);

        $historyLimit = max(4, min(40, (int) config('pbr_ai.history_messages', 20)));
        $history = $conversation->messages()
            ->latest('id')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->map(fn (AiMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $userMessage = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => trim($validated['message']),
            'metadata' => [
                'workspace_id' => $workspace->id,
            ],
        ]);

        $conversation->touch();

        $context = $contextBuilder->build($request->user(), $workspace);
        $actor = [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'access_level' => $request->user()->isAdmin()
                || (int) $workspace->owner_user_id === (int) $request->user()->id
                    ? 'owner_or_admin'
                    : 'accepted_partner',
        ];

        return response()->stream(function () use (
            $baseUrl,
            $secret,
            $validated,
            $history,
            $context,
            $actor,
            $conversation,
            $userMessage
        ): void {
            @set_time_limit(0);
            ignore_user_abort(true);

            $send = static function (array $payload): void {
                echo 'data: '.json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send([
                'type' => 'meta',
                'conversationId' => $conversation->id,
                'title' => $conversation->title,
            ]);

            $assistantText = '';
            $ragMode = null;
            $topScore = null;

            try {
                $client = new Client([
                    'timeout' => max(30, (int) config('pbr_ai.timeout', 180)),
                    'connect_timeout' => max(2, (int) config('pbr_ai.connect_timeout', 5)),
                    'http_errors' => false,
                ]);

                $upstream = $client->post($baseUrl.'/internal/pbr/chat', [
                    'stream' => true,
                    'headers' => [
                        'Accept' => 'text/event-stream',
                        'Content-Type' => 'application/json',
                        'X-PBR-Internal-Secret' => $secret,
                    ],
                    'json' => [
                        'message' => trim($validated['message']),
                        'history' => $history,
                        'workspaceContext' => $context,
                        'actor' => $actor,
                    ],
                ]);

                if ($upstream->getStatusCode() !== 200) {
                    $send([
                        'type' => 'error',
                        'text' => 'AI Advisor Service ကို ခဏဆက်သွယ်လို့မရသေးပါ။ ခဏနေရင် ထပ်စမ်းပါ။',
                    ]);
                    $send(['type' => 'done']);
                    return;
                }

                $body = $upstream->getBody();
                $buffer = '';

                while (! $body->eof()) {
                    $chunk = $body->read(2048);
                    if ($chunk === '') {
                        usleep(20000);
                        continue;
                    }

                    $buffer .= $chunk;

                    while (($separator = strpos($buffer, "\n\n")) !== false) {
                        $event = substr($buffer, 0, $separator);
                        $buffer = substr($buffer, $separator + 2);

                        foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
                            if (! str_starts_with($line, 'data: ')) {
                                continue;
                            }

                            $data = json_decode(substr($line, 6), true);
                            if (! is_array($data)) {
                                continue;
                            }

                            if (($data['type'] ?? null) === 'delta' && isset($data['text'])) {
                                $assistantText .= (string) $data['text'];
                            }

                            if (($data['type'] ?? null) === 'meta') {
                                $ragMode = $data['mode'] ?? null;
                                $topScore = $data['topScore'] ?? null;
                            }

                            $send($data);
                        }
                    }
                }

                if (trim($assistantText) !== '') {
                    $conversation->messages()->create([
                        'user_id' => null,
                        'role' => 'assistant',
                        'content' => $assistantText,
                        'metadata' => [
                            'rag_mode' => $ragMode,
                            'top_score' => $topScore,
                            'engine' => 'partner-ai-rag',
                        ],
                    ]);
                    $conversation->touch();
                }
            } catch (Throwable $e) {
                report($e);

                $userMessage->update([
                    'metadata' => array_merge($userMessage->metadata ?? [], [
                        'upstream_failed' => true,
                    ]),
                ]);

                $send([
                    'type' => 'error',
                    'text' => 'AI Advisor Service မှာ ခဏတာ အခက်အခဲရှိနေပါတယ်။ ထပ်စမ်းပေးပါ။',
                ]);
                $send(['type' => 'done']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function destroy(
        Request $request,
        PartnershipWorkspace $workspace,
        AiConversation $conversation
    ): RedirectResponse {
        abort_unless($request->user()->canAccessWorkspace($workspace), 403);
        abort_unless(
            (int) $conversation->workspace_id === (int) $workspace->id
                && (int) $conversation->user_id === (int) $request->user()->id,
            403
        );

        $conversation->delete();

        return redirect()
            ->route('workspaces.ai-advisor.index', $workspace)
            ->with('success', 'AI Conversation ကိုဖျက်ပြီးပါပြီ။');
    }
}
