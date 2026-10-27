# Newsletter Click-Tracking Redirect Fix

## Symptom

Two related failures were reported for newsletter tracked links in sent
emails:

1. **Original incident**: links work in preview but fail in the sent email.
   Offline reproduction of the send path yielded a `QueryException` that
   propagated to a 500 instead of redirecting to the destination.
2. **Confirmed follow-up (this change)**: tracked links whose base64 URL
   payload produces a path segment longer than ~260 characters are dead on
   Windows/IIS hosting — the request never reaches PHP.

## Diagnosis

### A. Redirect blocked by analytics errors (fixed earlier)

`PublicController::trackClick` (`app/Http/Controllers/Newsletter/PublicController.php`)
decoded the base64 URL, verified the token, then did analytics work
(`Campaign::find`, `Subscriber::find`, `AnalyticsEvent::create`) **before**
`return redirect($decodedUrl)`. Any `QueryException` in that block propagated
to the framework error handler -> 500, and the subscriber never reached the
destination. The original migration made `link_url` a `varchar(255)`, so a
~396-char wrapped destination (urldefense/AWS/SendIt style nesting) overflowed
the column on strict-mode MySQL -> `QueryException` -> 500.

### B. IIS http.sys kills long path segments before PHP (this change)

Tracked links were generated as literal path segments:

```
/newsletter/public/track-click/{campaign}/{subscriber}/{base64}/{token}
```

On Windows/IIS, `http.sys` enforces `UrlSegmentMaxLength` (default **260**
chars) at **kernel level** and returns **HTTP 400** before the request ever
reaches PHP/Laravel. A base64 path segment >~260 chars therefore means a dead
link no application code can resurrect — this matches the confirmed user
symptom. The `{url}` segment is `base64_encode(destination)`, so destinations
longer than ~190 chars were already at risk (base64 inflates ~4/3).

Moving the payload to the **query string** (`?u=` + `?t=`) raises the ceiling:
the IIS/HTTP query-string limit is ~2048 chars, covering destinations up to
roughly **1500 chars**.

**What stays broken**: destinations longer than ~1500 chars still exceed the
query limit (rare; nothing reasonable can fix them), and *already-sent* emails
whose path segments exceeded 260 chars remain dead — the only remedies would
be resending the campaign or rewriting stored email content, neither of which
is done here. Legacy-format links that fit under 260 chars continue to work.

## Fix

### Redirect resilience (earlier change)

- `trackClick`: token verification and redirect are unchanged; the analytics
  block is wrapped in `try/catch (QueryException)` -> `report($e)` and
  continues to `redirect()`. Invalid tokens still `abort(404)` *before* any
  DB work.
- Migration
  `database/migrations/2026_10_27_000001_expand_newsletter_analytics_link_url.php`
  widens `link_url` to `TEXT`. `down()` is intentionally a no-op.

### Query-string link format (this change)

- **`routes/web.php`** (`$publicRoutes` closure, registered under both the
  `newsletter` and `newsletter/public` prefixes, shared name prefix
  `newsletter.public.`):
  - New short route `track-click/{campaign}/{subscriber}` named
    `newsletter.public.track-click` — the payload arrives as query params.
  - The greedy route `track-click/{campaign}/{subscriber}/{url}/{token}`
    (`->where('url', '.*')`) is **kept** and renamed
    `newsletter.public.track-click.legacy` so links embedded in previously
    sent emails still resolve. Nothing else references these route names.
- **`PublicController::trackClick`** — signature is now
  `trackClick(Request $request, $campaign, $subscriber, $url = null, $token = null)`.
  Positional args take precedence; otherwise `$url`/`$token` fall back to
  `$request->query('u')` / `$request->query('t')`. Missing/empty -> `abort(404)`.
  Query values arrive already percent-decoded, so `base64_decode($url)`, the
  token check (`sha256` of campaign+subscriber+decoded URL+`app.key`,
  `hash_equals` -> `abort(404)`), the analytics `try/catch (QueryException)`
  block, and `redirect($decodedUrl)` are all unchanged.
- **`app/Mail/NewsletterMail.php`** — link generation now passes
  `'u' => base64_encode($originalUrl)` and `'t' => hash(...)` to
  `route('newsletter.public.track-click', ...)`; Laravel appends non-URI
  params as an RFC3986 query string (no manual concatenation/encoding). The
  token protocol is unchanged.
- **`stripTrackingLinks`** — all four copies of the unwrap logic accept the
  new query format and retain legacy path support:
  `app/Mail/NewsletterMail.php`,
  `app/Http/Controllers/Newsletter/CampaignController.php`,
  `resources/js/Pages/Newsletter/Campaigns/Create.vue`,
  `resources/js/Pages/Newsletter/Campaigns/Edit.vue`.
  Order per site: after the `track-click` match, read the `u` query param;
  if present it decides alone (URL-safe base64 normalize + repad + strict
  decode + `^https?://` -> restore; bad -> `href="#"`). If `u` is absent, the
  existing path-segment extraction runs; unparseable -> `href="#"` as before.

## Tests (offline, no Laravel app/env/DB)

`tests/Unit/NewsletterClickTrackingTest.php` — plain `TestCase`, isolated
`new Illuminate\Foundation\Application` (not `bootstrap/app.php`), synthetic
`app.key`, synthetic request, real `UrlGenerator`/`Redirector`/`Router`,
in-memory SQLite via Capsule, stub `ExceptionHandler` capturing `report()`.
Covers:

- Legacy positional format: exact 302 + event for short and ~400-char nested
  URLs; oversized-URL insert failure still redirects + reports (255 limit
  *simulated* with a SQLite CHECK); lookup failure still redirects; missing
  records redirect silently; tampered token/URL -> 404 before DB/report;
  migration widening to TEXT preserving rows.
- Query format: `Request::create` with `?u=..&t=..` -> 302, exact `Location`,
  event recorded; ~400-char nested destination round-trips exactly (its
  base64 exceeds 260 chars, which would be a dead path segment on IIS);
  missing `u`/`t`/both -> `NotFoundHttpException`; tampered `t` -> 404 proven
  to happen before any DB work (campaigns table dropped).
- Route level: the real `routes/web.php` is required onto the isolated app
  (registration is lazy — no controllers run, middleware names unresolved;
  `refreshNameLookups()`/`refreshActionLookups()` are called afterwards just
  as the framework's `RouteServiceProvider` does post-route-loading).
  `route('newsletter.public.track-click', [...'u' => base64, 't' => token])`
  produces path `track-click/{campaign}/{subscriber}` plus query `u=..&t=..`
  with no base64 in the path; dispatching that generated URL through
  `$app['router']` returns 302 to the destination and writes the event —
  proving route registration + query-param binding end to end. Dispatching a
  literal legacy path URL (base64 containing `/` chars, exercising
  `->where('url', '.*')` across decoded `%2F`) still returns 302.
- `stripTrackingLinks` (private in both PHP classes): invoked via reflection
  on `NewsletterMail` (in-memory `Campaign`/`Subscriber` models) and
  `CampaignController`. `?u=` hrefs restore the original, legacy path hrefs
  restore the original, malformed/non-http(s) `u` becomes `href="#"`,
  non-tracking links untouched. The two Vue copies mirror the same logic and
  are not unit-covered (no JS test harness).

Run: `vendor/bin/phpunit tests/Unit` (all 26 tests).

## Limitations

- No production access was used: the IIS 400 behavior is established
  Microsoft http.sys behavior (`UrlSegmentMaxLength`, default 260), matched
  against the confirmed symptom; the PHP-side changes are proven offline.
- Already-sent emails with >260-char path segments stay dead on IIS; only a
  resend or stored-content rewrite could fix them (explicitly out of scope).
- Destinations beyond ~1500 chars exceed the query-string ceiling too.
- No emails sent, no real tracking tokens used (synthetic key/IDs).
- The migration still must be run in production.
