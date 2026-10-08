# Newsletter Editor - Source Import & Image Handling Fix

## Problem

The newsletter `EmailBuilder` "Source" import path (`parseHtmlIntoBlocks`) and
the `EmailEditor` TipTap setup mangled common newsletter HTML:

- **`<p>` containing an `<img>` + text** was classified as an `image` block.
  The text lived only in `block.content` (not `block.data`), so editing opened
  the *Upload & Crop Image* modal instead of the text editor, and any
  `updateBlockData` regeneration (`getBlockHtml`) dropped the text entirely.
- **`getBlockHtml('image')` hardcoded `width:100%`** and discarded authored
  `width`/`height`/`class`/`style` attributes.
- **Unwrap loops shredded layout**: a padded `<div style="padding:...">` with
  mixed content was unwrapped and split into separate blocks, losing the
  container's padding; a `<div><img/></div>` unwrapped to a bare `<img>`
  candidate which classified as a *text* block with empty `data.content`
  (image deleted on regeneration).
- **`ResizableImage` was the default block-level TipTap image**, so an `<img>`
  inside a paragraph split it into two paragraphs with a block image between.
- **Floated images collapsed their block container** (`.group` height = 0),
  so the mouse physically landed in the next block's region and *that*
  block's toolbar appeared on hover. This is the locally verified mechanism
  behind a misplaced per-block toolbar; whether it is the exact cause of the
  originally reported "toolbar stuck on first block" screenshot is
  unconfirmed (no production environment was accessed). Floated images could
  also overlap adjacent blocks in preview.

## Fix scope

- `resources/js/Components/Newsletter/EmailBuilder.vue`
  - `parseHtmlIntoBlocks`: helpers detect `<img>` (element itself included),
    visible text, image-bearing content, and explicit `.header-block`.
    Structural unwrap only descends into wrappers that carry no text or
    images at any depth (newsletter containers and table layout chains
    excepted); the secondary unwrap never splits paragraphs or content
    divs. A candidate is an `image` block only when it contains exactly one
    image and no other meaningful content, checked recursively (empty
    `div`/`p`/`span`/`a`/`br` wrappers around the img are transparent, so an
    `<hr>` or a second image keeps the element as text). Image `data`
    preserves `src/alt/width/height/class/style` (attribute preferred,
    inline dimension fallback) plus wrapper `padding` when present.
    Elements with images that are not pure image blocks always fall through
    to `text`, ahead of the heading/button/footer heuristics, so embedded
    images are never dropped. Text `data.content` keeps `outerHTML` for
    content elements (paragraph/heading/list) and `innerHTML` for layout
    containers, with inline `padding`/`margin`/`blockBackground`/`color`
    carried in `block.data` - i.e. the supported layout metadata is
    preserved; this is not generic arbitrary-HTML losslessness.
  - `getBlockHtml('image')` renders preserved attributes: numeric
    dimensions become `px` CSS *and* HTML width/height attrs, `%`/`auto`
    keep units, author `class` is emitted, author inline `style` is
    appended after the default sizing rules so author CSS wins, and all
    attribute values are HTML-escaped.
  - `getBlockHtml` text/image wrappers and the canvas `v-html` render div
    use `display: flow-root` so floated images cannot collapse their
    container or overlap neighbours. Toolbar positioning/event logic is
    unchanged.
  - `addMarginResets` no longer rewrites margins on top-level `<img>`
    nodes.
  - `saveTextChanges` preserves `block.data.padding` instead of resetting
    to `'15px 35px'`.
- `resources/js/Components/WYSIWYG/EmailEditor.vue`
  - `ResizableImage.configure({ inline: true })` keeps an `<img>` inside
    its paragraph; existing width/height/class/style attribute support is
    unchanged.

## Test

```
npm run test:newsletter-editor
```

`scripts/newsletter-editor-regression.mjs` builds the real
`EmailBuilder`/`EmailEditor` into an IIFE bundle via the Vite build API
(`configFile:false`, isolated `envDir`, no Laravel plugin), mounts them in
Puppeteer (cached Chrome for Testing or system Chrome), intercepts **all**
network requests (the remote image URL is fulfilled with a synthetic PNG,
everything else is aborted) and asserts the regression cases end-to-end
through the real UI (Source -> Apply Changes -> canvas Edit button ->
toolbar formatting -> Save -> reopen), including toolbar hover geometry on
both imported and regenerated content, and preview rendering.

Evidence (per-case JSON, screenshots, console/request logs, assertions) is
written to a unique directory per run under
`$TMPDIR/newsletter-editor-regression-*/evidence/` - prior runs are kept.
Exit code is non-zero if any assertion fails.

## Sent-email image alignment (follow-up fix)

Separate defect found in the *sent* email path: the editor writes
`class="float-left mr-4 mb-2"` on images, which renders correctly in the
editor/preview because the app ships Tailwind (`.float-left{float:left}`).
`NewsletterMail` however emitted the raw `emails.newsletter` view with no CSS
inliner and no `.float-left` rule, so in the received email the image had
`float: none` and rendered as a baseline-aligned inline image - text started
at the *bottom* edge of the image instead of wrapping at its top right.

Verified offline against the frozen campaign-238 snapshot (browser geometry):
raw saved HTML -> img `float:none`, first text line ~296px below the image
top; same HTML with the utility rule present or inlined -> text wraps at the
image top right.

Fixes:

- `app/Services/NewsletterHtmlFormatter.php` - `format()` runs the send-path
  HTML through the already-installed `TijsVerkoyen\CssToInlineStyles` with an
  img-scoped utility stylesheet (`img.float-left`, `img.float-right`,
  `img.float-none`, `img.mr-4`, `img.ml-4`, `img.mb-2`, `img.mx-auto`,
  `img.block`, `img.w-full`). Authored inline styles are appended last so
  they still win. Empty input is returned unchanged.
- `app/Mail/NewsletterMail.php` - `content()` formats
  `addTrackingAndPersonalization(...)` output, so previously saved campaigns
  are corrected on future sends with no DB change/resave.
- `resources/js/Components/WYSIWYG/EmailEditor.vue` - `insertImage` now emits
  matching inline `style` for each position preset (left/right/center/
  full-width) so newly inserted images carry their layout in the HTML itself.

Tests:

- `tests/Unit/NewsletterHtmlFormatterTest.php` - pure PHPUnit, no Laravel
  boot, synthetic HTML only; covers all presets, author-override precedence,
  non-img class scoping, structure/mark/UTF-8/comment/token survival, empty
  input, idempotency, plus `NewsletterMail::content()` integration with
  in-memory Campaign/Subscriber and stubbed `url`/`config` container
  bindings (tracking on and off). Run: `vendor/bin/phpunit
  tests/Unit/NewsletterHtmlFormatterTest.php`.
- `scripts/newsletter-editor-regression.mjs` gained insertImage preset
  assertions (class + inline style, width attrs, reopen survival,
  full-width emits `width: 100%` with no fixed attrs).
- Offline browser check on the frozen snapshot HTML (before/after
  formatter, no app CSS): artifact under
  `/tmp/newsletter-238-align-repro/evidence/`.

Client limitations: the browser evidence proves wrapped text for browsers;
classic Windows Outlook does not reliably support CSS floats (its Word
renderer) - guaranteed side-by-side layout there would need a table-based
approach, which is out of scope. Emails already sent cannot be repaired;
this only corrects future sends.

- Production inspection used an explicitly authorized read-only query for
  campaign 238's saved structure and HTML in database `ticket`. Credentials
  were not logged, `.env` was not changed, and no production records were
  modified. Subsequent reproduction used only that frozen local snapshot.

## Source-fix verification limitations

- **Offline source-editor tests**: no Laravel server or database. The structure
  serialization round-trip is tested frontend-only (JSON -> `modelValue` ->
  `applyStructure`); the server-side save/reopen path (DB persistence) is
  intentionally *skipped* - no local DB was confirmed for these tests.
- Email-client rendering (Outlook etc.) is out of scope; preview assertions
  cover browser rendering only.
- Header/footer/logo *regeneration* is outside this fix's scope: a parsed
  `.header-block` keeps its `header` classification, but the known
  pre-existing behavior of not repopulating `data.logo` (a regenerated
  header drops the logo image) is unchanged.
