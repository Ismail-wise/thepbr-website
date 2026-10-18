<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Models\Article;
use App\Models\PartnershipWorkspace;
use App\Models\User;
use App\Models\Video;
use App\Models\WorkspaceKnowledgeSource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function publicationScriptPayload(): string
{
    return '</title></script><script data-owner-leak>window.ownerLeak=1</script>';
}
function publicationGraphs(string $html): array
{
    $dom=new DOMDocument;
    @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath=new DOMXPath($dom);$result=[];
    expect($xpath->query('//script[@data-owner-leak]')->length)->toBe(0);
    foreach($xpath->query('//script[@type="application/ld+json"]') as $node) $result[]=json_decode($node->textContent,true,512,JSON_THROW_ON_ERROR);
    return $result;
}

beforeEach(function():void {config(['partner_portal.enabled'=>true]);});

test('published Article metadata and paragraphs cannot inject script into an Owner browser',function():void{
    $payload=publicationScriptPayload();$description='"><script data-owner-leak>window.ownerLeak=2</script>';
    Article::create(['title'=>$payload,'slug'=>'safe-article-metadata','category'=>'Agreement','excerpt'=>$description,'body'=>$payload,'published_at'=>now()->subMinute()]);
    $owner=User::factory()->create(['role'=>'student','account_status'=>'active']);
    $response=$this->actingAs($owner)->get('/articles/safe-article-metadata')->assertOk()->assertDontSee('<script data-owner-leak>',false);
    $dom=new DOMDocument;@$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    expect($dom->getElementsByTagName('title')->item(0)->textContent)->toStartWith($payload);
    $graphs=collect(publicationGraphs($response->getContent()));
    expect($graphs->firstWhere('@type','Article')['headline'])->toBe($payload)
        ->and($graphs->firstWhere('@type','Article')['description'])->toBe($description)
        ->and($graphs->firstWhere('@type','Organization')['description'])->toBe($description);
    $response->assertSee(e($payload),false)->assertSee('\\u003C/script\\u003E',false);
});

test('published Video metadata remains valid structured data without executable closing tags',function():void{
    $payload=publicationScriptPayload();
    Video::create(['title'=>$payload,'slug'=>'safe-video-metadata','youtube_id'=>'dQw4w9WgXcQ','excerpt'=>$payload,'published_at'=>now()->subMinute()]);
    $response=$this->get('/videos/safe-video-metadata')->assertOk()->assertDontSee('<script data-owner-leak>',false);
    $graphs=collect(publicationGraphs($response->getContent()));
    expect($graphs->firstWhere('@type','VideoObject')['name'])->toBe($payload);
    $response->assertSee('dQw4w9WgXcQ')->assertSee(e($payload),false);
});

test('private Knowledge titles remain text even when pasted from an untrusted document',function():void{
    $owner=User::factory()->create(['role'=>'student','account_status'=>'active']);
    $workspace=PartnershipWorkspace::create(['owner_user_id'=>$owner->id,'name'=>publicationScriptPayload(),'status'=>'active']);
    $source=WorkspaceKnowledgeSource::create(['workspace_id'=>$workspace->id,'title'=>publicationScriptPayload(),'kind'=>'text','business_status'=>'draft','processing_status'=>'needs_review','reviewed_text'=>'Source text']);
    $this->actingAs($owner)->get(route('portal.workspaces.knowledge.show',[$workspace,$source]))->assertOk()->assertDontSee('<script data-owner-leak>',false)->assertSee(e(publicationScriptPayload()),false);
});

test('native Article publishing keeps bitmap covers and rejects active SVG uploads',function():void{
    Storage::fake('public');
    $admin=User::factory()->create(['role'=>'admin','is_admin'=>true,'account_status'=>'active']);$this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));Filament::bootCurrentPanel();
    $fields=['title'=>'Safe cover','category'=>'Agreement','excerpt'=>'Public excerpt','body'=>'Public body','published_at'=>null];
    $png=UploadedFile::fake()->createWithContent('cover.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ioAAAAASUVORK5CYII='));
    Livewire::test(CreateArticle::class)->fillForm($fields+['slug'=>'bitmap-cover','cover_image'=>$png])->call('create')->assertHasNoFormErrors();
    $article=Article::where('slug','bitmap-cover')->firstOrFail();Storage::disk('public')->assertExists($article->cover_image);
    $svg=UploadedFile::fake()->createWithContent('active.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>window.ownerLeak=1</script></svg>');
    Livewire::test(CreateArticle::class)->fillForm($fields+['slug'=>'active-cover','cover_image'=>$svg])->call('create')->assertHasFormErrors();
    expect(Article::where('slug','active-cover')->exists())->toBeFalse();
});
