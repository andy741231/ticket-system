<script setup>
import { ref, computed, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
  show: Boolean,
  teams: Array,
  currentFilter: String,
});

const emit = defineEmits(['close']);

const canvas = ref(null);
const isExporting = ref(false);
const width = ref(1920);
const height = ref(1080);
const title = ref('');
const selectedLogo = ref('');

// Available logos from the logos folder
const availableLogos = [
  { value: '', label: 'No Logo' },
  { value: '/storage/images/newsletters/logos/uhph-logo-red.png', label: 'UHPH Logo (Red)' },
  { value: '/storage/images/newsletters/logos/uhph-logo.png', label: 'UHPH Logo' },
];

const organizedTeams = computed(() => {
  const leadership = props.teams.filter(t => t.group_1 === 'leadership');
  const others = props.teams.filter(t => t.group_1 !== 'leadership');
  return { leadership, others };
});

const exportChart = async () => {
  isExporting.value = true;
  
  try {
    await nextTick();
    
    const canvasEl = canvas.value;
    const ctx = canvasEl.getContext('2d');
    
    // Set canvas to user-specified dimensions
    canvasEl.width = width.value;
    canvasEl.height = height.value;
    
    // Enable high-quality image rendering
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    
    // Fill white background
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width.value, height.value);
    
    // Define base dimensions
    const basePadding = 60;
    const baseCardWidth = 240;  // Increased from 200
    const baseCardHeight = 320; // Increased from 280
    const baseHorizontalGap = 20; // Reduced from 40
    const baseVerticalGap = 30;   // Reduced from 60
    const baseLogoHeight = 80;
    const baseLogoTopMargin = 20;
    const baseLogoBottomMargin = 40;
    
    // Calculate layout dimensions
    const { leadership, others } = organizedTeams.value;
    const baseContentWidth = width.value - (basePadding * 2);
    
    // Calculate required height at base scale
    let requiredHeight = basePadding; // Top padding
    
    // Only add logo area if a logo is selected
    if (selectedLogo.value) {
      requiredHeight += baseLogoTopMargin + baseLogoHeight + baseLogoBottomMargin; // Logo area
    }
    
    // Leadership row
    if (leadership.length > 0) {
      requiredHeight += baseCardHeight + baseVerticalGap + 40; // Extra gap after leadership
    }
    
    // Other rows
    if (others.length > 0) {
      const cardsPerRow = Math.min(8, Math.floor(baseContentWidth / (baseCardWidth + baseHorizontalGap)));
      const rows = Math.ceil(others.length / cardsPerRow);
      requiredHeight += rows * baseCardHeight + (rows - 1) * baseVerticalGap;
    }
    
    requiredHeight += basePadding; // Bottom padding
    
    // Calculate scale factor to fit content within canvas
    const scale = Math.min(1, height.value / requiredHeight);
    
    // Apply scaled dimensions
    const padding = basePadding * scale;
    const cardWidth = baseCardWidth * scale;
    const cardHeight = baseCardHeight * scale;
    const horizontalGap = baseHorizontalGap * scale;
    const verticalGap = baseVerticalGap * scale;
    const logoHeight = baseLogoHeight * scale;
    const logoTopMargin = baseLogoTopMargin * scale;
    const logoBottomMargin = baseLogoBottomMargin * scale;
    const contentWidth = width.value - (padding * 2);
    
    // Load logo if selected
    let logo = null;
    let logoWidth = 0;
    if (selectedLogo.value) {
      try {
        logo = await loadImage(selectedLogo.value);
        logoWidth = (logo.width / logo.height) * logoHeight;
      } catch (e) {
        console.warn('Logo not found, continuing without it');
      }
    }
    
    // Draw logo at top center (within padded area) if loaded
    const logoX = padding + (contentWidth - logoWidth) / 2;
    const logoY = padding + logoTopMargin;
    
    if (logo) {
      ctx.drawImage(logo, logoX, logoY, logoWidth, logoHeight);
    }
    
    // Draw title below logo (or at top if no logo) if provided
    let titleHeight = 0;
    if (title.value) {
      const titleFontSize = 64 * scale;
      const titleMargin = 20 * scale;
      
      ctx.fillStyle = '#54585a';
      ctx.font = `bold ${titleFontSize}px Arial, sans-serif`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';
      
      // Position title below logo if logo exists, otherwise at top
      const titleY = logo ? (logoY + logoHeight + titleMargin) : (padding + titleMargin);
      ctx.fillText(title.value, padding + contentWidth / 2, titleY);
      
      titleHeight = titleFontSize + titleMargin + 10 * scale; // Add extra spacing after title
    }
    
    // Calculate top margin based on what's present
    let topMargin;
    if (logo) {
      topMargin = logoY + logoHeight + logoBottomMargin + titleHeight;
    } else if (title.value) {
      topMargin = padding + titleHeight + 20 * scale;
    } else {
      topMargin = padding;
    }
    
    // Calculate positions
    let currentY = topMargin;
    
    // Draw leadership row
    if (leadership.length > 0) {
      const leadershipRowWidth = leadership.length * cardWidth + (leadership.length - 1) * horizontalGap;
      let currentX = padding + (contentWidth - leadershipRowWidth) / 2;
      
      for (const member of leadership) {
        await drawCard(ctx, member, currentX, currentY, cardWidth, cardHeight, scale);
        currentX += cardWidth + horizontalGap;
      }
      
      currentY += cardHeight + verticalGap + 40; // Extra gap after leadership
    }
    
    // Draw others in rows
    if (others.length > 0) {
      const cardsPerRow = Math.min(8, Math.floor(contentWidth / (cardWidth + horizontalGap)));
      const rows = Math.ceil(others.length / cardsPerRow);
      
      for (let row = 0; row < rows; row++) {
        const startIdx = row * cardsPerRow;
        const endIdx = Math.min(startIdx + cardsPerRow, others.length);
        const rowMembers = others.slice(startIdx, endIdx);
        
        const rowWidth = rowMembers.length * cardWidth + (rowMembers.length - 1) * horizontalGap;
        let currentX = padding + (contentWidth - rowWidth) / 2;
        
        for (const member of rowMembers) {
          await drawCard(ctx, member, currentX, currentY, cardWidth, cardHeight, scale);
          currentX += cardWidth + horizontalGap;
        }
        
        currentY += cardHeight + verticalGap;
      }
    }
    
    // Export as image
    const dataUrl = canvasEl.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = `organization-chart-${props.currentFilter || 'all'}-${Date.now()}.png`;
    link.href = dataUrl;
    link.click();
    
    emit('close');
  } catch (error) {
    console.error('Export failed:', error);
    alert('Failed to export chart. Please try again.');
  } finally {
    isExporting.value = false;
  }
};

const drawCard = async (ctx, member, x, y, cardWidth, cardHeight, scale = 1) => {
  // Draw card background
  ctx.fillStyle = '#ffffff';
  ctx.strokeStyle = '#e5e7eb';
  ctx.lineWidth = 2 * scale;
  ctx.beginPath();
  ctx.roundRect(x, y, cardWidth, cardHeight, 8 * scale);
  ctx.fill();
  ctx.stroke();
  
  // Draw profile image
  const imgSize = 140 * scale; // Increased from 120
  const imgX = x + (cardWidth - imgSize) / 2;
  const imgY = y + 20 * scale;
  
  if (member.img) {
    try {
      const profileImg = await loadImage(member.img);
      
      // Save context state
      ctx.save();
      
      // Create circular clipping path
      ctx.beginPath();
      ctx.arc(imgX + imgSize / 2, imgY + imgSize / 2, imgSize / 2, 0, Math.PI * 2);
      ctx.closePath();
      ctx.clip();
      
      // Draw image
      ctx.drawImage(profileImg, imgX, imgY, imgSize, imgSize);
      
      // Restore context
      ctx.restore();
      
      // Draw circle border
      ctx.strokeStyle = '#e5e7eb';
      ctx.lineWidth = 2 * scale;
      ctx.beginPath();
      ctx.arc(imgX + imgSize / 2, imgY + imgSize / 2, imgSize / 2, 0, Math.PI * 2);
      ctx.stroke();
    } catch (e) {
      // Draw placeholder if image fails to load
      drawPlaceholder(ctx, imgX, imgY, imgSize, scale);
    }
  } else {
    // Draw placeholder
    drawPlaceholder(ctx, imgX, imgY, imgSize, scale);
  }
  
  // Draw text content
  const textY = imgY + imgSize + 15 * scale;
  const textX = x + cardWidth / 2;
  
  // Helper function to get first value before comma
  const getFirstValue = (text) => {
    if (!text) return '';
    return text.split(',')[0].trim();
  };
  
  // Name
  ctx.fillStyle = '#111827';
  ctx.font = `bold ${16 * scale}px Arial, sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'top';
  const name = member.name || `${member.first_name} ${member.last_name}`;
  wrapText(ctx, name, textX, textY, cardWidth - 20 * scale, 20 * scale, 2);
  
  // Degree (take first value before comma)
  if (member.degree) {
    ctx.fillStyle = '#6b7280';
    ctx.font = `${12 * scale}px Arial, sans-serif`;
    const degreeText = getFirstValue(member.degree);
    wrapText(ctx, degreeText, textX, textY + 25 * scale, cardWidth - 20 * scale, 16 * scale, 1);
  }
  
  // Title (take first value before comma)
  if (member.title) {
    ctx.fillStyle = '#0d9488';
    ctx.font = `${14 * scale}px Arial, sans-serif`;
    const titleText = getFirstValue(member.title);
    wrapText(ctx, titleText, textX, textY + (member.degree ? 45 : 25) * scale, cardWidth - 20 * scale, 18 * scale, 2);
  }
};

const drawPlaceholder = (ctx, x, y, size, scale = 1) => {
  // Draw circle background
  ctx.fillStyle = '#f3f4f6';
  ctx.beginPath();
  ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2);
  ctx.fill();
  
  // Draw person icon
  ctx.strokeStyle = '#9ca3af';
  ctx.lineWidth = 3 * scale;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  
  const centerX = x + size / 2;
  const centerY = y + size / 2;
  const iconScale = size / 120;
  
  // Head circle
  ctx.beginPath();
  ctx.arc(centerX, centerY - 10 * iconScale, 15 * iconScale, 0, Math.PI * 2);
  ctx.stroke();
  
  // Body path
  ctx.beginPath();
  ctx.arc(centerX, centerY + 25 * iconScale, 25 * iconScale, Math.PI, 0, false);
  ctx.stroke();
};

const wrapText = (ctx, text, x, y, maxWidth, lineHeight, maxLines = 3) => {
  const words = text.split(' ');
  let line = '';
  let currentY = y;
  let lineCount = 0;
  
  for (let i = 0; i < words.length; i++) {
    const testLine = line + words[i] + ' ';
    const metrics = ctx.measureText(testLine);
    const testWidth = metrics.width;
    
    if (testWidth > maxWidth && i > 0) {
      // Check if we've reached max lines
      if (lineCount >= maxLines - 1) {
        // Truncate with ellipsis
        let truncated = line.trim();
        while (ctx.measureText(truncated + '...').width > maxWidth && truncated.length > 0) {
          truncated = truncated.slice(0, -1);
        }
        ctx.fillText(truncated + '...', x, currentY);
        return;
      }
      
      ctx.fillText(line.trim(), x, currentY);
      line = words[i] + ' ';
      currentY += lineHeight;
      lineCount++;
    } else {
      line = testLine;
    }
  }
  
  // Draw the last line if we haven't exceeded max lines
  if (lineCount < maxLines) {
    // Check if the last line fits, if not truncate it
    let finalLine = line.trim();
    if (ctx.measureText(finalLine).width > maxWidth) {
      while (ctx.measureText(finalLine + '...').width > maxWidth && finalLine.length > 0) {
        finalLine = finalLine.slice(0, -1);
      }
      finalLine += '...';
    }
    ctx.fillText(finalLine, x, currentY);
  }
};

const closeModal = () => {
  emit('close');
};

const loadImage = (src) => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
};
</script>

<template>
  <Modal :show="show" @close="closeModal" max-width="md">
    <div class="p-6 dark:bg-gray-800">
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
        Export Organization Chart
      </h2>
      
      <div class="space-y-4">
        <div>
          <InputLabel for="logo" value="Logo (optional)" />
          <select
            id="logo"
            v-model="selectedLogo"
            class="mt-1 block w-full rounded-md border-gray-300 focus:border-uh-teal focus:ring-uh-teal dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option v-for="logo in availableLogos" :key="logo.value" :value="logo.value">
              {{ logo.label }}
            </option>
          </select>
        </div>
        
        <div>
          <InputLabel for="title" value="Chart Title (optional)" />
          <TextInput
            id="title"
            v-model="title"
            type="text"
            class="mt-1 block w-full"
            placeholder="e.g., Organization Chart 2025"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Title will appear below the logo (or at top if no logo selected)
          </p>
        </div>
        
        <div>
          <InputLabel for="width" value="Width (px)" />
          <TextInput
            id="width"
            v-model.number="width"
            type="number"
            class="mt-1 block w-full"
            min="800"
            max="4096"
          />
        </div>
        
        <div>
          <InputLabel for="height" value="Height (px)" />
          <TextInput
            id="height"
            v-model.number="height"
            type="number"
            class="mt-1 block w-full"
            min="600"
            max="4096"
          />
        </div>
        
        <div class="text-sm text-gray-600 dark:text-gray-400">
          <p>Common sizes:</p>
          <ul class="list-disc list-inside mt-1">
            <li>1920×1080 (Full HD)</li>
            <li>2560×1440 (2K)</li>
            <li>3840×2160 (4K)</li>
          </ul>
        </div>
      </div>
      
      <div class="mt-6 flex justify-end gap-3">
        <SecondaryButton @click="closeModal" :disabled="isExporting">
          Cancel
        </SecondaryButton>
        <PrimaryButton @click="exportChart" :disabled="isExporting">
          <span v-if="isExporting">Exporting...</span>
          <span v-else>Export</span>
        </PrimaryButton>
      </div>
      
      <!-- Hidden canvas for rendering -->
      <canvas ref="canvas" style="display: none;"></canvas>
    </div>
  </Modal>
</template>
