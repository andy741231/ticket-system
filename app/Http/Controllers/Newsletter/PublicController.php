<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterSubscriptionNotificationMail;
use App\Models\Newsletter\AnalyticsEvent;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Group;
use App\Models\Newsletter\Subscriber;
use App\Models\Newsletter\SubscriptionNotificationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

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
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        try {
            $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'name' => ['nullable', 'string', 'max:255'],
                'first_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['nullable', 'string', 'max:255'],
                'organization' => ['nullable', 'string', 'max:255'],
                'groups' => ['nullable', 'array'],
                'groups.*' => [
                    Rule::exists('newsletter_groups', 'id')
                        ->when(Schema::hasColumn('newsletter_groups', 'is_external'), function ($rule) {
                            return $rule->where('is_external', true);
                        })
                        ->when(Schema::hasColumn('newsletter_groups', 'is_active'), function ($rule) {
                            return $rule->where('is_active', true);
                        }),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $email = (string) $request->email;
        $subscriber = Subscriber::where('email', $email)->first();
        $sendNotification = false;

        if ($subscriber) {
            // If they existed but were not active, treat this as a re-subscribe.
            if ($subscriber->status !== 'active') {
                $subscriber->resubscribe();
                $sendNotification = true;
            }

            $updates = [];
            if ($request->filled('name')) {
                $updates['name'] = $request->input('name');
            }
            if ($request->filled('first_name')) {
                $updates['first_name'] = $request->input('first_name');
            }
            if ($request->filled('last_name')) {
                $updates['last_name'] = $request->input('last_name');
            }
            if ($request->filled('organization')) {
                $updates['organization'] = $request->input('organization');
            }
            if (!empty($updates)) {
                $subscriber->fill($updates);
                $subscriber->save();
            }
        } else {
            $subscriber = Subscriber::create([
                'email' => $email,
                'name' => $request->input('name'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'organization' => $request->input('organization'),
                'status' => 'active',
            ]);
            $sendNotification = true;
        }

        if ($request->filled('groups')) {
            $groupIds = collect($request->groups)
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->values();

            $groupsQuery = Group::query();
            if (Schema::hasColumn('newsletter_groups', 'is_external')) {
                $groupsQuery->where('is_external', true);
            }
            if (Schema::hasColumn('newsletter_groups', 'is_active')) {
                $groupsQuery->where('is_active', true);
            }

            $allowedIds = $groupsQuery->whereIn('id', $groupIds->all())->pluck('id')->all();
            $subscriber->groups()->sync($allowedIds);
        } else {
            $defaultGroup = Group::query()->where('name', 'UHPH ListServ')->first();
            if ($defaultGroup) {
                $subscriber->groups()->syncWithoutDetaching([$defaultGroup->id]);
            }
        }

        if ($sendNotification) {
            try {
                $recipients = SubscriptionNotificationEmail::query()
                    ->where('is_active', true)
                    ->pluck('email')
                    ->filter()
                    ->values()
                    ->all();

                foreach ($recipients as $recipient) {
                    Mail::to($recipient)->send(new NewsletterSubscriptionNotificationMail($subscriber));
                }
            } catch (\Throwable $e) {
                // Intentionally swallow mail errors for public API subscribe flow.
            }
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

        $groupsQuery = Group::query();
        if (Schema::hasColumn('newsletter_groups', 'is_external')) {
            $groupsQuery->where('is_external', true);
        }
        if (Schema::hasColumn('newsletter_groups', 'is_active')) {
            $groupsQuery->where('is_active', true);
        }
        $groups = $groupsQuery->orderBy('name')->get();

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
            'groups.*' => [
                Rule::exists('newsletter_groups', 'id')
                    ->when(Schema::hasColumn('newsletter_groups', 'is_external'), function ($rule) {
                        return $rule->where('is_external', true);
                    })
                    ->when(Schema::hasColumn('newsletter_groups', 'is_active'), function ($rule) {
                        return $rule->where('is_active', true);
                    }),
            ],
        ]);

        $subscriber->update($request->only(['name', 'first_name', 'last_name']));

        if ($request->has('groups')) {
            $groupIds = collect($request->groups ?? [])
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->values();

            $groupsQuery = Group::query();
            if (Schema::hasColumn('newsletter_groups', 'is_external')) {
                $groupsQuery->where('is_external', true);
            }
            if (Schema::hasColumn('newsletter_groups', 'is_active')) {
                $groupsQuery->where('is_active', true);
            }
            $allowedIds = $groupsQuery->whereIn('id', $groupIds->all())->pluck('id')->all();
            $subscriber->groups()->sync($allowedIds);
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
            'groups.*' => [
                Rule::exists('newsletter_groups', 'id')
                    ->when(Schema::hasColumn('newsletter_groups', 'is_external'), function ($rule) {
                        return $rule->where('is_external', true);
                    })
                    ->when(Schema::hasColumn('newsletter_groups', 'is_active'), function ($rule) {
                        return $rule->where('is_active', true);
                    }),
            ],
        ]);

        $subscriber->update($request->only(['name', 'first_name', 'last_name']));

        if ($request->has('groups')) {
            $groupIds = collect($request->groups ?? [])
                ->filter(fn($v) => is_numeric($v))
                ->map(fn($v) => (int) $v)
                ->values();

            $groupsQuery = Group::query();
            if (Schema::hasColumn('newsletter_groups', 'is_external')) {
                $groupsQuery->where('is_external', true);
            }
            if (Schema::hasColumn('newsletter_groups', 'is_active')) {
                $groupsQuery->where('is_active', true);
            }

            $allowedIds = $groupsQuery->whereIn('id', $groupIds->all())->pluck('id')->all();
            $subscriber->groups()->sync($allowedIds);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'subscriber' => $subscriber->load('groups'),
            ]);
        }

        return redirect()
            ->route('newsletter.public.preferences', $subscriber->unsubscribe_token)
            ->with('status', 'Preferences updated successfully.');
    }

    public function archive(Request $request)
    {
        $query = Campaign::sent()
            ->with('creator')
            ->orderBy('sent_at', 'desc');

        // Guard against environments without the column to prevent 500s
        if (Schema::hasColumn('newsletter_campaigns', 'time_capsule')) {
            $query->where('time_capsule', false); // Exclude time capsule campaigns
        }

        $campaigns = $query->paginate(10);

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
}
