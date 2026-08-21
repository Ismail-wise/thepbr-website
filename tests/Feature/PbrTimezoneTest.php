<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Storage stays UTC; only admin input is read as Bangkok time.
 *
 * A video published at "2:22 PM" Thailand stayed hidden for seven hours
 * because the server read that as 2:22 PM UTC. The fix is per-field on the
 * date pickers, not a global APP_TIMEZONE change: Laravel writes whatever
 * timezone is configured straight into the database rather than converting,
 * so switching it globally would reinterpret every existing agreed_at,
 * effective_date and invitation_expires_at by seven hours.
 */
it('keeps the storage timezone on UTC', function (): void {
    // If this ever fails, 28 timestamp columns of existing data have just been
    // reinterpreted. It is the guard, not a formality.
    expect(config('app.timezone'))->toBe('UTC');
});

it('reads admin date input as Bangkok time', function (): void {
    expect(config('app.display_timezone'))->toBe('Asia/Bangkok');
});

it('publishes a video whose time has passed and withholds one that has not', function (): void {
    $live = Video::create([
        'title' => 'Live now',
        'slug' => 'live-now',
        'excerpt' => 'Visible',
        'category' => 'Getting started',
        'youtube_id' => 'dQw4w9WgXcQ',
        'published_at' => now()->subMinute(),
    ]);

    $soon = Video::create([
        'title' => 'Not yet',
        'slug' => 'not-yet',
        'excerpt' => 'Hidden',
        'category' => 'Getting started',
        'youtube_id' => 'dQw4w9WgXcQ',
        // Seven hours ahead is exactly the gap that caused the confusion.
        'published_at' => now()->addHours(7),
    ]);

    $response = $this->get('/videos');

    $response->assertOk();
    $response->assertSee($live->title);
    $response->assertDontSee($soon->title);

    $this->get('/videos/'.$soon->slug)->assertNotFound();
});
