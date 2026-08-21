<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Videos are YouTube embeds, not uploaded files.
 *
 * The fragile part is the link: whoever adds a video will paste whatever the
 * YouTube UI handed them — a watch URL, a Share short link, a Shorts link,
 * usually with a ?si= share token or ?t= timestamp attached. The model
 * normalises all of those to a bare ID on save, so a tracking parameter can
 * never reach an embed and the rest of the app never has to re-parse a URL.
 */
function makeVideo(array $attributes = []): Video
{
    return Video::create(array_merge([
        'title' => 'မိတ်ဖက်လုပ်ငန်း အခြေခံ',
        'slug' => 'partnership-basics',
        'excerpt' => 'အခြေခံ အချက်များ',
        'category' => 'Getting started',
        'youtube_id' => 'dQw4w9WgXcQ',
        'published_at' => now()->subDay(),
    ], $attributes));
}

it('extracts the video id from every common youtube link shape', function (string $input): void {
    expect(Video::extractYoutubeId($input))->toBe('dQw4w9WgXcQ');
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'https://www.youtube.com/live/dQw4w9WgXcQ',
    'dQw4w9WgXcQ',
]);

it('strips share tokens and timestamps from a pasted link', function (): void {
    // The Share button adds ?si=..., and "copy at current time" adds &t=.
    // Storing either would carry a tracking parameter into the embed.
    $video = makeVideo([
        'youtube_id' => 'https://youtu.be/dQw4w9WgXcQ?si=Ab1Cd2Ef3&t=42',
    ]);

    expect($video->youtube_id)->toBe('dQw4w9WgXcQ');
});

it('builds embed and thumbnail urls from the stored id', function (): void {
    $video = makeVideo();

    // youtube-nocookie keeps the privacy policy honest: no tracking cookies
    // until the viewer actually presses play.
    expect($video->embed_url)->toStartWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');

    // hqdefault, not maxresdefault: the latter 404s for videos uploaded below
    // a certain resolution, leaving a broken image with no fallback.
    expect($video->thumbnail_url)->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
});

it('shows a published video and lists it on the index', function (): void {
    $video = makeVideo();

    $this->get('/videos')
        ->assertOk()
        ->assertSee($video->title);

    $this->get('/videos/'.$video->slug)
        ->assertOk()
        ->assertSee($video->title);
});

it('hides drafts and scheduled videos from the public', function (): void {
    $draft = makeVideo(['slug' => 'draft-video', 'title' => 'Draft video', 'published_at' => null]);
    $future = makeVideo(['slug' => 'future-video', 'title' => 'Future video', 'published_at' => now()->addWeek()]);

    $index = $this->get('/videos');
    $index->assertOk();
    $index->assertDontSee('Draft video');
    $index->assertDontSee('Future video');

    $this->get('/videos/'.$draft->slug)->assertNotFound();
    $this->get('/videos/'.$future->slug)->assertNotFound();
});

it('does not load the youtube iframe until the viewer asks for it', function (): void {
    $video = makeVideo();

    $response = $this->get('/videos/'.$video->slug);

    $response->assertOk();
    // A YouTube iframe pulls roughly a megabyte of scripts before anyone has
    // decided to watch. On Myanmar mobile data that cost should be opt-in.
    $response->assertDontSee('<iframe', false);
    $response->assertSee('vplayer', false);
});

it('lists published videos in the sitemap and keeps drafts out', function (): void {
    $live = makeVideo(['slug' => 'live-video']);
    $draft = makeVideo(['slug' => 'hidden-video', 'published_at' => null]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertSee(route('videos.index'), false);
    $response->assertSee(route('videos.show', $live->slug), false);
    $response->assertDontSee('hidden-video', false);
});

it('links videos from the site navigation', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('videos.index'), false);
});
