<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\AnalyticsEvent;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PublicController extends Controller
{
    public function unsubscribe(Request $request, $token)
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            abort(404, 'Invalid unsubscribe link');
        }

        if ($request->isMethod('post')) {
            $subscriber->unsubscribe();

            // Record analytics event if campaign is provided
            if ($request->filled('campaign_id')) {
                $campaign = Campaign::find($request->campaign_id);
                if ($campaign) {
                    AnalyticsEvent::create([
                        'campaign_id' => $campaign->id,
                        'subscriber_id' => $subscriber->id,
                        'event_type' => 'unsubscribed',
                        'user_agent' => $request->userAgent(),
                        'ip_address' => $request->ip(),
                    ]);
                }
            }

            return view('newsletter.unsubscribed', compact('subscriber'));
        }

        return view('newsletter.unsubscribe', compact('subscriber'));
    }

    public function unsubscribeApi(Request $request, $token)
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return response()->json(['error' => 'Invalid unsubscribe link'], 404);
        }

        // Perform unsubscribe immediately for API flow (idempotent)
        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->unsubscribe();

            // Record analytics event if campaign is provided
            if ($request->filled('campaign_id')) {
                $campaign = Campaign::find($request->campaign_id);
                if ($campaign) {
                    AnalyticsEvent::create([
                        'campaign_id' => $campaign->id,
                        'subscriber_id' => $subscriber->id,
                        'event_type' => 'unsubscribed',
                        'user_agent' => $request->userAgent(),
                        'ip_address' => $request->ip(),
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'You have been unsubscribed from the newsletter.',
            'subscriber' => [
                'email' => $subscriber->email,
                'status' => $subscriber->status,
                'unsubscribed_at' => optional($subscriber->unsubscribed_at)->toISOString(),
            ],
        ]);
    }

    public function trackOpen(Request $request, $campaign, $subscriber, $token)
    {
        // Ensure consistent data types for token verification
        $campaignId = (string)$campaign;
        $subscriberId = (string)$subscriber;
        
        // Verify token
        $expectedToken = hash('sha256', $campaignId . $subscriberId . config('app.key'));
        
        if (!hash_equals($expectedToken, $token)) {
            \Log::warning('Invalid tracking token', [
                'campaign' => $campaignId,
                'subscriber' => $subscriberId,
                'expected_token' => $expectedToken,
                'received_token' => $token,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            abort(404, 'Invalid tracking token');
        }

        $campaignModel = Campaign::find($campaignId);
        $subscriberModel = Subscriber::find($subscriberId);

        if ($campaignModel && $subscriberModel) {
            // Check if already tracked to avoid duplicates
            $existingEvent = AnalyticsEvent::where('campaign_id', $campaign)
                ->where('subscriber_id', $subscriber)
                ->where('event_type', 'opened')
                ->first();

            if (!$existingEvent) {
                AnalyticsEvent::create([
                    'campaign_id' => $campaign,
                    'subscriber_id' => $subscriber,
                    'event_type' => 'opened',
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        // Return 1x1 transparent pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return Response::make($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function trackClick(Request $request, $campaign, $subscriber, $url, $token)
    {
        $decodedUrl = base64_decode($url);
        
        // Verify token
        $expectedToken = hash('sha256', $campaign . $subscriber . $decodedUrl . config('app.key'));
        if (!hash_equals($expectedToken, $token)) {
            abort(404);
        }

        $campaignModel = Campaign::find($campaign);
        $subscriberModel = Subscriber::find($subscriber);

        if ($campaignModel && $subscriberModel) {
            AnalyticsEvent::create([
                'campaign_id' => $campaign,
                'subscriber_id' => $subscriber,
                'event_type' => 'clicked',
                'link_url' => $decodedUrl,
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);
        }

        return redirect($decodedUrl);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:newsletter_subscribers,email'],
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        $subscriber = Subscriber::create([
            'email' => $request->email,
            'name' => $request->name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'status' => 'active',
        ]);

        if ($request->filled('groups')) {
            $subscriber->groups()->sync($request->groups);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully subscribed to newsletter',
            'subscriber' => $subscriber->load('groups'),
        ]);
    }

    public function preferences(Request $request, $token)
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            abort(404, 'Invalid preferences link');
        }

        $groups = \App\Models\Newsletter\Group::all();

        return view('newsletter.preferences', compact('subscriber', 'groups'));
    }

    public function updatePreferencesWeb(Request $request, $token)
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            abort(404, 'Invalid preferences link');
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        $subscriber->update($request->only(['name', 'first_name', 'last_name']));

        if ($request->has('groups')) {
            $subscriber->groups()->sync($request->groups ?? []);
        }

        return view('newsletter.preferences-updated', compact('subscriber'));
    }

    public function updatePreferences(Request $request, $token)
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->first();

        if (!$subscriber) {
            return response()->json(['error' => 'Invalid token'], 404);
        }

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['exists:newsletter_groups,id'],
        ]);

        $subscriber->update($request->only(['name', 'first_name', 'last_name']));

        if ($request->has('groups')) {
            $subscriber->groups()->sync($request->groups ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'subscriber' => $subscriber->load('groups'),
        ]);
    }

    public function archive(Request $request)
    {
        $campaigns = Campaign::sent()
            ->where('time_capsule', false) // Exclude time capsule campaigns
            ->with('creator')
            ->orderBy('sent_at', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($campaigns);
        }

        return view('newsletter.archive', compact('campaigns'));
    }

    public function viewCampaign(Campaign $campaign)
    {
        if ($campaign->status !== 'sent') {
            abort(404);
        }

        return view('newsletter.campaign-view', compact('campaign'));
    }

    /**
     * Display the public newsletter archive at /public/archive
     */
    public function publicArchive(Request $request)
    {
        $campaigns = Campaign::sent()
            ->where('time_capsule', false) // Exclude time capsule campaigns
            ->with('creator')
            ->orderBy('sent_at', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return response()->json($campaigns);
        }

        return view('newsletter.public-archive', [
            'campaigns' => $campaigns,
            'title' => 'Newsletter Archive'
        ]);
    }
}
