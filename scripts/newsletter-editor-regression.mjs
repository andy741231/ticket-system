/**
 * Offline regression harness for the newsletter EmailBuilder source parser
 * and EmailEditor (TipTap) controls.
 *
 * - Builds an IIFE bundle in-memory via the Vite build API (configFile:false,
 *   no Laravel plugin, envDir pointed at an empty dir so no .env is read).
 * - EmailBuilder.vue and EmailEditor.vue are compiled through a test-only SFC
 *   transform (@vue/compiler-sfc, inlineTemplate:false) so their <script setup>
 *   bindings are reachable via component instance setupState.
 * - Runs in Puppeteer with request interception: the remote image URL is
 *   fulfilled with a synthetic PNG; every other network request is aborted.
 * - Emits assertions: any failure -> nonzero exit code.
 * - Evidence (JSON + screenshots + logs) is written to a unique dir:
 *   <os.tmpdir()>/newsletter-editor-regression-XXXX/evidence/
 *   (prior runs are never erased).
 *
 * Usage: node scripts/newsletter-editor-regression.mjs
 *        npm run test:newsletter-editor
 * No network access, no DB, no Laravel required.
 */
import { build } from 'vite';
import vuePlugin from '@vitejs/plugin-vue';
import { parse, compileScript, compileTemplate, compileStyle } from '@vue/compiler-sfc';
import puppeteer from 'puppeteer';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..');
const resourcesJs = path.join(repoRoot, 'resources/js');
const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'newsletter-editor-regression-'));
const evidenceDir = path.join(workDir, 'evidence');

const IMG_URL =
  'https://uhph.uh.edu/hub/storage/images/newsletters/campaign-238/dr-martinez.jpg';
const IMG = `<img src="${IMG_URL}" width="200" class="float-left mr-4 mb-2" style="margin: 0 0.75em 0.75em 0">`;
const LOGO_URL = 'https://uhph.uh.edu/hub/storage/images/newsletters/campaign-238/logo.png';
const LOGO_IMG = `<img src="${LOGO_URL}" alt="Logo" style="max-width:150px; height:auto; display:block; margin:0 auto 10px;">`;

// 1x1 transparent PNG used to fulfill the remote image without fetching it.
const SYNTHETIC_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64'
);

const CASES = [
  {
    id: 'case1-single-p-text',
    html: '<p>Synthetic introductory text and <strong>bold</strong> closing text.</p>',
  },
  {
    id: 'case2-p-with-img-and-text',
    html: `<p>${IMG} Alpha introduction. Beta conclusion.</p>`,
  },
  {
    id: 'case3-padded-div-img-p-plus-text-p',
    html: `<div style="padding: 15px 35px"><p>${IMG} Alpha introduction.</p><p>Beta conclusion.</p></div>`,
  },
  {
    id: 'case4a-div-img-only',
    html: `<div>${IMG}</div>`,
  },
  {
    id: 'case4b-bare-img',
    html: IMG,
  },
  {
    id: 'case4d-div-img-padded-wrapper',
    html: `<div style="padding: 10px 20px">${IMG}</div>`,
  },
  {
    id: 'case4c-multi-image',
    html: `<div><p>${IMG}${IMG} Two images in one paragraph.</p></div>`,
  },
  {
    id: 'case5-header-image-footer-siblings',
    html:
      `<div style="background:#c8102e"><h1>Synthetic newsletter</h1><p>October update</p></div>` +
      `<div>${IMG}</div>` +
      `<div><p>Copyright Synthetic. Unsubscribe</p></div>`,
  },
  {
    id: 'case6-newsletter-container-full',
    html:
      `<div class="newsletter-container">` +
      `<div class="header-block" style="background:#c8102e; color:#fff; padding:30px 12px; text-align:center;">` +
      `${LOGO_IMG}<h1>Synthetic newsletter</h1><p>October update</p></div>` +
      `<div style="padding: 15px 35px"><p>${IMG} Alpha introduction.</p><p>Beta conclusion.</p></div>` +
      `<div>${IMG}</div>` +
      `<div><p>Copyright Synthetic. Unsubscribe</p></div>` +
      `</div>`,
  },
  {
    id: 'case7-mixed-padded-div-h2-img-paragraphs',
    html:
      `<div style="padding: 20px 35px">` +
      `<h2>Section heading</h2>` +
      `<p>${IMG} Alpha introduction.</p>` +
      `<p>Beta conclusion.</p>` +
      `</div>`,
  },
  {
    // Wrapper whose children are all divs but which carries content at depth:
    // must stay one text block (padding kept), not be unwrapped/split.
    id: 'case8-nested-divs-img-text-padded',
    html:
      `<div style="padding: 24px">` +
      `<div><p>${IMG} Alpha.</p></div>` +
      `<div><p>Beta.</p></div>` +
      `</div>`,
  },
  {
    // Direct text next to a structural-looking child div: must stay whole.
    id: 'case8b-direct-text-beside-inner-div',
    html: `<div style="padding: 10px">Direct lead text.<div><p>Nested para.</p></div></div>`,
  },
  {
    // img + <hr> inside a shared wrapper: hr must not be silently dropped.
    id: 'case9-img-hr-sibling',
    html: `<div><span>${IMG}<hr></span></div>`,
  },
  {
    // Two images, no visible text, footer-ish keyword inside an alt: must
    // classify as text and keep both images.
    id: 'case10-two-imgs-copyright-alt',
    html: `<div>${IMG}<img src="${IMG_URL}" alt="Copyright logo"></div>`,
  },
  {
    id: 'case11-img-height-attr',
    html: `<div><img src="${IMG_URL}" width="200" height="140" class="float-left mr-4 mb-2" style="margin: 0 0.75em 0.75em 0"></div>`,
  },
  {
    id: 'case12-img-percent-width',
    html: `<div><img src="${IMG_URL}" width="60%"></div>`,
  },
  {
    id: 'case13-img-style-size-fallback',
    html: `<div><img src="${IMG_URL}" style="width: 150px; height: 80px"></div>`,
  },
  {
    // Quotes/injection-looking text inside attribute values must round-trip
    // through regeneration escaped, without producing extra attributes.
    id: 'case14-img-attr-escaping',
    html: `<div><img src="${IMG_URL}" alt='pic " onmouseover=x' class='float-left q"z'></div>`,
  },
];

// ---------------------------------------------------------------------------
// Test-only SFC transform: compile EmailBuilder.vue / EmailEditor.vue with
// inlineTemplate:false so <script setup> bindings land on instance.setupState.
// ---------------------------------------------------------------------------
const SFC_INTERNALS = [
  path.join(resourcesJs, 'Components/Newsletter/EmailBuilder.vue'),
  path.join(resourcesJs, 'Components/WYSIWYG/EmailEditor.vue'),
];
const VIRTUAL_PREFIX = '\0harness-sfc:';

function harnessSfcPlugin() {
  const norm = (p) => p.replace(/\\/g, '/');
  const targetBySpecifier = new Map();
  for (const file of SFC_INTERNALS) {
    targetBySpecifier.set('@/' + norm(path.relative(resourcesJs, file)), file);
    targetBySpecifier.set(norm(file), file);
  }
  let counter = 0;
  const virtualToFile = new Map();
  return {
    name: 'harness-sfc',
    enforce: 'pre',
    resolveId(source) {
      const file = targetBySpecifier.get(norm(source));
      if (!file) return null;
      // Virtual id must NOT end in ".vue" or @vitejs/plugin-vue would pick it
      // up and try to re-parse the generated JS as an SFC.
      const vid = VIRTUAL_PREFIX + file.replace(/\.vue$/, '') + '.sfc.js';
      virtualToFile.set(vid, file);
      return vid;
    },
    load(id) {
      if (!id.startsWith(VIRTUAL_PREFIX)) return null;
      const filename = virtualToFile.get(id);
      const src = fs.readFileSync(filename, 'utf8');
      const { descriptor, errors } = parse(src, { filename });
      if (errors && errors.length) throw errors[0];
      const scopeId = 'harness' + counter++;
      const script = compileScript(descriptor, {
        id: scopeId,
        inlineTemplate: false,
      });
      // compileScript emits `export default { ... }`; rename to a binding so we
      // can attach the render function and scope id afterwards.
      let scriptCode = script.content;
      if (!/export default/.test(scriptCode)) {
        throw new Error('harness-sfc: expected default export in ' + filename);
      }
      scriptCode = scriptCode.replace(/export default/, 'const _sfc_main =');

      let tplCode = '';
      if (descriptor.template) {
        const tpl = compileTemplate({
          source: descriptor.template.content,
          filename,
          id: scopeId,
          compilerOptions: { bindingMetadata: script.bindings },
        });
        // tpl.code ends with `export function render(...)` -> make it local.
        tplCode = tpl.code.replace(/export function render/, 'function render');
      }

      let css = '';
      for (const style of descriptor.styles || []) {
        const compiled = compileStyle({
          source: style.content,
          filename,
          id: scopeId,
          scoped: !!style.scoped,
        });
        css += compiled.code + '\n';
      }
      const cssInject = css.trim()
        ? `\nif (typeof document !== 'undefined') { const __s = document.createElement('style'); __s.setAttribute('data-harness-sfc', ${JSON.stringify(
            path.basename(filename)
          )}); __s.textContent = ${JSON.stringify(css)}; document.head.appendChild(__s); }`
        : '';

      return [
        scriptCode,
        tplCode,
        `_sfc_main.render = typeof render === 'function' ? render : _sfc_main.render;`,
        `_sfc_main.__scopeId = ${JSON.stringify('data-v-' + scopeId)};`,
        `_sfc_main.__file = ${JSON.stringify(path.relative(repoRoot, filename))};`,
        `export default _sfc_main;`,
        cssInject,
      ].join('\n');
    },
  };
}

// ---------------------------------------------------------------------------
// Harness entry (written to a temp file, bundled as IIFE)
// ---------------------------------------------------------------------------
const ENTRY = `
import { createApp, h, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import EmailBuilder from '@/Components/Newsletter/EmailBuilder.vue';
import EmailEditor from '@/Components/WYSIWYG/EmailEditor.vue';

const builderRef = ref(null);
const editorRef = ref(null);
const editorModel = ref('');

const app = createApp({
  name: 'HarnessRoot',
  render() {
    return h('div', { id: 'harness-root' }, [
      h('div', { id: 'builder-mount' }, [
        h(EmailBuilder, {
          ref: builderRef,
          modelValue: '',
          initialHtml: '',
          campaignId: null,
          tempKey: 'harness-temp',
        }),
      ]),
      h('div', { id: 'editor-mount', style: 'margin-top: 48px; border-top: 2px dashed #94a3b8; padding: 16px;' }, [
        h('h2', { style: 'font-weight:700; margin-bottom: 8px;' }, 'Standalone EmailEditor'),
        h(EmailEditor, {
          ref: editorRef,
          modelValue: editorModel.value,
          'onUpdate:modelValue': (v) => { editorModel.value = v; },
        }),
      ]),
    ]);
  },
});
app.component('font-awesome-icon', FontAwesomeIcon);
window.__harness = { app, builderRef, editorRef, editorModel };
// Library handles for ad-hoc remount/persistence tests inside the page.
window.__harness.lib = { createApp, h, EmailBuilder, EmailEditor, FontAwesomeIcon };
app.mount('#app');
`;

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  fs.mkdirSync(evidenceDir, { recursive: true });
  fs.mkdirSync(path.join(workDir, 'empty-env'), { recursive: true });
  const evidence = (name, data) =>
    fs.writeFileSync(
      path.join(evidenceDir, name),
      typeof data === 'string' ? data : JSON.stringify(data, null, 2)
    );

  const assertions = [];
  const check = (name, cond, detail = '') =>
    assertions.push({ name, pass: !!cond, detail: String(detail).slice(0, 2000) });

  const environment = {
    node: process.version,
    platform: process.platform,
    rg: safeCmd('which rg'),
    workDir,
    versions: {},
    browsers: {},
  };
  for (const m of [
    'vite',
    '@vitejs/plugin-vue',
    '@vue/compiler-sfc',
    'vue',
    'puppeteer',
    'tailwindcss',
    '@tiptap/core',
  ]) {
    try {
      environment.versions[m] = JSON.parse(
        fs.readFileSync(path.join(repoRoot, 'node_modules', m, 'package.json'), 'utf8')
      ).version;
    } catch (e) {
      environment.versions[m] = 'MISSING';
    }
  }
  let executablePath;
  try {
    executablePath = puppeteer.executablePath();
    environment.browsers.puppeteerChrome = executablePath;
    environment.browsers.puppeteerChromeExists = fs.existsSync(executablePath);
  } catch (e) {
    environment.browsers.puppeteerChrome = 'unavailable: ' + e.message;
  }
  const systemChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  environment.browsers.systemChromeExists = fs.existsSync(systemChrome);
  if (!executablePath || !fs.existsSync(executablePath)) {
    executablePath = fs.existsSync(systemChrome) ? systemChrome : undefined;
  }
  environment.browsers.executablePathUsed = executablePath;
  evidence('environment.json', environment);

  // --- Tailwind CSS (compiled locally with the repo config) ----------------
  let tailwindCss = '';
  try {
    const twIn = path.join(workDir, 'tailwind-in.css');
    const twOut = path.join(workDir, 'tailwind-out.css');
    fs.writeFileSync(twIn, '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n');
    execFileSync(
      process.execPath,
      [path.join(repoRoot, 'node_modules/tailwindcss/lib/cli.js'),
       '-c', 'tailwind.config.js', '-i', twIn, '-o', twOut],
      { cwd: repoRoot, stdio: ['ignore', 'pipe', 'pipe'] }
    );
    tailwindCss = fs.readFileSync(twOut, 'utf8');
    environment.tailwindCssBytes = tailwindCss.length;
  } catch (e) {
    environment.tailwindError = String(e);
  }
  evidence('environment.json', environment);
  check('environment:tailwind-compiled', tailwindCss.length > 1000, environment.tailwindError || '');

  // --- Vite in-memory build -------------------------------------------------
  const entryPath = path.join(workDir, 'harness-entry.js');
  fs.writeFileSync(entryPath, ENTRY);
  // Bare imports (vue, tiptap, ...) resolve by walking up from the entry; link
  // the project's node_modules into the temp root.
  const nmLink = path.join(workDir, 'node_modules');
  if (!fs.existsSync(nmLink)) fs.symlinkSync(path.join(repoRoot, 'node_modules'), nmLink, 'junction');

  const result = await build({
    configFile: false,
    envDir: path.join(workDir, 'empty-env'),
    root: workDir,
    mode: 'development',
    logLevel: 'warn',
    define: { 'process.env.NODE_ENV': '"development"' },
    plugins: [harnessSfcPlugin(), vuePlugin()],
    resolve: {
      alias: { '@': resourcesJs },
      // entry lives in a temp dir outside the repo: resolve bare imports
      // against the project's node_modules.
      modules: [path.join(repoRoot, 'node_modules'), 'node_modules'],
      dedupe: ['vue'],
    },
    build: {
      write: false,
      minify: false,
      sourcemap: false,
      cssCodeSplit: false,
      rollupOptions: {
        input: entryPath,
        output: { format: 'iife', name: 'NewsletterHarness', inlineDynamicImports: true },
      },
    },
  });
  const rollupOut = Array.isArray(result) ? result[0] : result;
  const bundleJs = rollupOut.output
    .filter((o) => o.type === 'chunk')
    .map((o) => o.code)
    .join('\n');
  const bundleCss = rollupOut.output
    .filter((o) => o.type === 'asset')
    .map((o) => String(o.source))
    .join('\n');
  fs.writeFileSync(path.join(workDir, 'bundle.js'), bundleJs);
  fs.writeFileSync(path.join(workDir, 'bundle.css'), bundleCss);

  // --- Browser --------------------------------------------------------------
  const requests = [];
  const consoleLog = [];
  let browser;
  try {
    browser = await puppeteer.launch({
      headless: 'new',
      executablePath,
      args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1500, height: 1000 });

    page.on('console', (m) => consoleLog.push(`[${m.type()}] ${m.text()}`));
    page.on('pageerror', (e) => consoleLog.push(`[pageerror] ${e.message}`));
    await page.setRequestInterception(true);
    page.on('request', (req) => {
      const url = req.url();
      requests.push({ url, method: req.method(), type: req.resourceType() });
      if (url === IMG_URL || url === LOGO_URL || url.startsWith(IMG_URL + '?')) {
        req.respond({ status: 200, contentType: 'image/png', body: SYNTHETIC_PNG });
        return;
      }
      if (/^(data:|blob:|about:|chrome:|chrome-extension:|synthetic:)/.test(url)) {
        req.continue();
        return;
      }
      req.abort();
    });

    await page.setContent(
      '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="csrf-token" content="harness"></head>' +
        '<body><div id="app"></div></body></html>'
    );
    if (tailwindCss) await page.addStyleTag({ content: tailwindCss });
    if (bundleCss) await page.addStyleTag({ content: bundleCss });
    // Stub Laravel helpers before the bundle executes.
    await page.evaluate(() => {
      window.route = (name) => '/stub-route/' + String(name);
      window.asset = (p) => '/stub-asset/' + String(p);
    });
    await page.addScriptTag({ content: bundleJs });
    await page.waitForFunction(() => window.__harness && window.__harness.builderRef?.value, {
      timeout: 20000,
    });
    await new Promise((r) => setTimeout(r, 400));

    // Sanity: confirm setupState bindings are reachable.
    const internals = await page.evaluate(() => {
      const inst = window.__harness.builderRef?.value?.$;
      const keys = inst && inst.setupState ? Object.keys(inst.setupState) : [];
      return { keyCount: keys.length, sample: keys.slice(0, 80) };
    });
    evidence('internals.json', internals);
    check('harness:setupState-accessible', internals.keyCount > 0, JSON.stringify(internals));

    // Helpers evaluated inside the page.
    await page.evaluate(() => {
      window.__S = () => window.__harness.builderRef.value.$.setupState;
      window.__blocks = () =>
        window.__S().emailBlocks.map((b) => ({
          id: b.id,
          type: b.type,
          data: JSON.parse(JSON.stringify(b.data || {})),
          content: b.content,
        }));
      window.__modalFlags = () => {
        const s = window.__S();
        const flags = {};
        for (const k of [
          'showTextEditor',
          'showImageUpload',
          'showHeaderEditor',
          'showFooterEditor',
          'showColumnEditor',
          'showTableListEditor',
          'showButtonEditor',
          'showSourceEditor',
        ]) {
          try {
            flags[k] = !!s[k];
          } catch (e) {
            flags[k] = 'err';
          }
        }
        return flags;
      };
      window.__closeModals = () => {
        const s = window.__S();
        try { s.showTextEditor = false; } catch (e) {}
        try { s.showImageUpload = false; } catch (e) {}
        try { s.showHeaderEditor = false; } catch (e) {}
        try { s.showFooterEditor = false; } catch (e) {}
        try { s.showColumnEditor = false; } catch (e) {}
        try { s.showTableListEditor = false; } catch (e) {}
        try { s.showButtonEditor = false; } catch (e) {}
        try { s.showSourceEditor = false; } catch (e) {}
        try { s.editingBlock = null; } catch (e) {}
      };
      // Find the EmailEditor component instance inside the text-edit modal.
      window.__modalEditor = () => {
        const root = window.__harness.builderRef.value.$;
        const stack = [root.subTree];
        while (stack.length) {
          const v = stack.pop();
          if (!v) continue;
          if (v.component) {
            const s = v.component.setupState;
            if (s && s.editor) return v.component;
            stack.push(v.component.subTree);
          }
          if (Array.isArray(v.children)) stack.push(...v.children);
          if (v.dynamicChildren) stack.push(...v.dynamicChildren);
        }
        return null;
      };
    });

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    async function applySourceViaUI(html) {
      // Click the real "Source" button.
      await page.evaluate(() => {
        const btn = [...document.querySelectorAll('button')].find(
          (b) => b.textContent.trim() === 'Source'
        );
        if (!btn) throw new Error('Source button not found');
        btn.click();
      });
      await page.waitForSelector('textarea', { timeout: 5000 });
      await page.evaluate((h) => {
        const ta = document.querySelector('textarea');
        ta.value = h;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      }, html);
      await page.evaluate(() => {
        const btn = [...document.querySelectorAll('button')].find(
          (b) => b.textContent.trim() === 'Apply Changes'
        );
        if (!btn) throw new Error('Apply Changes button not found');
        btn.click();
      });
      await sleep(150);
    }

    const caseResults = {};
    for (let i = 0; i < CASES.length; i++) {
      const c = CASES[i];
      await applySourceViaUI(c.html);
      const blocks = await page.evaluate(() => window.__blocks());
      const finalHtml = await page.evaluate(() => window.__S().finalHtmlContent);
      const out = { case: c.id, inputHtml: c.html, blocks, finalHtml };

      // For each block: which modal does editBlock open? (actual modal title)
      out.editModalPerBlock = [];
      for (const b of blocks) {
        const info = await page.evaluate(async (id) => {
          const s = window.__S();
          window.__closeModals();
          s.editBlock(id);
          await new Promise((r) => setTimeout(r, 120)); // let Vue flush modal DOM
          const flags = window.__modalFlags();
          const modalTitle = [...document.querySelectorAll('h3')]
            .map((h) => h.textContent.trim())
            .filter(Boolean)
            .join(' | ');
          const prosemirrorHtml = flags.showTextEditor
            ? document.querySelector('.ProseMirror')?.innerHTML ?? null
            : null;
          const blk = s.emailBlocks.find((x) => x.id === id);
          return {
            id,
            type: blk.type,
            flags,
            modalTitle,
            textModalContent: flags.showTextEditor ? s.textModalContent : null,
            prosemirrorHtml,
          };
        }, b.id);
        out.editModalPerBlock.push(info);
        await page.evaluate(() => window.__closeModals());
        await sleep(80);
      }

      // Regeneration check: run a no-op updateBlockData and capture
      // block.content before/after (exercises getBlockHtml for every type).
      out.regenCheck = [];
      for (const b of blocks) {
        const r = await page.evaluate((id) => {
          const s = window.__S();
          const blk = s.emailBlocks.find((x) => x.id === id);
          const before = blk.content;
          s.updateBlockData(id, {});
          return { id, type: blk.type, before, after: blk.content };
        }, b.id);
        out.regenCheck.push(r);
      }

      await page.screenshot({ path: path.join(evidenceDir, `${i + 1}-${c.id}.png`) });
      evidence(`${i + 1}-${c.id}.json`, out);
      caseResults[c.id] = out;
    }

    // =========================================================================
    // ASSERTIONS
    // =========================================================================
    const imgTag = (h) => (h.match(/<img[^>]*>/) || [])[0] || '';

    // --- case1: plain paragraph keeps <p> and inline formatting -------------
    {
      const r = caseResults['case1-single-p-text'];
      const b = r.blocks[0];
      check('case1: single text block', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case1: data.content preserves <p>', /<p[\s>]/.test(b.data.content), b.data.content);
      check('case1: data.content preserves <strong>', b.data.content.includes('<strong>'), b.data.content);
      check('case1: regen keeps <p>', /<p[\s>]/.test(r.regenCheck[0].after), r.regenCheck[0].after);
      check('case1: Edit opens Edit Content modal', r.editModalPerBlock[0].modalTitle === 'Edit Content', r.editModalPerBlock[0].modalTitle);
    }

    // --- case2: image + text paragraph stays ONE text block -----------------
    {
      const r = caseResults['case2-p-with-img-and-text'];
      const b = r.blocks[0];
      check('case2: single text block (img+text not split)', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case2: data.content keeps <p> wrapper', /<p[\s>]/.test(b.data.content), b.data.content);
      check('case2: data.content keeps img', b.data.content.includes('<img'), b.data.content);
      check('case2: img width attr preserved', /width="200"/.test(b.data.content), b.data.content);
      check('case2: img class preserved', b.data.content.includes('float-left mr-4 mb-2'), b.data.content);
      check('case2: img style preserved', /margin:\s*0(px)?\s+0\.75em/.test(b.data.content), b.data.content);
      check('case2: both text strings present', b.data.content.includes('Alpha introduction') && b.data.content.includes('Beta conclusion'), b.data.content);
      check('case2: Edit opens Edit Content modal', r.editModalPerBlock[0].modalTitle === 'Edit Content', r.editModalPerBlock[0].modalTitle);
      const pm = r.editModalPerBlock[0].prosemirrorHtml || '';
      check('case2: editor keeps img INSIDE paragraph', /<p[^>]*>\s*<img/.test(pm) || /<p[^>]*>[\s\S]*?<img/.test(pm), pm);
      check('case2: editor paragraph contains text', pm.includes('Alpha introduction'), pm);
    }

    // --- case3: padded mixed div stays ONE text block, padding captured -----
    {
      const r = caseResults['case3-padded-div-img-p-plus-text-p'];
      const b = r.blocks[0];
      check('case3: single text block', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case3: two <p> retained in data.content', (b.data.content.match(/<p[\s>]/g) || []).length === 2, b.data.content);
      check('case3: img preserved in data.content', b.data.content.includes('<img'), b.data.content);
      check('case3: padding captured from source div', b.data.padding === '15px 35px', b.data.padding);
      const regen = r.regenCheck[0].after;
      check('case3: regen keeps both paragraphs', (regen.match(/<p[\s>]/g) || []).length === 2, regen);
      check('case3: regen keeps 15px 35px padding', regen.includes('padding: 15px 35px'), regen);
      check('case3: regen keeps img attrs', /width="200"/.test(regen) && regen.includes('float-left'), regen);
      check('case3: regen outer div is flow-root', regen.includes('display: flow-root'), regen);
    }

    // --- case4a/4b: image-only wrappers -> image block, attrs preserved -----
    for (const id of ['case4a-div-img-only', 'case4b-bare-img']) {
      const r = caseResults[id];
      const b = r.blocks[0];
      check(`${id}: single image block`, r.blocks.length === 1 && b.type === 'image', JSON.stringify(r.blocks.map((x) => x.type)));
      check(`${id}: src preserved`, b.data.src === IMG_URL, b.data.src);
      check(`${id}: width attr preserved`, b.data.width === '200', JSON.stringify(b.data));
      check(`${id}: class preserved`, b.data.class === 'float-left mr-4 mb-2', b.data.class);
      check(`${id}: style preserved`, /margin/.test(b.data.style || ''), b.data.style);
      const after = r.regenCheck[0].after;
      check(`${id}: regen emits width="200" attr`, /width="200"/.test(after), after);
      check(`${id}: regen emits width 200px css`, /width:\s*200px/.test(after), after);
      check(`${id}: regen keeps class`, after.includes('float-left mr-4 mb-2'), after);
      check(`${id}: regen keeps authored margin`, /margin:\s*0(px)?\s+0\.75em/.test(after), after);
      check(`${id}: regen outer div is flow-root`, after.includes('display: flow-root'), after);
      check(`${id}: Edit opens Upload & Crop Image modal`, r.editModalPerBlock[0].modalTitle === 'Upload & Crop Image', r.editModalPerBlock[0].modalTitle);
    }

    // --- case4d: wrapped image-only keeps authored wrapper padding ---------
    {
      const r = caseResults['case4d-div-img-padded-wrapper'];
      const b = r.blocks[0];
      check('case4d: single image block', r.blocks.length === 1 && b.type === 'image', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case4d: wrapper padding captured', b.data.padding === '10px 20px', JSON.stringify(b.data));
      check('case4d: regen keeps wrapper padding', r.regenCheck[0].after.includes('padding: 10px 20px'), r.regenCheck[0].after);
    }

    // --- case4c: multiple images -> editable text, no images dropped --------
    {
      const r = caseResults['case4c-multi-image'];
      const b = r.blocks[0];
      check('case4c: text block (multi-image not image block)', b.type === 'text', b.type);
      check('case4c: both images preserved', (b.data.content.match(/<img/g) || []).length === 2, b.data.content);
      check('case4c: text preserved', b.data.content.includes('Two images in one paragraph.'), b.data.content);
    }

    // --- case5: legacy header / image / footer classification ---------------
    {
      const r = caseResults['case5-header-image-footer-siblings'];
      check('case5: types header,image,footer', JSON.stringify(r.blocks.map((x) => x.type)) === '["header","image","footer"]', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case5: header title', r.blocks[0].data.title === 'Synthetic newsletter', JSON.stringify(r.blocks[0].data));
      check('case5: header modal title', r.editModalPerBlock[0].modalTitle === 'Edit Header', r.editModalPerBlock[0].modalTitle);
      check('case5: footer modal title', r.editModalPerBlock[2].modalTitle === 'Edit Footer', r.editModalPerBlock[2].modalTitle);
    }

    // --- case6: newsletter-container ---------------------------------------
    {
      const r = caseResults['case6-newsletter-container-full'];
      const types = r.blocks.map((x) => x.type);
      check('case6: types header,text,image,footer', JSON.stringify(types) === '["header","text","image","footer"]', JSON.stringify(types));
      check('case6: header-block w/ logo stays header', types[0] === 'header' && r.blocks[0].data.title === 'Synthetic newsletter', JSON.stringify(r.blocks[0].data));
      const mixed = r.blocks[1];
      check('case6: mixed padded div -> single text block', mixed.type === 'text', JSON.stringify(mixed.data));
      check('case6: mixed content keeps both paragraphs', (mixed.data.content.match(/<p[\s>]/g) || []).length === 2, mixed.data.content);
      check('case6: padding captured', mixed.data.padding === '15px 35px', mixed.data.padding);
    }

    // --- case7: mixed div w/ h2 -> one text block ---------------------------
    {
      const r = caseResults['case7-mixed-padded-div-h2-img-paragraphs'];
      const b = r.blocks[0];
      check('case7: single text block', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case7: h2 kept in content', /<h2[\s>]/.test(b.data.content), b.data.content);
      check('case7: img kept in content', b.data.content.includes('<img'), b.data.content);
      check('case7: both paragraphs kept', (b.data.content.match(/<p[\s>]/g) || []).length === 2, b.data.content);
      check('case7: padding captured', b.data.padding === '20px 35px', b.data.padding);
    }

    // --- case8: nested wrapper divs carrying content stay whole ------------
    {
      const r = caseResults['case8-nested-divs-img-text-padded'];
      const b = r.blocks[0];
      check('case8: single text block (no split of nested content)', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case8: img + both texts preserved', b.data.content.includes('<img') && b.data.content.includes('Alpha.') && b.data.content.includes('Beta.'), b.data.content);
      check('case8: two <p> preserved', (b.data.content.match(/<p[\s>]/g) || []).length === 2, b.data.content);
      check('case8: outer padding captured', b.data.padding === '24px', b.data.padding);
      check('case8: regen keeps padding', r.regenCheck[0].after.includes('padding: 24px'), r.regenCheck[0].after);
      check('case8: regen keeps img + paragraphs', /<img/.test(r.regenCheck[0].after) && (r.regenCheck[0].after.match(/<p[\s>]/g) || []).length === 2, r.regenCheck[0].after);
    }
    {
      const r = caseResults['case8b-direct-text-beside-inner-div'];
      const b = r.blocks[0];
      check('case8b: single text block', r.blocks.length === 1 && b.type === 'text', JSON.stringify(r.blocks.map((x) => x.type)));
      check('case8b: direct text + nested paragraph kept', b.data.content.includes('Direct lead text.') && b.data.content.includes('Nested para.'), b.data.content);
      check('case8b: padding captured', b.data.padding === '10px', b.data.padding);
    }

    // --- case9: img + <hr> sibling -> text, nothing dropped -----------------
    {
      const r = caseResults['case9-img-hr-sibling'];
      const b = r.blocks[0];
      check('case9: text block (hr must not be dropped)', b.type === 'text', b.type);
      check('case9: img kept in content', b.data.content.includes('<img'), b.data.content);
      check('case9: hr kept in content', /<hr/.test(b.data.content), b.data.content);
      check('case9: regen keeps hr', /<hr/.test(r.regenCheck[0].after), r.regenCheck[0].after);
    }

    // --- case10: two imgs + footer-ish alt text -> text, not footer --------
    {
      const r = caseResults['case10-two-imgs-copyright-alt'];
      const b = r.blocks[0];
      check('case10: text block (not footer/image)', b.type === 'text', b.type);
      check('case10: both images preserved', (b.data.content.match(/<img/g) || []).length === 2, b.data.content);
      check('case10: regen keeps both images', (r.regenCheck[0].after.match(/<img/g) || []).length === 2, r.regenCheck[0].after);
    }

    // --- case11: explicit height attribute ----------------------------------
    {
      const r = caseResults['case11-img-height-attr'];
      const b = r.blocks[0];
      const after = r.regenCheck[0].after;
      check('case11: image block', b.type === 'image', b.type);
      check('case11: height attr parsed', b.data.height === '140', JSON.stringify(b.data));
      check('case11: width attr parsed', b.data.width === '200', JSON.stringify(b.data));
      check('case11: regen emits height="140" attr', /height="140"/.test(after), after);
      check('case11: regen emits height 140px css', /height:\s*140px/.test(after), after);
      check('case11: regen emits width="200" attr', /width="200"/.test(after), after);
    }

    // --- case12/13: percent width + inline-style dimension fallback ---------
    {
      const r = caseResults['case12-img-percent-width'];
      const b = r.blocks[0];
      check('case12: percent width parsed', b.data.width === '60%', JSON.stringify(b.data));
      check('case12: regen keeps 60% css width', /width:\s*60%/.test(r.regenCheck[0].after), r.regenCheck[0].after);
    }
    {
      const r = caseResults['case13-img-style-size-fallback'];
      const b = r.blocks[0];
      check('case13: style width fallback parsed', b.data.width === '150px', JSON.stringify(b.data));
      check('case13: style height fallback parsed', b.data.height === '80px', JSON.stringify(b.data));
      check('case13: regen keeps 150px width', /width:\s*150px/.test(r.regenCheck[0].after), r.regenCheck[0].after);
      check('case13: regen keeps 80px height', /height:\s*80px/.test(r.regenCheck[0].after), r.regenCheck[0].after);
    }

    // --- case14: attribute escaping on regeneration --------------------------
    {
      const r = caseResults['case14-img-attr-escaping'];
      const tag = imgTag(r.regenCheck[0].after);
      check('case14: quotes escaped in regen img tag', tag.includes('&quot;'), tag);
      const reparsed = await page.evaluate((h) => {
        const img = new DOMParser().parseFromString(h, 'text/html').querySelector('img');
        return img
          ? { alt: img.getAttribute('alt'), cls: img.getAttribute('class'), attrs: img.getAttributeNames() }
          : null;
      }, r.regenCheck[0].after);
      check('case14: alt round-trips exactly', reparsed?.alt === 'pic " onmouseover=x', JSON.stringify(reparsed));
      check('case14: class round-trips exactly', reparsed?.cls === 'float-left q"z', JSON.stringify(reparsed));
      check('case14: no injected attributes', !!reparsed && !reparsed.attrs.some((n) => !['src', 'alt', 'width', 'height', 'class', 'style'].includes(n)), JSON.stringify(reparsed));
    }

    // =========================================================================
    // Full UI edit flow: Source -> Apply -> Edit Content modal -> formatting
    // commands -> Save -> reopen -> integrity. Runs on case3 (padded mixed div).
    // =========================================================================
    const editFlow = { steps: [] };
    try {
      await applySourceViaUI(CASES.find((c) => c.id === 'case3-padded-div-img-p-plus-text-p').html);
      await sleep(200);
      editFlow.beforeBlocks = await page.evaluate(() => window.__blocks());

      // Open the Edit Content modal via the real canvas toolbar Edit button.
      const grpHandle = await page.evaluateHandle(
        () => document.querySelector('.email-canvas .group.relative')
      );
      const grp = grpHandle.asElement();
      await grp.hover();
      await sleep(250);
      const editBtn = await grp.$('button[title="Edit"]');
      check('editFlow: canvas Edit button present', !!editBtn, '');
      await editBtn.click();
      await sleep(300);
      const editOpened = await page.evaluate(() => ({
        title: [...document.querySelectorAll('h3')].map((h) => h.textContent.trim()).join('|'),
        flags: window.__modalFlags(),
      }));
      editFlow.editOpened = editOpened;
      check('editFlow: Edit Content modal opens via real Edit button', editOpened.flags.showTextEditor && editOpened.title === 'Edit Content', JSON.stringify(editOpened));

      // Select text with setTextSelection, then click the real modal toolbar
      // buttons (Bold/Italic/Align/lists) and the real Add Link dialog.
      const fmt = await page.evaluate(async () => {
        const inst = window.__modalEditor();
        if (!inst) return { error: 'no modal editor instance' };
        const ed = inst.setupState.editor;
        const editor = ed && ed.getHTML ? ed : ed?.value;
        const out = {};
        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
        const modal = [...document.querySelectorAll('.fixed')].find((d) =>
          d.querySelector('.ProseMirror')
        );
        const btn = (prefix) => modal && modal.querySelector(`button[title^="${prefix}"]`);
        const selText = (needle) => {
          let from = -1, to = -1;
          editor.state.doc.descendants((node, pos) => {
            if (node.isText && node.text.includes(needle)) {
              from = pos + node.text.indexOf(needle);
              to = from + needle.length;
            }
          });
          return { from, to };
        };
        const select = (needle) => {
          const r = selText(needle);
          if (r.from < 0) return false;
          return editor.chain().setTextSelection(r).run();
        };
        out.buttonsFound = {};
        out.initialHTML = editor.getHTML();

        if (select('Alpha')) { out.buttonsFound.bold = !!btn('Bold'); btn('Bold')?.click(); await sleep(80); }
        out.afterBold = editor.getHTML();
        if (select('Beta')) { out.buttonsFound.italic = !!btn('Italic'); btn('Italic')?.click(); await sleep(80); }
        out.afterItalic = editor.getHTML();
        if (select('Beta')) { btn('Align Left')?.click(); await sleep(40); }
        out.afterAlignLeft = editor.getHTML();
        out.alignLeftActive = editor.isActive({ textAlign: 'left' });
        if (select('Beta')) { btn('Align Center')?.click(); await sleep(40); }
        out.afterAlignCenter = editor.getHTML();
        out.alignCenterActive = editor.isActive({ textAlign: 'center' });
        if (select('Beta')) { btn('Align Right')?.click(); await sleep(80); }
        out.afterAlignRight = editor.getHTML();
        out.alignRightActive = editor.isActive({ textAlign: 'right' });

        // Link via the real "Add Link" toolbar button + dialog.
        if (select('conclusion')) {
          btn('Add Link')?.click();
          await sleep(200);
          const urlInput = document.querySelector('#link-url');
          out.buttonsFound.linkDialog = !!urlInput;
          if (urlInput) {
            urlInput.value = 'https://example.test/x';
            urlInput.dispatchEvent(new Event('input', { bubbles: true }));
            await sleep(80);
            const insert = [...document.querySelectorAll('button')].find(
              (b) => b.textContent.trim() === 'Insert Link'
            );
            insert?.click();
            await sleep(150);
          }
        }
        out.afterLink = editor.getHTML();

        // Lists via the real toolbar buttons.
        editor.commands.selectAll();
        btn('Bullet List')?.click();
        await sleep(80);
        out.afterBulletList = editor.getHTML();
        editor.commands.selectAll();
        btn('Numbered List')?.click();
        await sleep(80);
        out.afterOrderedList = editor.getHTML();
        return out;
      });
      editFlow.commands = fmt;
      check('editFlow: toolbar buttons found', !!(fmt.buttonsFound || {}).bold && !!fmt.buttonsFound.italic && !!fmt.buttonsFound.linkDialog, JSON.stringify(fmt.buttonsFound));
      check('editFlow: bold applied', /<strong>/.test(fmt.afterBold || ''), fmt.afterBold);
      check('editFlow: italic applied', /<em>/.test(fmt.afterItalic || ''), fmt.afterItalic);
      check('editFlow: left align applied', /text-align:\s*left/.test(fmt.afterAlignLeft || '') || !!fmt.alignLeftActive, `${fmt.afterAlignLeft} | active=${fmt.alignLeftActive}`);
      check('editFlow: center align applied', /text-align:\s*center/.test(fmt.afterAlignCenter || '') || !!fmt.alignCenterActive, `${fmt.afterAlignCenter} | active=${fmt.alignCenterActive}`);
      check('editFlow: right align applied', /text-align:\s*right/.test(fmt.afterAlignRight || '') || !!fmt.alignRightActive, `${fmt.afterAlignRight} | active=${fmt.alignRightActive}`);
      check('editFlow: link applied via dialog', /<a [^>]*href="https:\/\/example\.test\/x"/.test(fmt.afterLink || ''), fmt.afterLink);
      check('editFlow: bullet list applied', /<ul[^>]*>/.test(fmt.afterBulletList || ''), fmt.afterBulletList);
      check('editFlow: ordered list applied', /<ol[^>]*>/.test(fmt.afterOrderedList || ''), fmt.afterOrderedList);

      // The list toggles replaced the paragraphs; restore the formatted
      // content captured right after the link step, then click the real Save.
      await page.evaluate((html) => {
        const inst = window.__modalEditor();
        const ed = inst.setupState.editor;
        const editor = ed && ed.getHTML ? ed : ed?.value;
        editor.commands.setContent(html);
      }, fmt.afterLink);
      await page.evaluate(() => {
        const btns = [...document.querySelectorAll('button')];
        const save = btns.find((b) => b.textContent.trim() === 'Save' && b.closest('.fixed'));
        if (!save) throw new Error('Save button not found');
        save.click();
      });
      await sleep(200);
      editFlow.afterSave = await page.evaluate(() => window.__blocks());
      const saved = editFlow.afterSave[0];
      const sc = saved?.data?.content || '';
      check('editFlow: after Save still one text block', editFlow.afterSave.length === 1 && saved.type === 'text', JSON.stringify(editFlow.afterSave.map((x) => x.type)));
      check('editFlow: padding preserved after Save', saved.data.padding === '15px 35px', saved.data.padding);
      check('editFlow: saved keeps img + 2 paragraphs', sc.includes('<img') && (sc.match(/<p[\s>]/g) || []).length === 2, sc);
      check('editFlow: saved img width/class/style attrs', /width="200"/.test(sc) && sc.includes('float-left') && /margin/.test(sc), sc);
      check('editFlow: saved keeps <strong>Alpha', /<strong[^>]*>\s*Alpha/.test(sc), sc);
      check('editFlow: saved keeps <em>Beta', /<em[^>]*>\s*Beta/.test(sc), sc);
      check('editFlow: saved keeps link', /href="https:\/\/example\.test\/x"/.test(sc), sc);
      check('editFlow: saved keeps right align', /text-align:\s*right/.test(sc), sc);
      check('editFlow: block content div is flow-root', saved.content.includes('display: flow-root'), saved.content);

      // Reopen the same block through the real canvas Edit button.
      const grp2Handle = await page.evaluateHandle(
        () => document.querySelector('.email-canvas .group.relative')
      );
      const grp2 = grp2Handle.asElement();
      await grp2.hover();
      await sleep(250);
      const editBtn2 = await grp2.$('button[title="Edit"]');
      await editBtn2.click();
      await sleep(300);
      const reopen = await page.evaluate(() => {
        const inst = window.__modalEditor();
        const ed = inst && inst.setupState.editor;
        const editor = ed && ed.getHTML ? ed : ed?.value;
        const pm = document.querySelector('.ProseMirror');
        return {
          title: [...document.querySelectorAll('h3')].map((h) => h.textContent.trim()).join('|'),
          editorHTML: editor ? editor.getHTML() : null,
          prosemirrorHtml: pm ? pm.innerHTML : null,
        };
      });
      editFlow.reopen = reopen;
      const rh = reopen.editorHTML || '';
      check('editFlow: reopen is Edit Content', reopen.title === 'Edit Content', reopen.title);
      check('editFlow: reopen keeps img inside paragraph', /<p[^>]*>[\s\S]*?<img/.test(rh), rh);
      check('editFlow: reopen keeps both paragraphs', (rh.match(/<p[\s>]/g) || []).length === 2, rh);
      check('editFlow: reopen img attrs intact', /width="200"/.test(rh) && rh.includes('float-left') && /margin/.test(rh), rh);
      check('editFlow: reopen keeps <strong>Alpha', /<strong[^>]*>\s*Alpha/.test(rh), rh);
      check('editFlow: reopen keeps <em>Beta', /<em[^>]*>\s*Beta/.test(rh), rh);
      check('editFlow: reopen keeps link', /href="https:\/\/example\.test\/x"/.test(rh), rh);
      check('editFlow: reopen keeps right align', /text-align:\s*right/.test(rh), rh);
      await page.evaluate(() => window.__closeModals());
    } catch (e) {
      editFlow.error = String(e);
      check('editFlow: completed without exception', false, String(e));
    }
    evidence('edit-flow.json', editFlow);

    // =========================================================================
    // Standalone EmailEditor: inline image topology + attrs round-trip
    // =========================================================================
    const editorChecks = await page.evaluate(async (IMG_HTML) => {
      const s = window.__harness.editorRef.value.$.setupState;
      const ed = s.editor && s.editor.getHTML ? s.editor : s.editor?.value;
      const out = {};
      if (!ed || !ed.commands) {
        out.error = 'editor instance not reachable';
        return out;
      }
      const docTop = () =>
        ed.state.doc.content.content.map((n) => ({
          type: n.type.name,
          attrs: n.attrs,
          text: n.textContent?.slice(0, 80),
          childTypes: n.content?.content?.map((c) => c.type.name),
        }));

      const input1 = `<p>${IMG_HTML} Alpha introduction. Beta conclusion.</p>`;
      ed.commands.setContent(input1);
      out.e1_input = input1;
      out.e1_docTop = docTop();
      out.e1_getHTML = ed.getHTML();

      // formatting round-trip
      const selText = (needle) => {
        let from = -1, to = -1;
        ed.state.doc.descendants((node, pos) => {
          if (node.isText && node.text.includes(needle)) {
            from = pos + node.text.indexOf(needle);
            to = from + needle.length;
          }
        });
        return { from, to };
      };
      let r = selText('Alpha');
      if (r.from >= 0) ed.chain().setTextSelection(r).toggleBold().run();
      out.afterBold = ed.getHTML();
      r = selText('Beta');
      if (r.from >= 0) ed.chain().setTextSelection(r).toggleItalic().run();
      out.afterItalic = ed.getHTML();
      const htmlBeforeReopen = ed.getHTML();
      ed.commands.setContent(htmlBeforeReopen); // simulate save -> reopen
      out.reopen_getHTML = ed.getHTML();
      out.reopen_docTop = docTop();
      out.reopen_imgTag = (out.reopen_getHTML.match(/<img[^>]*>/) || [])[0] || null;

      // e2: explicit height attribute fixture (parse -> getHTML -> reopen).
      const imgH = IMG_HTML.replace('width="200"', 'width="200" height="140"');
      ed.commands.setContent(`<p>${imgH} Sized text.</p>`);
      out.e2_getHTML = ed.getHTML();
      ed.commands.setContent(out.e2_getHTML); // simulate save -> reopen
      out.e2_reopen = ed.getHTML();
      return out;
    }, IMG);
    evidence('emaileditor-standalone.json', editorChecks);
    {
      const e = editorChecks;
      const top = e.e1_docTop || [];
      check('editor: single top-level paragraph (no split)', top.length === 1 && top[0].type === 'paragraph', JSON.stringify(top));
      check('editor: image inside paragraph', (top[0]?.childTypes || []).includes('image'), JSON.stringify(top));
      check('editor: getHTML keeps img inside <p>', /<p[^>]*>\s*<img/.test(e.e1_getHTML || ''), e.e1_getHTML);
      const tag = e.reopen_imgTag || '';
      check('editor: img width attr preserved', /width="200"/.test(tag), tag);
      check('editor: img class preserved', tag.includes('float-left mr-4 mb-2'), tag);
      check('editor: img style preserved', /margin/.test(tag), tag);
      check('editor: bold survives', /<strong>/.test(e.afterBold || ''), e.afterBold);
      check('editor: italic survives', /<em>/.test(e.afterItalic || ''), e.afterItalic);
      check('editor: reopen keeps marks', /<strong>/.test(e.reopen_getHTML || '') && /<em>/.test(e.reopen_getHTML || ''), e.reopen_getHTML);
      check('editor: height attr survives getHTML', /height="140"/.test(e.e2_getHTML || ''), e.e2_getHTML);
      check('editor: height attr survives reopen', /height="140"/.test(e.e2_reopen || ''), e.e2_reopen);
      check('editor: height-fixture img stays inside <p>', /<p[^>]*>\s*<img/.test(e.e2_reopen || ''), e.e2_reopen);
    }
    await page.screenshot({ path: path.join(evidenceDir, 'emaileditor-standalone.png') });

    // =========================================================================
    // insertImage presets: class + mirrored inline style so sent mail keeps
    // the layout without the app stylesheet.
    // =========================================================================
    const insertChecks = await page.evaluate(() => {
      const s = window.__harness.editorRef.value.$.setupState;
      const ed = s.editor && s.editor.getHTML ? s.editor : s.editor?.value;
      const imgTag = (h) => (h.match(/<img[^>]*>/) || [])[0] || '';
      const out = {};
      const insert = (position, preset) => {
        ed.commands.setContent('<p>Intro text.</p>');
        // setupState is proxyRefs: plain assignment writes through to the ref.
        s.selectedImagePosition = position;
        s.sizePreset = preset;
        s.insertImage('https://synthetic.test/face.png');
        return ed.getHTML();
      };
      out.left = insert('float-left', 'small');
      out.leftReopen = (ed.commands.setContent(out.left), ed.getHTML());
      out.right = insert('float-right', 'small');
      out.center = insert('center', 'medium');
      out.full = insert('full-width', 'large');
      out.none = insert('none', 'original');
      for (const k of Object.keys(out)) out[k] = imgTag(out[k]);
      return out;
    });
    evidence('insertimage-presets.json', insertChecks);
    {
      const ic = insertChecks;
      check('insert: float-left class kept', /class="float-left mr-4 mb-2"/.test(ic.left), ic.left);
      check('insert: float-left inline style', /style="[^"]*float:\s*left/.test(ic.left) && /margin-right:\s*1rem/.test(ic.left) && /margin-bottom:\s*0\.5rem/.test(ic.left), ic.left);
      check('insert: small preset width=200', /width="200"/.test(ic.left), ic.left);
      check('insert: float-left survives reopen', /float:\s*left/.test(ic.leftReopen) && /width="200"/.test(ic.leftReopen) && ic.leftReopen.includes('float-left'), ic.leftReopen);
      check('insert: float-right inline style', /float:\s*right/.test(ic.right) && /margin-left:\s*1rem/.test(ic.right) && /class="float-right ml-4 mb-2"/.test(ic.right), ic.right);
      check('insert: center inline style', /margin-left:\s*auto/.test(ic.center) && /display:\s*block/.test(ic.center) && /class="mx-auto block"/.test(ic.center), ic.center);
      check('insert: center medium width=400', /width="400"/.test(ic.center), ic.center);
      check('insert: full-width css only', /width:\s*100%/.test(ic.full) && /class="w-full"/.test(ic.full), ic.full);
      check('insert: full-width no fixed attrs', !/width="\d+"/.test(ic.full) && !/height="\d+"/.test(ic.full), ic.full);
      check('insert: none preset no style/float', !/float/.test(ic.none) && !/style=/.test(ic.none), ic.none);
    }

    // =========================================================================
    // Toolbar geometry on case6 (real Tailwind): each group >0 height and each
    // hover activates THAT block's toolbar at right-edge+8 / top. Runs on the
    // freshly imported canvas (flow-root on the v-html wrapper) and again on
    // regenerated content.
    // =========================================================================
    const toolbarEvidence = { passes: [] };
    const runToolbarPass = async (label) => {
      const groupCount = await page.evaluate(
        () => document.querySelectorAll('.email-canvas .group.relative').length
      );
      check(`toolbar(${label}): case6 produced 4 block groups`, groupCount === 4, String(groupCount));
      const pass = { label, groupCount, hovers: [] };
      for (let i = 0; i < groupCount; i++) {
        const handle = await page.evaluateHandle(
          (i) => document.querySelectorAll('.email-canvas .group.relative')[i],
          i
        );
        const el = handle.asElement();
        await el.evaluate((n) => n.scrollIntoView({ block: 'center' }));
        await sleep(120);
        const box = await el.boxModel();
        if (box) await page.mouse.move(box.content[0].x + 20, box.content[0].y + 10);
        await sleep(300);
        const measure = await page.evaluate(() => {
          const groups = [...document.querySelectorAll('.email-canvas .group.relative')];
          return groups.map((g, gi) => {
            const tb = [...g.querySelectorAll('div')].find((d) =>
              d.querySelector('button[title="Edit"]')
            );
            if (!tb) return { gi, toolbar: null };
            const cs = getComputedStyle(tb);
            const r = tb.getBoundingClientRect();
            const gr = g.getBoundingClientRect();
            return {
              gi,
              groupRect: { x: gr.x, y: gr.y, w: gr.width, h: gr.height },
              toolbarRect: { x: r.x, y: r.y, w: r.width, h: r.height },
              opacity: cs.opacity,
              pointerEvents: cs.pointerEvents,
            };
          });
        });
        pass.hovers.push({ hoveredIndex: i, toolbars: measure });
        const mine = measure[i];
        check(`toolbar(${label}): group ${i} has positive height`, mine.groupRect.h > 0, JSON.stringify(measure.map((m) => m.groupRect.h)));
        check(`toolbar(${label}): hover ${i} shows its own toolbar`, mine.opacity === '1' && mine.pointerEvents === 'auto', JSON.stringify(measure.map((m) => m.opacity)));
        const others = measure.filter((m, gi) => gi !== i && m.opacity === '1');
        check(`toolbar(${label}): hover ${i} shows no other toolbar`, others.length === 0, JSON.stringify(others));
        check(`toolbar(${label}): toolbar ${i} x == group right + 8`, Math.abs(mine.toolbarRect.x - (mine.groupRect.x + mine.groupRect.w + 8)) < 2, JSON.stringify(mine.toolbarRect));
        check(`toolbar(${label}): toolbar ${i} y == group top`, Math.abs(mine.toolbarRect.y - mine.groupRect.y) < 2, JSON.stringify(mine.toolbarRect));
        await page.screenshot({ path: path.join(evidenceDir, `toolbar-${label}-block-${i}.png`) });
        handle.dispose?.();
      }
      toolbarEvidence.passes.push(pass);
    };
    try {
      const c6 = CASES.find((c) => c.id === 'case6-newsletter-container-full');
      await applySourceViaUI(c6.html);
      await sleep(200);
      await runToolbarPass('imported');
      // Regenerate contents once so every block has flow-root wrappers.
      await page.evaluate(() => {
        const s = window.__S();
        for (const b of s.emailBlocks) s.updateBlockData(b.id, {});
      });
      await sleep(300);
      await runToolbarPass('regenerated');
    } catch (e) {
      toolbarEvidence.error = String(e);
      check('toolbar: completed without exception', false, String(e));
    }
    evidence('toolbar-hover.json', toolbarEvidence);

    // =========================================================================
    // Preview: floated image is contained (no overlap), attrs intact.
    // =========================================================================
    const previewCheck = {};
    try {
      // blocks currently hold the regenerated case6 content
      await page.evaluate(async () => {
        const s = window.__S();
        s.openPreview ? s.openPreview() : (s.showPreview = true);
        await new Promise((r) => setTimeout(r, 150));
      });
      await sleep(200);
      const pv = await page.evaluate(() => {
        const root = document.querySelector('.email-preview');
        if (!root) return { error: 'no .email-preview' };
        const img = root.querySelector('img');
        const ps = root.querySelectorAll('p').length;
        const blocks = [...root.children].map((c) => {
          const r = c.getBoundingClientRect();
          return { tag: c.tagName, top: r.top, bottom: r.bottom, h: r.height };
        });
        let imgInfo = null;
        if (img) {
          const r = img.getBoundingClientRect();
          const cs = getComputedStyle(img);
          const parent = img.closest('div').getBoundingClientRect();
          imgInfo = {
            w: r.width, h: r.height,
            top: r.top, bottom: r.bottom,
            float: cs.float,
            marginRight: cs.marginRight,
            cls: img.getAttribute('class'),
            styleAttr: img.getAttribute('style'),
            widthAttr: img.getAttribute('width'),
            parentBottom: parent.bottom,
          };
        }
        return { imgInfo, paragraphCount: ps, blocks };
      });
      Object.assign(previewCheck, pv);
      check('preview: img rendered', !!pv.imgInfo, JSON.stringify(pv));
      if (pv.imgInfo) {
        check('preview: img width == 200px', Math.abs(pv.imgInfo.w - 200) < 1, JSON.stringify(pv.imgInfo));
        check('preview: computed float is left', pv.imgInfo.float === 'left', JSON.stringify(pv.imgInfo));
        check('preview: img style attr keeps margin', /margin/.test(pv.imgInfo.styleAttr || ''), JSON.stringify(pv.imgInfo));
        check('preview: img margin-right applied', parseFloat(pv.imgInfo.marginRight) > 0, JSON.stringify(pv.imgInfo));
        check('preview: img contained by parent (no overlap)', pv.imgInfo.bottom <= pv.imgInfo.parentBottom + 2, JSON.stringify(pv.imgInfo));
        check('preview: img has positive height (taller than text line)', pv.imgInfo.h > 30, JSON.stringify(pv.imgInfo));
      }
      check('preview: paragraphs present', (pv.paragraphCount || 0) >= 2, String(pv.paragraphCount));
      const overlaps = [];
      for (let i = 1; i < (pv.blocks || []).length; i++) {
        if (pv.blocks[i].top < pv.blocks[i - 1].bottom - 1) overlaps.push(i);
      }
      check('preview: no adjacent block overlap', overlaps.length === 0, JSON.stringify(pv.blocks));
      await page.screenshot({ path: path.join(evidenceDir, 'preview.png') });
      await page.evaluate(() => {
        const s = window.__S();
        try { s.showPreview = false; } catch (e) {}
      });
    } catch (e) {
      previewCheck.error = String(e);
      check('preview: completed without exception', false, String(e));
    }
    evidence('preview.json', previewCheck);

    // =========================================================================
    // Persistence (frontend only): serialize structure -> remount -> compare.
    // No DB involved; Laravel/PDO save path is NOT verified here.
    // =========================================================================
    const persistenceCheck = {};
    try {
      const p = await page.evaluate(async () => {
        const s = window.__S();
        const structJson = JSON.stringify(s.emailStructure);
        const origTypes = s.emailBlocks.map((b) => b.type);
        const origTextContent = s.emailBlocks.map((b) => (b.type === 'text' ? b.data?.content : null));
        const { createApp, h, EmailBuilder, FontAwesomeIcon } = window.__harness.lib;
        const host = document.createElement('div');
        host.id = 'app-remount';
        document.body.appendChild(host);
        let childRef = null;
        const app2 = createApp({
          render() {
            return h(EmailBuilder, { ref: (r) => (childRef = r), modelValue: structJson });
          },
        });
        app2.component('font-awesome-icon', FontAwesomeIcon);
        app2.mount(host);
        await new Promise((r) => setTimeout(r, 250));
        const s2 = childRef.$.setupState;
        const newTypes = s2.emailBlocks.map((b) => b.type);
        const newTextContent = s2.emailBlocks.map((b) => (b.type === 'text' ? b.data?.content : null));
        app2.unmount();
        host.remove();
        return { origTypes, newTypes, origTextContent, newTextContent };
      });
      Object.assign(persistenceCheck, p);
      check('persist: block types identical after remount', JSON.stringify(p.origTypes) === JSON.stringify(p.newTypes), JSON.stringify(p));
      check('persist: text data.content identical after remount', JSON.stringify(p.origTextContent) === JSON.stringify(p.newTextContent), JSON.stringify(p));
    } catch (e) {
      persistenceCheck.error = String(e);
      check('persist: completed without exception', false, String(e));
    }
    evidence('persistence.json', persistenceCheck);

    // --- Logs -----------------------------------------------------------------
    evidence('console.log', consoleLog.join('\n'));
    evidence('requests.json', requests);
    const summary = Object.values(caseResults).map((c) => ({
      case: c.case,
      blockTypes: c.blocks.map((b) => b.type),
      editModals: c.editModalPerBlock.map((m) => ({ type: m.type, modalTitle: m.modalTitle })),
    }));
    evidence('summary.json', summary);
  } finally {
    if (browser) await browser.close();
  }

  evidence('assertions.json', assertions);
  const failed = assertions.filter((a) => !a.pass);
  console.log('Evidence at: ' + evidenceDir);
  console.log(`Assertions: ${assertions.length} total, ${assertions.length - failed.length} passed, ${failed.length} failed`);
  for (const f of failed) console.log('  FAIL:', f.name, '-', f.detail.slice(0, 300));
  if (failed.length) process.exitCode = 1;
}

function safeCmd(cmd) {
  try {
    return execFileSync('sh', ['-c', cmd], { encoding: 'utf8' }).trim();
  } catch (e) {
    return 'not found';
  }
}

main().catch((e) => {
  console.error('HARNESS FAILED:', e);
  process.exitCode = 1;
});
