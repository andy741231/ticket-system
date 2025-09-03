<?php

use App\Models\Newsletter\Subscriber;

Route::get('/test-subscriber-count', function () {
    return response()->json([
        'total_active_subscribers' => Subscriber::where('status', 'active')->count()
    ]);
});
