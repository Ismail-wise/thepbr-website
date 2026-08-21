<?php

use App\Models\Article;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Slugs are generated so nobody has to type one, and the byline is set once
 * in config rather than hardcoded in the template.
 *
 * The generator works from the English words in a title because Burmese
 * percent-encodes into an unreadable URL, which defeats the purpose: Google
 * reads the words in a URL, and so does anyone deciding whether to click a
 * link shared on Facebook.
 */
it('builds a slug from the english words in a burmese title', function (): void {
    $article = Article::create([
        'title' => 'Partnership Business မစခင် သဘောတူထားရမည့် အချက်',
        'excerpt' => 'Excerpt',
        'category' => 'Agreement',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    expect($article->slug)->toBe('partnership-business');
});

it('falls back to a usable slug when a title has no english at all', function (): void {
    $article = Article::create([
        'title' => 'မိတ်ဖက်လုပ်ငန်း စည်းမျဉ်းများ',
        'excerpt' => 'Excerpt',
        'category' => 'Agreement',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    // Not empty, and reachable.
    expect($article->slug)->not->toBe('');
    $this->get('/articles/'.$article->slug)->assertOk();
});

it('keeps recurring phrases unique instead of colliding', function (): void {
    // "Partnership Business" will appear in many titles on this site, so
    // collisions are the normal case rather than an edge case.
    $first = Article::create([
        'title' => 'Partnership Business အခြေခံ',
        'excerpt' => 'One', 'category' => 'Agreement', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $second = Article::create([
        'title' => 'Partnership Business ဒုတိယပိုင်း',
        'excerpt' => 'Two', 'category' => 'Agreement', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    expect($first->slug)->toBe('partnership-business');
    expect($second->slug)->toBe('partnership-business-2');

    $this->get('/articles/'.$first->slug)->assertOk()->assertSee('One');
    $this->get('/articles/'.$second->slug)->assertOk()->assertSee('Two');
});

it('never changes an existing slug when the title is edited', function (): void {
    $article = Article::create([
        'title' => 'Capital Planning အခြေခံ',
        'excerpt' => 'Excerpt', 'category' => 'Agreement', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $original = $article->slug;

    $article->update(['title' => 'Ownership Equity လုံးဝ ကွဲပြားသော ခေါင်းစဉ်']);

    // The old URL may already be shared on Facebook or indexed by Google.
    // Regenerating it on edit would silently break every one of those links.
    expect($article->fresh()->slug)->toBe($original);
});

it('respects a slug that was typed by hand', function (): void {
    $article = Article::create([
        'title' => 'Partnership Business အခြေခံ',
        'slug' => 'my-chosen-url',
        'excerpt' => 'Excerpt', 'category' => 'Agreement', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    expect($article->slug)->toBe('my-chosen-url');
});

it('generates slugs for videos the same way', function (): void {
    $video = Video::create([
        'title' => 'Profit Split ဘယ်လို တွက်မလဲ',
        'excerpt' => 'Excerpt',
        'category' => 'Getting started',
        'youtube_id' => 'dQw4w9WgXcQ',
        'published_at' => now()->subDay(),
    ]);

    expect($video->slug)->toBe('profit-split');
    $this->get('/videos/'.$video->slug)->assertOk();
});

it('shows the configured author on an article', function (): void {
    $article = Article::create([
        'title' => 'Governance အခြေခံ',
        'excerpt' => 'Excerpt', 'category' => 'Agreement', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles/'.$article->slug);

    $response->assertOk();
    $response->assertSee(config('pbr.author_name'));

    // The byline previously named someone other than the instructor shown on
    // the homepage; it must not reappear.
    $response->assertDontSee('စည်သူအောင်');
});

it('names the author in article structured data', function (): void {
    $article = Article::create([
        'title' => 'Exit Buyout အခြေခံ',
        'excerpt' => 'Excerpt', 'category' => 'Exit', 'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles/'.$article->slug);

    $response->assertOk();
    $response->assertSee('"@type":"Person"', false);
    $response->assertSee(config('pbr.author_name'), false);
});
