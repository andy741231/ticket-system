<?php

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\App as SubApp;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppsController extends Controller
{
    public function index(Request $request)
    {
        $apps = SubApp::query()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Rbac/Apps/Index', [
            'apps' => $apps,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9\-]+$/', Rule::unique('apps', 'slug')],
            'name' => ['required', 'string', 'max:100'],
        ]);

        // Create app
        $app = SubApp::create($validated);

        // Create default roles for this app (team_id = app id)
        Role::firstOrCreate(
            ['slug' => 'admin', 'guard_name' => 'web', 'team_id' => $app->id],
            ['name' => 'admin', 'is_mutable' => false, 'description' => 'Administrator']
        );
        Role::firstOrCreate(
            ['slug' => 'user', 'guard_name' => 'web', 'team_id' => $app->id],
            ['name' => 'user', 'is_mutable' => true, 'description' => 'Standard User']
        );

        return redirect()
            ->route('admin.rbac.apps.index')
            ->with('success', 'App created.');
    }
}
