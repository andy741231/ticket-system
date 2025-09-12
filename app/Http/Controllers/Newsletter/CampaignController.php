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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::with(['creator', 'template'])
            ->where('time_capsule', false); // Exclude time capsule campaigns

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

        $campaigns = $query->orderBy('created_at', 'desc')->paginate(25);

        return Inertia::render('Newsletter/Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'status']),
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

        return Inertia::render('Newsletter/Campaigns/Create', [
            'templates' => $templates,
            'groups' => $groups,
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
            'send_type' => 'required|in:immediate,scheduled,recurring',
            'scheduled_at' => 'required_if:send_type,scheduled|date|after:now',
            'target_groups' => 'required_without:send_to_all|array',
            'target_groups.*' => 'exists:newsletter_groups,id',
            'send_to_all' => 'boolean',
            'enable_tracking' => 'sometimes|boolean',
            'recurring_config' => 'required_if:send_type,recurring|array',
            'recurring_config.frequency' => 'required_if:send_type,recurring|in:daily,weekly,monthly,quarterly',
            'recurring_config.days_of_week' => 'required_if:recurring_config.frequency,weekly|array',
            'recurring_config.days_of_week.*' => 'integer|min:0|max:6',
            'recurring_config.day_of_month' => 'required_if:recurring_config.frequency,monthly|integer|min:1|max:31',
            'recurring_config.has_end_date' => 'boolean',
            'recurring_config.end_date' => 'required_if:recurring_config.has_end_date,true|date|after:today',
            'recurring_config.occurrences' => 'nullable|integer|min:1',
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
        
        // Set the initial status based on send type
        if ($data['send_type'] === 'immediate') {
            $data['status'] = 'draft';
        } elseif ($data['send_type'] === 'recurring') {
            $data['status'] = 'active'; // Recurring campaigns are active by default
        } else {
            $data['status'] = 'scheduled';
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
        
        // Calculate total recipients using the model's method
        $data['total_recipients'] = $campaign->getRecipientsQuery()->count();
        
        // Update the campaign with the recipient count
        $campaign->update(['total_recipients' => $data['total_recipients']]);

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
        $subscribers = $campaign->getRecipientsQuery()->get();
        
        foreach ($subscribers as $subscriber) {
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
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
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

        return Inertia::render('Newsletter/Campaigns/Edit', [
            'campaign' => $campaign,
            'templates' => $templates,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
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
            'target_groups.*' => ['exists:newsletter_groups,id'],
            'send_to_all' => ['boolean'],
            'enable_tracking' => ['sometimes', 'boolean'],
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

            // Recalculate total recipients
            $recipientQuery = Subscriber::active();
            if (!$data['send_to_all'] && !empty($data['target_groups'])) {
                $recipientQuery->whereHas('groups', function ($q) use ($data) {
                    $q->whereIn('newsletter_groups.id', $data['target_groups']);
                });
            }
            $data['total_recipients'] = $recipientQuery->count();

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

            // For Inertia requests, always return a redirect with 303 status so the client can properly navigate
            // Only return JSON for non-Inertia API clients
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'redirect' => route('newsletter.campaigns.show', $campaign),
                ], 200);
            }

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

        if ($campaign->total_recipients === 0) {
            return back()->with('error', 'No recipients found for this campaign.');
        }

        // Dispatch the send job
        SendCampaign::dispatch($campaign);

        $campaign->markAsSending();

        return back()->with('success', 'Campaign is being sent. Check the dashboard for progress.');
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

        $campaigns = $query->orderBy('updated_at', 'desc')->paginate(25);

        return Inertia::render('Newsletter/Campaigns/TimeCapsule', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'status']),
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
