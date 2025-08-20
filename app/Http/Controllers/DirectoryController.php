<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');

        $teams = Team::query()
            ->select(['id', 'name', 'description', 'img', 'title', 'degree', 'email', 'bio', 'group_1', 'program', 'team'])
            ->when($query, function ($q, $query) {
                // Search across name and description with proper grouping
                $q->where(function ($qq) use ($query) {
                    $qq->where('name', 'like', "%{$query}%")
                       ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return Inertia::render('Directory/Index', [
            'teams' => $teams,
            'query' => $query,
        ]);
    }

    public function show(Team $team)
    {
        return Inertia::render('Directory/Show', [
            'team' => $team,
        ]);
    }

    public function edit(Team $team)
    {
        return Inertia::render('Directory/Edit', [
            'team' => $team,
        ]);
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:500',
            'degree' => 'nullable|string|max:255',
            'email' => 'required|email',
            'bio' => 'nullable|string',
            'img' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'message' => 'nullable|string',
            'group_1' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:128',
            'team' => 'nullable|string|max:128',
            'department' => 'nullable|string|max:128',
        ]);

        $team->update($validated);

        return redirect()->route('directory.show', $team);
    }

    public function updateImage(Request $request, Team $team)
    {
        $validated = $request->validate([
            'img' => 'nullable|string|max:500',
        ]);

        $team->update($validated);

        return response()->json(['message' => 'Image updated successfully.']);
    }
}
