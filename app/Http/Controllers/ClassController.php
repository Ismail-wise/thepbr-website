<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;

class ClassController extends Controller
{
    public function index()
    {
        $visible = ClassSession::query()->where('is_visible', true);

        $upcoming = (clone $visible)
            ->whereDate('starts_on', '>=', now()->startOfDay())
            ->orderBy('starts_on')
            ->get();

        $past = (clone $visible)
            ->whereDate('starts_on', '<', now()->startOfDay())
            ->orderByDesc('starts_on')
            ->get();

        // Stats come from real records — nothing is hand-typed.
        $stats = [
            'sessions'  => $past->count(),
            'students'  => $past->sum('enrolled'),
            'locations' => $past->pluck('location')->unique()->count(),
        ];

        // Past classes grouped by calendar year for the archive.
        $pastByYear = $past->groupBy(fn ($item) => $item->starts_on->year);

        return view('classes', compact('upcoming', 'pastByYear', 'stats'));
    }
}
