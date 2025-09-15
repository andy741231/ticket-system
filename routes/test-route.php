<?php

use App\Models\Newsletter\Subscriber;
use Illuminate\Http\Request;

Route::get('/test-subscriber-count', function (Request $request) {
    $payload = [
        'total_active_subscribers' => Subscriber::where('status', 'active')->count()
    ];

    // Only return JSON for non-Inertia API clients
    if (!$request->header('X-Inertia') && $request->acceptsJson()) {
        return response()->json($payload);
    }

    // For Inertia or normal web requests, redirect back to prevent plain JSON responses
    return redirect()->back();
});
