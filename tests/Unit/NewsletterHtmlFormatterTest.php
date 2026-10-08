<?php

namespace Tests\Unit;

use App\Mail\NewsletterMail;
use App\Models\Newsletter\Campaign;
use App\Models\Newsletter\Subscriber;
use App\Services\NewsletterHtmlFormatter;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class NewsletterHtmlFormatterTest extends TestCase
{
    private ?Container $previousContainer = null;

    private function formatter(): NewsletterHtmlFormatter
    {
        return new NewsletterHtmlFormatter();
    }

    private function imgStyle(string $html): ?string
    {
        return preg_match('/<img[^>]*style="([^"]*)"/i', $html, $m) ? $m[1] : null;
    }

    public function test_float_left_class_becomes_inline_style_and_keeps_attributes(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<p><img src="https://synthetic.test/pic.jpg" width="200" class="float-left mr-4 mb-2" ' .
            'style="margin: 0 0.75em 0.75em 0">Lead text.</p></body></html>';

        $out = $this->formatter()->format($html);
        $style = $this->imgStyle($out);

        $this->assertNotNull($style);
        $this->assertStringContainsString('float: left', $style);
        $this->assertStringContainsString('margin-right: 1rem', $style);
        $this->assertStringContainsString('margin-bottom: 0.5rem', $style);
        // Authored inline style is appended last so its shorthand wins.
        $this->assertStringContainsString('margin: 0 0.75em 0.75em 0', $style);
        $this->assertMatchesRegularExpression('/<img[^>]*class="[^"]*float-left[^"]*"/', $out);
        $this->assertMatchesRegularExpression('/<img[^>]*width="200"/', $out);
        $this->assertStringContainsString('https://synthetic.test/pic.jpg', $out);
        $this->assertStringContainsString('Lead text.', $out);
    }

    public function test_other_position_presets_inline_their_styles(): void
    {
        $cases = [
            'float-right ml-4 mb-2' => ['float: right', 'margin-left: 1rem'],
            'mx-auto block' => ['margin-left: auto', 'margin-right: auto', 'display: block'],
            'w-full' => ['width: 100%'],
        ];
        foreach ($cases as $class => $expected) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
                '<p><img src="https://synthetic.test/p.png" class="' . $class . '"></p></body></html>';
            $style = $this->imgStyle($this->formatter()->format($html));
            $this->assertNotNull($style, "no style attr for class {$class}");
            foreach ($expected as $decl) {
                $this->assertStringContainsString($decl, $style, "{$decl} missing for {$class}");
            }
        }
    }

    public function test_authored_inline_float_overrides_class_default(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<p><img src="https://synthetic.test/p.png" class="float-left" style="float: right; margin: 0"></p>' .
            '</body></html>';
        $style = $this->imgStyle($this->formatter()->format($html));
        $this->assertNotNull($style);
        // Author style is appended after utility declarations -> wins.
        $this->assertStringContainsString('float: right', $style);
        $posLeft = strpos($style, 'float: left');
        $posRight = strrpos($style, 'float: right');
        if ($posLeft !== false) {
            $this->assertGreaterThan($posLeft, $posRight);
        }
        $this->assertStringContainsString('margin: 0', $style);
    }

    public function test_unrelated_elements_with_same_classes_are_untouched(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<div class="float-left"><p>Text</p></div></body></html>';
        $out = $this->formatter()->format($html);
        $this->assertMatchesRegularExpression('/<div class="float-left"[^>]*>/', $out);
        // The img-scoped rules must not leak float onto the div.
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*style="[^"]*float/', $out);
    }

    public function test_structure_marks_links_utf8_and_comments_survive(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<!-- note --><p><img src="https://synthetic.test/p.png" width="200" height="140" ' .
            'class="float-left mr-4 mb-2">José — café déjà vu.</p>' .
            '<p><strong>Bold</strong> and <em>ital</em> <a href="https://synthetic.test/x">link</a>.</p>' .
            '<p>Final para.</p></body></html>';
        $out = $this->formatter()->format($html);

        $this->assertStringContainsString('<!-- note -->', $out);
        $this->assertStringContainsString('José — café déjà vu.', $out);
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<em>', $out);
        $this->assertStringContainsString('href="https://synthetic.test/x"', $out);
        $this->assertMatchesRegularExpression('/<img[^>]*height="140"/', $out);
        $this->assertSame(3, substr_count($out, '<p'));
    }

    public function test_tokens_survive_formatter_unchanged(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<p>Hi {{ first_name }}, see {{unsubscribe_url}}</p></body></html>';
        $out = $this->formatter()->format($html);
        $this->assertStringContainsString('{{ first_name }}', $out);
        $this->assertStringContainsString('{{unsubscribe_url}}', $out);
    }

    public function test_empty_and_whitespace_input_unchanged(): void
    {
        $this->assertSame('', $this->formatter()->format(''));
        $this->assertSame("  \n\t ", $this->formatter()->format("  \n\t "));
    }

    public function test_idempotent(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<p><img src="https://synthetic.test/p.png" class="float-left mr-4 mb-2" width="200">x</p>' .
            '</body></html>';
        $once = $this->formatter()->format($html);
        $twice = $this->formatter()->format($once);
        // Second pass may normalize serialization but must not change the img's
        // effective inline style.
        $this->assertStringContainsString('float: left', (string) $this->imgStyle($twice));
        $this->assertMatchesRegularExpression('/<img[^>]*width="200"/', $twice);
        $this->assertSame($this->imgStyle($once), $this->imgStyle($twice));
    }

    // ------------------------------------------------------------------
    // Integration: NewsletterMail::content() wires the formatter after
    // tracking/personalization, using in-memory models only.
    // ------------------------------------------------------------------

    private function makeMail(bool $tracking): NewsletterMail
    {
        $campaign = new Campaign();
        $campaign->id = 238;
        $campaign->name = 'Synthetic Campaign';
        $campaign->subject = 'Synthetic Subject';
        $campaign->enable_tracking = $tracking;
        $campaign->html_content =
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' .
            '<p>Hi {{ first_name }}</p>' .
            '<p><img src="https://synthetic.test/face.jpg" width="200" class="float-left mr-4 mb-2" ' .
            'style="margin: 0 0.75em 0.75em 0">Bio text.</p>' .
            '<p><a href="https://synthetic.test/story">Read more</a></p>' .
            '</body></html>';

        $subscriber = new Subscriber();
        $subscriber->id = 5;
        $subscriber->email = 'ada@synthetic.test';
        $subscriber->first_name = 'Ada';
        $subscriber->last_name = 'Lovelace';
        $subscriber->unsubscribe_token = 'synthetic-token-123';

        return new NewsletterMail($campaign, $subscriber);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();

        $container = new Container();
        $container->instance('url', new class {
            public function route($name, $parameters = [], $absolute = true): string
            {
                $p = is_array($parameters)
                    ? implode('/', array_map(fn ($v) => (string) $v, $parameters))
                    : (string) $parameters;
                return 'https://stub.local/' . $name . '/' . $p;
            }
        });
        $container->instance('config', new Repository([
            'app' => ['key' => 'synthetic-test-key'],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_content_inlines_float_and_personalizes(): void
    {
        $content = $this->makeMail(false)->content();
        $html = $content->with['htmlContent'] ?? null;
        $this->assertIsString($html);
        $this->assertSame('emails.newsletter', $content->view);

        $style = $this->imgStyle($html);
        $this->assertNotNull($style);
        $this->assertStringContainsString('float: left', $style);

        $this->assertStringContainsString('Hi Ada', $html);
        $this->assertStringNotContainsString('{{ first_name }}', $html);
        // Tracking disabled: no pixel, no rewritten links.
        $this->assertStringNotContainsString('track-open', $html);
        $this->assertStringNotContainsString('track-click', $html);
        $this->assertStringContainsString('href="https://synthetic.test/story"', $html);
    }

    public function test_content_with_tracking_adds_pixel_and_rewrites_links(): void
    {
        $content = $this->makeMail(true)->content();
        $html = $content->with['htmlContent'] ?? '';
        $this->assertStringContainsString('track-open', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*display:\s*none/i', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*track-click[^"]*"/', $html);
        // Formatter still applied.
        $this->assertStringContainsString('float: left', (string) $this->imgStyle($html));
    }
}
