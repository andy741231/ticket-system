<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\AnalyticsEvent;
use App\Models\Newsletter\Subscriber;

class DashboardController extends Controller
{
    public function analytics(Request $request): Response
    {
        $period = (int) $request->query('period', 30);
        if ($period <= 0) {
            $period = 30;
        }

        $start = now()->subDays($period);
        $prevStart = now()->subDays($period * 2);
        $prevEnd = $start;

        // Overview counts
        $totalCampaigns = Campaign::where('created_at', '>=', $start)->count();
        $events = AnalyticsEvent::where('created_at', '>=', $start);
        $totalOpens = (clone $events)->opens()->count();
        $totalClicks = (clone $events)->clicks()->count();
        $totalUnsubs = (clone $events)->unsubscribes()->count();

        // Changes vs previous period
        $prevEvents = AnalyticsEvent::whereBetween('created_at', [$prevStart, $prevEnd]);
        $prevOpens = (clone $prevEvents)->opens()->count();
        $prevClicks = (clone $prevEvents)->clicks()->count();
        $prevUnsubs = (clone $prevEvents)->unsubscribes()->count();

        $percentChange = function (int $current, int $previous): int {
            if ($previous === 0) {
                return $current > 0 ? 100 : 0;
            }
            return (int) round((($current - $previous) / $previous) * 100);
        };

        // Average rates across sent campaigns in period
        $sentCampaigns = Campaign::whereNotNull('sent_at')
            ->where('sent_at', '>=', $start)
            ->get();

        $avgOpen = $sentCampaigns->avg(fn ($c) => $c->open_rate) ?? 0;
        $avgClick = $sentCampaigns->avg(fn ($c) => $c->click_rate) ?? 0;
        $avgUnsub = $sentCampaigns->avg(fn ($c) => $c->unsubscribe_rate) ?? 0;
        $avgBounce = $sentCampaigns->avg(fn ($c) => $c->bounce_rate) ?? 0;

        // Subscriber stats
        $totalSubscribers = Subscriber::count();
        $newSubscribers = Subscriber::whereNotNull('subscribed_at')->where('subscribed_at', '>=', $start)->count();
        $lostSubscribers = Subscriber::whereNotNull('unsubscribed_at')->where('unsubscribed_at', '>=', $start)->count();

        // Top performers (by open rate)
        $topPerformers = Campaign::whereNotNull('sent_at')
            ->where('sent_at', '>=', $start)
            ->get()
            ->map(function (Campaign $c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'subject' => $c->subject,
                    'total_recipients' => (int) ($c->total_recipients ?? $c->sent_count ?? 0),
                    'open_rate' => (float) $c->open_rate,
                    'click_rate' => (float) $c->click_rate,
                    'sent_at' => optional($c->sent_at)->toDateTimeString(),
                ];
            })
            ->sortByDesc('open_rate')
            ->values()
            ->take(5);

        // Recent activity (normalize event_type for UI expectations)
        $recentActivity = AnalyticsEvent::with(['subscriber:id,email', 'campaign:id,name'])
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(function (AnalyticsEvent $e) {
                $type = $e->event_type;
                if ($type === 'opened') $type = 'open';
                if ($type === 'clicked') $type = 'click';
                if ($type === 'unsubscribed') $type = 'unsubscribe';
                // 'unsubscribed' and 'bounced' are fine; UI treats others as bounce/warn
                return [
                    'id' => $e->id,
                    'event_type' => $type,
                    'created_at' => $e->created_at->toDateTimeString(),
                    'subscriber' => [
                        'id' => optional($e->subscriber)->id,
                        'email' => optional($e->subscriber)->email,
                    ],
                    'campaign' => [
                        'id' => optional($e->campaign)->id,
                        'name' => optional($e->campaign)->name,
                    ],
                ];
            });

        $overview = [
            'total_campaigns' => $totalCampaigns,
            'total_opens' => $totalOpens,
            'total_clicks' => $totalClicks,
            'total_unsubscribes' => $totalUnsubs,
            'opens_change' => $percentChange($totalOpens, $prevOpens),
            'clicks_change' => $percentChange($totalClicks, $prevClicks),
            'unsubscribes_change' => $percentChange($totalUnsubs, $prevUnsubs),
            'avg_open_rate' => round($avgOpen, 2),
            'avg_click_rate' => round($avgClick, 2),
            'avg_unsubscribe_rate' => round($avgUnsub, 2),
            'avg_bounce_rate' => round($avgBounce, 2),
            'total_subscribers' => $totalSubscribers,
            'new_subscribers' => $newSubscribers,
            'lost_subscribers' => $lostSubscribers,
            'net_growth' => $newSubscribers - $lostSubscribers,
        ];

        return Inertia::render('Newsletter/Analytics/Index', [
            'overview' => $overview,
            'campaigns' => [],
            'topPerformers' => $topPerformers,
            'recentActivity' => $recentActivity,
            'dateRange' => (string) $period,
        ]);
    }
}
