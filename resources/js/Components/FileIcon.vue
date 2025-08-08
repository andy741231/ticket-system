<template>
  <span class="file-icon">
    <component :is="iconComponent" class="w-5 h-5" />
  </span>
</template>

<script>
import { defineComponent, computed } from 'vue';
import { 
  DocumentIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  PhotoIcon,
  DocumentArrowDownIcon,
  ChartBarIcon,
  DocumentChartBarIcon,
  DocumentDuplicateIcon,
  FolderIcon
} from '@heroicons/vue/24/outline';

export default defineComponent({
  name: 'FileIcon',
  components: {
    DocumentIcon,
    DocumentTextIcon,
    ArrowDownTrayIcon,
    PhotoIcon,
    DocumentArrowDownIcon,
    ChartBarIcon,
    DocumentChartBarIcon,
    DocumentDuplicateIcon,
    FolderIcon
  },
  props: {
    mimeType: {
      type: String,
      default: 'application/octet-stream'
    },
    fileName: {
      type: String,
      default: ''
    }
  },
  setup(props) {
    const iconComponent = computed(() => {
      const type = props.mimeType.toLowerCase();
      
      // Images
      if (type.startsWith('image/')) {
        return 'PhotoIcon';
      }
      
      // PDFs
      if (type === 'application/pdf') {
        return 'DocumentArrowDownIcon';
      }
      
      // Word documents
      if (type === 'application/msword' || 
          type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        return 'DocumentTextIcon';
      }
      
      // Excel spreadsheets
      if (type === 'application/vnd.ms-excel' || 
          type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
        return 'ChartBarIcon';
      }
      
      // Text files
      if (type === 'text/plain' || type === 'text/csv') {
        return 'DocumentTextIcon';
      }
      
      // Archives
      if (type === 'application/zip' || 
          type === 'application/x-rar-compressed' || 
          type === 'application/x-7z-compressed') {
        return 'FolderIcon';
      }
      
      // Default document icon
      return 'DocumentIcon';
    });
    
    return {
      iconComponent
    };
  }
});
</script>

<style scoped>
.file-icon {
  @apply inline-flex items-center justify-center;
}
</style>
