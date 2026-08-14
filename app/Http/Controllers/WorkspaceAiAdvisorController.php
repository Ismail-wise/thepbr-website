<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PartnershipWorkspace;
use App\Services\Ai\PbrAiContextBuilder;
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
        $this->authorizeAiAccess($request, $workspace);

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
        $this->authorizeAiAccess($request, $workspace);

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
            $streamHadError = false;
            $errorEventSent = false;
            $receivedDone = false;
            $upstream = null;

            try {
                $payload = json_encode([
                    'message' => trim($validated['message']),
                    'history' => $history,
                    'workspaceContext' => $context,
                    'actor' => $actor,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                $timeout = max(30, (int) config('pbr_ai.timeout', 180));
                $streamContext = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", [
                            'Accept: text/event-stream',
                            'Content-Type: application/json',
                            'X-PBR-Internal-Secret: '.$secret,
                            'Connection: close',
                        ]),
                        'content' => $payload,
                        'timeout' => $timeout,
                        'ignore_errors' => true,
                        'protocol_version' => 1.1,
                    ],
                ]);

                $upstream = @fopen(
                    $baseUrl.'/internal/pbr/chat',
                    'rb',
                    false,
                    $streamContext
                );

                if (! is_resource($upstream)) {
                    $userMessage->delete();
                    $send([
                        'type' => 'error',
                        'text' => 'AI Advisor Service ကို ခဏဆက်သွယ်လို့မရသေးပါ။ ခဏနေရင် ထပ်စမ်းပါ။',
                    ]);
                    $send(['type' => 'done']);
                    return;
                }

                $statusCode = 0;
                foreach ($http_response_header ?? [] as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $headerLine, $matches)) {
                        $statusCode = (int) $matches[1];
                        break;
                    }
                }

                if ($statusCode !== 200) {
                    $userMessage->delete();
                    $send([
                        'type' => 'error',
                        'text' => 'AI Advisor Service ကို ခဏဆက်သွယ်လို့မရသေးပါ။ ခဏနေရင် ထပ်စမ်းပါ။',
                    ]);
                    $send(['type' => 'done']);
                    return;
                }

                stream_set_timeout($upstream, $timeout);
                $buffer = '';

                while (! feof($upstream)) {
                    $chunk = fread($upstream, 2048);
                    if ($chunk === false) {
                        $streamHadError = true;
                        break;
                    }

                    if ($chunk === '') {
                        $meta = stream_get_meta_data($upstream);
                        if (($meta['timed_out'] ?? false) === true) {
                            $streamHadError = true;
                            break;
                        }
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

                            if (($data['type'] ?? null) === 'error') {
                                $streamHadError = true;
                                $errorEventSent = true;
                            }

                            if (($data['type'] ?? null) === 'done') {
                                $receivedDone = true;
                            }

                            $send($data);
                        }
                    }
                }

                if ($streamHadError && ! $errorEventSent) {
                    $send([
                        'type' => 'error',
                        'text' => 'AI Response မပြီးဆုံးသေးပါ။ ထပ်စမ်းပေးပါ။',
                    ]);
                    $errorEventSent = true;
                }

                if (! $receivedDone) {
                    $send(['type' => 'done']);
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
                            'incomplete' => $streamHadError,
                        ],
                    ]);
                    $conversation->touch();
                } else {
                    // Never leave a failed user-only turn in persisted history because
                    // Gemini chat history must remain a clean user/model sequence.
                    $userMessage->delete();
                }
            } catch (Throwable $e) {
                report($e);
                $userMessage->delete();

                $send([
                    'type' => 'error',
                    'text' => 'AI Advisor Service မှာ ခဏတာ အခက်အခဲရှိနေပါတယ်။ ထပ်စမ်းပေးပါ။',
                ]);
                $send(['type' => 'done']);
            } finally {
                if (is_resource($upstream)) {
                    fclose($upstream);
                }
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
        $this->authorizeAiAccess($request, $workspace);
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

    private function authorizeAiAccess(
        Request $request,
        PartnershipWorkspace $workspace
    ): void {
        $user = $request->user();

        abort_unless(
            $user
                && $user->canUsePbrAiAdvisor()
                && $user->canAccessWorkspace($workspace),
            403
        );
    }
}
