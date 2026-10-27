<?php

namespace Tests\Unit;

use App\Http\Controllers\Newsletter\CampaignController;
use App\Http\Controllers\Newsletter\PublicController;
use App\Mail\NewsletterMail;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * PublicController::trackClick regression tests.
 *
 * Covers both link formats: legacy path-segment base64
 * (/track-click/{campaign}/{subscriber}/{base64}/{token}) and the new
 * query-string format (/track-click/{campaign}/{subscriber}?u={base64}&t={token})
 * introduced because IIS http.sys rejects >260-char path segments at kernel
 * level (HTTP 400 before PHP ever runs).
 *
 * Runs on an isolated Illuminate\Foundation\Application (NOT bootstrap/app.php,
 * so no project .env/config cache is loaded) with a synthetic app.key, a
 * synthetic-request-bound UrlGenerator/Redirector, and in-memory SQLite via
 * Capsule. NOTE: SQLite does not enforce varchar widths, so the original
 * varchar(255) restriction is SIMULATED with a CHECK constraint - this proves
 * the controller-level fallback, it is not a MySQL byte-for-byte reproduction.
 */
class NewsletterClickTrackingTest extends TestCase
{
    private const APP_KEY = 'synthetic-test-app-key';
    private const CAMPAIGN_ID = 101;
    private const SUBSCRIBER_ID = 202;

    private $previousContainer;
    private $previousFacadeApp;
    private $previousResolver;
    private $previousDispatcher;

    private Application $app;
    private Capsule $capsule;
    private Request $request;
    private object $handler;
    private PublicController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        // Snapshot global state so the rest of the suite is unaffected.
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApp = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();

        $this->app = new Application();
        $this->app->instance('config', new Repository(['app' => ['key' => self::APP_KEY]]));

        $this->handler = new class implements ExceptionHandler {
            public array $reported = [];

            public function report(Throwable $e): void
            {
                $this->reported[] = $e;
            }

            public function shouldReport(Throwable $e): bool
            {
                return true;
            }

            public function render($request, Throwable $e)
            {
                return null;
            }

            public function renderForConsole($output, Throwable $e)
            {
            }
        };
        $this->app->instance(ExceptionHandler::class, $this->handler);

        // Synthetic request host; real UrlGenerator/Redirector resolve lazily
        // from the framework's RoutingServiceProvider using this request.
        $this->request = Request::create('https://stub.local/newsletter/public/track-click', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'synthetic-test-agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $this->app->instance('request', $this->request);

        $this->capsule = new Capsule($this->app);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->capsule->bootEloquent();
        $this->app->instance('db', $this->capsule->getDatabaseManager());
        $this->app->bind('db.schema', fn () => $this->capsule->getConnection()->getSchemaBuilder());

        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();

        $this->controller = new PublicController();
        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver($this->previousResolver ?? Model::getConnectionResolver());
        if ($this->previousResolver === null) {
            Model::unsetConnectionResolver();
        }
        if ($this->previousDispatcher) {
            Model::setEventDispatcher($this->previousDispatcher);
        } else {
            Model::unsetEventDispatcher();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApp);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function buildSchema(): void
    {
        $schema = $this->capsule->getConnection()->getSchemaBuilder();
        $schema->create('newsletter_campaigns', function (Blueprint $t) {
            $t->id();
        });
        $schema->create('newsletter_subscribers', function (Blueprint $t) {
            $t->id();
        });
        // Mirrors the original migration: link_url was ->string() (varchar 255)
        $schema->create('newsletter_analytics_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('campaign_id');
            $t->unsignedBigInteger('subscriber_id');
            $t->string('event_type');
            $t->string('link_url')->nullable();
            $t->string('user_agent')->nullable();
            $t->string('ip_address')->nullable();
            $t->timestamps();
        });
        $this->capsule->getConnection()->table('newsletter_campaigns')->insert(['id' => self::CAMPAIGN_ID]);
        $this->capsule->getConnection()->table('newsletter_subscribers')->insert(['id' => self::SUBSCRIBER_ID]);
    }

    private function tokenFor(string $decodedUrl): string
    {
        return hash('sha256', self::CAMPAIGN_ID . self::SUBSCRIBER_ID . $decodedUrl . self::APP_KEY);
    }

    private function callTrackClick(string $decodedUrl, ?string $urlParam = null, ?string $token = null)
    {
        $urlParam ??= base64_encode($decodedUrl);
        $token ??= $this->tokenFor($decodedUrl);
        return $this->controller->trackClick(
            $this->request,
            (string) self::CAMPAIGN_ID,
            (string) self::SUBSCRIBER_ID,
            $urlParam,
            $token
        );
    }

    private function eventCount(): int
    {
        return $this->capsule->getConnection()->table('newsletter_analytics_events')->count();
    }

    public function test_valid_short_url_redirects_and_records_event(): void
    {
        $dest = 'https://synthetic.test/story?utm_source=newsletter';
        $response = $this->callTrackClick($dest);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame($dest, $response->headers->get('Location'));
        $this->assertSame(1, $this->eventCount());
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame('clicked', $row->event_type);
        $this->assertSame($dest, $row->link_url);
        $this->assertSame([], $this->handler->reported);
    }

    public function test_long_nested_wrapped_url_redirects_exact_and_stores_full(): void
    {
        // Synthetic urldefense/AWS-style nested URL >= 396 chars incl. escaped
        // query params, ampersands and a fragment.
        $inner = 'https%3A%2F%2Fportal.example.com%2Fenroll%3Fcampaign%3Dfall%26ref%3Demail%23details';
        $dest = 'https://urldefense.example.com/v3/__' . $inner . '__/!!/?' .
            'token=' . str_repeat('Ab9', 60) . '&sig=' . str_repeat('xY', 60) . '#frag';
        $this->assertGreaterThanOrEqual(396, strlen($dest));

        $response = $this->callTrackClick($dest);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame($dest, $response->headers->get('Location'));
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame($dest, $row->link_url);
    }

    public function test_oversized_url_redirects_even_when_insert_fails(): void
    {
        // Simulate the original varchar(255) restriction (SQLite ignores
        // declared width, so enforce it via CHECK). With the fix the redirect
        // must still happen and the failure is only reported.
        $schema = $this->capsule->getConnection()->getSchemaBuilder();
        $schema->drop('newsletter_analytics_events');
        $this->capsule->getConnection()->statement(
            'CREATE TABLE newsletter_analytics_events (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'campaign_id INTEGER NOT NULL, subscriber_id INTEGER NOT NULL, ' .
            'event_type VARCHAR NOT NULL, ' .
            'link_url VARCHAR(255) NULL CHECK (link_url IS NULL OR length(link_url) <= 255), ' .
            'user_agent VARCHAR NULL, ip_address VARCHAR NULL, ' .
            'created_at DATETIME NULL, updated_at DATETIME NULL)'
        );

        $dest = 'https://synthetic.test/deep/' . str_repeat('long-', 80) . '?ref=email';
        $response = $this->callTrackClick($dest);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame(0, $this->eventCount());
        $this->assertCount(1, $this->handler->reported);
        $this->assertInstanceOf(QueryException::class, $this->handler->reported[0]);
    }

    public function test_lookup_query_exception_still_redirects(): void
    {
        // Drop the campaigns table -> Campaign::find throws QueryException.
        $this->capsule->getConnection()->getSchemaBuilder()->drop('newsletter_campaigns');

        $dest = 'https://synthetic.test/still-redirects';
        $response = $this->callTrackClick($dest);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertCount(1, $this->handler->reported);
        $this->assertInstanceOf(QueryException::class, $this->handler->reported[0]);
    }

    public function test_missing_records_redirects_without_event_or_report(): void
    {
        $this->capsule->getConnection()->table('newsletter_campaigns')->delete();

        $dest = 'https://synthetic.test/no-campaign';
        $response = $this->callTrackClick($dest);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame(0, $this->eventCount());
        $this->assertSame([], $this->handler->reported);
    }

    public function test_tampered_token_aborts_404_before_any_db_work(): void
    {
        // Drop the campaigns table: any lookup before token validation would
        // surface as a QueryException instead of the expected 404.
        $this->capsule->getConnection()->getSchemaBuilder()->drop('newsletter_campaigns');

        $dest = 'https://synthetic.test/story';
        try {
            $this->callTrackClick($dest, null, 'tampered-token');
            $this->fail('expected 404');
        } catch (NotFoundHttpException $e) {
            $this->assertTrue(true);
        }
        $this->assertSame(0, $this->eventCount());
        $this->assertSame([], $this->handler->reported);
    }

    public function test_tampered_url_aborts_404(): void
    {
        $dest = 'https://synthetic.test/story';
        $other = 'https://synthetic.test/elsewhere';
        try {
            // Token signed for $dest but url param decodes to $other.
            $this->callTrackClick($dest, base64_encode($other), $this->tokenFor($dest));
            $this->fail('expected 404');
        } catch (NotFoundHttpException $e) {
            $this->assertTrue(true);
        }
        $this->assertSame(0, $this->eventCount());
        $this->assertSame([], $this->handler->reported);
    }

    public function test_nested_url_preserves_ampersands_escapes_fragment(): void
    {
        $dest = 'https://synthetic.test/path?a=1&b=two%20words&c=x%26y&d=https%3A%2F%2Fnested.example%2Fq%3Fp%3D1#frag-9';
        $response = $this->callTrackClick($dest);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame($dest, $response->headers->get('Location'));
        $this->assertStringContainsString('#frag-9', $response->headers->get('Location'));
    }

    public function test_migration_widens_link_url_to_text_and_preserves_data(): void
    {
        $this->capsule->getConnection()->table('newsletter_analytics_events')->insert([
            'campaign_id' => self::CAMPAIGN_ID,
            'subscriber_id' => self::SUBSCRIBER_ID,
            'event_type' => 'clicked',
            'link_url' => 'https://synthetic.test/keep-me',
        ]);

        $migration = include dirname(__DIR__, 2) .
            '/database/migrations/2026_10_27_000001_expand_newsletter_analytics_link_url.php';
        $migration->up();

        $type = strtolower((string) Schema::getColumnType('newsletter_analytics_events', 'link_url'));
        $this->assertContains($type, ['text', 'mediumtext', 'longtext'], "unexpected type {$type}");
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame('https://synthetic.test/keep-me', $row->link_url);
    }

    // ------------------------------------------------------------------
    // Query-string format (?u= & ?t=): the base64 payload moved out of the
    // path because IIS http.sys rejects >260-char path segments at kernel
    // level (HTTP 400 before PHP runs).
    // ------------------------------------------------------------------

    private function queryRequest(
        string $dest,
        ?string $tokenOverride = null,
        bool $includeU = true,
        bool $includeT = true
    ): Request {
        $qs = [];
        if ($includeU) {
            $qs[] = 'u=' . urlencode(base64_encode($dest));
        }
        if ($includeT) {
            $qs[] = 't=' . ($tokenOverride ?? $this->tokenFor($dest));
        }
        return Request::create(
            'https://stub.local/newsletter/public/track-click/' .
                self::CAMPAIGN_ID . '/' . self::SUBSCRIBER_ID .
                ($qs ? '?' . implode('&', $qs) : ''),
            'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'synthetic-test-agent',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
    }

    private function nestedDest(): string
    {
        // Same ~400-char urldefense-style nested destination the legacy test
        // uses (escaped query params, ampersands and a fragment).
        $inner = 'https%3A%2F%2Fportal.example.com%2Fenroll%3Fcampaign%3Dfall%26ref%3Demail%23details';
        return 'https://urldefense.example.com/v3/__' . $inner . '__/!!/?' .
            'token=' . str_repeat('Ab9', 60) . '&sig=' . str_repeat('xY', 60) . '#frag';
    }

    public function test_query_param_call_redirects_and_records_event(): void
    {
        $dest = 'https://synthetic.test/story?utm_source=newsletter';
        $response = $this->controller->trackClick(
            $this->queryRequest($dest),
            (string) self::CAMPAIGN_ID,
            (string) self::SUBSCRIBER_ID
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame($dest, $response->headers->get('Location'));
        $this->assertSame(1, $this->eventCount());
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame('clicked', $row->event_type);
        $this->assertSame($dest, $row->link_url);
        $this->assertSame([], $this->handler->reported);
    }

    public function test_query_param_long_nested_url_redirects_exact_and_stores_full(): void
    {
        $dest = $this->nestedDest();
        $this->assertGreaterThanOrEqual(396, strlen($dest));
        // Its base64 (~530 chars) would be a dead >260-char path segment on IIS.
        $this->assertGreaterThan(260, strlen(base64_encode($dest)));

        $response = $this->controller->trackClick(
            $this->queryRequest($dest),
            (string) self::CAMPAIGN_ID,
            (string) self::SUBSCRIBER_ID
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->getTargetUrl());
        $this->assertSame($dest, $response->headers->get('Location'));
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame($dest, $row->link_url);
    }

    public function test_missing_u_or_t_query_params_abort_404(): void
    {
        $dest = 'https://synthetic.test/story';
        $requests = [
            'missing u' => $this->queryRequest($dest, null, false, true),
            'missing t' => $this->queryRequest($dest, null, true, false),
            'missing both' => $this->queryRequest($dest, null, false, false),
        ];
        foreach ($requests as $label => $req) {
            try {
                $this->controller->trackClick(
                    $req, (string) self::CAMPAIGN_ID, (string) self::SUBSCRIBER_ID
                );
                $this->fail("expected 404 for {$label}");
            } catch (NotFoundHttpException $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame(0, $this->eventCount());
        $this->assertSame([], $this->handler->reported);
    }

    public function test_tampered_t_query_param_aborts_404_before_db_work(): void
    {
        // Drop the campaigns table: any lookup before token validation would
        // surface as a QueryException instead of the expected 404.
        $this->capsule->getConnection()->getSchemaBuilder()->drop('newsletter_campaigns');

        $dest = 'https://synthetic.test/story';
        try {
            $this->controller->trackClick(
                $this->queryRequest($dest, 'tampered-token'),
                (string) self::CAMPAIGN_ID,
                (string) self::SUBSCRIBER_ID
            );
            $this->fail('expected 404');
        } catch (NotFoundHttpException $e) {
            $this->assertTrue(true);
        }
        $this->assertSame(0, $this->eventCount());
        $this->assertSame([], $this->handler->reported);
    }

    // ------------------------------------------------------------------
    // Route level: the real routes/web.php is loaded onto the isolated app.
    // Route registration is lazy (controllers not executed, middleware names
    // not resolved), so no app bootstrap/env is needed for this.
    // ------------------------------------------------------------------

    private function dispatch(Request $req)
    {
        // The HTTP kernel normally binds the dispatched request into the
        // container; mirror that so the controller's Request typehint and the
        // 'request' alias resolve to the request being dispatched.
        $this->app->instance('request', $req);
        return $this->app['router']->dispatch($req);
    }

    public function test_routes_generate_query_format_and_dispatch_end_to_end(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));
        require base_path('routes/web.php');
        // Fluent ->name() calls run after routes enter the collection; the
        // real app's RouteServiceProvider refreshes the lookup tables after
        // route loading — no such provider exists here, so do it manually.
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        $dest = 'https://synthetic.test/landing?x=1&y=two%20words';
        $generated = route('newsletter.public.track-click', [
            'campaign' => self::CAMPAIGN_ID,
            'subscriber' => self::SUBSCRIBER_ID,
            'u' => base64_encode($dest),
            't' => $this->tokenFor($dest),
        ]);

        // Generated link: short path + query params; no base64 in the path.
        $parts = parse_url($generated);
        $this->assertStringEndsWith(
            'track-click/' . self::CAMPAIGN_ID . '/' . self::SUBSCRIBER_ID,
            $parts['path']
        );
        $this->assertStringNotContainsString(base64_encode($dest), $parts['path']);
        $this->assertNotEmpty($parts['query'] ?? '');
        parse_str($parts['query'], $q);
        $this->assertSame(base64_encode($dest), $q['u'] ?? null);
        $this->assertSame($this->tokenFor($dest), $q['t'] ?? null);

        // Dispatch that generated URL through the real router.
        $response = $this->dispatch(Request::create($generated, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'synthetic-test-agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->headers->get('Location'));
        $this->assertSame(1, $this->eventCount());
        $row = $this->capsule->getConnection()->table('newsletter_analytics_events')->first();
        $this->assertSame($dest, $row->link_url);
    }

    public function test_legacy_path_route_still_dispatches_end_to_end(): void
    {
        $this->app->setBasePath(dirname(__DIR__, 2));
        require base_path('routes/web.php');
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        // A ~530-char base64 payload contains '/' characters -> the literal
        // path URL relies on ->where('url', '.*') matching across decoded
        // %2F segments, exactly as sent emails already carry it.
        $dest = $this->nestedDest();
        $legacyUrl = 'https://stub.local/newsletter/public/track-click/' .
            self::CAMPAIGN_ID . '/' . self::SUBSCRIBER_ID . '/' .
            rawurlencode(base64_encode($dest)) . '/' . $this->tokenFor($dest);

        $response = $this->dispatch(Request::create($legacyUrl, 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'synthetic-test-agent',
            'REMOTE_ADDR' => '127.0.0.1',
        ]));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($dest, $response->headers->get('Location'));
        $this->assertSame(1, $this->eventCount());
    }

    // ------------------------------------------------------------------
    // stripTrackingLinks (private, duplicated in NewsletterMail +
    // CampaignController): must unwrap both the new ?u= format and the
    // legacy path format, and neutralize unparseable tracking links.
    // ------------------------------------------------------------------

    private function stripVia(object $obj, string $html): string
    {
        // stripTrackingLinks is private in both classes. Since PHP 8.1 all
        // methods are invocable via reflection (setAccessible() is a no-op
        // there and deprecated in 8.5), so invoke directly.
        $method = new ReflectionMethod($obj, 'stripTrackingLinks');
        return $method->invoke($obj, $html);
    }

    public function test_strip_tracking_links_restores_query_and_legacy_formats(): void
    {
        $dest = 'https://synthetic.test/original-article?a=1&b=2';
        $base = 'https://stub.local/newsletter/public/track-click/' .
            self::CAMPAIGN_ID . '/' . self::SUBSCRIBER_ID;

        $queryHref = $base . '?u=' . rawurlencode(base64_encode($dest)) . '&t=' . $this->tokenFor($dest);
        $legacyHref = $base . '/' . rawurlencode(base64_encode($dest)) . '/' . $this->tokenFor($dest);
        $badSchemeHref = $base . '?u=' . rawurlencode(base64_encode('ftp://not-http')) . '&t=x';
        $garbageHref = $base . '?u=' . rawurlencode('!!!not-base64') . '&t=x';

        $subjects = [
            'NewsletterMail' => new NewsletterMail(new Campaign(), new Subscriber()),
            'CampaignController' => new CampaignController(),
        ];

        foreach ($subjects as $label => $subject) {
            // New query format restores the original destination.
            $this->assertSame(
                '<a href="' . $dest . '">x</a>',
                $this->stripVia($subject, '<a href="' . $queryHref . '">x</a>'),
                "{$label}: ?u= format not restored"
            );
            // Legacy path format still restores.
            $this->assertSame(
                '<a href="' . $dest . '">x</a>',
                $this->stripVia($subject, '<a href="' . $legacyHref . '">x</a>'),
                "{$label}: legacy path format not restored"
            );
            // u present but decodes to a non-http(s) destination -> '#'.
            $this->assertSame(
                '<a href="#">x</a>',
                $this->stripVia($subject, '<a href="' . $badSchemeHref . '">x</a>'),
                "{$label}: non-http(s) u not neutralized"
            );
            // u present but not decodable -> '#'.
            $this->assertSame(
                '<a href="#">x</a>',
                $this->stripVia($subject, '<a href="' . $garbageHref . '">x</a>'),
                "{$label}: malformed u not neutralized"
            );
            // Non-tracking links untouched.
            $this->assertSame(
                '<a href="https://synthetic.test/plain">x</a>',
                $this->stripVia($subject, '<a href="https://synthetic.test/plain">x</a>'),
                "{$label}: non-tracking link modified"
            );
        }
    }
}
