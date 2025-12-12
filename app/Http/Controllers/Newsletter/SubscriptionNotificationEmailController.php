<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\SubscriptionNotificationEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SubscriptionNotificationEmailController extends Controller
{
    public function index()
    {
        $emails = SubscriptionNotificationEmail::query()
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'is_active', 'created_at']);

        return Inertia::render('Newsletter/NotificationEmails/Index', [
            'emails' => $emails,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscription_notification_emails,email'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        SubscriptionNotificationEmail::create([
            'email' => $request->input('email'),
            'is_active' => (bool) $request->input('is_active', true),
        ]);

        return back()->with('success', 'Notification email added.');
    }

    public function update(Request $request, SubscriptionNotificationEmail $notificationEmail)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $notificationEmail->update($validator->validated());

        return back()->with('success', 'Notification email updated.');
    }

    public function destroy(SubscriptionNotificationEmail $notificationEmail)
    {
        $notificationEmail->delete();

        return back()->with('success', 'Notification email removed.');
    }
}
