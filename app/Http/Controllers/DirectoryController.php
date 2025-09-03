<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

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
            'description' => 'nullable|string',
            'message' => 'nullable|string',
            'group_1' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:128',
            'team' => 'nullable|string|max:128',
            'department' => 'nullable|string|max:128',
            'tmp_folder' => 'nullable|string',
            'tmp_filename' => 'nullable|string',
        ]);

                if ($request->filled('tmp_folder')) {
            // The tmp_folder now contains the unique temporary filename
            $tmpFilename = $request->input('tmp_folder');
            $temp_path = public_path('storage/images/people/temp/' . $tmpFilename);
            
            if (file_exists($temp_path)) {
                // Delete old image if it exists
                if ($team->img) {
                    $old_path = public_path('storage' . $team->img);
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }

                // Generate a clean filename from the person's name
                $permanent_filename = str_replace(' ', '_', $validated['name']) . '.jpg';
                $destination_path = public_path('storage/images/people/' . $permanent_filename);
                
                // Ensure the directory exists
                if (!file_exists(dirname($destination_path))) {
                    mkdir(dirname($destination_path), 0755, true);
                }
                
                // Move from temp to final location
                rename($temp_path, $destination_path);
                
                $validated['img'] = '/storage/images/people/' . $permanent_filename;
            }
        } elseif (is_null($request->input('img'))) {
            // Handle case where image is deleted without a new one
            if ($team->img) {
                $old_path = public_path('storage' . $team->img);
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            $validated['img'] = null;
        }

        $team->update($validated);

        return redirect()->route('directory.show', $team);
    }


    public function create()
    {
        return Inertia::render('Directory/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:500',
            'degree' => 'nullable|string|max:255',
            'email' => 'required|email|unique:directory_team,email',
            'bio' => 'nullable|string',
            'description' => 'nullable|string',
            'message' => 'nullable|string',
            'group_1' => 'nullable|string|max:100',
            'program' => 'nullable|string|max:128',
            'team' => 'nullable|string|max:128',
            'department' => 'nullable|string|max:128',
            'tmp_folder' => 'nullable|string',
            'tmp_filename' => 'nullable|string',
        ]);

        if ($request->filled('tmp_folder')) {
            $tmpFilename = $request->input('tmp_folder');
            $temp_path = public_path('storage/images/people/temp/' . $tmpFilename);
            
            if (file_exists($temp_path)) {
                $permanent_filename = str_replace(' ', '_', $validated['name']) . '.jpg';
                $destination_path = public_path('storage/images/people/' . $permanent_filename);
                
                // Ensure the directory exists
                if (!file_exists(dirname($destination_path))) {
                    mkdir(dirname($destination_path), 0755, true);
                }
                
                // Move from temp to final location
                rename($temp_path, $destination_path);
                
                $validated['img'] = '/storage/images/people/' . $permanent_filename;
            }
        }

        $team = Team::create($validated);

        return redirect()->route('directory.show', $team);
    }
}
