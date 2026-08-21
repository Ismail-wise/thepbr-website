<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ClassSession;
use App\Models\Video;
use Illuminate\Http\Response;

/**
 * XML sitemap for search engines.
 *
 * Generated from the database rather than kept as a static file, so a newly
 * published article is discoverable without anyone remembering to update a
 * list. Only public, indexable pages appear: the entire authenticated portal
 * is excluded, and the portal layout already sends noindex,nofollow.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Static public pages, with change frequencies that reflect how these
        // pages actually behave rather than optimistic defaults.
        $urls[] = [
            'loc' => route('home'),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ];

        $urls[] = [
            'loc' => route('about'),
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ];

        $urls[] = [
            'loc' => route('articles.index'),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        $urls[] = [
            'loc' => route('videos.index'),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ];

        // The classes page changes whenever a session is added, so its
        // lastmod tracks the most recently touched visible session.
        $latestSession = ClassSession::query()
            ->where('is_visible', true)
            ->max('updated_at');

        $urls[] = array_filter([
            'loc' => route('classes'),
            'lastmod' => $latestSession
                ? \Illuminate\Support\Carbon::parse($latestSession)->toAtomString()
                : null,
            'changefreq' => 'weekly',
            'priority' => '0.9',
        ]);

        // Published articles only. A draft in the sitemap would send crawlers
        // to a 404 and waste crawl budget.
        Article::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (Article $article) use (&$urls): void {
                $urls[] = array_filter([
                    'loc' => route('articles.show', $article->slug),
                    'lastmod' => optional($article->updated_at ?? $article->published_at)
                        ->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ]);
            });

        // Published videos, same rule as articles: a draft or a future-dated
        // record in the sitemap sends a crawler to a 404.
        Video::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (Video $video) use (&$urls): void {
                $urls[] = array_filter([
                    'loc' => route('videos.show', $video->slug),
                    'lastmod' => optional($video->updated_at ?? $video->published_at)
                        ->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ]);
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }
}
