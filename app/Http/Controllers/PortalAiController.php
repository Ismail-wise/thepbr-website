<?php

namespace App\Http\Controllers;

use App\Models\PortalConversation;
use App\Models\PortalSavedItem;
use App\Services\Portal\PartnerAiGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PortalAiController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = PortalConversation::query()->where('user_id', $request->user()->id)->latest('updated_at')->paginate(20);
        $selected = $request->integer('conversation') > 0
            ? PortalConversation::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('conversation'))
            : null;
        $messages = $selected?->messages()->oldest('id')->get() ?? collect();

        $prefill = '';
        if ($request->integer('saved') > 0) {
            $item = PortalSavedItem::query()->where('user_id', $request->user()->id)->findOrFail($request->integer('saved'));
            $prefill = 'ဒီစမ်းတွက်ချက်မှုကို ဆွေးနွေးချင်ပါတယ်။'."\n".Str::limit(json_encode($item->output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 4400, '… (ရလဒ်အကျဉ်းချုပ်)');
        }

        return view('portal.ai', compact('conversations', 'selected', 'messages', 'prefill'));
    }

    public function chat(Request $request, PartnerAiGateway $gateway): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);
        $message = trim($validated['message']);
        abort_if($message === '', 422);
        $conversation = empty($validated['conversation_id']) ? null : PortalConversation::query()
            ->where('user_id', $request->user()->id)->findOrFail($validated['conversation_id']);
        $lock = Cache::lock('portal-ai-user:'.$request->user()->id, (int) config('pbr_ai.timeout', 180) + 30);
        if (! $lock->get()) {
            return response()->json(['message' => 'လက်ရှိ AI အဖြေပြီးဆုံးသည်အထိ ခဏစောင့်ပါ။'], 409);
        }

        try {
            $history = $conversation?->messages()->latest('id')->limit(20)->get()->reverse()
                ->map(fn ($row): array => ['role' => $row->role, 'content' => $row->content])->values()->all() ?? [];
            $upstream = $gateway->open($message, $history);
            $conversation ??= PortalConversation::create([
                'user_id' => $request->user()->id,
                'title' => Str::limit($message, 72, '…'),
            ]);
            $userMessage = $conversation->messages()->create(['role' => 'user', 'content' => $message]);
            $conversation->touch();
        } catch (Throwable $error) {
            $lock->release();
            report($error);

            return response()->json(['message' => 'PBR AI ကို ခဏဆက်သွယ်လို့မရသေးပါ။ Admin နှင့် စစ်ဆေးပြီး ထပ်စမ်းပါ။'], 503);
        }

        return response()->stream(function () use ($conversation, $userMessage, $upstream, $lock): void {
            $send = static function (array $event): void {
                echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            $text = '';
            $buffer = '';
            $done = false;
            $failed = false;
            $body = $upstream->toPsrResponse()->getBody();
            $send(['type' => 'meta', 'conversationId' => $conversation->id]);
            $deadline = microtime(true) + max(5, (int) config('pbr_ai.timeout', 180));
            try {
                while (! $body->eof()) {
                    if (microtime(true) > $deadline) {
                        throw new \RuntimeException('AI stream timeout.');
                    }
                    $chunk = $body->read(4096);
                    if ($chunk === '') {
                        usleep(20000);
                        continue;
                    }
                    $buffer .= $chunk;
                    $buffer = str_replace("\r\n", "\n", $buffer);
                    if (strlen($buffer) > 1048576 || strlen($text) > 1048576) {
                        throw new \RuntimeException('AI response limit reached.');
                    }
                    while (($end = strpos($buffer, "\n\n")) !== false) {
                        $event = substr($buffer, 0, $end);
                        $buffer = substr($buffer, $end + 2);
                        foreach (explode("\n", $event) as $line) {
                            if (! str_starts_with($line, 'data:')) {
                                continue;
                            }
                            $data = json_decode(trim(substr($line, 5)), true);
                            if (! is_array($data)) {
                                continue;
                            }
                            if (($data['type'] ?? '') === 'delta' && is_string($data['text'] ?? null)) {
                                $text .= $data['text'];
                                $send(['type' => 'delta', 'text' => $data['text']]);
                            } elseif (($data['type'] ?? '') === 'error') {
                                $failed = true;
                            } elseif (($data['type'] ?? '') === 'done') {
                                $done = true;
                            }
                        }
                    }
                }
            } catch (Throwable $error) {
                report($error);
                $failed = true;
            } finally {
                try {
                    if (trim($text) !== '') {
                        $conversation->messages()->create([
                            'role' => 'assistant', 'content' => $text,
                            'metadata' => ['engine' => 'partner-ai-rag', 'incomplete' => $failed || ! $done],
                        ]);
                        $conversation->touch();
                    } else {
                        $userMessage->delete();
                        $failed = true;
                    }
                    if ($failed || ! $done) {
                        $send(['type' => 'error', 'text' => 'AI အဖြေ မပြီးဆုံးသေးပါ။ ခဏနေရင် ပြန်စမ်းပါ။']);
                    }
                    $send(['type' => 'done']);
                } finally {
                    $body->close();
                    $lock->release();
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function destroy(Request $request, PortalConversation $conversation): RedirectResponse
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        $lock = Cache::lock('portal-ai-user:'.$request->user()->id, (int) config('pbr_ai.timeout', 180) + 30);
        abort_unless($lock->get(), 409);
        try {
            $conversation->delete();
        } finally {
            $lock->release();
        }

        return redirect()->route('portal.ai');
    }
}
