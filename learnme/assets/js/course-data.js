// Course Data Structure
const courseData = {
    modules: [
        {
            id: 1,
            title: "Project Setup & Architecture",
            description: "Set up Laravel 12 with Vue 3, Inertia.js, and understand the project architecture",
            duration: "90 minutes",
            lessons: 8,
            status: "available",
            lessons_data: [
                {
                    id: "1-1",
                    title: "Course Introduction & What We'll Build",
                    type: "video",
                    duration: "10 min",
                    content: {
                        title: "Welcome to Laravel Mastery",
                        objectives: [
                            "Understand what we'll build in this course",
                            "See the final application demo",
                            "Learn about the tech stack",
                            "Set expectations for the learning journey"
                        ],
                        video_url: "#",
                        transcript: `
                            Welcome to this comprehensive course on building a full-stack ticket management system!
                            
                            In this course, you'll learn to build a production-ready application using:
                            - Laravel 12 (latest version)
                            - Vue 3 with Composition API
                            - Inertia.js for seamless SPA experience
                            - Tailwind CSS for modern styling
                            - Role-Based Access Control (RBAC)
                            - Newsletter management system
                            
                            By the end of this course, you'll have built a complete application that includes:
                            - User authentication and management
                            - Ticket system with file uploads
                            - Comment system with reactions
                            - Newsletter campaigns and subscriber management
                            - Advanced RBAC with permissions and overrides
                            - Modern, responsive UI
                        `
                    }
                },
                {
                    id: "1-2",
                    title: "Development Environment Setup",
                    type: "hands-on",
                    duration: "15 min",
                    content: {
                        title: "Setting Up Your Development Environment",
                        objectives: [
                            "Install PHP 8.2+ and Composer",
                            "Install Node.js and npm",
                            "Set up database (MySQL/PostgreSQL)",
                            "Configure development tools"
                        ],
                        steps: [
                            {
                                title: "Install PHP and Composer",
                                description: "We need PHP 8.2 or higher for Laravel 12",
                                code: `# macOS (using Homebrew)
brew install php@8.2 composer

# Ubuntu/Debian
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl composer

# Windows (using Chocolatey)
choco install php composer`,
                                language: "bash"
                            },
                            {
                                title: "Install Node.js and npm",
                                description: "Required for Vue.js and asset compilation",
                                code: `# Download from nodejs.org or use package manager
# macOS
brew install node

# Ubuntu/Debian  
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Verify installation
node --version
npm --version`,
                                language: "bash"
                            },
                            {
                                title: "Database Setup",
                                description: "Install and configure MySQL or PostgreSQL",
                                code: `# MySQL (macOS)
brew install mysql
brew services start mysql

# MySQL (Ubuntu)
sudo apt install mysql-server
sudo systemctl start mysql

# Create database
mysql -u root -p
CREATE DATABASE ticket_system;
CREATE USER 'laravel'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON ticket_system.* TO 'laravel'@'localhost';
FLUSH PRIVILEGES;`,
                                language: "sql"
                            }
                        ]
                    }
                },
                {
                    id: "1-3",
                    title: "Creating a New Laravel Project",
                    type: "hands-on",
                    duration: "10 min",
                    content: {
                        title: "Creating Our Laravel Project",
                        objectives: [
                            "Create a new Laravel 12 project",
                            "Configure environment variables",
                            "Test the installation"
                        ],
                        steps: [
                            {
                                title: "Create Laravel Project",
                                description: "Use Composer to create a new Laravel project",
                                code: `# Create new Laravel project
composer create-project laravel/laravel ticket-system

# Navigate to project directory
cd ticket-system

# Check Laravel version
php artisan --version`,
                                language: "bash"
                            },
                            {
                                title: "Configure Environment",
                                description: "Set up your .env file with database credentials",
                                code: `# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ticket_system
DB_USERNAME=laravel
DB_PASSWORD=password`,
                                language: "bash"
                            },
                            {
                                title: "Test Installation",
                                description: "Start the development server and test",
                                code: `# Start development server
php artisan serve

# Visit http://localhost:8000 in your browser
# You should see the Laravel welcome page`,
                                language: "bash"
                            }
                        ]
                    }
                },
                {
                    id: "1-4",
                    title: "Installing Inertia.js and Vue 3",
                    type: "hands-on",
                    duration: "15 min",
                    content: {
                        title: "Setting Up Inertia.js with Vue 3",
                        objectives: [
                            "Install Inertia.js server-side adapter",
                            "Install Vue 3 and Inertia client-side",
                            "Configure Vite for asset compilation",
                            "Create your first Inertia page"
                        ],
                        steps: [
                            {
                                title: "Install Server-Side Dependencies",
                                description: "Add Inertia.js to Laravel",
                                code: `# Install Inertia Laravel adapter
composer require inertiajs/inertia-laravel

# Publish Inertia middleware
php artisan inertia:middleware`,
                                language: "bash"
                            },
                            {
                                title: "Install Client-Side Dependencies",
                                description: "Add Vue 3 and Inertia client packages",
                                code: `# Install Vue 3 and Inertia
npm install @inertiajs/vue3 vue@next @vitejs/plugin-vue

# Install additional dependencies
npm install @tailwindcss/forms autoprefixer postcss tailwindcss`,
                                language: "bash"
                            },
                            {
                                title: "Configure Vite",
                                description: "Update vite.config.js for Vue support",
                                code: `import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});`,
                                language: "javascript"
                            },
                            {
                                title: "Configure App.js",
                                description: "Set up Vue 3 with Inertia",
                                code: `import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

createInertiaApp({
    title: (title) => \`\${title} - Ticket System\`,
    resolve: (name) => resolvePageComponent(\`./Pages/\${name}.vue\`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
    progress: {
        color: '#4B5563',
    },
})`,
                                language: "javascript"
                            }
                        ]
                    }
                }
            ]
        },
        {
            id: 2,
            title: "Authentication & User Management",
            description: "Implement user authentication, registration, and basic user management features",
            duration: "120 minutes",
            lessons: 6,
            status: "locked"
        },
        {
            id: 3,
            title: "RBAC System Implementation",
            description: "Build a comprehensive Role-Based Access Control system with Spatie Permission",
            duration: "150 minutes",
            lessons: 8,
            status: "locked"
        },
        {
            id: 4,
            title: "Database Design & Models",
            description: "Design the database schema and create Eloquent models for tickets, users, and relationships",
            duration: "90 minutes",
            lessons: 5,
            status: "locked"
        },
        {
            id: 5,
            title: "Ticket System Core",
            description: "Build the core ticket functionality with CRUD operations, file uploads, and status management",
            duration: "180 minutes",
            lessons: 10,
            status: "locked"
        },
        {
            id: 6,
            title: "Advanced Ticket Features",
            description: "Add comments, assignments, due dates, and advanced ticket management features",
            duration: "150 minutes",
            lessons: 8,
            status: "locked"
        },
        {
            id: 7,
            title: "Newsletter Management System",
            description: "Build subscriber management, groups, campaigns, and email sending functionality",
            duration: "120 minutes",
            lessons: 7,
            status: "locked"
        },
        {
            id: 8,
            title: "Frontend with Vue 3 & Inertia",
            description: "Create responsive, interactive UI components using Vue 3 Composition API",
            duration: "200 minutes",
            lessons: 12,
            status: "locked"
        },
        {
            id: 9,
            title: "Advanced UI Components",
            description: "Build complex components like data tables, forms, modals, and rich text editors",
            duration: "180 minutes",
            lessons: 10,
            status: "locked"
        },
        {
            id: 10,
            title: "Background Jobs & Queues",
            description: "Implement email sending, campaign processing, and other background tasks",
            duration: "90 minutes",
            lessons: 6,
            status: "locked"
        },
        {
            id: 11,
            title: "Testing & Quality Assurance",
            description: "Write comprehensive tests for your application using PHPUnit and Vue Test Utils",
            duration: "120 minutes",
            lessons: 8,
            status: "locked"
        },
        {
            id: 12,
            title: "Deployment & Production",
            description: "Deploy your application to production with proper configuration and optimization",
            duration: "90 minutes",
            lessons: 6,
            status: "locked"
        }
    ]
};

// Progress tracking
let courseProgress = {
    currentModule: 1,
    currentLesson: "1-1",
    completedLessons: [],
    totalLessons: courseData.modules.reduce((total, module) => total + module.lessons, 0)
};

// Load progress from localStorage
function loadProgress() {
    const saved = localStorage.getItem('courseProgress');
    if (saved) {
        courseProgress = { ...courseProgress, ...JSON.parse(saved) };
    }
}

// Save progress to localStorage
function saveProgress() {
    localStorage.setItem('courseProgress', JSON.stringify(courseProgress));
}

// Mark lesson as completed
function completeLesson(lessonId) {
    if (!courseProgress.completedLessons.includes(lessonId)) {
        courseProgress.completedLessons.push(lessonId);
        saveProgress();
        updateProgressBar();
    }
}

// Update progress bar
function updateProgressBar() {
    const percentage = (courseProgress.completedLessons.length / courseProgress.totalLessons) * 100;
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    
    if (progressFill) {
        progressFill.style.width = `${percentage}%`;
    }
    if (progressText) {
        progressText.textContent = `${Math.round(percentage)}% Complete`;
    }
}

// Initialize progress on page load
document.addEventListener('DOMContentLoaded', () => {
    loadProgress();
    updateProgressBar();
});
