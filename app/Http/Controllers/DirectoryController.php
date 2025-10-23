<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');
        $group = $request->input('group', 'default');
        $program = $request->input('program');

        $teams = Team::query()
            ->select(['id', 'first_name', 'last_name', 'description', 'img', 'title', 'degree', 'email', 'bio', 'group_1', 'program', 'team'])
            ->when($group === 'default', function ($q) {
                $q->whereIn('group_1', ['leadership', 'team']);
            })
            ->when($group && $group !== 'default', function ($q) use ($group) {
                $q->where('group_1', $group);
            })
            ->when($program, function ($q, $program) {
                $q->where('program', $program);
            })
            ->when($query, function ($q, $query) {
                // Search across first/last name and description with proper grouping
                $q->where(function ($qq) use ($query) {
                    $qq->where('first_name', 'like', "%{$query}%")
                       ->orWhere('last_name', 'like', "%{$query}%")
                       ->orWhere(DB::raw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))"), 'like', "%{$query}%")
                       ->orWhere('description', 'like', "%{$query}%");
                });
            })
            // Default sort: by id ascending
            ->orderBy('id')
            ->limit(50)
            ->get();

        // Get available programs for filter dropdown
        $availablePrograms = Team::query()
            ->whereNotNull('program')
            ->where('program', '!=', '')
            ->distinct()
            ->pluck('program')
            ->sort()
            ->values();

        return Inertia::render('Directory/Index', [
            'teams' => $teams,
            'query' => $query,
            'group' => $group,
            'program' => $program,
            'availablePrograms' => $availablePrograms,
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
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

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

                // Generate a clean filename from the person's name and append a short token to avoid cache issues
                $fullName = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
                $baseName = str_replace(' ', '_', $fullName !== '' ? $fullName : 'profile');
                $token = Str::lower(Str::random(6));
                $permanent_filename = $baseName . '-' . $token . '.jpg';
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

        return redirect()
            ->route('directory.show', $team)
            ->with('success', 'Directory entry updated successfully!');
    }

    public function create()
    {
        return Inertia::render('Directory/Create');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        if ($request->filled('tmp_folder')) {
            $tmpFilename = $request->input('tmp_folder');
            $temp_path = public_path('storage/images/people/temp/' . $tmpFilename);
            
            if (file_exists($temp_path)) {
                $fullName = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
                $baseName = str_replace(' ', '_', $fullName !== '' ? $fullName : 'profile');
                $token = Str::lower(Str::random(6));
                $permanent_filename = $baseName . '-' . $token . '.jpg';
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

        return redirect()
            ->route('directory.show', $team)
            ->with('success', 'Directory entry created successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team)
    {
        try {
            // Delete associated image if it exists
            if ($team->img) {
                $imagePath = public_path('storage' . $team->img);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $team->delete();

            return redirect()
                ->route('directory.index')
                ->with('success', 'Directory entry deleted successfully!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete directory entry. Please try again.');
        }
    }
}
