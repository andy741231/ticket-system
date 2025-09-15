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
            'file' => 'required|file|max:15360', // 15MB = 15360KB
            'name' => 'required|string|max:255',
            'folder' => 'nullable|string|max:255',
            'campaign_id' => 'nullable|integer|exists:newsletter_campaigns,id',
            'temp_key' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $name = $request->input('name');
        $slug = Str::slug($name);
        $extension = $file->getClientOriginalExtension();
        $fileName = $slug . '.' . $extension;

        // Determine and sanitize target folder
        $baseFolder = $request->input('folder', 'images/people');
        $campaignId = $request->input('campaign_id');
        $tempKey = $request->input('temp_key');
        
        // If campaign_id is provided and folder is newsletters, create campaign-specific folder
        if ($campaignId && str_contains($baseFolder, 'newsletters')) {
            $folder = "images/newsletters/campaign-{$campaignId}";
        } elseif ($tempKey && str_contains($baseFolder, 'newsletters')) {
            // If we're in creation flow, use a temporary folder
            $folder = "images/newsletters/tmp/" . trim(preg_replace('/[^A-Za-z0-9_-]/', '-', $tempKey), '-');
        } else {
            $folder = $baseFolder;
        }
        
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
            Storage::disk('public')->makeDirectory($folder, 0755, true);
        }

        $path = $file->storeAs($folder, $fileName, 'public');

        $publicUrl = Storage::url($path);           // e.g., /storage/images/...
        $absoluteUrl = url($publicUrl);             // e.g., https://example.com/storage/images/...
        return response()->json(['url' => $absoluteUrl, 'path' => $path]);
    }
}
