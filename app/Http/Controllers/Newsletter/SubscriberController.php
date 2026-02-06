<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\Group;
use App\Models\Newsletter\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubscriberController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Subscriber::class, 'subscriber', [
            'except' => ['getActiveCount']
        ]);
    }
    
    /**
     * Get total count of active subscribers
     */
    public function getActiveCount()
    {
        $count = Subscriber::where('status', 'active')->count();
        
        return response()->json([
            'total_active_subscribers' => $count
        ]);
    }

    public function testCount()
    {
        return response()->json(['count' => 123]);
    }

    public function index(Request $request)
    {
        $request->validate([
            // Allow empty values from the UI without failing validation
            'status' => 'nullable|string|in:active,unsubscribed,bounced,pending',
            'search' => 'nullable|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'page' => 'sometimes|integer|min:1',
            'group_id' => 'nullable|string'
        ]);

        $query = Subscriber::with('groups');

        // Filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Group filter
        if ($request->filled('group_id')) {
            if ($request->group_id === 'no_group') {
                // Filter for subscribers with no groups
                $query->whereDoesntHave('groups');
            } else {
                // Filter for subscribers in a specific group
                $query->whereHas('groups', function ($q) use ($request) {
                    $q->where('newsletter_groups.id', $request->group_id);
                });
            }
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('organization', 'like', "%{$search}%");
            });
        }

        // Order and paginate
        $perPage = $request->input('per_page', 15);
        $subscribers = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $groups = Group::active()->get();

        // Default: return Inertia response for web requests (including Inertia navigations)
        return Inertia::render('Newsletter/Subscribers/Index', [
            'subscribers' => $subscribers,
            'groups' => $groups,
            'filters' => $request->only(['search', 'status', 'group_id', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $baseRules = [
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'unsubscribed', 'bounced', 'pending'])],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ];

        $validator = Validator::make($request->all(), $baseRules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $existingSubscriber = Subscriber::where('email', $request->email)->first();
        if ($existingSubscriber) {
            if ($request->filled('groups')) {
                $requestedGroupIds = $request->groups;
                $existingGroupIds = $existingSubscriber->groups()->pluck('newsletter_groups.id')->toArray();
                
                $newGroups = array_diff($requestedGroupIds, $existingGroupIds);
                $duplicateGroups = array_intersect($requestedGroupIds, $existingGroupIds);
                
                if (empty($newGroups) && !empty($duplicateGroups)) {
                    $groupNames = Group::whereIn('id', $duplicateGroups)->pluck('name')->toArray();
                    return back()->withErrors([
                        'groups' => 'Subscriber is already in: ' . implode(', ', $groupNames)
                    ]);
                }
                
                if (!empty($newGroups)) {
                    $existingSubscriber->groups()->syncWithoutDetaching($newGroups);
                }
                
                // If there are duplicates, show as warning in modal (not flash success)
                if (!empty($duplicateGroups)) {
                    $message = [];
                    if (!empty($newGroups)) {
                        $addedNames = Group::whereIn('id', $newGroups)->pluck('name')->toArray();
                        $message[] = 'Subscriber added successfully to: ' . implode(', ', $addedNames);
                    }
                    $duplicateNames = Group::whereIn('id', $duplicateGroups)->pluck('name')->toArray();
                    $message[] = 'Subscriber is already in: ' . implode(', ', $duplicateNames);
                    
                    return back()->withErrors([
                        'groups' => implode("\n", $message)
                    ]);
                }
                
                // Only new groups added - complete success
                return back()->with('success', 'Subscriber added to group(s) successfully.');
            }

            return back()->withErrors(['email' => 'Subscriber already exists but no groups selected.']);
        }

        $subscriber = Subscriber::create($validator->validated());

        // Handle organization in metadata
        if ($request->filled('organization')) {
            $subscriber->organization = $request->organization;
            $subscriber->save();
        }

        if ($request->filled('groups')) {
            $subscriber->groups()->sync($request->groups);
        }

        return back()->with('success', 'Subscriber created successfully.');
    }

    public function show(Subscriber $subscriber)
    {
        $subscriber->load(['groups', 'analyticsEvents.campaign']);
        
        return Inertia::render('Newsletter/Subscribers/Show', [
            'subscriber' => $subscriber,
            'groups' => Group::active()->get(),
            'analytics' => [
                'total_campaigns' => $subscriber->analyticsEvents()->distinct('campaign_id')->count(),
                'total_opens' => $subscriber->analyticsEvents()->where('event_type', 'opened')->count(),
                'total_clicks' => $subscriber->analyticsEvents()->where('event_type', 'clicked')->count(),
                'recent_events' => $subscriber->analyticsEvents()
                    ->with('campaign')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get(),
            ],
        ]);
    }

    public function update(Request $request, Subscriber $subscriber)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', Rule::unique('newsletter_subscribers', 'email')->ignore($subscriber->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'unsubscribed', 'bounced', 'pending'])],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $subscriber->update($validator->validated());

        // Handle organization in metadata
        $subscriber->organization = $request->organization;
        $subscriber->save();

        if ($request->has('groups')) {
            $subscriber->groups()->sync($request->groups ?? []);
        }

        return back()->with('success', 'Subscriber updated successfully.');
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();
        return back()->with('success', 'Subscriber deleted successfully.');
    }

    public function downloadImportTemplate()
    {
        $filename = 'subscriber_import_template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'First Name', 'Last Name', 'Organization']);
            fputcsv($file, ['example@email.com', 'John', 'Doe', 'Example Corp']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'group_id' => ['nullable', 'exists:newsletter_groups,id'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        
        $imported = 0;
        $existingAddedToGroup = 0;
        $existingNoChange = 0;
        $errors = [];
        $addedEmails = [];
        $existingAddedEmails = [];
        $existingNoChangeEmails = [];
        $row = 0;

        // Skip header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            
            if (empty($data[0])) continue;

            // Sanitize and validate input data
            $email = isset($data[0]) ? trim($data[0]) : '';
            $firstName = isset($data[1]) && $data[1] !== '' ? mb_substr(trim($data[1]), 0, 255) : null;
            $lastName = isset($data[2]) && $data[2] !== '' ? mb_substr(trim($data[2]), 0, 255) : null;
            $organization = isset($data[3]) && $data[3] !== '' ? mb_substr(trim($data[3]), 0, 255) : null;

            // Build full name from first and last name (limit to 255 chars)
            $name = null;
            if ($firstName && $lastName) {
                $name = mb_substr(trim($firstName . ' ' . $lastName), 0, 255);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$row}: Invalid email format";
                continue;
            }

            try {
                $existingSubscriber = Subscriber::where('email', $email)->first();
                
                if ($existingSubscriber) {
                    // Check if group is specified and if subscriber is already in it
                    if ($request->filled('group_id')) {
                        $isInGroup = $existingSubscriber->groups()->where('newsletter_groups.id', $request->group_id)->exists();
                        if (!$isInGroup) {
                            // Existing subscriber added to NEW group - treat as success
                            $existingSubscriber->groups()->attach($request->group_id);
                            $existingAddedToGroup++;
                            $existingAddedEmails[] = $email;
                        } else {
                            // Existing subscriber already in this group - no change
                            $existingNoChange++;
                            $existingNoChangeEmails[] = $email;
                        }
                    } else {
                        // No group specified, subscriber exists - no change
                        $existingNoChange++;
                        $existingNoChangeEmails[] = $email;
                    }
                    continue;
                }

                $subscriber = Subscriber::create([
                    'email' => $email,
                    'name' => $name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => 'active',
                ]);

                // Handle organization in metadata
                if ($organization) {
                    $subscriber->organization = $organization;
                    $subscriber->save();
                }

                if ($request->filled('group_id')) {
                    $subscriber->groups()->attach($request->group_id);
                }

                $imported++;
                $addedEmails[] = $email;
            } catch (\Exception $e) {
                $errors[] = "Row {$row}: Database error - " . $e->getMessage();
                continue;
            }
        }

        fclose($handle);

        $report = [
            'imported' => $imported,
            'existing_added_to_group' => $existingAddedToGroup,
            'existing_no_change' => $existingNoChange,
            'added_emails' => $addedEmails,
            'existing_added_emails' => $existingAddedEmails,
            'existing_no_change_emails' => $existingNoChangeEmails,
        ];

        $totalSuccess = $imported + $existingAddedToGroup;
        $message = "Import completed: {$totalSuccess} subscriber(s) processed successfully";
        if ($existingNoChange > 0) {
            $message .= ", {$existingNoChange} already existed with no changes";
        }
        
        return back()->with('success', $message)
                    ->with('import_report', $report)
                    ->with('import_errors', $errors);
    }

    public function bulkExport(Request $request)
    {
        $query = Subscriber::with('groups');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('group_id')) {
            if ($request->group_id === 'no_group') {
                $query->whereDoesntHave('groups');
            } else {
                $query->whereHas('groups', function ($q) use ($request) {
                    $q->where('newsletter_groups.id', $request->group_id);
                });
            }
        }

        $subscribers = $query->get();

        $filename = 'subscribers_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($subscribers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'Name', 'First Name', 'Last Name', 'Organization', 'Status', 'Groups', 'Subscribed At']);

            foreach ($subscribers as $subscriber) {
                fputcsv($file, [
                    $subscriber->email,
                    $subscriber->name,
                    $subscriber->first_name,
                    $subscriber->last_name,
                    $subscriber->organization, // This will use the accessor
                    $subscriber->status,
                    $subscriber->groups->pluck('name')->join(', '),
                    $subscriber->subscribed_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'subscriber_ids' => ['required', 'array'],
            'subscriber_ids.*' => ['exists:newsletter_subscribers,id'],
        ]);

        $count = Subscriber::whereIn('id', $request->subscriber_ids)->delete();

        return back()->with('success', "Deleted {$count} subscribers successfully.");
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'subscriber_ids' => ['required', 'array'],
            'subscriber_ids.*' => ['exists:newsletter_subscribers,id'],
            'status' => ['required', Rule::in(['active', 'unsubscribed', 'bounced', 'pending'])],
        ]);

        $count = Subscriber::whereIn('id', $request->subscriber_ids)
            ->update(['status' => $request->status]);

        return back()->with('success', "Updated status for {$count} subscribers successfully.");
    }

    public function findByEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $subscriber = Subscriber::with('groups')
            ->where('email', $request->email)
            ->first();

        return response()->json([
            'subscriber' => $subscriber,
        ]);
    }

    public function addToGroups(Request $request, Subscriber $subscriber)
    {
        $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        if ($request->has('groups')) {
            $subscriber->groups()->sync($request->groups);
        }

        return back()->with('success', 'Subscriber groups updated successfully.');
    }
}
