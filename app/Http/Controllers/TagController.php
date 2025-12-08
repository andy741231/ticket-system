<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TagController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        return Inertia::render('Tickets/Labels/Index', [
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name',
        ]);

        Tag::create($validated);

        return redirect()->back()->with('success', 'Label created successfully.');
    }

    public function update(Request $request, Tag $tag)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,' . $tag->id,
        ]);

        $tag->update($validated);

        return redirect()->back()->with('success', 'Label updated successfully.');
    }

    public function destroy(Tag $tag)
    {
        $this->authorizeAccess();

        $tag->delete();

        return redirect()->back()->with('success', 'Label deleted successfully.');
    }

    /**
     * Check if the user is authorized to manage tags.
     */
    protected function authorizeAccess()
    {
        $user = Auth::user();
        
        // Allow if Super Admin
        if ($user->isSuperAdmin()) {
            return;
        }

        // Allow if user has "tickets admin" role
        // We need to check if they have the role specifically for the tickets app
        // Assuming the role name is "tickets admin" as seen in seeders
        $hasTicketAdmin = $user->roles()
            ->where('name', 'tickets admin')
            ->exists();

        if ($hasTicketAdmin) {
            return;
        }

        abort(403, 'Unauthorized access. Only Ticket Admins or Superadmins can manage labels.');
    }
}
