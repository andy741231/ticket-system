<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '#ffffff' },
  showAlpha: { type: Boolean, default: true },
  label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

// ---------- Color utilities ----------
function clamp(n, min, max) { return Math.min(max, Math.max(min, n)); }

function parseHex(hex) {
  if (!hex || typeof hex !== 'string') return null;
  const h = hex.trim().replace(/^#/, '');
  if (h.length === 3) {
    const r = parseInt(h[0] + h[0], 16);
    const g = parseInt(h[1] + h[1], 16);
    const b = parseInt(h[2] + h[2], 16);
    return { r, g, b, a: 1 };
  }
  if (h.length === 6) {
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return { r, g, b, a: 1 };
  }
  if (h.length === 8) {
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const a = parseInt(h.slice(6, 8), 16) / 255;
    return { r, g, b, a };
  }
  return null;
}

function componentToHex(c) {
  const hex = c.toString(16);
  return hex.length === 1 ? '0' + hex : hex;
}

function rgbToHex({ r, g, b, a = 1 }, includeAlpha = false) {
  const hex = `#${componentToHex(r)}${componentToHex(g)}${componentToHex(b)}`;
  if (includeAlpha) {
    const aByte = Math.round(clamp(a, 0, 1) * 255);
    return hex + componentToHex(aByte);
  }
  return hex;
}

function rgbToHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;
  if (max === min) { h = s = 0; }
  else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 1); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h /= 6;
  }
  return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}

function hslToRgb(h, s, l) {
  h = clamp(h, 0, 360) / 360; s = clamp(s, 0, 100) / 100; l = clamp(l, 0, 100) / 100;
  if (s === 0) {
    const v = Math.round(l * 255);
    return { r: v, g: v, b: v };
  }
  const hue2rgb = (p, q, t) => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1/6) return p + (q - p) * 6 * t;
    if (t < 1/2) return q;
    if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
    return p;
  };
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  const r = Math.round(hue2rgb(p, q, h + 1/3) * 255);
  const g = Math.round(hue2rgb(p, q, h) * 255);
  const b = Math.round(hue2rgb(p, q, h - 1/3) * 255);
  return { r, g, b };
}

function parseRgb(str) {
  const m = str.match(/rgba?\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(\d*\.?\d+))?\s*\)/i);
  if (!m) return null;
  const r = clamp(parseInt(m[1]), 0, 255);
  const g = clamp(parseInt(m[2]), 0, 255);
  const b = clamp(parseInt(m[3]), 0, 255);
  const a = m[4] != null ? clamp(parseFloat(m[4]), 0, 1) : 1;
  return { r, g, b, a };
}

function parseHsl(str) {
  const m = str.match(/hsla?\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})%\s*,\s*(\d{1,3})%(?:\s*,\s*(\d*\.?\d+))?\s*\)/i);
  if (!m) return null;
  const h = clamp(parseInt(m[1]), 0, 360);
  const s = clamp(parseInt(m[2]), 0, 100);
  const l = clamp(parseInt(m[3]), 0, 100);
  const a = m[4] != null ? clamp(parseFloat(m[4]), 0, 1) : 1;
  const { r, g, b } = hslToRgb(h, s, l);
  return { r, g, b, a };
}

function parseColor(str) {
  if (!str) return { r: 255, g: 255, b: 255, a: 1 };
  const val = String(str).trim();
  if (val.toLowerCase() === 'transparent') return { r: 0, g: 0, b: 0, a: 0 };
  if (val.startsWith('#')) return parseHex(val) || { r: 255, g: 255, b: 255, a: 1 };
  if (/^rgba?\s*\(/i.test(val)) return parseRgb(val) || { r: 255, g: 255, b: 255, a: 1 };
  if (/^hsla?\s*\(/i.test(val)) return parseHsl(val) || { r: 255, g: 255, b: 255, a: 1 };
  // Try letting the browser parse named colors
  const tmp = document.createElement('div');
  tmp.style.color = val;
  document.body.appendChild(tmp);
  const cs = getComputedStyle(tmp).color;
  document.body.removeChild(tmp);
  return parseRgb(cs) || { r: 255, g: 255, b: 255, a: 1 };
}

function toCssColor({ r, g, b, a }) {
  if (a == null || a >= 1) return rgbToHex({ r, g, b });
  return `rgba(${r}, ${g}, ${b}, ${a.toFixed(3).replace(/0+$/,'').replace(/\.$/,'')})`;
}

// ---------- State ----------
const isOpen = ref(false);
const rootEl = ref(null);
const triggerEl = ref(null);

const rgba = ref(parseColor(props.modelValue));
const hsl = ref({ ...rgbToHsl(rgba.value.r, rgba.value.g, rgba.value.b) });
const alpha = ref(rgba.value.a ?? 1);

watch(() => props.modelValue, (v) => {
  const c = parseColor(v);
  rgba.value = c;
  hsl.value = rgbToHsl(c.r, c.g, c.b);
  alpha.value = c.a ?? 1;
});

watch([hsl, alpha], () => {
  const { r, g, b } = hslToRgb(hsl.value.h, hsl.value.s, hsl.value.l);
  rgba.value = { r, g, b, a: alpha.value };
  const out = toCssColor(rgba.value);
  emit('update:modelValue', out);
  emit('change', out);
}, { deep: true });

// ---------- Presets ----------
const UH_PRESETS = [
  // Primary
  { name: 'UH Red', value: 'rgb(200, 16, 46)' },
  { name: 'UH White', value: 'rgb(255, 255, 255)' },
  // Secondary
  { name: 'UH Black', value: 'rgb(0, 0, 0)' },
  { name: 'UH Slate', value: 'rgb(84, 88, 90)' },
  // Accent
  { name: 'UH Brick', value: 'rgb(150, 12, 34)' },
  { name: 'UH Chocolate', value: 'rgb(100, 8, 23)' },
  { name: 'UH Cream', value: 'rgb(255, 249, 217)' },
  { name: 'UH Gray', value: 'rgb(136, 139, 141)' },
  { name: 'UH Gold', value: 'rgb(246, 190, 0)' },
  { name: 'UH Mustard', value: 'rgb(216, 155, 0)' },
  { name: 'UH Ocher', value: 'rgb(185, 120, 0)' },
  { name: 'UH Teal', value: 'rgb(0, 179, 136)' },
  { name: 'UH Green', value: 'rgb(0, 134, 108)' },
  { name: 'UH Forest', value: 'rgb(0, 89, 80)' },
];

const GRAYS = [
  '#ffffff', '#f9fafb', '#f3f4f6', '#e5e7eb', '#d1d5db', '#9ca3af', '#6b7280', '#4b5563', '#374151', '#111827', '#000000'
];

// ---------- Picker interactions ----------
const pickerRef = ref(null);
const svRef = ref(null);
const hueRef = ref(null);
const alphaRef = ref(null);

function svBackground(h) {
  return {
    background: `linear-gradient(to top, black, transparent), linear-gradient(to right, white, hsl(${h}, 100%, 50%))`,
  };
}

const svCursorStyle = computed(() => ({
  left: `${hsl.value.s}%`,
  top: `${100 - hsl.value.l}%`,
}));

function onSvDown(e) {
  const el = svRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const move = (clientX, clientY) => {
    const x = clamp(clientX - rect.left, 0, rect.width);
    const y = clamp(clientY - rect.top, 0, rect.height);
    const s = Math.round((x / rect.width) * 100);
    const l = Math.round(100 - (y / rect.height) * 100);
    hsl.value = { ...hsl.value, s, l };
  };
  const onMove = (ev) => move(ev.clientX, ev.clientY);
  const onTouch = (ev) => { if (ev.touches[0]) move(ev.touches[0].clientX, ev.touches[0].clientY); };
  const up = () => {
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', up);
    window.removeEventListener('touchmove', onTouch);
    window.removeEventListener('touchend', up);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', up);
  window.addEventListener('touchmove', onTouch, { passive: false });
  window.addEventListener('touchend', up);
  // Initial
  if (e.type === 'mousedown') onMove(e);
  if (e.type === 'touchstart') onTouch(e);
}

function onHueDown(e) {
  const el = hueRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const move = (clientX) => {
    const x = clamp(clientX - rect.left, 0, rect.width);
    const h = Math.round((x / rect.width) * 360);
    hsl.value = { ...hsl.value, h };
  };
  const onMove = (ev) => move(ev.clientX);
  const onTouch = (ev) => { if (ev.touches[0]) move(ev.touches[0].clientX); };
  const up = () => {
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', up);
    window.removeEventListener('touchmove', onTouch);
    window.removeEventListener('touchend', up);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', up);
  window.addEventListener('touchmove', onTouch, { passive: false });
  window.addEventListener('touchend', up);
  if (e.type === 'mousedown') onMove(e);
  if (e.type === 'touchstart') onTouch(e);
}

function onAlphaDown(e) {
  const el = alphaRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const move = (clientX) => {
    const x = clamp(clientX - rect.left, 0, rect.width);
    alpha.value = +(x / rect.width).toFixed(3);
  };
  const onMove = (ev) => move(ev.clientX);
  const onTouch = (ev) => { if (ev.touches[0]) move(ev.touches[0].clientX); };
  const up = () => {
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', up);
    window.removeEventListener('touchmove', onTouch);
    window.removeEventListener('touchend', up);
  };
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', up);
  window.addEventListener('touchmove', onTouch, { passive: false });
  window.addEventListener('touchend', up);
  if (e.type === 'mousedown') onMove(e);
  if (e.type === 'touchstart') onTouch(e);
}

// ---------- Single CSS input ----------
const cssInput = ref('');

function syncCssFromState() {
  cssInput.value = toCssColor({ ...rgba.value, a: alpha.value });
}

watch([rgba, hsl, alpha], syncCssFromState, { deep: true, immediate: true });

function onCssChange() {
  const c = parseColor(cssInput.value);
  if (c) {
    rgba.value = c;
    hsl.value = rgbToHsl(c.r, c.g, c.b);
    alpha.value = c.a ?? 1;
  }
}

// ---------- Popover management ----------
function handleClickOutside(e) {
  if (!isOpen.value) return;
  const target = e.target;
  const inRoot = rootEl.value && rootEl.value.contains(target);
  const inTrigger = triggerEl.value && triggerEl.value.contains(target);
  const inPicker = pickerRef.value && pickerRef.value.contains(target);
  if (inRoot || inTrigger || inPicker) return;
  isOpen.value = false;
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside);
  document.addEventListener('touchstart', handleClickOutside, { passive: true });
});

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside);
  document.removeEventListener('touchstart', handleClickOutside);
});

// ---------- Teleport + positioning ----------
const portalPos = ref({ top: 0, left: 0, minWidth: 0 });
const portalPlacement = ref('bottom'); // 'bottom' | 'top'

async function computePosition() {
  await nextTick();
  const t = triggerEl.value;
  const p = pickerRef.value; // panel contents
  if (!t) return;
  const rect = t.getBoundingClientRect();
  const panelH = p ? p.offsetHeight : 300;
  const panelW = p ? p.offsetWidth : 320;
  const gap = 8;
  let top = rect.bottom + gap;
  let placement = 'bottom';
  if (top + panelH > window.innerHeight) {
    top = Math.max(0, rect.top - gap - panelH);
    placement = 'top';
  }
  let left = Math.min(rect.left, Math.max(0, window.innerWidth - panelW - 8));
  portalPos.value = { top: Math.round(top), left: Math.round(left), minWidth: Math.round(rect.width) };
  portalPlacement.value = placement;
}

function toggleOpen() {
  isOpen.value = !isOpen.value;
  if (isOpen.value) computePosition();
}

watch(isOpen, (open) => {
  if (open) {
    computePosition();
    window.addEventListener('resize', computePosition);
    window.addEventListener('scroll', computePosition, true);
  } else {
    window.removeEventListener('resize', computePosition);
    window.removeEventListener('scroll', computePosition, true);
  }
});

// Computed gradient styles
const hueGradient = `linear-gradient(to right, 
  #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%)`;

const alphaGradient = computed(() => {
  const { r, g, b } = rgba.value;
  return `linear-gradient(to right, rgba(${r}, ${g}, ${b}, 0), rgba(${r}, ${g}, ${b}, 1))`;
});

const swatchStyle = computed(() => ({
  background: toCssColor({ ...rgba.value, a: alpha.value }),
}));

function setPreset(val) {
  const c = parseColor(val);
  rgba.value = c;
  hsl.value = rgbToHsl(c.r, c.g, c.b);
  alpha.value = c.a ?? 1;
}
</script>

<template>
  <div class="relative inline-block text-left" ref="rootEl">
    <label v-if="label" class="block text-xs text-gray-600 dark:text-gray-300 mb-1">{{ label }}</label>
    <span ref="triggerEl" class="inline-flex" @click.stop="toggleOpen">
      <slot name="trigger">
        <button type="button"
                class="w-10 h-10 rounded border border-gray-300 dark:border-gray-600 shadow-sm flex items-center justify-center"
                :style="swatchStyle"
                title="Open color picker">
          <span class="sr-only">Pick color</span>
        </button>
      </slot>
    </span>

    <teleport to="body">
      <div v-if="isOpen"
           class="fixed z-[1000] w-80 sm:w-96 p-3 rounded-md border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl"
           :style="{ top: portalPos.top + 'px', left: portalPos.left + 'px', minWidth: portalPos.minWidth + 'px' }"
           ref="pickerRef"
           @mousedown.stop
           @click.stop>
        <!-- Presets -->
        <div>
          <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">UH Presets</div>
          <div class="grid grid-cols-8 gap-2 mb-3">
            <button v-for="p in UH_PRESETS" :key="p.name" type="button"
                    class="h-6 rounded border border-gray-200 hover:scale-105 transition"
                    :title="p.name" :style="{ background: p.value }"
                    @click="setPreset(p.value)" />
          </div>
          <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Grayscale</div>
          <div class="grid grid-cols-11 gap-1 mb-3">
            <button v-for="g in GRAYS" :key="g" type="button"
                    class="h-5 rounded border border-gray-200 hover:scale-105 transition"
                    :style="{ background: g }"
                    @click="setPreset(g)" />
          </div>
        </div>

        <!-- Advanced -->
        <div class="space-y-3">
          <!-- SV Square -->
          <div ref="svRef"
               class="relative h-40 w-full rounded cursor-crosshair"
               :style="svBackground(hsl.h)"
               @mousedown.prevent="onSvDown"
               @touchstart.prevent="onSvDown">
            <div class="absolute w-3 h-3 -mt-1.5 -ml-1.5 rounded-full border border-white shadow"
                 :style="{ left: svCursorStyle.left, top: svCursorStyle.top }"></div>
          </div>

          <!-- Hue slider -->
          <div ref="hueRef"
               class="h-3 w-full rounded cursor-pointer"
               :style="{ background: hueGradient }"
               @mousedown.prevent="onHueDown"
               @touchstart.prevent="onHueDown">
            <div class="h-3 w-1 bg-white/90 shadow" :style="{ position: 'relative', left: `${(hsl.h/360)*100}%` }"></div>
          </div>

          <!-- Alpha slider -->
          <div v-if="showAlpha" class="relative">
            <div class="h-3 w-full rounded bg-[linear-gradient(45deg,#ccc_25%,transparent_25%,transparent_75%,#ccc_75%),linear-gradient(45deg,#ccc_25%,transparent_25%,transparent_75%,#ccc_75%)] bg-[length:10px_10px,10px_10px] bg-[0_0,5px_5px] rounded"></div>
            <div ref="alphaRef" class="-mt-3 h-3 w-full rounded cursor-pointer"
                 :style="{ background: alphaGradient }"
                 @mousedown.prevent="onAlphaDown"
                 @touchstart.prevent="onAlphaDown">
              <div class="h-3 w-1 bg-white/90 shadow" :style="{ position: 'relative', left: `${alpha*100}%` }"></div>
            </div>
          </div>

          <!-- Input: any CSS color -->
          <div class="grid grid-cols-7 gap-2 items-end">
            <div class="col-span-7">
              <label class="block text-[10px] uppercase tracking-wide text-gray-500 dark:text-gray-400">CSS</label>
              <input v-model="cssInput" @change="onCssChange" type="text" placeholder="Any CSS color (e.g., rgba(0,0,0,.5), hsl(...), transparent)"
                     class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-xs bg-white dark:bg-gray-900" />
            </div>
          </div>


          <div class="flex justify-end pt-2">
            <button type="button" class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600" @click="isOpen = false">Done</button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>
