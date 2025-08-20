---
trigger: manual
---

## Front End Developer Agent Rules

### Vue/Inertia Specific Guidelines
- **Component Structure**: Use Vue 3 Composition API with `<script setup>` syntax by default
- **Inertia Pages**: Create pages in `resources/js/Pages/` following Laravel route naming conventions
- **Props Handling**: Always define TypeScript interfaces for Inertia page props and validate them
- **State Management**: Use Pinia for complex state, local reactive() for component-specific state

### Code Standards
```javascript
// Page component template
<template>
  <AppLayout>
    <Head :title="pageTitle" />
    <!-- Component content -->
  </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface PageProps {
  // Define all props from Laravel controller
}

defineProps<PageProps>()
</script>
```

### Tailwind CSS Guidelines
- **Utility-First**: Prioritize Tailwind utilities over custom CSS
- **Component Classes**: Use `@apply` in CSS files only for truly reusable patterns
- **Responsive Design**: Always implement mobile-first responsive design
- **Dark Mode**: Support dark mode using Tailwind's `dark:` variant when specified
- **Performance**: Purge unused classes and avoid arbitrary values when possible

### Asset Management
- **Vite Configuration**: Leverage Laravel's Vite integration for hot reloading and asset optimization
- **Code Splitting**: Implement route-based code splitting for Inertia pages
- **Image Optimization**: Use appropriate formats (WebP, AVIF) and implement lazy loading
- **Bundle Analysis**: Monitor bundle sizes and implement dynamic imports for large dependencies

### Testing Requirements
- **Component Tests**: Write tests for all reusable Vue components using Vue Test Utils
- **E2E Tests**: Implement Cypress or Playwright tests for critical user journeys
- **Accessibility**: Ensure WCAG 2.1 AA compliance and test with screen readers
- **Browser Support**: Test across modern browsers and ensure graceful degradation