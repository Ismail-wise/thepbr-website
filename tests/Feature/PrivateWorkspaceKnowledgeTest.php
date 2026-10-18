<?php

use App\Models\PartnershipWorkspace;
use App\Models\PortalConversation;
use App\Models\User;
use App\Models\WorkspaceAiConversation;
use App\Models\WorkspaceKnowledgeChunk;
use App\Models\WorkspaceKnowledgeSource;
use App\Services\Portal\PrivateWorkspaceAccess;
use App\Services\Portal\WorkspaceKnowledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function knowledgeOwner(array $values = []): User
{
    return User::factory()->create($values + ['role'=>'student', 'is_admin'=>false, 'account_status'=>'active', 'portal_access_expires_at'=>now()->addMonth()]);
}
function knowledgeWorkspace(User $owner, string $name = 'Private business'): PartnershipWorkspace
{
    return PartnershipWorkspace::create(['owner_user_id'=>$owner->id,'name'=>$name,'business_name'=>$name,'status'=>'active']);
}
function knowledgeNote(PartnershipWorkspace $workspace, string $text = 'Owner confidential text', array $extra = []): WorkspaceKnowledgeSource
{
    return WorkspaceKnowledgeSource::create($extra + ['workspace_id'=>$workspace->id,'title'=>'Owner note','kind'=>'text','business_status'=>'current','processing_status'=>'needs_review','reviewed_text'=>$text,'extracted_text'=>$text])->fresh();
}
function knowledgeApprove($test, PartnershipWorkspace $workspace, WorkspaceKnowledgeSource $source, array $extra = []): void
{
    $test->put(route('portal.workspaces.knowledge.update', [$workspace,$source]), $extra + ['title'=>$source->title,'content'=>$source->reviewed_text,'business_status'=>$source->business_status,'revision'=>$source->revision,'action'=>'approve','reviewed'=>1])->assertRedirect();
}
function knowledgeFakeAi(string $sse = "data: {\"type\":\"delta\",\"text\":\"Owner advice [S1]\"}\n\ndata: {\"type\":\"done\"}\n\n", bool $failExtraction = false, array $queryVector = []): void
{
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use ($sse, $failExtraction, $queryVector) {
        if (str_ends_with($request->url(), '/health')) return Http::response(['service'=>'pbr-ai-advisor','knowledgeReady'=>true,'vectors'=>657,'privateKnowledgeProtocol'=>1]);
        if (str_ends_with($request->url(), '/private/embed')) return Http::response(['vectors'=>array_map(fn ($text)=>$request['mode']==='query' && $queryVector !== [] ? $queryVector : array_fill(0,128,0.25), $request['texts']), 'embeddingKey'=>'test:128:private-v1']);
        if (str_ends_with($request->url(), '/private/extract')) return $failExtraction ? Http::response(['error'=>'PRIVATE PROVIDER PAYLOAD'],503) : Http::response(['text'=>'မြန်မာ PDF 250,000','partial'=>false]);
        if (str_ends_with($request->url(), '/internal/pbr/chat')) return Http::response($sse,200,['Content-Type'=>'text/event-stream']);
        throw new RuntimeException('Unexpected isolated HTTP request');
    });
}

beforeEach(function (): void {
    config(['partner_portal.enabled'=>true,'pbr_ai.base_url'=>'http://127.0.0.1:3107','pbr_ai.internal_secret'=>str_repeat('private-test-',4)]);
    Storage::fake('workspace_private');Http::preventStrayRequests();
});

test('Owner text requires review and business status stays distinct from AI readiness', function (): void {
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);knowledgeFakeAi();
    $this->actingAs($owner)->get(route('portal.workspaces.knowledge.create',$workspace))->assertOk();
    $this->post(route('portal.workspaces.knowledge.store',$workspace),['title'=>'Proposed agreement','business_status'=>'draft','content'=>'အကြမ်းဖျဉ်း 25%','processing_status'=>'ready','workspace_id'=>999])->assertRedirect();
    $source=WorkspaceKnowledgeSource::firstOrFail();
    expect($source->workspace_id)->toBe($workspace->id)->and($source->processing_status)->toBe('needs_review')->and(WorkspaceKnowledgeChunk::count())->toBe(0);
    Http::assertNothingSent();
    $this->put(route('portal.workspaces.knowledge.update',[$workspace,$source]),['title'=>'Note','content'=>'Changed','business_status'=>'current','action'=>'approve','revision'=>$source->revision])->assertSessionHasErrors('reviewed');
    knowledgeApprove($this,$workspace,$source);
    expect($source->fresh()->processing_status)->toBe('ready')->and($source->fresh()->business_status)->toBe('draft')->and(WorkspaceKnowledgeChunk::count())->toBe(1);
    $this->get(route('portal.workspaces.knowledge.show',[$workspace,$source]))->assertOk()->assertHeader('Cache-Control','no-store, private');
});

test('private PDF and photo originals stay outside public storage and return only through Owner authorization', function (): void {
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);knowledgeFakeAi();$this->actingAs($owner);
    foreach ([UploadedFile::fake()->createWithContent('agreement.pdf',"%PDF-1.7\nprivate original"), UploadedFile::fake()->createWithContent('note.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ioAAAAASUVORK5CYII='))] as $file) {
        $this->post(route('portal.workspaces.knowledge.store',$workspace),['title'=>'Private upload','business_status'=>'historical','file'=>$file])->assertRedirect();
        $source=WorkspaceKnowledgeSource::latest('id')->firstOrFail();
        expect($source->reviewed_text)->toBe('မြန်မာ PDF 250,000')->and($source->processing_status)->toBe('needs_review');
        Storage::disk('workspace_private')->assertExists($source->file_path);
        expect($source->file_path)->toStartWith('workspaces/'.$workspace->id.'/')->not->toContain($file->getClientOriginalName());
        $this->get(route('portal.workspaces.knowledge.download',[$workspace,$source]))->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Content-Security-Policy','sandbox')->assertHeader('Cache-Control','no-store, private');
    }
    Http::assertSent(fn(ClientRequest $r)=>str_ends_with($r->url(),'/private/extract') && isset($r['data']) && $r->hasHeader('X-PBR-Internal-Secret'));
});

test('all private source chat download and mutation URLs deny other owners admins and invitation only Partners',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$source=knowledgeNote($workspace);
    $chat=WorkspaceAiConversation::create(['workspace_id'=>$workspace->id,'user_id'=>$owner->id,'title'=>'Secret chat','knowledge_signature'=>str_repeat('a',64)]);
    $partner=knowledgeOwner(['role'=>'public','portal_access_expires_at'=>null]);
    $workspace->memberships()->create(['user_id'=>$partner->id,'member_role'=>'partner','invitation_status'=>'accepted','invited_email'=>$partner->email,'permissions'=>['approved_workspace_read_only']]);
    foreach ([knowledgeOwner(),knowledgeOwner(['role'=>'admin','is_admin'=>true]),$partner] as $actor) {
        $this->actingAs($actor);
        foreach ([['GET','knowledge.index',[$workspace]],['GET','knowledge.create',[$workspace]],['POST','knowledge.store',[$workspace]],['GET','knowledge.show',[$workspace,$source]],['GET','knowledge.download',[$workspace,$source]],['PUT','knowledge.update',[$workspace,$source]],['POST','knowledge.extract',[$workspace,$source]],['DELETE','knowledge.destroy',[$workspace,$source]],['GET','ai',[$workspace]],['POST','ai.chat',[$workspace]],['DELETE','ai.destroy',[$workspace,$chat]]] as [$method,$name,$params]) {
            $response=$this->call($method,route('portal.workspaces.'.$name,$params));
            expect(in_array($response->status(),[403,404],true))->toBeTrue($name.' '.$response->status());
        }
    }
    Http::assertNothingSent();expect(WorkspaceKnowledgeSource::count())->toBe(1)->and(WorkspaceAiConversation::count())->toBe(1);
});

test('one Owner cannot mix sources or chat history between their own Workspaces or personal AI', function():void{
    $owner=knowledgeOwner();$a=knowledgeWorkspace($owner,'Business A');$b=knowledgeWorkspace($owner,'Business B');
    $source=knowledgeNote($a);$conversation=WorkspaceAiConversation::create(['workspace_id'=>$a->id,'user_id'=>$owner->id,'title'=>'Secret A','knowledge_signature'=>str_repeat('a',64)]);
    $this->actingAs($owner)->get(route('portal.workspaces.knowledge.show',[$b,$source]))->assertNotFound();
    $this->get(route('portal.workspaces.knowledge.download',[$b,$source]))->assertNotFound();
    $this->delete(route('portal.workspaces.knowledge.destroy',[$b,$source]))->assertNotFound();
    $this->get(route('portal.workspaces.ai',[$b,'conversation'=>$conversation->id]))->assertNotFound();
    $this->postJson(route('portal.workspaces.ai.chat',$b),['message'=>'private','conversation_id'=>$conversation->id])->assertNotFound();
    $this->delete(route('portal.workspaces.ai.destroy',[$b,$conversation]))->assertNotFound();
    $this->get(route('portal.ai'))->assertOk()->assertDontSee('Secret A');
    expect(PortalConversation::count())->toBe(0);Http::assertNothingSent();
});

test('Workspace AI retrieves only reviewed sources from the selected Workspace and saves private citations',function():void{
    $owner=knowledgeOwner();$a=knowledgeWorkspace($owner,'Selected A');$b=knowledgeWorkspace($owner,'Other B');knowledgeFakeAi();$this->actingAs($owner);
    $current=knowledgeNote($a,'CURRENT AGREEMENT 55%');knowledgeApprove($this,$a,$current);
    $draft=knowledgeNote($a,'UNAPPROVED SECRET');$foreign=knowledgeNote($b,'FOREIGN WORKSPACE SECRET');knowledgeApprove($this,$b,$foreign);
    $past=knowledgeNote($a,'PAST AGREEMENT 20%',['business_status'=>'historical']);knowledgeApprove($this,$a,$past);
    $response=$this->postJson(route('portal.workspaces.ai.chat',$a),['message'=>'What is current?','workspace_id'=>$b->id,'workspaceContext'=>['evil'=>'FOREIGN']])->assertOk();
    expect($response->streamedContent())->toContain('Owner advice [S1]')->toContain('"sources"');
    Http::assertSent(function(ClientRequest $r):bool {
        if(!str_ends_with($r->url(),'/internal/pbr/chat')) return false;
        $json=json_encode($r['workspaceContext']);
        return $r['workspaceContext']['scope']==='owner_private_knowledge_v1' && str_contains($json,'CURRENT AGREEMENT') && str_contains($json,'PAST AGREEMENT') && str_contains($json,'historical') && !str_contains($json,'UNAPPROVED SECRET') && !str_contains($json,'FOREIGN');
    });
    $chat=WorkspaceAiConversation::firstOrFail();expect($chat->workspace_id)->toBe($a->id)->and($chat->messages()->count())->toBe(2)->and(PortalConversation::count())->toBe(0);
    $assistant=$chat->messages()->where('role','assistant')->first();expect($assistant->metadata['sources'])->toHaveCount(2);
    $this->get(route('portal.workspaces.ai',[$a,'conversation'=>$chat->id]))->assertOk()->assertSee('Selected A')->assertSee('Owner advice [S1]')->assertSee('Knowledge');
});

test('editing or deleting knowledge stops reuse of obsolete private answers and physically removes originals',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);knowledgeFakeAi();$source=knowledgeNote($workspace);$this->actingAs($owner);knowledgeApprove($this,$workspace,$source);$source->refresh();
    $response=$this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'First question']);$response->streamedContent();$chat=WorkspaceAiConversation::firstOrFail();
    $this->put(route('portal.workspaces.knowledge.update',[$workspace,$source]),['title'=>'Revised','content'=>'NEW CURRENT TEXT','business_status'=>'current','action'=>'draft','revision'=>$source->revision])->assertRedirect();
    expect($source->fresh()->processing_status)->toBe('needs_review')->and(WorkspaceKnowledgeChunk::count())->toBe(0);
    $this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'Continue','conversation_id'=>$chat->id])->assertStatus(409);
    $this->get(route('portal.workspaces.ai',[$workspace,'conversation'=>$chat->id]))->assertOk()->assertSee('Knowledge သို့မဟုတ်');
    Storage::disk('workspace_private')->put('workspaces/'.$workspace->id.'/fixture.txt','original private');
    $source->update(['file_path'=>'workspaces/'.$workspace->id.'/fixture.txt']);
    $this->delete(route('portal.workspaces.knowledge.destroy',[$workspace,$source]))->assertRedirect();Storage::disk('workspace_private')->assertMissing('workspaces/'.$workspace->id.'/fixture.txt');
    $this->get(route('portal.workspaces.ai',[$workspace,'conversation'=>$chat->id]))->assertOk()->assertSee('ဖယ်ထားပြီး');
    $this->delete(route('portal.workspaces.ai.destroy',[$workspace,$chat]))->assertRedirect();expect($chat->messages()->count())->toBe(0);
});

test('extraction failures preserve originals and manual review can recover without exposing provider errors',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$this->actingAs($owner);
    knowledgeFakeAi(failExtraction: true);
    $this->post(route('portal.workspaces.knowledge.store',$workspace),['title'=>'Scan','business_status'=>'current','file'=>UploadedFile::fake()->createWithContent('scan.pdf',"%PDF-1.7\nprivate")])->assertRedirect();
    $source=WorkspaceKnowledgeSource::firstOrFail();expect($source->processing_status)->toBe('extraction_failed');Storage::disk('workspace_private')->assertExists($source->file_path);
    $this->get(route('portal.workspaces.knowledge.show',[$workspace,$source]))->assertOk()->assertDontSee('PRIVATE PROVIDER PAYLOAD');
    knowledgeFakeAi();knowledgeApprove($this,$workspace,$source,['content'=>'Owner transcribed 30%']);expect($source->fresh()->processing_status)->toBe('ready');
});

test('stale forms and concurrent chat cannot overwrite or delete knowledge mid-answer',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$source=knowledgeNote($workspace);$this->actingAs($owner);
    $source->update(['revision'=>3]);
    $this->put(route('portal.workspaces.knowledge.update',[$workspace,$source]),['title'=>'Stale','content'=>'Old','business_status'=>'current','action'=>'draft','revision'=>1])->assertStatus(409);
    $lock=app(PrivateWorkspaceAccess::class)->lock($workspace);
    $this->delete(route('portal.workspaces.knowledge.destroy',[$workspace,$source]))->assertStatus(409);
    $this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'No concurrent read'])->assertStatus(409);$lock->release();
    expect($source->fresh()->reviewed_text)->toBe('Owner confidential text');Http::assertNothingSent();
});

test('bad uploads whitespace source text and expired access fail before provider calls',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$this->actingAs($owner);
    $this->post(route('portal.workspaces.knowledge.store',$workspace),['title'=>'Executable','business_status'=>'current','file'=>UploadedFile::fake()->createWithContent('bad.svg','<svg><script>bad()</script></svg>')])->assertSessionHasErrors('file');
    $this->post(route('portal.workspaces.knowledge.store',$workspace),['title'=>'Empty','business_status'=>'current','content'=>'   '])->assertSessionHasErrors('content');
    $owner->update(['portal_access_expires_at'=>now()->subMinute()]);
    $this->get(route('portal.workspaces.knowledge.index',$workspace))->assertForbidden();
    $this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'Denied'])->assertForbidden();
    expect(WorkspaceKnowledgeSource::count())->toBe(0);Http::assertNothingSent();
});

test('partial streams remain marked incomplete and private model changes require reapproval',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$source=knowledgeNote($workspace);$this->actingAs($owner);knowledgeFakeAi();knowledgeApprove($this,$workspace,$source);
    knowledgeFakeAi("data: {\"type\":\"delta\",\"text\":\"Partial\"}\n\n");
    $response=$this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'Try']);expect($response->streamedContent())->toContain('"type":"error"');
    $chat=WorkspaceAiConversation::firstOrFail();expect($chat->messages()->where('role','assistant')->first()->metadata['incomplete'])->toBeTrue();
    $source->update(['embedding_key'=>'changed-model']);
    $this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'New chat'])->assertStatus(503);
    expect(WorkspaceAiConversation::count())->toBe(1);
});


test('the historical test reset refuses all real private knowledge and conversations',function():void{
    $owner=knowledgeOwner();knowledgeOwner(['is_admin'=>true,'role'=>'admin']);$workspace=knowledgeWorkspace($owner);knowledgeNote($workspace);
    $cleanup=app(\App\Services\Portal\PortalTestDataCleanup::class);$before=$cleanup->fingerprint();
    expect(fn()=>$cleanup->plan())->toThrow(RuntimeException::class,'PRIVATE_WORKSPACE_DATA_PRESENT');
    expect($cleanup->fingerprint())->toBe($before);
});


test('legacy Workspace deletion cannot leave private originals orphaned and metadata edits honor active AI work',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$source=knowledgeNote($workspace);$this->actingAs($owner);
    $this->delete(route('workspaces.destroy',$workspace),['confirmation_name'=>$workspace->name])->assertStatus(409);
    expect($source->fresh())->not->toBeNull();
    $lock=app(PrivateWorkspaceAccess::class)->lock($workspace);
    $this->put(route('portal.workspaces.update',$workspace),['name'=>'During chat'])->assertStatus(409);
    $this->put(route('workspaces.update',$workspace),['name'=>'Legacy during chat'])->assertStatus(409);
    $lock->release();expect($workspace->fresh()->name)->toBe('Private business');
});


test('long Burmese questions keep the full user message while retrieval stays within the multilingual embedding budget',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$source=knowledgeNote($workspace);$this->actingAs($owner);knowledgeFakeAi();knowledgeApprove($this,$workspace,$source);
    $question=str_repeat('မြန်မာ ',600).' FULL QUESTION END';
    $response=$this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>$question]);$response->assertOk();$response->streamedContent();
    Http::assertSent(fn(ClientRequest $r)=>str_ends_with($r->url(),'/internal/pbr/chat') && $r['message']===$question);
    Http::assertSent(fn(ClientRequest $r)=>str_ends_with($r->url(),'/private/embed') && $r['mode']==='query' && mb_strlen($r['texts'][0])<=1800);
});


test('source references stay stable when a followup retrieves a different document first',function():void{
    $owner=knowledgeOwner();$workspace=knowledgeWorkspace($owner);$this->actingAs($owner);knowledgeFakeAi();
    $a=knowledgeNote($workspace,'Agreement A');$b=knowledgeNote($workspace,'Agreement B');knowledgeApprove($this,$workspace,$a);knowledgeApprove($this,$workspace,$b);
    $va=array_fill(0,128,0.0);$va[0]=1.0;$vb=array_fill(0,128,0.0);$vb[1]=1.0;
    WorkspaceKnowledgeChunk::where('source_id',$a->id)->update(['embedding'=>json_encode($va)]);
    WorkspaceKnowledgeChunk::where('source_id',$b->id)->update(['embedding'=>json_encode($vb)]);
    config(['pbr_knowledge.retrieval_chunks'=>1]);knowledgeFakeAi(queryVector:$va);
    $first=$this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'Agreement A?']);$first->assertOk();$first->streamedContent();
    $chat=WorkspaceAiConversation::firstOrFail();$answer=$chat->messages()->where('role','assistant')->latest('id')->first();
    expect($answer->metadata['sources'][0]['id'])->toBe($a->id)->and($answer->metadata['sources'][0]['reference'])->toBe('S1');
    knowledgeFakeAi("data: {\"type\":\"delta\",\"text\":\"Agreement B [S2]\"}\n\ndata: {\"type\":\"done\"}\n\n",queryVector:$vb);
    $second=$this->postJson(route('portal.workspaces.ai.chat',$workspace),['message'=>'Agreement B?','conversation_id'=>$chat->id]);$second->assertOk();$second->streamedContent();
    $answer=$chat->messages()->where('role','assistant')->latest('id')->first();
    expect($answer->metadata['sources'][0]['id'])->toBe($b->id)->and($answer->metadata['sources'][0]['reference'])->toBe('S2');
});
