<?php

namespace App\Http\Controllers;

use App\Models\PartnershipWorkspace;
use App\Models\WorkspaceKnowledgeChunk;
use App\Models\WorkspaceKnowledgeSource;
use App\Services\Portal\PartnerAiGateway;
use App\Services\Portal\PrivateWorkspaceAccess;
use App\Services\Portal\WorkspaceKnowledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PortalKnowledgeController extends Controller
{
    public function __construct(private PrivateWorkspaceAccess $access, private WorkspaceKnowledge $knowledge) {}

    private function source(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source): void
    {
        $this->access->authorize($request, $workspace);
        abort_unless((int) $source->workspace_id === (int) $workspace->id, 404);
    }

    public function index(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->access->authorize($request, $workspace);

        return view('portal.workspaces.knowledge.index', [
            'workspace' => $workspace,
            'sources' => $this->knowledge->sources($workspace)->latest('id')->paginate(15),
        ]);
    }

    public function create(Request $request, PartnershipWorkspace $workspace): View
    {
        $this->access->authorize($request, $workspace);

        return view('portal.workspaces.knowledge.create', compact('workspace'));
    }

    public function store(Request $request, PartnershipWorkspace $workspace, PartnerAiGateway $gateway): RedirectResponse
    {
        $this->access->authorize($request, $workspace);
        $values = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'business_status' => ['required', Rule::in(array_keys(WorkspaceKnowledgeSource::BUSINESS_STATUSES))],
            'content' => ['required_without:file', 'nullable', 'string', 'max:60000'],
            'file' => ['required_without:content', 'nullable', Rule::prohibitedIf(fn () => filled($request->input('content'))), 'file',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,text/plain', 'max:'.config('pbr_knowledge.upload_kb', 10240)],
        ]);
        abort_if(trim($values['title']) === '', 422);
        $file = $request->file('file');
        $text = trim((string) ($values['content'] ?? ''));
        abort_if(! $file && $text === '', 422);
        $lock = $this->access->lock($workspace);
        $path = null; $source = null;
        try {
            $attributes = ['workspace_id' => $workspace->id, 'title' => trim($values['title']),
                'business_status' => $values['business_status'], 'kind' => $file ? 'file' : 'text',
                'processing_status' => 'needs_review', 'extracted_text' => $text, 'reviewed_text' => $text];
            if ($file) {
                $mime = $file->getMimeType();
                $extension = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp', 'text/plain'=>'txt'][$mime] ?? null;
                abort_unless($extension, 422);
                // Names supplied by browsers never become filesystem paths.
                $path = $file->storeAs('workspaces/'.$workspace->id, Str::uuid().'.'.$extension, 'workspace_private');
                $filename = mb_substr(preg_replace('/[\x00-\x1f\x7f\/\\\\]/u', '_', $file->getClientOriginalName()) ?: 'source.'.$extension, 0, 200);
                $attributes += ['file_path' => $path, 'original_filename' => $filename, 'mime_type' => $mime,
                    'file_bytes' => $file->getSize(), 'file_sha256' => hash_file('sha256', $file->getRealPath())];
            }
            $source = WorkspaceKnowledgeSource::create($attributes);
            if ($file) $this->extractSource($source, $gateway);
        } catch (Throwable $error) {
            if ($path && ! $source) Storage::disk('workspace_private')->delete($path);
            throw $error;
        } finally { $lock->release(); }

        return redirect()->route('portal.workspaces.knowledge.show', [$workspace, $source])
            ->with('success', 'သိမ်းပြီးပါပြီ။ စာသားကို စစ်ပြီး AI သုံးရန် အတည်ပြုပေးပါ။');
    }

    public function show(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source): View
    {
        $this->source($request, $workspace, $source);

        return view('portal.workspaces.knowledge.show', compact('workspace', 'source'));
    }

    private function original(WorkspaceKnowledgeSource $source): string
    {
        abort_unless($source->file_path && str_starts_with($source->file_path, 'workspaces/'.$source->workspace_id.'/'), 404);
        $bytes = Storage::disk('workspace_private')->get($source->file_path);
        abort_unless(is_string($bytes) && hash_equals((string) $source->file_sha256, hash('sha256', $bytes)), 409);

        return $bytes;
    }

    private function extractSource(WorkspaceKnowledgeSource $source, PartnerAiGateway $gateway): void
    {
        try {
            $bytes = $this->original($source);
            if ($source->mime_type === 'text/plain') {
                if (! mb_check_encoding($bytes, 'UTF-8')) throw new \RuntimeException('UTF8_REQUIRED');
                $result = ['text' => mb_substr($bytes, 0, 60000), 'partial' => mb_strlen($bytes) > 60000];
            } else { $result = $gateway->extractPrivate($bytes, $source->mime_type); }
            if (trim($result['text']) === '') throw new \RuntimeException('NO_READABLE_TEXT');
            $source->update(['extracted_text' => $result['text'], 'reviewed_text' => $result['text'],
                'extracted_partial' => $result['partial'], 'processing_status' => 'needs_review']);
        } catch (Throwable) {
            // Upstream exception messages can contain private text or file data.
            // Preserve the original; the Owner can retry or transcribe manually.
            $source->update(['processing_status' => 'extraction_failed']);
        }
    }

    public function extract(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source, PartnerAiGateway $gateway): RedirectResponse
    {
        $this->source($request, $workspace, $source);
        $request->validate(['revision' => ['required', 'integer']]);
        $lock = $this->access->lock($workspace);
        try {
            $source->refresh();
            abort_unless($source->revision === $request->integer('revision'), 409);
            // Re-extraction never overwrites an already reviewed or approved note.
            abort_unless($source->processing_status === 'extraction_failed' && trim((string) $source->reviewed_text) === '', 409);
            $this->extractSource($source, $gateway);
        } finally { $lock->release(); }

        return redirect()->route('portal.workspaces.knowledge.show', [$workspace, $source]);
    }

    public function update(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source, PartnerAiGateway $gateway): RedirectResponse
    {
        $this->source($request, $workspace, $source);
        $values = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'content' => ['required', 'string', 'max:60000'],
            'business_status' => ['required', Rule::in(array_keys(WorkspaceKnowledgeSource::BUSINESS_STATUSES))],
            'action' => ['required', Rule::in(['draft', 'approve'])],
            'reviewed' => ['exclude_unless:action,approve', 'required', 'accepted'], 'revision' => ['required', 'integer'],
        ]);
        abort_if(trim($values['title']) === '' || trim($values['content']) === '', 422);
        $lock = $this->access->lock($workspace); $failed = false;
        try {
            $source->refresh();
            abort_unless($source->revision === $request->integer('revision'), 409, 'စာမျက်နှာကို refresh လုပ်ပြီး ပြန်စမ်းပါ။');
            DB::transaction(function () use ($source, $values): void {
                WorkspaceKnowledgeChunk::where('source_id', $source->id)->delete();
                $source->update(['title' => trim($values['title']), 'reviewed_text' => trim($values['content']),
                    'business_status' => $values['business_status'], 'processing_status' => 'needs_review',
                    'revision' => $source->revision + 1, 'ready_at' => null, 'embedding_key' => null]);
            });
            if ($values['action'] === 'approve') {
                try { $this->knowledge->approve($source, $gateway); } catch (Throwable) { $failed = true; }
            }
        } finally { $lock->release(); }
        $response = redirect()->route('portal.workspaces.knowledge.show', [$workspace, $source]);

        return $failed ? $response->withErrors(['knowledge' => 'စာသားကို သိမ်းထားပါတယ်။ AI သုံးရန် အတည်ပြုမှု မပြီးသေးလို့ ခဏနေရင် ပြန်စမ်းပါ။'])
            : $response->with('success', $source->processing_status === 'ready' ? 'AI သုံးရန် အတည်ပြုပြီးပါပြီ။' : 'စစ်ဆေးဆဲအဖြစ် သိမ်းထားပါတယ်။ AI က မသုံးသေးပါ။');
    }

    public function download(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source): Response
    {
        $this->source($request, $workspace, $source);
        $bytes = $source->kind === 'text' ? (string) $source->extracted_text : $this->original($source);

        return response($bytes, 200, ['Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="knowledge-'.$source->id.'.'.($source->kind === 'text' ? 'txt' : pathinfo($source->file_path, PATHINFO_EXTENSION)).'"',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox']);
    }

    public function destroy(Request $request, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source): RedirectResponse
    {
        $this->source($request, $workspace, $source);
        $lock = $this->access->lock($workspace);
        try {
            if ($source->file_path) Storage::disk('workspace_private')->delete($source->file_path);
            $source->delete();
        } finally { $lock->release(); }

        return redirect()->route('portal.workspaces.knowledge.index', $workspace)
            ->with('success', 'Knowledge နှင့် မူရင်းဖိုင်ကို ဖယ်ပြီးပါပြီ။ Chat အဟောင်းရှိ အဖြေများကို ဖျက်လိုပါက စကားဝိုင်းကိုလည်း ဖျက်နိုင်ပါတယ်။');
    }
}
