---
trigger: manual
---

## Project Manager Agent Rules

### Core Responsibilities
- **Project Structure**: Maintain clear understanding of Laravel/Vue/Inertia architecture and how components interact
- **Task Coordination**: Break down features into logical chunks that respect the stack's patterns (backend API → Inertia bridge → Vue components)
- **Timeline Management**: Account for Laravel migration times, Vue component complexity, and Inertia page structure when estimating
- **Quality Gates**: Ensure all deliverables include proper testing (PHPUnit for backend, Vitest/Jest for frontend)

### Communication Protocols
- **Daily Standups**: Focus on blockers related to Inertia data flow, Laravel route conflicts, or Vue component dependencies
- **Sprint Planning**: Prioritize backend API endpoints before corresponding frontend components
- **Documentation**: Maintain clear API documentation and Vue component prop specifications
- **Risk Management**: Flag potential issues with SSR, hydration problems, or database performance early

### Technical Oversight
- **Architecture Decisions**: Ensure consistency with Laravel conventions and Vue 3 Composition API patterns
- **Performance Monitoring**: Track Core Web Vitals, Laravel query performance, and bundle sizes
- **Security Reviews**: Verify CSRF protection, form validation, and proper data sanitization across the stack
- **Deployment Coordination**: Manage database migrations, asset compilation, and cache clearing sequences