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

    public function destroy(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'campaign_id' => 'nullable|integer|exists:newsletter_campaigns,id',
            'temp_key' => 'nullable|string|max:255',
        ]);

        $url = $request->input('url');
        $campaignId = $request->input('campaign_id');
        $tempKey = $request->input('temp_key');

        // Extract the path from the URL
        // URL format: https://example.com/storage/images/newsletters/campaign-123/file.jpg
        // We need: images/newsletters/campaign-123/file.jpg
        
        $path = null;
        
        // Try to extract path from /storage/ URL
        if (preg_match('#/storage/(.+)$#', $url, $matches)) {
            $path = $matches[1];
        }
        
        if (!$path) {
            return response()->json(['error' => 'Invalid URL format'], 400);
        }

        // Security: Only allow deletion from campaign-specific or temp folders
        $allowedPrefixes = ['images/newsletters/campaign-', 'images/newsletters/tmp/'];
        $isAllowed = false;
        
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        // Additional check: if campaign_id is provided, ensure path matches
        if ($campaignId && !str_contains($path, "campaign-{$campaignId}")) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Additional check: if temp_key is provided, ensure path matches
        if ($tempKey) {
            $sanitizedKey = trim(preg_replace('/[^A-Za-z0-9_-]/', '-', $tempKey), '-');
            if (!str_contains($path, "tmp/{$sanitizedKey}")) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        if (!$isAllowed) {
            return response()->json(['error' => 'Deletion not allowed for this path'], 403);
        }

        // Delete the file if it exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['success' => true, 'message' => 'File deleted']);
        }

        return response()->json(['success' => false, 'message' => 'File not found'], 404);
    }
}
