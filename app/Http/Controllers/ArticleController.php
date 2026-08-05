<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /** The five categories, fixed in code — no separate table needed. */
    public const CATEGORIES = [
        'Agreement',
        'Profit split',
        'Exit',
        'Structure',
        'Decisions',
    ];

    public function index(Request $request)
    {
        $category = $request->query('category');
        $q        = trim((string) $request->query('q', ''));

        // Only published articles are ever visible to the public.
        $base = Article::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $articles = (clone $base)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($q, fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            }))
            ->latest('published_at')
            ->paginate(7)
            ->withQueryString();

        // Counts for the filter chips.
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

        return view('articles.index', [
            'articles'    => $articles,
            'categories'  => $categories,
            'totalCount'  => (clone $base)->count(),
            'category'    => $category,
            'q'           => $q,
            // The wide "Newest" card only makes sense on an unfiltered first page.
            'showFeature' => ! $category && $q === '' && $articles->currentPage() === 1,
        ]);
    }

    public function show(string $slug)
    {
        $article = Article::where('slug', $slug)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        $related = Article::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->latest('published_at')
            ->take(3)
            ->get();

        // Not enough in the same category? Top up with the most recent.
        if ($related->count() < 3) {
            $related = $related->concat(
                Article::query()
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('id', '!=', $article->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->latest('published_at')
                    ->take(3 - $related->count())
                    ->get()
            );
        }

        return view('articles.show', compact('article', 'related'));
    }
}
