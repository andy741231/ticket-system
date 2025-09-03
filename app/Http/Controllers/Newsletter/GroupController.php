<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\Group;
use App\Models\Newsletter\Subscriber;
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

    public function show(Request $request, Group $group)
    {
        $search = trim((string) $request->get('search', ''));
        $sort = $request->get('sort', 'created_at');
        $direction = strtolower($request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['email', 'name', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        // Current subscribers list with filters/sort
        $subsQuery = $group->subscribers()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            });

        if ($sort === 'email') {
            $subsQuery->orderBy('email', $direction);
        } elseif ($sort === 'name') {
            $subsQuery->orderBy('first_name', $direction)->orderBy('last_name', $direction);
        } else { // created_at
            $subsQuery->orderBy('created_at', $direction);
        }

        $subscribers = $subsQuery->paginate(25)->withQueryString();

        // Available subscribers to add (limited, client-side multi-select); filter on same search
        $optionsQuery = Subscriber::whereDoesntHave('groups', function ($q) use ($group) {
                $q->where('newsletter_groups.id', $group->id);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            });

        if ($sort === 'name') {
            $optionsQuery->orderBy('first_name', $direction)->orderBy('last_name', $direction);
        } else {
            $optionsQuery->orderBy('email', $direction);
        }

        return Inertia::render('Newsletter/Groups/Show', [
            'group' => $group,
            'subscribers' => $subscribers,
            'subscribersOptions' => $optionsQuery
                ->paginate(25)
                ->withQueryString()
                ->through(function ($s) {
                    return [
                        'id' => $s->id,
                        'email' => $s->email,
                        'first_name' => $s->first_name,
                        'last_name' => $s->last_name,
                    ];
                }),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
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

    public function getActiveSubscriberCountAttribute(): int
    {
        return $this->activeSubscribers()->count();
    }

    /**
     * Get count of unique active subscribers across multiple groups
     */
    public function getUniqueSubscribersCount(Request $request)
    {
        $validated = $request->validate([
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:newsletter_groups,id',
        ]);

        // De-duplicate incoming group IDs to avoid redundant work
        $groupIds = array_values(array_unique($validated['group_ids']));

        // Use EXISTS via whereHas on the base subscribers table to avoid join + DISTINCT overhead
        $count = Subscriber::query()
            ->where('status', 'active')
            ->whereHas('groups', function ($q) use ($groupIds) {
                $q->whereIn('newsletter_subscriber_groups.group_id', $groupIds);
            })
            ->count();

        return response()->json([
            'unique_subscribers_count' => $count,
        ]);
    }

    public function destroy(Group $group)
    {
        $group->delete();
        return back()->with('success', 'Group deleted successfully.');
    }

    public function addSubscribers(Request $request, Group $group)
    {
        $validated = $request->validate([
            'subscriber_ids' => 'required|array',
            'subscriber_ids.*' => 'exists:newsletter_subscribers,id',
        ]);

        $group->subscribers()->syncWithoutDetaching($validated['subscriber_ids']);

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
