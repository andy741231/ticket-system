<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index()
    {
        $groups = Group::withCount(['subscribers', 'activeSubscribers'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Newsletter/Groups/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:newsletter_groups,name'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Group::create($validator->validated());

        return back()->with('success', 'Group created successfully.');
    }

    public function show(Group $group)
    {
        $group->load(['subscribers' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return Inertia::render('Newsletter/Groups/Show', [
            'group' => $group,
            'subscribers' => $group->subscribers()->paginate(25),
        ]);
    }

    public function update(Request $request, Group $group)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:newsletter_groups,name,' . $group->id],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $group->update($validator->validated());

        return back()->with('success', 'Group updated successfully.');
    }

    public function destroy(Group $group)
    {
        $group->delete();
        return back()->with('success', 'Group deleted successfully.');
    }

    public function addSubscribers(Request $request, Group $group)
    {
        $request->validate([
            'subscriber_ids' => ['required', 'array'],
            'subscriber_ids.*' => ['exists:newsletter_subscribers,id'],
        ]);

        $group->subscribers()->syncWithoutDetaching($request->subscriber_ids);

        return back()->with('success', 'Subscribers added to group successfully.');
    }

    public function removeSubscribers(Request $request, Group $group)
    {
        $request->validate([
            'subscriber_ids' => ['required', 'array'],
            'subscriber_ids.*' => ['exists:newsletter_subscribers,id'],
        ]);

        $group->subscribers()->detach($request->subscriber_ids);

        return back()->with('success', 'Subscribers removed from group successfully.');
    }
}
