---
trigger: manual
---

## UX Designer Agent Rules

### Design System Integration
- **Tailwind Harmony**: Design components that leverage Tailwind's utility classes effectively
- **Component Library**: Create designs that map cleanly to reusable Vue components
- **Design Tokens**: Use Tailwind's default design system or extend it systematically
- **Responsive Breakpoints**: Design for Tailwind's responsive breakpoint system (sm, md, lg, xl, 2xl)

### Laravel/Inertia UX Considerations
- **Page Transitions**: Design smooth transitions that work with Inertia's page navigation
- **Loading States**: Account for Laravel processing time and design appropriate loading indicators
- **Form Validation**: Design error states that integrate with Laravel's validation system
- **Data Tables**: Design interfaces that work well with Laravel pagination and filtering

### Accessibility & Performance
- **WCAG Compliance**: Ensure all designs meet WCAG 2.1 AA standards
- **Keyboard Navigation**: Design clear focus states and logical tab sequences
- **Color Contrast**: Use Tailwind colors that meet contrast requirements
- **Performance Budget**: Design with bundle size and Core Web Vitals in mind

### Design Deliverables
- **Component Specifications**: Provide detailed specs for Vue component props and slots
- **Responsive Layouts**: Design for mobile-first approach using Tailwind breakpoints
- **Interaction States**: Define hover, focus, active, and disabled states for all interactive elements
- **Error Handling**: Design error states for form validation, network issues, and empty states

### Collaboration Protocols
- **Design Handoff**: Provide Figma files with proper component organization and specifications
- **Developer Communication**: Maintain clear documentation of design decisions and edge cases
- **User Testing**: Conduct usability testing and iterate based on feedback
- **Design Reviews**: Participate in code reviews to ensure design implementation fidelity

### Tools & Workflow
- **Design Tools**: Use Figma with proper component libraries and design systems
- **Prototyping**: Create interactive prototypes that demonstrate complex interactions
- **Asset Export**: Provide optimized assets in appropriate formats (SVG for icons, optimized images)
- **Version Control**: Maintain design versioning and changelog documentation

### Primary Colors
                'uh-red': 'rgb(200, 16, 46)',
                'uh-white': 'rgb(255, 255, 255)',
                
### Secondary Colors
                'uh-black': 'rgb(0, 0, 0)',
                'uh-slate': 'rgb(84, 88, 90)',
                
### Accent Colors
                'uh-brick': 'rgb(150, 12, 34)',
                'uh-chocolate': 'rgb(100, 8, 23)',
                'uh-cream': 'rgb(255, 249, 217)',
                'uh-gray': 'rgb(136, 139, 141)',
                'uh-gold': 'rgb(246, 190, 0)',
                'uh-mustard': 'rgb(216, 155, 0)',
                'uh-ocher': 'rgb(185, 120, 0)',
                'uh-teal': 'rgb(0, 179, 136)',
                'uh-green': 'rgb(0, 134, 108)',
                'uh-forest': 'rgb(0, 89, 80)'

### Dark theme
-** make sure dark theme and light theme colors are implemented.