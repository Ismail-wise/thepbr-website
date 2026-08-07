<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;

class AboutController extends Controller
{
    public function index()
    {
        $nextClass = ClassSession::query()
            ->where('is_visible', true)
            ->whereDate('starts_on', '>=', now()->startOfDay())
            ->orderBy('starts_on')
            ->first();

        return view('about', compact('nextClass'));
    }
}
