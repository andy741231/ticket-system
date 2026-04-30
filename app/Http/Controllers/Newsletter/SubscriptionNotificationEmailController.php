<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\Group;
use App\Models\Newsletter\SubscriptionNotificationEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class SubscriptionNotificationEmailController extends Controller
{
    public function index()
    {
        $emails = SubscriptionNotificationEmail::query()
            ->with(['groups:id,name'])
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'is_active', 'created_at']);

        $groups = Group::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Newsletter/NotificationEmails/Index', [
            'emails' => $emails,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscription_notification_emails,email'],
            'is_active' => ['sometimes', 'boolean'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer', Rule::exists('newsletter_groups', 'id')->where('is_active', true)],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $notificationEmail = SubscriptionNotificationEmail::create([
            'email' => $request->input('email'),
            'is_active' => (bool) $request->input('is_active', true),
        ]);
        $notificationEmail->groups()->sync($validator->validated()['groups']);

        return back()->with('success', 'Notification email added.');
    }

    public function update(Request $request, SubscriptionNotificationEmail $notificationEmail)
    {
        $validator = Validator::make($request->all(), [
            'is_active' => ['sometimes', 'boolean'],
            'groups' => ['sometimes', 'array', 'min:1'],
            'groups.*' => ['integer', Rule::exists('newsletter_groups', 'id')->where('is_active', true)],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        if (array_key_exists('is_active', $validated)) {
            $notificationEmail->update(['is_active' => $validated['is_active']]);
        }

        if (array_key_exists('groups', $validated)) {
            $notificationEmail->groups()->sync($validated['groups']);
        }

        return back()->with('success', 'Notification email updated.');
    }

    public function destroy(SubscriptionNotificationEmail $notificationEmail)
    {
        $notificationEmail->delete();

        return back()->with('success', 'Notification email removed.');
    }
}
