# Newsletter Click-Tracking Redirect Fix

## Symptom

User reports that newsletter links work in preview but fail in the sent
email. Offline reproduction of the send path yields a `QueryException` that
propagates to a 500 instead of redirecting to the destination.

## Diagnosis (offline)

`PublicController::trackClick` (`app/Http/Controllers/Newsletter/PublicController.php`)
decodes the base64 URL, verifies the token, then does analytics work
(`Campaign::find`, `Subscriber::find`, `AnalyticsEvent::create`) **before**
`return redirect($decodedUrl)`. Any `QueryException` in that block propagated
to the framework error handler -> 500, and the subscriber never reached the
destination.

Two contributing facts, distinguished:

- **Demonstrated code defect (fixed)**: any DB exception during analytics
  recording blocked the redirect. This is now covered by offline unit tests
  (simulated oversized `link_url` insert, dropped/missing tables).
- **Likely production cause (not confirmed)**: the original migration made
  `link_url` a `varchar(255)`. The real clicked URL from the incident is a
  ~396-char security-gateway wrapped link (urldefense/AWS/SendIt style
  nesting). On MySQL in strict SQL mode, `INSERT` of a 396-char value into
  `varchar(255)` raises "Data too long for column" -> `QueryException` -> the
  defect above.
  Whether that is what actually happened in production is unconfirmed; no
  production access was used.

## Fix

- `trackClick`: token verification and redirect are unchanged; only the
  analytics block is wrapped in `try/catch (QueryException)` -> `report($e)`
  and continue to `redirect()`. Invalid tokens still `abort(404)` *before*
  any DB work or the catch.
- New migration
  `database/migrations/2026_10_27_000001_expand_newsletter_analytics_link_url.php`
  widens `link_url` to `TEXT` (`->nullable()->change()`). The original
  migration is untouched. `down()` is intentionally a no-op: shrinking could
  truncate destinations already recorded (irreversible widening).
- The migration has only been run against the tests' synthetic in-memory
  SQLite schema, not against any real database; it must still be run in
  production later. The controller fallback already protects redirects even
  before the column is widened.
- No URL encoding, mail generation, or token protocol changes.

## Tests (offline, no Laravel app/env/DB)

`tests/Unit/NewsletterClickTrackingTest.php` - plain `TestCase`, isolated
`new Illuminate\Foundation\Application` (not `bootstrap/app.php`), synthetic
`app.key`, synthetic request, real `UrlGenerator`/`Redirector`, in-memory
SQLite via Capsule, stub `ExceptionHandler` capturing `report()`. Covers:
exact 302 + event for short and ~400-char nested URLs (ampersands/percent
escapes/fragment preserved in `Location`), oversized-URL insert failure
still redirects + reports (255 limit *simulated* with a SQLite CHECK -
SQLite does not enforce varchar width, so this proves the fallback, not
byte-for-byte MySQL behavior), lookup failure still redirects, missing
records redirect silently, tampered token/URL -> 404 before DB/report, and
the migration widening to TEXT preserving existing rows.

Run: `vendor/bin/phpunit tests/Unit/NewsletterClickTrackingTest.php`

Before the controller fix the oversized/lookup tests raised uncaught
`QueryException` (i.e. the 500); after, all pass. Evidence logs under
`/tmp/click-tracking-evidence/`.

## Limitations

- Production error was never observed; the 255-char overflow is inferred
  from schema + URL length, the redirect-blocking defect is proven offline.
- No emails sent, no real tracking tokens used (synthetic key/IDs).
