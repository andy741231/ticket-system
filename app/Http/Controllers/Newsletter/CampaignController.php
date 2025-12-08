<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaign;
use App\Jobs\ProcessRecurringCampaigns;
use App\Jobs\ProcessScheduledSends;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Group;
use App\Models\Newsletter\Subscriber;
use App\Models\Newsletter\Template;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::with(['creator', 'template'])
            ->where('time_capsule', false); // Exclude time capsule campaigns

        // View/Status filter: default to 'in_progress'; support composite 'in_progress'
        $view = $request->input('status');
        if ($view === null || $view === '') {
            $view = 'in_progress';
        }
        if ($view === 'in_progress') {
            $query->whereIn('status', ['draft', 'scheduled', 'sending', 'paused', 'cancelled']);
        } elseif ($view === 'sent') {
            // Archives: only show sent campaigns, exclude drafts
            $query->where('status', 'sent');
        } else {
            // Back-compat: allow filtering by a specific status value
            $query->where('status', $view);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Per-page pagination support with sensible bounds
        $perPage = (int) $request->input('per_page', 25);
        $allowed = [25, 50, 100, 150, 200, 250];
        if (!in_array($perPage, $allowed, true)) {
            $perPage = 25;
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        return Inertia::render('Newsletter/Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function create()
    {
        $templates = Template::orderBy('is_default', 'desc')->orderBy('name')->get();
        $groups = Group::active()
            ->withCount(['activeSubscribers'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'color' => $group->color,
                    'is_active' => $group->is_active,
                    'active_subscriber_count' => $group->active_subscribers_count,
                ];
            });

        // Append protected virtual group (UHPH Directory)
        try {
            $dirEmails = Team::query()
                ->whereIn('group_1', ['team', 'leadership'])
                ->whereNotNull('email')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();

            // Count ALL directory members that match criteria, regardless of newsletter subscription status
            $protectedCount = $dirEmails->count();

            $groups->push([
                'id' => 'protected_dir_team',
                'name' => 'UHPH Directory',
                'description' => 'Auto-managed group of directory members (team, leadership).',
                'color' => '#334155',
                'is_active' => true,
                'active_subscriber_count' => $protectedCount,
            ]);
        } catch (\Throwable $e) {
            // Fail-safe: still render without protected group if directory connection missing
        }

        return Inertia::render('Newsletter/Campaigns/Create', [
            'templates' => $templates,
            'groups' => $groups,
            'defaultFromName' => env('MAIL_NEWSLETTER_FROM_NAME', config('mail.from.name', 'UHPH News')),
            'defaultFromEmail' => env('MAIL_NEWSLETTER_FROM_ADDRESS', config('mail.from.address', 'noreply@central.uh.edu')),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|array',
            'html_content' => 'required|string',
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'template_id' => 'nullable|exists:newsletter_templates,id',
            'send_type' => 'required|in:immediate,scheduled,recurring',
            'scheduled_at' => 'required_if:send_type,scheduled|date|after:now',
            'target_groups' => 'required_without:send_to_all|array',
            'target_groups.*' => [
                function ($attribute, $value, $fail) {
                    if ($value === 'protected_dir_team') {
                        return; // allow special virtual group id
                    }
                    $exists = \Illuminate\Support\Facades\DB::table('newsletter_groups')->where('id', $value)->exists();
                    if (!$exists) {
                        $fail('The selected group is invalid.');
                    }
                }
            ],
            'send_to_all' => 'boolean',
            'enable_tracking' => 'sometimes|boolean',
            'status' => 'sometimes|in:draft,scheduled,sending,sent,paused,cancelled',
            'recurring_config' => 'required_if:send_type,recurring|array',
            'recurring_config.frequency' => 'required_if:send_type,recurring|in:daily,weekly,monthly,quarterly',
            'recurring_config.days_of_week' => 'required_if:recurring_config.frequency,weekly|array',
            'recurring_config.days_of_week.*' => 'integer|min:0|max:6',
            'recurring_config.day_of_month' => 'required_if:recurring_config.frequency,monthly|integer|min:1|max:31',
            'recurring_config.has_end_date' => 'boolean',
            'recurring_config.end_date' => 'required_if:recurring_config.has_end_date,true|date|after:today',
            'recurring_config.occurrences' => 'nullable|integer|min:1',
            // Temporary upload key used before campaign ID exists
            'temp_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            // For Inertia requests, redirect back with errors so the client handles them properly
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();
        $data['enable_tracking'] = (bool)($request->input('enable_tracking', true));
        
        // Set default empty content if not provided
        if (!isset($data['content'])) {
            $data['content'] = [];
        }
        
        // Ensure html_content has a default value if empty
        if (empty($data['html_content'])) {
            $data['html_content'] = '<p>Draft content</p>';
        }
        
        // Ensure target_groups is always an array
        $data['target_groups'] = $data['target_groups'] ?? [];
        
        // Set the initial status - respect the status parameter if provided, otherwise use send_type logic
        if ($request->has('status')) {
            $data['status'] = $request->input('status');
        } else {
            // Set the initial status based on send type
            if ($data['send_type'] === 'immediate') {
                $data['status'] = 'draft';
            } elseif ($data['send_type'] === 'recurring') {
                $data['status'] = 'active'; // Recurring campaigns are active by default
            } else {
                $data['status'] = 'scheduled';
            }
        }

        // Format recurring config
        if ($data['send_type'] === 'recurring') {
            $data['recurring_config'] = [
                'frequency' => $data['recurring_config']['frequency'],
                'days_of_week' => $data['recurring_config']['days_of_week'] ?? [],
                'day_of_month' => $data['recurring_config']['day_of_month'] ?? null,
                'end_date' => $data['recurring_config']['has_end_date'] ? $data['recurring_config']['end_date'] : null,
                'occurrences' => $data['recurring_config']['occurrences'] ?? null,
                'last_scheduled_at' => null,
                'next_scheduled_at' => now(), // Will be calculated by the job
            ];
            
            // For recurring campaigns, scheduled_at is when the first send should occur
            if (empty($data['scheduled_at'])) {
                $data['scheduled_at'] = now();
            }
        } else {
            $data['recurring_config'] = null;
        }

        // Create the campaign first to get an ID
        $campaign = Campaign::create($data);

        // If a temp_key was provided, move any uploaded files from tmp to campaign folder and rewrite URLs
        if ($request->filled('temp_key')) {
            try {
                $tempKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $request->string('temp_key'));
                $disk = \Storage::disk('public');
                $tmpDir = "images/newsletters/tmp/{$tempKey}";
                $destDir = "images/newsletters/campaign-{$campaign->id}";

                if ($disk->exists($tmpDir)) {
                    // Ensure destination exists
                    if (!$disk->exists($destDir)) {
                        $disk->makeDirectory($destDir);
                    }

                    // Move all files
                    $files = $disk->allFiles($tmpDir);
                    foreach ($files as $file) {
                        $relative = ltrim(str_replace($tmpDir, '', $file), '/');
                        $destPath = rtrim($destDir, '/') . '/' . $relative;
                        // Ensure subdirectories exist
                        $destSubdir = dirname($destPath);
                        if (!$disk->exists($destSubdir)) {
                            $disk->makeDirectory($destSubdir);
                        }
                        $disk->move($file, $destPath);
                    }

                    // Attempt to remove the empty tmp directory
                    try { $disk->deleteDirectory($tmpDir); } catch (\Throwable $e) { /* ignore */ }

                    // Rewrite URLs in html_content and content
                    $oldBase = url(\Storage::url($tmpDir . '/'));
                    $newBase = url(\Storage::url($destDir . '/'));

                    $updated = [];
                    // html_content
                    if (!empty($campaign->html_content)) {
                        $updated['html_content'] = str_replace($oldBase, $newBase, $campaign->html_content);
                    }
                    // content (array) -> stringify, replace, decode
                    if (!empty($campaign->content)) {
                        $json = json_encode($campaign->content);
                        if ($json !== false) {
                            $json = str_replace($oldBase, $newBase, $json);
                            $decoded = json_decode($json, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $updated['content'] = $decoded;
                            }
                        }
                    }

                    if (!empty($updated)) {
                        $campaign->update($updated);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to migrate temp newsletter assets', [
                    'campaign_id' => $campaign->id,
                    'temp_key' => $request->input('temp_key'),
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Calculate total recipients using email-based logic (includes directory members even if not subscribers)
        if (method_exists($campaign, 'getRecipientEmails')) {
            $data['total_recipients'] = $campaign->getRecipientEmails()->count();
        } else {
            $data['total_recipients'] = $campaign->getRecipientsQuery()->count();
        }
        
        // Update the campaign with the recipient count
        $campaign->update(['total_recipients' => $data['total_recipients']]);
        
        // Check if this is a draft save
        // $isDraftSave = $campaign->status === 'draft' && $request->input('status') === 'draft';

        // Handle different send types
        if ($campaign->isRecurring()) {
            // For recurring campaigns, dispatch the job to process the first send
            ProcessRecurringCampaigns::dispatch($campaign);
            
            // Log the recurring campaign creation
            \Log::info("Recurring campaign #{$campaign->id} created. First send will be processed soon.");
            
            return redirect()->route('newsletter.campaigns.show', $campaign)
                           ->with('success', 'Recurring campaign created successfully. First send is scheduled.')
                           ->setStatusCode(303);
                            
        } elseif ($campaign->send_type === 'scheduled') {
            // For scheduled campaigns, create the scheduled sends
            $this->scheduleCampaignSends($campaign, $campaign->scheduled_at);
            
            return redirect()->route('newsletter.campaigns.show', $campaign)
                           ->with('success', 'Campaign scheduled successfully.')
                           ->setStatusCode(303);
                           
        } elseif ($campaign->send_type === 'immediate') {
            // Check if this is an explicit draft save - stay on edit page
            // We check specifically for the 'save_as_draft' flag from the frontend
            $isExplicitDraftSave = $request->boolean('save_as_draft', false);

            if ($isExplicitDraftSave && $request->header('X-Inertia')) {
                return redirect()->route('newsletter.campaigns.edit', $campaign)
                               ->with('success', 'Draft saved successfully.')
                               ->setStatusCode(303);
            }
            
            // Do not auto-send on creation. Keep as draft; user will send from the campaign page.
            return redirect()->route('newsletter.campaigns.show', $campaign)
                           ->with('success', 'Campaign created as draft. You can send it when ready.')
                           ->setStatusCode(303);
        }
        
        // Default redirect (shouldn't normally reach here)
        return redirect()->route('newsletter.campaigns.show', $campaign)
                       ->with('success', 'Campaign created successfully.')
                       ->setStatusCode(303);
    }

    /**
     * Schedule a campaign to be sent at a specific time.
     *
     * @param  \App\Models\Newsletter\Campaign  $campaign
     * @param  \Carbon\Carbon  $scheduledAt
     * @return void
     */
    protected function scheduleCampaignSends(Campaign $campaign, $scheduledAt)
    {
        // Use email-based resolution so directory-only members are included
        $emails = method_exists($campaign, 'getRecipientEmails')
            ? $campaign->getRecipientEmails()
            : $campaign->getRecipientsQuery()->pluck('email')->unique();

        // Ensure Subscriber records exist for each email
        $existing = Subscriber::query()->whereIn('email', $emails)->get()->keyBy(fn($s) => strtolower($s->email));
        foreach ($emails as $email) {
            $key = strtolower($email);
            $subscriber = $existing->get($key);
            if (!$subscriber) {
                $subscriber = Subscriber::create([
                    'email' => $email,
                    'status' => 'active',
                ]);
                $existing->put($key, $subscriber);
            }

            $campaign->scheduledSends()->create([
                'subscriber_id' => $subscriber->id,
                'scheduled_at' => $scheduledAt,
                'status' => \App\Models\Newsletter\ScheduledSend::STATUS_PENDING,
            ]);
        }
        
        // Update the campaign status to scheduled if it's not already
        if ($campaign->status !== 'scheduled') {
            $campaign->update(['status' => 'scheduled']);
        }
    }
    
    public function show(Campaign $campaign)
    {
        $campaign->load(['creator', 'template', 'analyticsEvents']);

        $analytics = [
            'total_sent' => $campaign->sent_count,
            'open_rate' => $campaign->open_rate,
            'click_rate' => $campaign->click_rate,
            'unsubscribe_rate' => $campaign->unsubscribe_rate,
            'bounce_rate' => $campaign->bounce_rate,
            'recent_events' => $campaign->analyticsEvents()
                ->with('subscriber')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
        ];

        return Inertia::render('Newsletter/Campaigns/Show', [
            'campaign' => $campaign,
            'analytics' => $analytics,
            'recentEvents' => $analytics['recent_events'],
        ]);
    }

    public function edit(Campaign $campaign)
    {
        // Allow editing drafts and scheduled campaigns
        // Also allow editing campaigns being sent as test drafts
        $metadata = $campaign->metadata ?? [];
        $isTestSend = $metadata['is_test_send'] ?? false;
        
        if (!in_array($campaign->status, ['draft', 'scheduled']) && !$isTestSend) {
            return back()->with('error', 'Cannot edit a campaign that has been sent.');
        }

        $templates = Template::orderBy('is_default', 'desc')->orderBy('name')->get();
        // Keep groups payload consistent with create() for UI parity
        $groups = Group::active()
            ->withCount(['activeSubscribers'])
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'color' => $group->color,
                    'is_active' => $group->is_active,
                    'active_subscriber_count' => $group->active_subscribers_count,
                ];
            });

        // Append protected virtual group (UHPH Directory)
        try {
            $dirEmails = Team::query()
                ->whereIn('group_1', ['team', 'leadership'])
                ->whereNotNull('email')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();

            // Count ALL directory members that match criteria, regardless of newsletter subscription status
            $protectedCount = $dirEmails->count();

            $groups->push([
                'id' => 'protected_dir_team',
                'name' => 'UHPH Directory',
                'description' => 'Auto-managed group of directory members (team, leadership).',
                'color' => '#334155',
                'is_active' => true,
                'active_subscriber_count' => $protectedCount,
            ]);
        } catch (\Throwable $e) {
            // Fail-safe: continue without virtual group if directory connection not available
        }

        return Inertia::render('Newsletter/Campaigns/Edit', [
            'campaign' => $campaign,
            'templates' => $templates,
            'groups' => $groups,
            'defaultFromName' => env('MAIL_NEWSLETTER_FROM_NAME', config('mail.from.name', 'UHPH News')),
            'defaultFromEmail' => env('MAIL_NEWSLETTER_FROM_ADDRESS', config('mail.from.address', 'noreply@central.uh.edu')),
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        // Allow updating drafts and scheduled campaigns
        // Also allow updating campaigns being sent as test drafts
        $metadata = $campaign->metadata ?? [];
        $isTestSend = $metadata['is_test_send'] ?? false;
        
        if (!in_array($campaign->status, ['draft', 'scheduled']) && !$isTestSend) {
            return back()->with('error', 'Cannot update a campaign that has been sent.');
        }

        // Ensure these are defined before any later use
        $wasScheduled = ($campaign->send_type === 'scheduled');
        $oldScheduledAt = $campaign->scheduled_at ? $campaign->scheduled_at->toDateTimeString() : null;

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'html_content' => ['required', 'string'],
            'template_id' => ['nullable', 'exists:newsletter_templates,id'],
            'send_type' => ['required', Rule::in(['immediate', 'scheduled', 'recurring'])],
            // Add a small buffer to tolerate client/server clock skew
            'scheduled_at' => ['required_if:send_type,scheduled,recurring', 'nullable', 'date', 'after:' . now()->addMinutes(2)],
            'recurring_config' => ['required_if:send_type,recurring', 'nullable', 'array'],
            'recurring_config.frequency' => ['required_if:send_type,recurring', 'string', 'in:daily,weekly,monthly,quarterly'],
            'recurring_config.days_of_week' => ['required_if:recurring_config.frequency,weekly', 'array', 'min:1'],
            'recurring_config.days_of_week.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'recurring_config.day_of_month' => ['required_if:recurring_config.frequency,monthly', 'integer', 'min:1', 'max:31'],
            'recurring_config.has_end_date' => ['sometimes', 'boolean'],
            'recurring_config.end_date' => ['required_if:recurring_config.has_end_date,true', 'date', 'after:scheduled_at'],
            'recurring_config.occurrences' => ['nullable', 'integer', 'min:1'],
            // Require target_groups unless sending to all
            'target_groups' => ['required_without:send_to_all', 'array'],
            'target_groups.*' => [
                function ($attribute, $value, $fail) {
                    if ($value === 'protected_dir_team') {
                        return; // allow special virtual group id
                    }
                    $exists = DB::table('newsletter_groups')->where('id', $value)->exists();
                    if (!$exists) {
                        $fail('The selected group is invalid.');
                    }
                }
            ],
            'send_to_all' => ['boolean'],
            'enable_tracking' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,scheduled,sending,sent,paused,cancelled'],
        ]);

        if ($validator->fails()) {
            // For Inertia requests, redirect back with errors so the client handles them properly
            if ($request->header('X-Inertia')) {
                return back()
                    ->withErrors($validator)
                    ->withInput()
                    ->setStatusCode(303);
            }
            // For non-Inertia API clients, return JSON errors
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = $validator->validated();
            $data['enable_tracking'] = (bool)($request->input('enable_tracking', true));

            // Ensure defaults
            $data['send_to_all'] = (bool)($data['send_to_all'] ?? false);
            $data['target_groups'] = $data['send_to_all'] ? [] : ($data['target_groups'] ?? []);

            // Format recurring config
            if ($data['send_type'] === 'recurring') {
                $data['recurring_config'] = [
                    'frequency' => $data['recurring_config']['frequency'],
                    'days_of_week' => $data['recurring_config']['days_of_week'] ?? [],
                    'day_of_month' => $data['recurring_config']['day_of_month'] ?? null,
                    'has_end_date' => $data['recurring_config']['has_end_date'] ?? false,
                    'end_date' => ($data['recurring_config']['has_end_date'] ?? false) ? $data['recurring_config']['end_date'] : null,
                    'occurrences' => $data['recurring_config']['occurrences'] ?? null,
                ];

                if ($campaign->send_type !== 'recurring' || $campaign->recurring_config !== $data['recurring_config']) {
                    $campaign->scheduledSends()->delete();
                    ProcessRecurringCampaigns::dispatch($campaign);
                }
            } else {
                $data['recurring_config'] = null;
                if ($campaign->send_type === 'recurring') {
                    $campaign->scheduledSends()->delete();
                }
            }

            // Recalculate total recipients using email-based logic (union of subscribers and directory emails)
            if (method_exists($campaign, 'getRecipientEmails')) {
                // Temporarily apply request data to campaign clone for accurate resolution
                $tmp = clone $campaign;
                $tmp->send_to_all = (bool)$data['send_to_all'];
                $tmp->target_groups = $data['target_groups'] ?? [];
                $data['total_recipients'] = $tmp->getRecipientEmails()->count();
            } else {
                $recipientQuery = Subscriber::active();
                if (!$data['send_to_all'] && !empty($data['target_groups'])) {
                    $selected = collect($data['target_groups']);
                    $hasProtected = $selected->contains('protected_dir_team');
                    $normalIds = $selected->filter(fn($v) => $v !== 'protected_dir_team')->values()->all();

                    $recipientQuery->where(function ($q) use ($normalIds, $hasProtected) {
                        if (!empty($normalIds)) {
                            $q->whereHas('groups', function ($qq) use ($normalIds) {
                                $qq->whereIn('newsletter_groups.id', $normalIds);
                            });
                        }
                        if ($hasProtected) {
                            $dirEmails = Team::query()
                                ->whereIn('group_1', ['team', 'leadership'])
                                ->whereNotNull('email')
                                ->pluck('email')
                                ->filter()
                                ->unique()
                                ->values();
                            if (!empty($normalIds)) {
                                $q->orWhereIn('email', $dirEmails);
                            } else {
                                $q->whereIn('email', $dirEmails);
                            }
                        }
                    });
                }
                $data['total_recipients'] = $recipientQuery->count();
            }

            // Persist campaign
            $campaign->update($data);

            // Handle scheduled sends based on changes
            if ($data['send_type'] === 'scheduled') {
                $newScheduledAt = $data['scheduled_at'] ?? null;
                $scheduleChanged = $oldScheduledAt !== $newScheduledAt;
                if (!$wasScheduled || $scheduleChanged) {
                    $campaign->scheduledSends()->delete();
                    $this->scheduleCampaignSends($campaign, $newScheduledAt);
                }
            } else {
                if ($wasScheduled) {
                    $campaign->scheduledSends()->delete();
                }
            }

            $message = 'Campaign updated successfully.';
            if ($data['send_type'] === 'recurring') {
                $message = 'Recurring campaign updated successfully. The schedule has been updated.';
            } elseif ($data['send_type'] === 'scheduled') {
                $message = 'Scheduled campaign updated successfully.';
            }
            
            // Check if this is a draft save
            // We prioritize the explicit flag if present, falling back to false if not provided
            // This allows "Send Campaign" (which updates with status=draft for immediate sends) to redirect to show page
            $isDraftSave = $request->boolean('save_as_draft', false);

            // For Inertia requests, check if it's a draft save
            if ($request->header('X-Inertia')) {
                if ($isDraftSave) {
                    // For draft saves, return back to stay on the same page
                    return back()
                        ->with('success', 'Draft saved successfully.')
                        ->setStatusCode(303);
                } else {
                    // For other updates, redirect to show page
                    return redirect()
                        ->route('newsletter.campaigns.show', $campaign)
                        ->with('success', $message)
                        ->setStatusCode(303);
                }
            }
            
            // For non-Inertia API clients, return JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'redirect' => route('newsletter.campaigns.show', $campaign),
                ], 200);
            }

            // Default fallback
            return redirect()
                ->route('newsletter.campaigns.show', $campaign)
                ->with('success', $message)
                ->setStatusCode(303);
        } catch (\Throwable $e) {
            \Log::error('Campaign update failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Update failed',
                    'error' => $e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Campaign update failed: ' . $e->getMessage());
        }
    }

    public function destroy(Campaign $campaign)
    {
        // Allow permanent deletion if the campaign is in the Time Capsule, even if it's already sent
        if ($campaign->time_capsule) {
            try {
                // Clean up related data
                $campaign->scheduledSends()->delete();
                $campaign->analyticsEvents()->delete();

                $campaign->delete();

                return redirect()
                    ->route('newsletter.campaigns.timecapsule')
                    ->with('success', 'Campaign permanently deleted.');
            } catch (\Throwable $e) {
                \Log::error('Failed to delete time capsule campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                return back()->with('error', 'Failed to delete campaign.');
            }
        }

        // For non-time-capsule campaigns, keep safety guard
        if (in_array($campaign->status, ['sending', 'sent'])) {
            return back()->with('error', 'Cannot delete a campaign that has been sent or is being sent.');
        }

        $campaign->delete();
        return redirect()->route('newsletter.campaigns.index')
                       ->with('success', 'Campaign deleted successfully.');
    }

    public function send(Campaign $campaign)
    {
        if (!$campaign->canBeSent()) {
            return back()->with('error', 'Campaign cannot be sent. Please check the campaign details.');
        }

        // Recalculate total recipients using current campaign configuration
        // This ensures we have the latest count even if the campaign wasn't recently updated
        $recipientCount = 0;
        if (method_exists($campaign, 'getRecipientEmails')) {
            $recipientCount = $campaign->getRecipientEmails()->count();
        } else {
            $recipientCount = $campaign->getRecipientsQuery()->count();
        }

        // Update the campaign with the fresh recipient count
        $campaign->update(['total_recipients' => $recipientCount]);

        if ($recipientCount === 0) {
            return back()->with('error', 'No recipients selected for this campaign. Please select at least one group or enable "Send to All".');
        }

        // Dispatch the send job
        SendCampaign::dispatch($campaign);

        $campaign->markAsSending();

        return back()->with('success', 'Campaign is being sent. Check the dashboard for progress.');
    }

    public function sendDraft(Campaign $campaign)
    {
        // Only allow sending drafts
        if ($campaign->status !== 'draft') {
            return back()->with('error', 'Only draft campaigns can be sent as test drafts.');
        }

        // Recalculate total recipients using current campaign configuration
        // This ensures we have the latest count even if the campaign wasn't recently updated
        $recipientCount = 0;
        if (method_exists($campaign, 'getRecipientEmails')) {
            $recipientCount = $campaign->getRecipientEmails()->count();
        } else {
            $recipientCount = $campaign->getRecipientsQuery()->count();
        }

        // Update the campaign with the fresh recipient count
        $campaign->update(['total_recipients' => $recipientCount]);

        if ($recipientCount === 0) {
            return back()->with('error', 'No recipients selected for this campaign. Please select at least one group or enable "Send to All".');
        }

        // For test sends, we'll send synchronously and keep the campaign as draft throughout
        // This avoids issues with queue workers not running
        
        try {
            // Get recipient emails
            $emails = method_exists($campaign, 'getRecipientEmails')
                ? $campaign->getRecipientEmails()
                : $campaign->getRecipientsQuery()->pluck('email')->unique();
            
            // Ensure we have Subscriber records for all emails
            $existing = \App\Models\Newsletter\Subscriber::query()
                ->whereIn('email', $emails)
                ->get()
                ->keyBy(fn($s) => strtolower($s->email));
            
            $recipients = collect();
            foreach ($emails as $email) {
                $key = strtolower($email);
                $subscriber = $existing->get($key);
                if (!$subscriber) {
                    $subscriber = \App\Models\Newsletter\Subscriber::create([
                        'email' => $key,
                        'status' => 'active',
                        'metadata' => ['source' => 'directory']
                    ]);
                }
                $recipients->push($subscriber);
            }
            
            // Send emails synchronously using the mailer directly
            $successCount = 0;
            $failCount = 0;
            
            foreach ($recipients as $recipient) {
                try {
                    $mail = new \App\Mail\NewsletterMail($campaign, $recipient);
                    \Mail::mailer('campus_smtp')->to($recipient->email)->send($mail);
                    $successCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send test email', [
                        'campaign_id' => $campaign->id,
                        'recipient_email' => $recipient->email,
                        'error' => $e->getMessage()
                    ]);
                    $failCount++;
                }
            }
            
            \Log::info('Test send completed', [
                'campaign_id' => $campaign->id,
                'success_count' => $successCount,
                'fail_count' => $failCount
            ]);
            
            // Campaign remains as 'draft' throughout
            $message = "Test send completed: {$successCount} sent successfully";
            if ($failCount > 0) {
                $message .= ", {$failCount} failed (check logs)";
            }
            $message .= ". Campaign remains as draft.";
            
            return back()->with('success', $message);
            
        } catch (\Exception $e) {
            \Log::error('Test send failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->with('error', 'Test send failed: ' . $e->getMessage());
        }
    }

    public function pause(Campaign $campaign)
    {
        if ($campaign->status !== 'sending') {
            return back()->with('error', 'Can only pause campaigns that are currently being sent.');
        }

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused successfully.');
    }

    public function resume(Campaign $campaign)
    {
        if ($campaign->status !== 'paused') {
            return back()->with('error', 'Can only resume paused campaigns.');
        }

        $campaign->update(['status' => 'sending']);

        // Kick the processor to continue pending sends
        \App\Jobs\ProcessScheduledSends::dispatch();

        return back()->with('success', 'Campaign resumed successfully.');
    }

    public function cancel(Campaign $campaign)
    {
        if (!in_array($campaign->status, ['scheduled', 'sending', 'paused'])) {
            return back()->with('error', 'Cannot cancel this campaign.');
        }

        $campaign->update(['status' => 'cancelled']);

        // Mark any remaining pending or processing sends as cancelled
        $campaign->scheduledSends()
            ->whereIn('status', [\App\Models\Newsletter\ScheduledSend::STATUS_PENDING, \App\Models\Newsletter\ScheduledSend::STATUS_PROCESSING])
            ->update(['status' => \App\Models\Newsletter\ScheduledSend::STATUS_CANCELLED]);

        return back()->with('success', 'Campaign cancelled successfully.');
    }

    public function duplicate(Campaign $campaign)
    {
        $newCampaign = $campaign->replicate();
        $newCampaign->name = $campaign->name . ' (Copy)';
        $newCampaign->status = 'draft';
        $newCampaign->scheduled_at = null;
        $newCampaign->sent_at = null;
        $newCampaign->sent_count = 0;
        $newCampaign->failed_count = 0;
        $newCampaign->created_by = auth()->id();
        $newCampaign->save();

        // Copy campaign files to new directory and rewrite URLs
        try {
            $disk = \Storage::disk('public');
            $sourceDir = "images/newsletters/campaign-{$campaign->id}";
            $destDir = "images/newsletters/campaign-{$newCampaign->id}";

            if ($disk->exists($sourceDir)) {
                // Ensure destination directory exists
                if (!$disk->exists($destDir)) {
                    $disk->makeDirectory($destDir);
                }

                // Copy all files from source to destination
                $files = $disk->allFiles($sourceDir);
                foreach ($files as $file) {
                    $relative = ltrim(str_replace($sourceDir, '', $file), '/');
                    $destPath = rtrim($destDir, '/') . '/' . $relative;
                    
                    // Ensure subdirectories exist
                    $destSubdir = dirname($destPath);
                    if (!$disk->exists($destSubdir)) {
                        $disk->makeDirectory($destSubdir);
                    }
                    
                    // Copy the file
                    $disk->copy($file, $destPath);
                }

                // Rewrite URLs in html_content and content
                $oldBase = url(\Storage::url($sourceDir . '/'));
                $newBase = url(\Storage::url($destDir . '/'));

                $updated = [];
                
                // Update html_content
                if (!empty($newCampaign->html_content)) {
                    $updated['html_content'] = str_replace($oldBase, $newBase, $newCampaign->html_content);
                }
                
                // Update content (array) -> stringify, replace, decode
                if (!empty($newCampaign->content)) {
                    $json = json_encode($newCampaign->content);
                    if ($json !== false) {
                        $json = str_replace($oldBase, $newBase, $json);
                        $decoded = json_decode($json, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $updated['content'] = $decoded;
                        }
                    }
                }

                if (!empty($updated)) {
                    $newCampaign->update($updated);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to copy campaign files during duplication', [
                'original_campaign_id' => $campaign->id,
                'new_campaign_id' => $newCampaign->id,
                'error' => $e->getMessage(),
            ]);
            // Continue even if file copy fails - the campaign is still duplicated
        }

        return redirect()->route('newsletter.campaigns.edit', $newCampaign)
                       ->with('success', 'Campaign duplicated successfully.');
    }

    public function preview(Campaign $campaign)
    {
        $html = $campaign->html_content;
        if (($campaign->enable_tracking ?? true) === false) {
            $html = $this->stripTrackingLinks($html);
        }

        return Inertia::render('Newsletter/Campaigns/Preview', [
            'campaign' => $campaign,
            'html_content' => $html,
        ]);
    }

    /**
     * Return paginated scheduled sends for a campaign (JSON only).
     */
    public function scheduledSends(Request $request, Campaign $campaign)
    {
        $request->validate([
            'status' => 'nullable|string|in:pending,processing,sent,failed,skipped,cancelled',
            'search' => 'nullable|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            // Quick guard: ensure table exists to avoid opaque SQL exceptions in dev
            if (!\Illuminate\Support\Facades\Schema::hasTable('newsletter_scheduled_sends')) {
                return response()->json([
                    'message' => 'Scheduled sends table does not exist.',
                ], 500);
            }

            $perPage = (int) $request->input('per_page', 15);

            $query = $campaign->scheduledSends()->with(['subscriber' => function ($q) {
                $q->select('id', 'email');
            }]);

            if ($request->filled('status')) {
                $status = (string) $request->input('status');
                $query->where('status', $status);
            }

            if ($request->filled('search')) {
                $term = $request->string('search');
                $query->whereHas('subscriber', function ($q) use ($term) {
                    $q->where('email', 'like', "%{$term}%");
                });
            }

            $sends = $query->orderByDesc('id')->paginate($perPage);

            // Build counts by status using a fresh query to avoid inherited order clauses
            $counts = \App\Models\Newsletter\ScheduledSend::query()
                ->where('campaign_id', $campaign->id)
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');

            return response()->json([
                'data' => $sends->items(),
                'meta' => [
                    'current_page' => $sends->currentPage(),
                    'per_page' => $sends->perPage(),
                    'total' => $sends->total(),
                    'last_page' => $sends->lastPage(),
                ],
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            \Log::error('scheduledSends endpoint failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to fetch scheduled sends',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry selected scheduled sends for a campaign. Optionally auto-resume.
     */
    public function retryScheduledSends(Request $request, Campaign $campaign)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'auto_resume' => 'sometimes|boolean',
        ]);

        $ids = collect($validated['ids'])->unique()->values();

        // Ensure all IDs belong to this campaign
        $sends = $campaign->scheduledSends()->whereIn('id', $ids)->get();
        if ($sends->count() !== $ids->count()) {
            return response()->json(['message' => 'One or more selected items do not belong to this campaign.'], 422);
        }

        // If paused and auto_resume is true, resume campaign; else require sending state
        if ($campaign->status === 'paused' && $request->boolean('auto_resume')) {
            $campaign->update(['status' => 'sending']);
        }
        if ($campaign->status !== 'sending') {
            return response()->json(['message' => 'Campaign must be in sending state to dispatch retries.'], 422);
        }

        $processed = 0;
        foreach ($sends as $send) {
            // Reset failed/skipped back to pending; leave pending as-is
            if (in_array($send->status, ['failed', 'skipped'])) {
                $send->update(['status' => 'pending', 'error_message' => null]);
            }
            // Dispatch individual send job; it will validate status and send
            \App\Jobs\SendEmailToSubscriber::dispatch($send);
            $processed++;
        }

        return response()->json(['message' => 'Dispatching selected sends', 'count' => $processed]);
    }

    /**
     * Kick processing of all pending sends for this campaign. Optionally auto-resume.
     */
    public function processPending(Request $request, Campaign $campaign)
    {
        if ($campaign->status === 'cancelled') {
            return response()->json(['message' => 'Cannot process a cancelled campaign.'], 422);
        }

        if (in_array($campaign->status, ['paused', 'scheduled']) && $request->boolean('auto_resume')) {
            $campaign->update(['status' => 'sending']);
        }

        if ($campaign->status !== 'sending') {
            return response()->json(['message' => 'Campaign must be in sending state to process pending sends.'], 422);
        }

        \App\Jobs\ProcessScheduledSends::dispatch();
        return response()->json(['message' => 'Processing pending sends has been dispatched.']);
    }

    /**
     * Remove newsletter click-tracking wrappers from links and restore original URLs.
     */
    private function stripTrackingLinks(string $html = null): string
    {
        if (!$html) return '';
        return preg_replace_callback(
            '/href=("|\')([^"\']+)(\1)/i',
            function ($m) {
                $quote = $m[1];
                $url = $m[2] ?? '';
                if ($url === '' || !preg_match('#/newsletter/(public/)?track-click/#i', $url)) {
                    return $m[0];
                }
                try {
                    $parsed = parse_url($url);
                    $path = $parsed['path'] ?? '';
                    $parts = array_values(array_filter(explode('/', $path)));
                    $idx = null;
                    foreach ($parts as $i => $p) {
                        if (strtolower($p) === 'track-click') { $idx = $i; break; }
                    }
                    $b64 = ($idx !== null && isset($parts[$idx + 3])) ? $parts[$idx + 3] : null;
                    if ($b64) {
                        $b64 = rawurldecode($b64);
                        $b64 = strtr($b64, '-_', '+/');
                        $pad = strlen($b64) % 4;
                        if ($pad) { $b64 .= str_repeat('=', 4 - $pad); }
                        $orig = base64_decode($b64, true);
                        if ($orig && preg_match('#^https?://#i', $orig)) {
                            return 'href=' . $quote . $orig . $quote;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore and fall through
                }
                return 'href=' . $quote . '#' . $quote;
            },
            $html
        );
    }

    /**
     * Display Time Capsule campaigns
     */
    public function timeCapsule(Request $request)
    {
        $query = Campaign::with(['creator', 'template'])
            ->where('time_capsule', true);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Per-page pagination support with sensible bounds
        $perPage = (int) $request->input('per_page', 25);
        $allowed = [25, 50, 100, 150, 200, 250];
        if (!in_array($perPage, $allowed, true)) {
            $perPage = 25;
        }

        $campaigns = $query->orderBy('updated_at', 'desc')->paginate($perPage)->withQueryString();

        return Inertia::render('Newsletter/Campaigns/TimeCapsule', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    /**
     * Move campaigns to Time Capsule
     */
    public function storeTimeCapsule(Request $request)
    {
        $request->validate([
            'campaign_ids' => 'required|array',
            'campaign_ids.*' => 'exists:newsletter_campaigns,id'
        ]);

        $campaigns = Campaign::whereIn('id', $request->campaign_ids)
            ->where('status', 'sent')
            ->where('time_capsule', false)
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update(['time_capsule' => true]);
        }

        return redirect()->back()->with('success', 
            count($campaigns) === 1 
                ? 'Campaign moved to Time Capsule successfully.'
                : count($campaigns) . ' campaigns moved to Time Capsule successfully.'
        );
    }

    /**
     * Restore campaign from Time Capsule
     */
    public function restoreFromTimeCapsule(Campaign $campaign)
    {
        if (!$campaign->time_capsule) {
            return redirect()->back()->with('error', 'Campaign is not in Time Capsule.');
        }

        $campaign->update(['time_capsule' => false]);

        return redirect()->back()->with('success', 'Campaign restored from Time Capsule successfully.');
    }
}
