<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class DashboardController
{
    public function index(Request $request): RedirectResponse
    {
        // Redirect to Users management index as the Admin landing page
        return redirect()->route('admin.users.index');
    }
}
