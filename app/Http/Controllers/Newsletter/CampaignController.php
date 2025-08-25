<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaign;
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
        $query = Campaign::with(['creator', 'template']);

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
        $groups = Group::active()->get();

        return Inertia::render('Newsletter/Campaigns/Create', [
            'templates' => $templates,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'html_content' => ['required', 'string'],
            'template_id' => ['nullable', 'exists:newsletter_templates,id'],
            'send_type' => ['required', Rule::in(['immediate', 'scheduled', 'recurring'])],
            'scheduled_at' => ['required_if:send_type,scheduled,recurring', 'nullable', 'date', 'after:now'],
            'recurring_config' => ['required_if:send_type,recurring', 'nullable', 'array'],
            'target_groups' => ['nullable', 'array'],
            'target_groups.*' => ['exists:newsletter_groups,id'],
            'send_to_all' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['send_type'] === 'immediate' ? 'draft' : 'scheduled';

        // Calculate total recipients
        $recipientQuery = Subscriber::active();
        if (!$data['send_to_all'] && !empty($data['target_groups'])) {
            $recipientQuery->whereHas('groups', function ($q) use ($data) {
                $q->whereIn('newsletter_groups.id', $data['target_groups']);
            });
        }
        $data['total_recipients'] = $recipientQuery->count();

        $campaign = Campaign::create($data);

        if ($data['send_type'] === 'immediate') {
            return redirect()->route('newsletter.campaigns.show', $campaign)
                           ->with('success', 'Campaign created successfully. Ready to send.');
        }

        return redirect()->route('newsletter.campaigns.index')
                       ->with('success', 'Campaign scheduled successfully.');
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
        ]);
    }

    public function edit(Campaign $campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return back()->with('error', 'Cannot edit a campaign that has been sent.');
        }

        $templates = Template::orderBy('is_default', 'desc')->orderBy('name')->get();
        $groups = Group::active()->get();

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

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
            'html_content' => ['required', 'string'],
            'template_id' => ['nullable', 'exists:newsletter_templates,id'],
            'send_type' => ['required', Rule::in(['immediate', 'scheduled', 'recurring'])],
            'scheduled_at' => ['required_if:send_type,scheduled,recurring', 'nullable', 'date', 'after:now'],
            'recurring_config' => ['required_if:send_type,recurring', 'nullable', 'array'],
            'target_groups' => ['nullable', 'array'],
            'target_groups.*' => ['exists:newsletter_groups,id'],
            'send_to_all' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // Recalculate total recipients
        $recipientQuery = Subscriber::active();
        if (!$data['send_to_all'] && !empty($data['target_groups'])) {
            $recipientQuery->whereHas('groups', function ($q) use ($data) {
                $q->whereIn('newsletter_groups.id', $data['target_groups']);
            });
        }
        $data['total_recipients'] = $recipientQuery->count();

        $campaign->update($data);

        return back()->with('success', 'Campaign updated successfully.');
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

        return back()->with('success', 'Campaign resumed successfully.');
    }

    public function cancel(Campaign $campaign)
    {
        if (!in_array($campaign->status, ['scheduled', 'sending', 'paused'])) {
            return back()->with('error', 'Cannot cancel this campaign.');
        }

        $campaign->update(['status' => 'cancelled']);

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
        return Inertia::render('Newsletter/Campaigns/Preview', [
            'campaign' => $campaign,
            'html_content' => $campaign->html_content,
        ]);
    }
}
