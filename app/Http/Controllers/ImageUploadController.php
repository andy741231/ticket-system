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
        ]);

        $file = $request->file('image');
        $name = $request->input('name');
        $slug = Str::slug($name);
        $extension = $file->getClientOriginalExtension();
        $fileName = $slug . '.' . $extension;

        $path = $file->storeAs('images/people', $fileName, 'public');

        return response()->json(['url' => Storage::url($path)]);
    }
}
