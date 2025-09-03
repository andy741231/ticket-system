// Main application logic
class CourseApp {
    constructor() {
        this.currentModule = null;
        this.currentLesson = null;
        this.init();
    }

    init() {
        this.loadModules();
        this.setupEventListeners();
        this.loadTheme();
    }

    setupEventListeners() {
        // Theme toggle
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', this.toggleTheme);
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' && e.ctrlKey) {
                this.previousLesson();
            } else if (e.key === 'ArrowRight' && e.ctrlKey) {
                this.nextLesson();
            }
        });
    }

    loadModules() {
        const modulesGrid = document.getElementById('modules-grid');
        if (!modulesGrid) return;

        modulesGrid.innerHTML = courseData.modules.map(module => `
            <div class="module-card" onclick="app.openModule(${module.id})">
                <div class="module-header">
                    <div class="module-number">${module.id}</div>
                    <div class="module-title">${module.title}</div>
                </div>
                <div class="module-description">${module.description}</div>
                <div class="module-meta">
                    <span>${module.lessons} lessons • ${module.duration}</span>
                    <span class="module-status status-${module.status}">${this.getStatusText(module.status)}</span>
                </div>
            </div>
        `).join('');
    }

    getStatusText(status) {
        const statusMap = {
            'available': 'Available',
            'locked': 'Locked',
            'completed': 'Completed'
        };
        return statusMap[status] || status;
    }

    openModule(moduleId) {
        const module = courseData.modules.find(m => m.id === moduleId);
        if (!module || module.status === 'locked') return;

        this.currentModule = module;
        this.showLearningArea();
        this.loadLessons();
        
        // Load first lesson or resume from last position
        const firstLesson = module.lessons_data?.[0];
        if (firstLesson) {
            this.loadLesson(firstLesson.id);
        }
    }

    showLearningArea() {
        document.querySelector('.hero').style.display = 'none';
        document.querySelector('.modules').style.display = 'none';
        document.getElementById('learning-area').style.display = 'grid';
    }

    showOverview() {
        document.querySelector('.hero').style.display = 'block';
        document.querySelector('.modules').style.display = 'block';
        document.getElementById('learning-area').style.display = 'none';
        this.currentModule = null;
        this.currentLesson = null;
    }

    loadLessons() {
        const lessonNav = document.getElementById('lesson-nav');
        if (!lessonNav || !this.currentModule.lessons_data) return;

        lessonNav.innerHTML = this.currentModule.lessons_data.map(lesson => {
            const isCompleted = courseProgress.completedLessons.includes(lesson.id);
            const isCurrent = courseProgress.currentLesson === lesson.id;
            
            return `
                <div class="lesson-item ${isCurrent ? 'active' : ''}" onclick="app.loadLesson('${lesson.id}')">
                    <div class="lesson-icon ${isCompleted ? 'completed' : isCurrent ? 'current' : 'locked'}">
                        ${isCompleted ? '✓' : lesson.id.split('-')[1]}
                    </div>
                    <div class="lesson-title">${lesson.title}</div>
                </div>
            `;
        }).join('');
    }

    loadLesson(lessonId) {
        const lesson = this.currentModule.lessons_data.find(l => l.id === lessonId);
        if (!lesson) return;

        this.currentLesson = lesson;
        courseProgress.currentLesson = lessonId;
        saveProgress();

        const content = document.getElementById('lesson-content');
        content.innerHTML = this.renderLessonContent(lesson);
        
        // Update navigation
        this.updateLessonNavigation();
        this.loadLessons(); // Refresh lesson nav to show current
        
        // Scroll to top
        content.scrollTop = 0;
        
        // Initialize code highlighting
        if (window.Prism) {
            Prism.highlightAll();
        }
    }

    renderLessonContent(lesson) {
        const content = lesson.content;
        
        let html = `
            <div class="lesson-header fade-in">
                <h1>${content.title}</h1>
                <div class="lesson-meta">
                    <span>📹 ${lesson.type}</span>
                    <span>⏱️ ${lesson.duration}</span>
                    <span>📚 Module ${this.currentModule.id}</span>
                </div>
            </div>
            <div class="lesson-body fade-in">
        `;

        // Learning objectives
        if (content.objectives) {
            html += `
                <div class="info-box">
                    <h3>🎯 Learning Objectives</h3>
                    <ul>
                        ${content.objectives.map(obj => `<li>${obj}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        // Video content
        if (content.video_url && content.video_url !== '#') {
            html += `
                <div class="video-container">
                    <video controls width="100%">
                        <source src="${content.video_url}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            `;
        }

        // Transcript
        if (content.transcript) {
            html += `
                <div class="transcript">
                    <h3>📝 Transcript</h3>
                    <div class="transcript-content">
                        ${content.transcript.split('\n').map(line => 
                            line.trim() ? `<p>${line.trim()}</p>` : ''
                        ).join('')}
                    </div>
                </div>
            `;
        }

        // Steps for hands-on lessons
        if (content.steps) {
            html += `<h2>🛠️ Step-by-Step Instructions</h2>`;
            content.steps.forEach((step, index) => {
                html += `
                    <div class="step-container">
                        <h3>Step ${index + 1}: ${step.title}</h3>
                        <p>${step.description}</p>
                        ${step.code ? `
                            <div class="code-block">
                                <div class="code-header">
                                    <span class="code-language">${step.language || 'text'}</span>
                                    <button class="copy-button" onclick="app.copyCode(this)">Copy</button>
                                </div>
                                <pre><code class="language-${step.language || 'text'}">${this.escapeHtml(step.code)}</code></pre>
                            </div>
                        ` : ''}
                    </div>
                `;
            });
        }

        // Exercise
        if (lesson.type === 'exercise') {
            html += this.renderExercise(lesson);
        }

        // Quiz
        if (lesson.type === 'quiz') {
            html += this.renderQuiz(lesson);
        }

        html += `
                <div class="lesson-actions">
                    <button class="cta-button" onclick="app.markLessonComplete('${lesson.id}')">
                        Mark as Complete ✓
                    </button>
                </div>
            </div>
        `;

        return html;
    }

    renderExercise(lesson) {
        return `
            <div class="exercise-box">
                <div class="exercise-header">
                    🏋️ Exercise: ${lesson.title}
                </div>
                <div class="exercise-content">
                    <p>Complete the following task to practice what you've learned:</p>
                    <ul>
                        <li>Follow the steps outlined above</li>
                        <li>Test your implementation</li>
                        <li>Compare with the provided solution</li>
                    </ul>
                    <button class="cta-button" onclick="app.showSolution('${lesson.id}')">
                        Show Solution
                    </button>
                </div>
            </div>
        `;
    }

    renderQuiz(lesson) {
        return `
            <div class="quiz-box">
                <div class="quiz-question">
                    ❓ Quick Check: What did you learn?
                </div>
                <div class="quiz-options">
                    <label class="quiz-option">
                        <input type="radio" name="quiz" value="a">
                        Laravel 12 requires PHP 8.2 or higher
                    </label>
                    <label class="quiz-option">
                        <input type="radio" name="quiz" value="b">
                        Inertia.js replaces traditional API endpoints
                    </label>
                    <label class="quiz-option">
                        <input type="radio" name="quiz" value="c">
                        Vue 3 Composition API is required for Inertia
                    </label>
                </div>
                <button class="cta-button" onclick="app.checkQuiz()">Check Answer</button>
            </div>
        `;
    }

    updateLessonNavigation() {
        const prevButton = document.getElementById('prev-lesson');
        const nextButton = document.getElementById('next-lesson');
        
        if (!this.currentModule || !this.currentLesson) return;

        const lessons = this.currentModule.lessons_data;
        const currentIndex = lessons.findIndex(l => l.id === this.currentLesson.id);
        
        prevButton.disabled = currentIndex === 0;
        nextButton.disabled = currentIndex === lessons.length - 1;
    }

    previousLesson() {
        if (!this.currentModule || !this.currentLesson) return;
        
        const lessons = this.currentModule.lessons_data;
        const currentIndex = lessons.findIndex(l => l.id === this.currentLesson.id);
        
        if (currentIndex > 0) {
            this.loadLesson(lessons[currentIndex - 1].id);
        }
    }

    nextLesson() {
        if (!this.currentModule || !this.currentLesson) return;
        
        const lessons = this.currentModule.lessons_data;
        const currentIndex = lessons.findIndex(l => l.id === this.currentLesson.id);
        
        if (currentIndex < lessons.length - 1) {
            this.loadLesson(lessons[currentIndex + 1].id);
        }
    }

    markLessonComplete(lessonId) {
        completeLesson(lessonId);
        this.loadLessons(); // Refresh to show completion
        
        // Show success message
        this.showNotification('Lesson completed! 🎉', 'success');
        
        // Auto-advance to next lesson
        setTimeout(() => {
            this.nextLesson();
        }, 1500);
    }

    copyCode(button) {
        const codeBlock = button.closest('.code-block').querySelector('code');
        const text = codeBlock.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = 'Copy';
            }, 2000);
        });
    }

    showSolution(lessonId) {
        this.showNotification('Solution revealed! Check the code examples above.', 'info');
    }

    checkQuiz() {
        const selected = document.querySelector('input[name="quiz"]:checked');
        if (!selected) {
            this.showNotification('Please select an answer first.', 'warning');
            return;
        }
        
        // For demo purposes, assume 'a' is correct
        if (selected.value === 'a') {
            this.showNotification('Correct! Great job! 🎉', 'success');
        } else {
            this.showNotification('Not quite right. Try again!', 'warning');
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        `;
        
        const colors = {
            success: '#48bb78',
            warning: '#ed8936',
            error: '#f56565',
            info: '#4299e1'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update theme toggle icon
        const toggle = document.querySelector('.theme-toggle');
        toggle.textContent = newTheme === 'dark' ? '☀️' : '🌙';
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        const toggle = document.querySelector('.theme-toggle');
        if (toggle) {
            toggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Global functions for onclick handlers
function startCourse() {
    app.openModule(1);
}

function showOverview() {
    app.showOverview();
}

function previousLesson() {
    app.previousLesson();
}

function nextLesson() {
    app.nextLesson();
}

function toggleTheme() {
    app.toggleTheme();
}

// Initialize app
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new CourseApp();
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .info-box {
        background: var(--info-bg, #d1ecf1);
        border: 1px solid var(--info-border, #bee5eb);
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin: 1.5rem 0;
        color: var(--info-text, #0c5460);
    }
    
    .step-container {
        margin: 2rem 0;
        padding: 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        background: var(--card-bg);
    }
    
    .lesson-actions {
        text-align: center;
        margin: 3rem 0;
        padding: 2rem;
        border-top: 1px solid var(--border-color);
    }
    
    .transcript {
        margin: 2rem 0;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border-radius: 0.5rem;
    }
    
    .transcript-content {
        max-height: 300px;
        overflow-y: auto;
        margin-top: 1rem;
    }
    
    .video-container {
        margin: 2rem 0;
        border-radius: 0.5rem;
        overflow: hidden;
        box-shadow: var(--shadow);
    }
`;
document.head.appendChild(style);
