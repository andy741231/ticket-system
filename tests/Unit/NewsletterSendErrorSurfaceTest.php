<?php

namespace Tests\Unit;

use App\Jobs\SendEmailToSubscriber;
use App\Models\Newsletter\ScheduledSend;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log as LogFacade;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * SendEmailToSubscriber error-surfacing regression tests.
 *
 * Reproduces the campaign-251 incident shape: every send throws (prod cause:
 * stale route table -> "Missing required parameters ... url, token"), the
 * job's catch block records the failure, and the campaign UI's "Error Details"
 * box must show the real message instead of "No specific error details
 * available". Campaign::getLastErrorAttribute() reads metadata['last_error']
 * first, so the catch now writes it there — same shape as recordError() but
 * WITHOUT setting status='failed' (concurrent sends may still be in flight).
 *
 * Runs on an isolated Illuminate\Foundation\Application (NOT bootstrap/app.php,
 * so no project .env/config cache is loaded) with a synthetic app.key and
 * in-memory SQLite via Capsule. Mail::mailer('campus_smtp') is replaced by a
 * stub mail.manager binding whose send() either returns null or throws a
 * chosen \Exception; the global \Log facade alias is pointed at a stub 'log'
 * binding so the job's unconditional log calls resolve.
 */
class NewsletterSendErrorSurfaceTest extends TestCase
{
    private const APP_KEY = 'synthetic-test-app-key';
    private const CAMPAIGN_ID = 251;
    private const SUBSCRIBER_ID = 1;
    private const SUBSCRIBER2_ID = 2;
    private const SEND_ID = 11;
    private const SEND2_ID = 12;

    private $previousContainer;
    private $previousFacadeApp;
    private $previousResolver;
    private $previousDispatcher;

    private Application $app;
    private Capsule $capsule;
    private object $handler;
    private StubLog $log;
    private StubPendingMail $pendingMail;

    protected function setUp(): void
    {
        parent::setUp();
        // Snapshot global state so the rest of the suite is unaffected.
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApp = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();

        $this->app = new Application();
        $this->app->instance('config', new Repository([
            'app' => ['key' => self::APP_KEY, 'timezone' => 'UTC'],
        ]));

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

        // SendEmailToSubscriber calls \Log::info/error unconditionally (global
        // 'Log' class alias — only registered by the real app's AliasLoader,
        // which does not exist here). Alias it to the Log facade and bind a
        // recording stub as the 'log' service it resolves to.
        if (!class_exists('Log', false)) {
            class_alias(LogFacade::class, 'Log');
        }
        $this->log = new StubLog();
        $this->app->instance('log', $this->log);

        // Mail::mailer('campus_smtp')->to(...)->send($mail) resolves the
        // 'mail.manager' service. Swap in a controllable stub.
        $this->pendingMail = new StubPendingMail();
        $this->app->instance('mail.manager', new StubMailManager($this->pendingMail));

        $this->capsule = new Capsule($this->app);
        $this->capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->capsule->bootEloquent();
        $this->app->instance('db', $this->capsule->getDatabaseManager());
        $this->app->bind('db.schema', fn () => $this->capsule->getConnection()->getSchemaBuilder());

        Facade::setFacadeApplication($this->app);
        Facade::clearResolvedInstances();

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
        // Minimal subset of the real newsletter_campaigns columns (incl. the
        // metadata json + last_error text columns added by later migrations).
        $schema->create('newsletter_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('subject')->nullable();
            $t->string('status')->default('draft');
            $t->unsignedInteger('total_recipients')->default(0);
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->json('metadata')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        $schema->create('newsletter_subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('status')->default('active');
            $t->string('unsubscribe_token')->nullable();
            $t->string('name')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('organization')->nullable();
            $t->timestamp('subscribed_at')->nullable();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        $schema->create('newsletter_scheduled_sends', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('campaign_id');
            $t->unsignedBigInteger('subscriber_id');
            $t->string('status')->default('pending');
            $t->timestamp('scheduled_at');
            $t->timestamp('sent_at')->nullable();
            $t->text('error_message')->nullable();
            $t->integer('retry_count')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
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
    }

    private function seedCampaign(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $this->capsule->getConnection()->table('newsletter_campaigns')->insert([
            'id' => self::CAMPAIGN_ID,
            'name' => 'Synthetic Campaign',
            'subject' => 'Synthetic Subject',
            'status' => 'sending',
            'total_recipients' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'metadata' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedSubscriber(int $id, string $email): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $this->capsule->getConnection()->table('newsletter_subscribers')->insert([
            'id' => $id,
            'email' => $email,
            'status' => 'active',
            'unsubscribe_token' => 'token-' . $id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedPendingSend(int $id, int $subscriberId): ScheduledSend
    {
        $now = now()->format('Y-m-d H:i:s');
        $this->capsule->getConnection()->table('newsletter_scheduled_sends')->insert([
            'id' => $id,
            'campaign_id' => self::CAMPAIGN_ID,
            'subscriber_id' => $subscriberId,
            'status' => 'pending',
            'scheduled_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'retry_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return ScheduledSend::findOrFail($id);
    }

    public function test_send_failure_writes_campaign_last_error_metadata(): void
    {
        $this->seedCampaign();
        $this->seedSubscriber(self::SUBSCRIBER_ID, 'sub1@synthetic.test');
        $send = $this->seedPendingSend(self::SEND_ID, self::SUBSCRIBER_ID);

        $message = 'Missing required parameters for [Route: newsletter.public.track-click] '
            . '[Missing parameters: url, token]';
        $this->pendingMail->sendException = new \Exception($message);

        (new SendEmailToSubscriber($send))->handle();

        // Per-send failure accounting still happened.
        $freshSend = $this->capsule->getConnection()
            ->table('newsletter_scheduled_sends')->find(self::SEND_ID);
        $this->assertSame('failed', $freshSend->status);
        $this->assertSame($message, $freshSend->error_message);
        $this->assertSame(1, (int) $freshSend->retry_count);

        // Campaign-level failure count incremented.
        $campaign = \App\Models\Newsletter\Campaign::findOrFail(self::CAMPAIGN_ID);
        $this->assertSame(1, $campaign->failed_count);

        // The real error is surfaced: metadata['last_error'] written and the
        // last_error accessor (which the UI reads) returns the message.
        $this->assertSame($message, $campaign->last_error);
        $this->assertSame($message, $campaign->metadata['last_error']['message']);
        $this->assertArrayHasKey('file', $campaign->metadata['last_error']);
        $this->assertArrayHasKey('line', $campaign->metadata['last_error']);
        $this->assertArrayHasKey('trace', $campaign->metadata['last_error']);
        $this->assertArrayHasKey('time', $campaign->metadata['last_error']);

        // Completion logic still ran after the metadata write: the only send
        // is failed, so the campaign closes out as 'sent' (not stuck
        // 'sending', and NOT 'failed' — recordError() was deliberately not
        // called).
        $this->assertSame('sent', $campaign->status);
    }

    public function test_send_success_leaves_no_last_error(): void
    {
        $this->seedCampaign();
        $this->seedSubscriber(self::SUBSCRIBER_ID, 'sub1@synthetic.test');
        $send = $this->seedPendingSend(self::SEND_ID, self::SUBSCRIBER_ID);

        (new SendEmailToSubscriber($send))->handle();

        $freshSend = $this->capsule->getConnection()
            ->table('newsletter_scheduled_sends')->find(self::SEND_ID);
        $this->assertSame('sent', $freshSend->status);
        $this->assertNotNull($freshSend->sent_at);
        $this->assertNull($freshSend->error_message);

        // 'sent' analytics event recorded.
        $events = $this->capsule->getConnection()->table('newsletter_analytics_events')->get();
        $this->assertCount(1, $events);
        $this->assertSame('sent', $events[0]->event_type);
        $this->assertSame(self::CAMPAIGN_ID, (int) $events[0]->campaign_id);

        $campaign = \App\Models\Newsletter\Campaign::findOrFail(self::CAMPAIGN_ID);
        $this->assertSame(1, $campaign->sent_count);
        $this->assertSame(0, $campaign->failed_count);
        $this->assertSame('sent', $campaign->status);
        $metadata = $campaign->metadata ?? [];
        $this->assertArrayNotHasKey('last_error', $metadata);
    }

    public function test_second_failure_overwrites_last_error_with_latest(): void
    {
        $this->seedCampaign();
        $this->seedSubscriber(self::SUBSCRIBER_ID, 'sub1@synthetic.test');
        $this->seedSubscriber(self::SUBSCRIBER2_ID, 'sub2@synthetic.test');
        $send1 = $this->seedPendingSend(self::SEND_ID, self::SUBSCRIBER_ID);
        $send2 = $this->seedPendingSend(self::SEND2_ID, self::SUBSCRIBER2_ID);

        // Documents overwrite semantics: last_error always holds the LATEST
        // failure, which is what the campaign UI surfaces.
        $this->pendingMail->sendException = new \Exception('first failure: connection timeout');
        (new SendEmailToSubscriber($send1))->handle();

        $this->pendingMail->sendException = new \Exception('second failure: missing url/token params');
        (new SendEmailToSubscriber($send2))->handle();

        $campaign = \App\Models\Newsletter\Campaign::findOrFail(self::CAMPAIGN_ID);
        $this->assertSame(2, $campaign->failed_count);
        $this->assertSame(
            'second failure: missing url/token params',
            $campaign->metadata['last_error']['message']
        );
        $this->assertSame(
            'second failure: missing url/token params',
            $campaign->last_error
        );
        // Both sends processed -> campaign closed out.
        $this->assertSame('sent', $campaign->status);
    }
}

/**
 * Stub for the 'mail.manager' service: Mail::mailer('campus_smtp') returns
 * the shared StubPendingMail regardless of mailer name.
 */
class StubMailManager
{
    public ?string $lastMailerName = null;

    public function __construct(private StubPendingMail $pendingMail) {}

    public function mailer($name = null): StubPendingMail
    {
        $this->lastMailerName = $name;
        return $this->pendingMail;
    }
}

/**
 * Stands in for Mailer/PendingMail: ->to() returns itself, ->send() throws
 * the configured exception (failure path) or records and returns null
 * (success path).
 */
class StubPendingMail
{
    public ?string $toAddress = null;
    public array $sent = [];
    public ?Throwable $sendException = null;

    public function to($address): self
    {
        $this->toAddress = $address;
        return $this;
    }

    public function send($mailable)
    {
        if ($this->sendException !== null) {
            throw $this->sendException;
        }
        $this->sent[] = $mailable;
        return null;
    }
}

/**
 * Records every log call so tests can assert on (or ignore) them; the job
 * calls info/warning/error unconditionally so the 'log' binding must exist.
 */
class StubLog
{
    public array $records = [];

    public function __call(string $method, array $args): void
    {
        $this->records[] = ['level' => $method, 'args' => $args];
    }
}
