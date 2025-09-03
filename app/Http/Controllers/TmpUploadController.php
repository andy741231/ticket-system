<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TmpUploadController extends Controller
{
    public function store(Request $request)
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalFilename = $file->getClientOriginalName();
            // Create a unique filename to avoid collisions in the temp directory
            $tmpFilename = uniqid() . '-' . now()->timestamp . '-' . $originalFilename;
            
            // Save to public/storage/images/people/temp/
            $tempDir = public_path('storage/images/people/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $file->move($tempDir, $tmpFilename);

            // Return the filename for identification
            return response()->json(['folder' => $tmpFilename, 'filename' => $tmpFilename]);
        }

        return response()->json(['error' => 'No file uploaded.'], 400);
    }

    public function destroy(Request $request)
    {
        // The 'folder' input now holds the unique temporary filename
        $tmpFilename = $request->input('folder');

        if ($tmpFilename) {
            $tempFilePath = public_path('storage/images/people/temp/' . $tmpFilename);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            return response()->json(['success' => 'Temporary file deleted.']);
        }

        return response()->json(['error' => 'No file specified.'], 400);
    }
}
