<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Categories are fixed in code, matching ArticleController — a separate
     * lookup table would be more machinery than five stable values need.
     */
    public const CATEGORIES = [
        'Getting started',
        'Capital',
        'Ownership',
        'Governance',
        'Exit',
    ];

    public function index(Request $request)
    {
        $category = $request->query('category');
        $q = trim((string) $request->query('q', ''));

        // Only published videos are ever visible to the public. A video with a
        // future published_at is scheduled, not live.
        $base = Video::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $videos = (clone $base)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%");
            }))
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $counts = (clone $base)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $categories = [];
        foreach (self::CATEGORIES as $name) {
            if (($counts[$name] ?? 0) > 0) {
                $categories[$name] = $counts[$name];
            }
        }

        return view('videos.index', [
            'videos' => $videos,
            'categories' => $categories,
            'totalCount' => (clone $base)->count(),
            'category' => $category,
            'q' => $q,
        ]);
    }

    public function show(string $slug)
    {
        $video = Video::where('slug', $slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $related = Video::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $video->id)
            ->where('category', $video->category)
            ->latest('published_at')
            ->take(3)
            ->get();

        // Not enough in the same category? Top up with the most recent, so the
        // page never ends with a dead end.
        if ($related->count() < 3) {
            $related = $related->concat(
                Video::query()
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('id', '!=', $video->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->latest('published_at')
                    ->take(3 - $related->count())
                    ->get()
            );
        }

        return view('videos.show', compact('video', 'related'));
    }
}
