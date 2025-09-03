<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'name' => 'required|string|max:255',
            'folder' => 'nullable|string|max:255',
        ]);

        $file = $request->file('image');
        $name = $request->input('name');
        $slug = Str::slug($name);
        $extension = $file->getClientOriginalExtension();
        $fileName = $slug . '.' . $extension;

        // Determine and sanitize target folder (default kept for backward compatibility)
        $folder = $request->input('folder', 'images/people');
        $folder = trim($folder, '/');
        // Only allow safe characters and limited separators
        $folder = preg_replace('/[^A-Za-z0-9_\/-]/', '', $folder) ?: 'images/people';
        // Prevent path traversal and enforce base path
        if (str_contains($folder, '..')) {
            $folder = 'images/people';
        }
        if (!str_starts_with($folder, 'images/')) {
            $folder = 'images/' . ltrim($folder, '/');
        }

        // Ensure target directory exists on the public disk
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $path = $file->storeAs($folder, $fileName, 'public');

        $publicUrl = Storage::url($path);           // e.g., /storage/images/...
        $absoluteUrl = url($publicUrl);             // e.g., https://example.com/storage/images/...
        return response()->json(['url' => $absoluteUrl]);
    }
}
