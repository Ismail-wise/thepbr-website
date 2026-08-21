<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Public discoverability: social cards, canonical URLs, sitemap and robots.
 *
 * The homepage carries Facebook and Viber share buttons — the two channels
 * that matter most for this audience — but shared links previously rendered as
 * bare URLs because no Open Graph tags existed. These tests keep that fixed.
 */
it('gives every public page open graph and canonical tags', function (string $route): void {
    $response = $this->get($route);

    $response->assertOk();
    $response->assertSee('property="og:title"', false);
    $response->assertSee('property="og:description"', false);
    $response->assertSee('property="og:image"', false);
    $response->assertSee('name="twitter:card"', false);
    $response->assertSee('rel="canonical"', false);
})->with(['/', '/about', '/classes', '/articles']);

it('points canonical at the clean url without query parameters', function (): void {
    // Campaign tags and pagination must not fork the canonical, or the same
    // page competes with itself in search results.
    $response = $this->get('/about?utm_source=facebook&utm_campaign=launch');

    $response->assertOk();
    $response->assertSee('<link rel="canonical" href="'.url('/about').'">', false);
    $response->assertDontSee('utm_source=facebook', false);
});

it('describes the organization once per page in structured data', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"Organization"', false);
});

it('marks an article as an article, not a website', function (): void {
    $article = Article::create([
        'title' => 'မိတ်ဖက်လုပ်ငန်း စတင်ခြင်း',
        'slug' => 'partnership-start',
        'excerpt' => 'အခြေခံ အချက်များ',
        'category' => 'Guide',
        'body' => "စာပိုဒ် တစ်\n\nစာပိုဒ် နှစ်",
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles/'.$article->slug);

    $response->assertOk();
    $response->assertSee('content="article"', false);
    $response->assertSee('"@type":"Article"', false);
});

it('serves a sitemap listing public pages and published articles', function (): void {
    $published = Article::create([
        'title' => 'Published piece',
        'slug' => 'published-piece',
        'excerpt' => 'Visible',
        'category' => 'Guide',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    $response->assertSee(route('home'), false);
    $response->assertSee(route('articles.show', $published->slug), false);
});

it('keeps unpublished articles out of the sitemap', function (): void {
    $draft = Article::create([
        'title' => 'Draft piece',
        'slug' => 'draft-piece',
        'excerpt' => 'Hidden',
        'category' => 'Guide',
        'body' => 'Body',
        'published_at' => null,
    ]);

    $future = Article::create([
        'title' => 'Scheduled piece',
        'slug' => 'scheduled-piece',
        'excerpt' => 'Later',
        'category' => 'Guide',
        'body' => 'Body',
        'published_at' => now()->addWeek(),
    ]);

    $response = $this->get('/sitemap.xml');

    // A crawler sent to a draft wastes crawl budget and may 404.
    $response->assertDontSee($draft->slug, false);
    $response->assertDontSee($future->slug, false);
});

it('disallows the private portal in robots.txt', function (): void {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /workspaces');
    expect($robots)->toContain('Disallow: /account');
    expect($robots)->toContain('Sitemap: https://thepbr.io/sitemap.xml');
});

it('links privacy and terms from the footer', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee(route('privacy'), false);
    $response->assertSee(route('terms'), false);

    $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    $this->get('/terms')->assertOk()->assertSee('Terms of Service');
});

it('states plainly that tool output is not legal advice', function (): void {
    // The product handles partnership money and governance. The disclaimer
    // carried inside the tools must also exist on the public terms page.
    $response = $this->get('/terms');

    $response->assertOk();
    $response->assertSee('ဥပဒေရေးရာ');
});

it('points the article social card at the real cover image path', function (): void {
    // cover_image holds a path relative to the storage disk. Every <img> on
    // the site renders it as asset('storage/'.$cover), so the social card
    // must use the same prefix or Facebook fetches a 404.
    $article = Article::create([
        'title' => 'Cover image article',
        'slug' => 'cover-image-article',
        'excerpt' => 'Has a cover',
        'category' => 'Guide',
        'cover_image' => 'articles/cover.jpg',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles/'.$article->slug);

    $response->assertOk();
    $response->assertSee('storage/articles/cover.jpg', false);
});

it('labels article cover images for screen readers', function (): void {
    $article = Article::create([
        'title' => 'မိတ်ဖက်လုပ်ငန်း အခြေခံ',
        'slug' => 'labelled-cover',
        'excerpt' => 'Excerpt',
        'category' => 'Guide',
        'cover_image' => 'articles/cover.jpg',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles');

    $response->assertOk();
    // An empty alt on a content image announces nothing at all.
    expect($response->getContent())->toContain('alt="မိတ်ဖက်လုပ်ငန်း အခြေခံ"');
});

it('falls back to a real image when no social card exists yet', function (): void {
    // Until images/og-default.png is created, the logo is used. A missing
    // og:image is worse than a badly-shaped one: Facebook shows no preview
    // at all rather than a cropped card.
    $response = $this->get('/');

    $response->assertOk();

    $usesCard = str_contains($response->getContent(), 'images/og-default.png');
    $usesLogo = str_contains($response->getContent(), 'images/pbr-logo.png');

    expect($usesCard || $usesLogo)->toBeTrue();

    // Whichever is used must actually exist on disk.
    $file = $usesCard ? 'images/og-default.png' : 'images/pbr-logo.png';
    expect(file_exists(public_path($file)))->toBeTrue();
});

it('only declares image dimensions for the known-size social card', function (): void {
    // Article covers vary in size, so claiming 1200x630 for them would tell
    // Facebook something false and produce a stretched preview.
    $article = Article::create([
        'title' => 'Sized cover',
        'slug' => 'sized-cover',
        'excerpt' => 'Excerpt',
        'category' => 'Guide',
        'cover_image' => 'articles/cover.jpg',
        'body' => 'Body',
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/articles/'.$article->slug);

    $response->assertOk();
    $response->assertDontSee('og:image:width', false);
});
