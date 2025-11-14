<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LogoController extends Controller
{
    protected string $baseFolder = 'images/newsletters/logos';

    public function index(Request $request)
    {
        $folder = $this->baseFolder;
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $files = Storage::disk('public')->files($folder);
        $logos = [];
        foreach ($files as $path) {
            // Only images by extension
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                continue;
            }
            $logos[] = [
                'filename' => basename($path),
                'path' => $path,
                'url' => url(Storage::url($path)),
                'size' => Storage::disk('public')->size($path),
                'modified_at' => Storage::disk('public')->lastModified($path),
            ];
        }

        // Sort by latest modified desc
        usort($logos, function ($a, $b) {
            return ($b['modified_at'] ?? 0) <=> ($a['modified_at'] ?? 0);
        });

        return response()->json(['logos' => $logos]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'logo' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp,svg|max:2048',
        ]);

        $file = $request->file('logo');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize filename
        $filename = preg_replace('/[^A-Za-z0-9_.-]/', '-', $filename);
        $filename = ltrim($filename, '.');
        
        // Ensure unique filename
        $counter = 1;
        $finalName = $filename . '.' . $extension;
        while (Storage::disk('public')->exists($this->baseFolder . '/' . $finalName)) {
            $finalName = $filename . '-' . $counter . '.' . $extension;
            $counter++;
        }

        $path = $file->storeAs($this->baseFolder, $finalName, 'public');

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'filename' => $finalName,
            'path' => $path,
            'url' => url(Storage::url($path)),
            'size' => Storage::disk('public')->size($path),
            'modified_at' => Storage::disk('public')->lastModified($path),
        ]);
    }

    public function destroy(Request $request, string $filename)
    {
        $filename = basename($filename); // prevent path traversal
        $path = $this->baseFolder . '/' . $filename;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }
        Storage::disk('public')->delete($path);
        return response()->json(['message' => 'Deleted']);
    }

    public function rename(Request $request, string $filename)
    {
        $request->validate([
            'new_name' => 'required|string|max:255',
        ]);

        $oldFilename = basename($filename);
        $oldPath = $this->baseFolder . '/' . $oldFilename;
        if (!Storage::disk('public')->exists($oldPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $newNameInput = trim($request->input('new_name'));
        // Sanitize new name
        $newNameInput = preg_replace('/[^A-Za-z0-9_.-]/', '-', $newNameInput);
        if ($newNameInput === '' || $newNameInput === '.' || $newNameInput === '..') {
            return response()->json(['message' => 'Invalid name'], 422);
        }

        // Preserve original extension if user did not provide one
        $origExt = pathinfo($oldFilename, PATHINFO_EXTENSION);
        $providedExt = pathinfo($newNameInput, PATHINFO_EXTENSION);
        if ($providedExt === '') {
            $newName = pathinfo($newNameInput, PATHINFO_FILENAME) . ($origExt ? ('.' . $origExt) : '');
        } else {
            $newName = $newNameInput;
        }

        $newName = ltrim($newName, '.');
        $newPath = $this->baseFolder . '/' . $newName;
        if ($newPath === $oldPath) {
            return response()->json(['message' => 'No changes'], 200);
        }
        if (Storage::disk('public')->exists($newPath)) {
            return response()->json(['message' => 'A file with that name already exists'], 409);
        }

        Storage::disk('public')->move($oldPath, $newPath);
        return response()->json([
            'message' => 'Renamed',
            'filename' => basename($newPath),
            'url' => url(Storage::url($newPath)),
        ]);
    }
}
