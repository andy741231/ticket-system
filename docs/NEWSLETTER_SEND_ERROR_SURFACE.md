# Newsletter Send Errors Now Reach the Campaign UI

## Symptom

When sends fail during a campaign, the campaign detail page's "Error Details"
box showed only the generic **"No specific error details available"** banner —
even though every individual `newsletter_scheduled_sends` row had a real
`error_message`.

## Cause

`SendEmailToSubscriber::handle()` catches `\Exception` from the mailer, marks
the scheduled send failed, and increments `failed_count` — but it swallowed the
exception (deliberately: no rethrow, so the last batch of failures can't leave
the campaign stuck in `sending`).

Because nothing was rethrown, the queue worker never invoked the job's
`failed()` method — which is the only place that called
`Campaign::recordError()`. `recordError()` is what writes
`metadata['last_error']`, and `Campaign::getLastErrorAttribute()` reads
`metadata['last_error']` **first** before falling back to the `failed_jobs`
table. Result: metadata stayed empty, the accessor returned null, and the UI
had nothing to show.

## Fix

`app/Jobs/SendEmailToSubscriber.php` — inside the existing
`catch (\Exception $e)` block, after `markAsFailed()` / `failed_count` /
`\Log::error` and before `checkCampaignCompletion()`, the job now writes the
same `metadata['last_error']` shape that `recordError()` produces
(`message`, `file`, `line`, `trace`, `time`).

Two deliberate differences from `recordError()`:

- **No `status = 'failed'` write.** `recordError()` sets the whole campaign to
  `failed`, which would corrupt state while other sends for the same campaign
  are still in flight. Final campaign status stays owned by
  `checkCampaignCompletion()`.
- **Wrapped in a nested `try/catch (\Throwable)`.** Error reporting can never
  break failure accounting or skip campaign-completion evaluation; a failed
  metadata write only produces a `Log::warning`.

`last_error` holds the **latest** failure (each failing send overwrites it) —
accurate for fleet-wide failures like campaign 251 where every send throws the
same error.

## The campaign-251 incident

All 71/71 sends failed with:

```
Missing required parameters for [Route: newsletter.public.track-click]
[Missing parameters: url, token]
```

`NewsletterMail` had been updated to generate the new query-string click
tracking format (`?u=…&t=…`) via `route('newsletter.public.track-click', …)`,
but the server was still running a **stale route table** where that name
resolved to the old greedy path-segment route — so `u`/`t` query parameters
were rejected as unknown route parameters and every mailable build threw.

Remediation for that incident (ops, not code):

1. Clear the route cache and opcache on the web server so the new route
   registration is loaded.
2. Retry/resend the failed scheduled sends — with routes correct, the same job
   succeeds and (now) any real residual failures surface in the UI instead of
   the generic banner.

## Limitations

- Only the **last** send error is kept on the campaign (`last_error` key).
  Per-send history remains in `newsletter_scheduled_sends.error_message`.
- Errors thrown *outside* the `try` in `handle()` (e.g. DB down before the
  send attempt, `fresh()` returning null) still rely on the `failed_jobs`
  fallback path in `getLastErrorAttribute()`.
- `\Error` throwables are not caught by `catch (\Exception)` — unchanged from
  before; extending to `\Throwable` would change retry semantics and was out
  of scope.
