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
    public function index(Request $request)
    {
        $query = Subscriber::with('groups');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Group filter
        if ($request->filled('group_id')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('newsletter_groups.id', $request->group_id);
            });
        }

        $subscribers = $query->orderBy('created_at', 'desc')->paginate(25);
        $groups = Group::active()->get();

        return Inertia::render('Newsletter/Subscribers/Index', [
            'subscribers' => $subscribers,
            'groups' => $groups,
            'filters' => $request->only(['search', 'status', 'group_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:newsletter_subscribers,email'],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'unsubscribed', 'bounced', 'pending'])],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $subscriber = Subscriber::create($validator->validated());

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
            'status' => ['required', Rule::in(['active', 'unsubscribed', 'bounced', 'pending'])],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $subscriber->update($validator->validated());

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

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'group_id' => ['nullable', 'exists:newsletter_groups,id'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        
        $imported = 0;
        $errors = [];
        $row = 0;

        // Skip header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            
            if (empty($data[0])) continue;

            $email = trim($data[0]);
            $name = isset($data[1]) ? trim($data[1]) : null;
            $firstName = isset($data[2]) ? trim($data[2]) : null;
            $lastName = isset($data[3]) ? trim($data[3]) : null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$row}: Invalid email format";
                continue;
            }

            if (Subscriber::where('email', $email)->exists()) {
                $errors[] = "Row {$row}: Email already exists";
                continue;
            }

            $subscriber = Subscriber::create([
                'email' => $email,
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'status' => 'active',
            ]);

            if ($request->filled('group_id')) {
                $subscriber->groups()->attach($request->group_id);
            }

            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Imported {$imported} subscribers successfully.")
                    ->with('errors', $errors);
    }

    public function bulkExport(Request $request)
    {
        $query = Subscriber::with('groups');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('group_id')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('newsletter_groups.id', $request->group_id);
            });
        }

        $subscribers = $query->get();

        $filename = 'subscribers_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($subscribers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Email', 'Name', 'First Name', 'Last Name', 'Status', 'Groups', 'Subscribed At']);

            foreach ($subscribers as $subscriber) {
                fputcsv($file, [
                    $subscriber->email,
                    $subscriber->name,
                    $subscriber->first_name,
                    $subscriber->last_name,
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
}
