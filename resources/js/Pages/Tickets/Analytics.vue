<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Chart, registerables } from 'chart.js';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';

Chart.register(...registerables);

const props = defineProps({
    canManage: Boolean,
    allUsers: Array,
    allTags: Array,
    allStatuses: Array,
    filters: Object,
});

const loading = ref(false);
const analyticsData = ref(null);
const viewMode = ref('table');
const chartInstances = ref({});

const dateFrom = ref(props.filters?.date_from || getDefaultDateFrom());
const dateTo = ref(props.filters?.date_to || new Date().toISOString().split('T')[0]);
const selectedStatuses = ref(props.filters?.status ? (Array.isArray(props.filters.status) ? props.filters.status : props.filters.status.split(',')) : []);
const selectedTags = ref(props.filters?.tags ? (Array.isArray(props.filters.tags) ? props.filters.tags : props.filters.tags.split(',')) : []);
const selectedAssignees = ref(props.filters?.assignee ? (Array.isArray(props.filters.assignee) ? props.filters.assignee : props.filters.assignee.split(',').map(Number)) : []);
const searchQuery = ref('');

function getDefaultDateFrom() {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
}

const filteredTickets = computed(() => {
    if (!analyticsData.value?.tickets) return [];
    let tickets = analyticsData.value.tickets;
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        tickets = tickets.filter(ticket => 
            ticket.title.toLowerCase().includes(query) ||
            ticket.id.toString().includes(query) ||
            ticket.submitter.toLowerCase().includes(query)
        );
    }
    return tickets;
});

const metrics = computed(() => analyticsData.value?.metrics || {});

async function fetchAnalytics() {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            date_from: dateFrom.value,
            date_to: dateTo.value,
        });
        if (selectedStatuses.value.length > 0) params.append('status', selectedStatuses.value.join(','));
        if (selectedTags.value.length > 0) params.append('tags', selectedTags.value.join(','));
        if (props.canManage && selectedAssignees.value.length > 0) params.append('assignee', selectedAssignees.value.join(','));
        
        const response = await fetch(`/api/tickets/analytics/data?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const result = await response.json();
        if (result.success) {
            analyticsData.value = result.data;
            // Only render charts if we're in chart mode
            if (viewMode.value === 'chart') {
                destroyCharts();
                await nextTick();
                renderCharts();
            }
        }
    } catch (error) {
        console.error('Failed to fetch analytics:', error);
    } finally {
        loading.value = false;
    }
}

function renderCharts() {
    destroyCharts();
    if (!analyticsData.value?.metrics) return;
    
    // Use setTimeout to ensure canvas elements are fully mounted
    setTimeout(() => {
        renderStatusChart();
        renderTicketsOverTimeChart();
        renderTagDistributionChart();
        if (props.canManage && analyticsData.value.metrics.assignee_performance?.length > 0) {
            renderAssigneePerformanceChart();
        }
    }, 100);
}

function destroyCharts() {
    // Destroy all chart instances
    Object.values(chartInstances.value).forEach(chart => {
        if (chart && typeof chart.destroy === 'function') {
            try {
                chart.destroy();
            } catch (e) {
                console.warn('Error destroying chart:', e);
            }
        }
    });
    chartInstances.value = {};
    
    // Also destroy any charts that might be attached to canvas elements directly
    const canvasIds = ['statusChart', 'ticketsOverTimeChart', 'tagDistributionChart', 'assigneePerformanceChart'];
    canvasIds.forEach(id => {
        const canvas = document.getElementById(id);
        if (canvas) {
            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }
        }
    });
}

function renderStatusChart() {
    const canvas = document.getElementById('statusChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const data = metrics.value.status_breakdown || {};
    chartInstances.value.status = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: ['#3b82f6', '#10b981', '#ef4444'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Status Breakdown' } }
        }
    });
}

function renderTicketsOverTimeChart() {
    const canvas = document.getElementById('ticketsOverTimeChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const data = metrics.value.tickets_over_time || {};
    chartInstances.value.timeline = new Chart(ctx, {
        type: 'line',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Tickets Created',
                data: Object.values(data),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, title: { display: true, text: 'Tickets Over Time' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function renderTagDistributionChart() {
    const canvas = document.getElementById('tagDistributionChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const data = metrics.value.tag_distribution || {};
    const sortedTags = Object.entries(data).sort((a, b) => b[1] - a[1]).slice(0, 10);
    chartInstances.value.tags = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sortedTags.map(([tag]) => tag),
            datasets: [{ label: 'Ticket Count', data: sortedTags.map(([, count]) => count), backgroundColor: '#8b5cf6' }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, title: { display: true, text: 'Top Tags' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function renderAssigneePerformanceChart() {
    const canvas = document.getElementById('assigneePerformanceChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const data = metrics.value.assignee_performance || [];
    chartInstances.value.assignees = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(a => a.name),
            datasets: [
                { label: 'Total Tickets', data: data.map(a => a.total), backgroundColor: '#3b82f6' },
                { label: 'Completed', data: data.map(a => a.completed), backgroundColor: '#10b981' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Assignee Performance' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function addCanvasToPdf(doc, canvas, x, y, maxWidth, maxHeight, scale = 2) {
    if (!canvas) return y;

    const origWidth = canvas.width || canvas.offsetWidth;
    const origHeight = canvas.height || canvas.offsetHeight;
    if (!origWidth || !origHeight) return y;

    const aspect = origHeight / origWidth;
    let renderWidth = maxWidth;
    let renderHeight = renderWidth * aspect;
    if (renderHeight > maxHeight) {
        renderHeight = maxHeight;
        renderWidth = renderHeight / aspect;
    }

    // Render to a higher‑resolution temporary canvas to reduce pixelation
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = origWidth * scale;
    exportCanvas.height = origHeight * scale;
    const ctx = exportCanvas.getContext('2d');
    if (!ctx) return y;
    ctx.scale(scale, scale);
    ctx.drawImage(canvas, 0, 0);

    const imgData = exportCanvas.toDataURL('image/png');
    doc.addImage(imgData, 'PNG', x, y, renderWidth, renderHeight);
    return y + renderHeight;
}

async function exportToPDF() {
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    let yPosition = 20;
    
    doc.setFontSize(20);
    doc.setTextColor(40);
    doc.text('Ticket Analytics Report', pageWidth / 2, yPosition, { align: 'center' });
    yPosition += 10;
    
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(`Date Range: ${dateFrom.value} to ${dateTo.value}`, pageWidth / 2, yPosition, { align: 'center' });
    yPosition += 15;
    
    doc.setFontSize(14);
    doc.setTextColor(40);
    doc.text('Summary Metrics', 14, yPosition);
    yPosition += 8;
    
    doc.setFontSize(10);
    const metricsData = [
        ['New Tickets', metrics.value.new_tickets || 0],
        ['Completed Tickets', metrics.value.completed_tickets || 0],
        ['Rejected Tickets', metrics.value.rejected_tickets || 0],
        ['Total Tickets', metrics.value.total_tickets || 0],
        ['Average Time Spent', metrics.value.avg_time_spent || 'N/A'],
    ];
    if (props.canManage && metrics.value.total_users) metricsData.push(['Total Users', metrics.value.total_users]);
    
    autoTable(doc, {
        startY: yPosition,
        head: [['Metric', 'Value']],
        body: metricsData,
        theme: 'striped',
        headStyles: { fillColor: [220, 38, 38] },
    });
    yPosition = doc.lastAutoTable.finalY + 15;
    
    if (viewMode.value === 'chart') {
        const statusCanvas = document.getElementById('statusChart');
        if (statusCanvas && yPosition < pageHeight - 90) {
            doc.setFontSize(12);
            doc.text('Status Breakdown', 14, yPosition);
            yPosition += 5;
            yPosition = addCanvasToPdf(doc, statusCanvas, 14, yPosition, 80, 80);
            yPosition += 10;
        }
        if (yPosition > pageHeight - 80) {
            doc.addPage();
            yPosition = 20;
        }
        const timelineCanvas = document.getElementById('ticketsOverTimeChart');
        if (timelineCanvas) {
            doc.setFontSize(12);
            doc.text('Tickets Over Time', 14, yPosition);
            yPosition += 5;
            yPosition = addCanvasToPdf(doc, timelineCanvas, 14, yPosition, pageWidth - 28, 80);
            yPosition += 10;
        }
    }
    
    doc.addPage();
    yPosition = 20;
    doc.setFontSize(14);
    doc.text('Ticket Details', 14, yPosition);
    yPosition += 8;
    
    const tableData = filteredTickets.value.map(ticket => [
        ticket.id,
        ticket.title.substring(0, 30) + (ticket.title.length > 30 ? '...' : ''),
        ticket.status,
        ticket.time_spent,
        ticket.submitter,
    ]);
    
    autoTable(doc, {
        startY: yPosition,
        head: [['ID', 'Title', 'Status', 'Time Spent', 'Submitter']],
        body: tableData,
        theme: 'striped',
        headStyles: { fillColor: [220, 38, 38] },
        styles: { fontSize: 8 },
        columnStyles: { 0: { cellWidth: 15 }, 1: { cellWidth: 60 }, 2: { cellWidth: 25 }, 3: { cellWidth: 35 }, 4: { cellWidth: 40 } },
    });
    
    const filename = `ticket-analytics-${dateFrom.value}-to-${dateTo.value}.pdf`;
    doc.save(filename);
}

function applyFilters() { fetchAnalytics(); }
function resetFilters() {
    dateFrom.value = getDefaultDateFrom();
    dateTo.value = new Date().toISOString().split('T')[0];
    selectedStatuses.value = [];
    selectedTags.value = [];
    selectedAssignees.value = [];
    searchQuery.value = '';
    fetchAnalytics();
}

async function toggleViewMode(mode) {
    if (mode === 'chart') {
        destroyCharts();
        viewMode.value = mode;
        await nextTick();
        renderCharts();
    } else {
        destroyCharts();
        viewMode.value = mode;
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString();
}

function getStatusColor(status) {
    const colors = {
        'Received': 'bg-blue-100 text-blue-800',
        'Completed': 'bg-green-100 text-green-800',
        'Rejected': 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

onMounted(() => { fetchAnalytics(); });

onBeforeUnmount(() => {
    destroyCharts();
});
</script>

<template>
    <Head title="Ticket Analytics" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-200 leading-tight">Ticket Analytics</h2>
                <div class="flex items-center space-x-3">
                    <div class="inline-flex rounded-lg shadow-sm">
                        <button @click="toggleViewMode('table')" :class="['px-4 py-2 text-sm font-medium rounded-l-lg border transition-all duration-200 focus:z-10 focus:ring-2 focus:ring-uh-red', viewMode === 'table' ? 'bg-uh-red border-uh-red text-white' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700']">
                            <font-awesome-icon :icon="['fas', 'table']" class="mr-2" />Table
                        </button>
                        <button @click="toggleViewMode('chart')" :class="['px-4 py-2 text-sm font-medium rounded-r-lg border-t border-b border-r transition-all duration-200 focus:z-10 focus:ring-2 focus:ring-uh-red', viewMode === 'chart' ? 'bg-uh-red border-uh-red text-white' : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700']">
                            <font-awesome-icon :icon="['fas', 'chart-bar']" class="mr-2" />Charts
                        </button>
                    </div>
                    <button @click="exportToPDF" :disabled="loading || !analyticsData" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg font-semibold text-sm text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-uh-red focus:ring-offset-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <font-awesome-icon :icon="['fas', 'file-pdf']" class="mr-2 text-red-600" />Export PDF
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center">
                                <font-awesome-icon :icon="['fas', 'filter']" class="mr-2 text-uh-red" />
                                Filters
                            </h3>
                            <div class="flex items-center space-x-3">
                                <button @click="resetFilters" :disabled="loading" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline transition-colors disabled:opacity-50">
                                    Reset
                                </button>
                                <button @click="applyFilters" :disabled="loading" class="inline-flex items-center px-4 py-2 bg-uh-red border border-transparent rounded-lg font-semibold text-sm text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all disabled:opacity-50">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div class="space-y-1">
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Date Range</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input v-model="dateFrom" type="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-uh-red focus:ring-uh-red text-sm h-[42px]" />
                                    <input v-model="dateTo" type="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-uh-red focus:ring-uh-red text-sm h-[42px]" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <MultiSelect 
                                    v-model="selectedStatuses" 
                                    :options="allStatuses" 
                                    label="Status" 
                                    placeholder="All Statuses" 
                                />
                            </div>
                            <div class="space-y-1">
                                <MultiSelect 
                                    v-model="selectedTags" 
                                    :options="allTags" 
                                    label="Tags" 
                                    placeholder="All Tags" 
                                />
                            </div>
                            <div v-if="canManage" class="space-y-1">
                                <MultiSelect 
                                    v-model="selectedAssignees" 
                                    :options="allUsers.map(u => ({ label: u.name, value: u.id }))" 
                                    label="Assignees" 
                                    placeholder="All Assignees" 
                                />
                            </div>
                            <div v-if="viewMode === 'table'" class="md:col-span-2 lg:col-span-4 space-y-1">
                                <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Search</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <font-awesome-icon :icon="['fas', 'search']" class="text-gray-400" />
                                    </div>
                                    <input v-model="searchQuery" type="text" placeholder="Search by title, ID, or submitter..." class="w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-uh-red focus:ring-uh-red text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="loading" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-12 text-center">
                        <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-4xl text-uh-red mb-4" />
                        <p class="text-gray-600 dark:text-gray-400">Loading analytics data...</p>
                    </div>
                </div>

                <div v-if="!loading && analyticsData" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 group">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">New Tickets</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 group-hover:text-uh-red transition-colors">{{ metrics.new_tickets || 0 }}</p>
                                </div>
                                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
                                    <font-awesome-icon :icon="['fas', 'inbox']" class="text-2xl text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 group">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completed</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 group-hover:text-green-600 transition-colors">{{ metrics.completed_tickets || 0 }}</p>
                                </div>
                                <div class="p-3 bg-green-50 dark:bg-green-900/30 rounded-xl group-hover:bg-green-100 dark:group-hover:bg-green-900/50 transition-colors">
                                    <font-awesome-icon :icon="['fas', 'check-circle']" class="text-2xl text-green-600 dark:text-green-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 group">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rejected</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 group-hover:text-red-600 transition-colors">{{ metrics.rejected_tickets || 0 }}</p>
                                </div>
                                <div class="p-3 bg-red-50 dark:bg-red-900/30 rounded-xl group-hover:bg-red-100 dark:group-hover:bg-red-900/50 transition-colors">
                                    <font-awesome-icon :icon="['fas', 'times-circle']" class="text-2xl text-red-600 dark:text-red-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 group">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Time</p>
                                    <p class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 group-hover:text-purple-600 transition-colors">{{ metrics.avg_time_spent || 'N/A' }}</p>
                                </div>
                                <div class="p-3 bg-purple-50 dark:bg-purple-900/30 rounded-xl group-hover:bg-purple-100 dark:group-hover:bg-purple-900/50 transition-colors">
                                    <font-awesome-icon :icon="['fas', 'clock']" class="text-2xl text-purple-600 dark:text-purple-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="canManage && metrics.total_users" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 hover:shadow-md transition-all duration-300 group">
                        <div class="p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100 mt-2 group-hover:text-indigo-600 transition-colors">{{ metrics.total_users }}</p>
                                </div>
                                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 transition-colors">
                                    <font-awesome-icon :icon="['fas', 'users']" class="text-2xl text-indigo-600 dark:text-indigo-400" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!loading && analyticsData && viewMode === 'chart'" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                            <canvas id="statusChart" height="300"></canvas>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                            <canvas id="tagDistributionChart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                        <canvas id="ticketsOverTimeChart" height="200"></canvas>
                    </div>
                    <div v-if="canManage && metrics.assignee_performance?.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700 p-6">
                        <canvas id="assigneePerformanceChart" height="300"></canvas>
                    </div>
                </div>

                <div v-if="!loading && analyticsData && viewMode === 'table'" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-100 dark:border-gray-700">
                    <div class="p-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <font-awesome-icon :icon="['fas', 'list']" class="mr-2 text-uh-red" />
                            Ticket Details
                        </h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Title</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Created</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Time Spent</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Submitter</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider sticky top-0 bg-gray-50 dark:bg-gray-900">Assignees</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="ticket in filteredTickets" :key="ticket.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">#{{ ticket.id }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 font-medium">{{ ticket.title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="['px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full shadow-sm', getStatusColor(ticket.status)]">
                                                {{ ticket.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(ticket.created_at) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">{{ ticket.time_spent }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div class="flex items-center">
                                                <div class="h-6 w-6 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-xs font-bold mr-2">
                                                    {{ ticket.submitter.charAt(0).toUpperCase() }}
                                                </div>
                                                {{ ticket.submitter }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ ticket.assignees || 'Unassigned' }}</td>
                                    </tr>
                                    <tr v-if="filteredTickets.length === 0">
                                        <td colspan="7" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <font-awesome-icon :icon="['fas', 'search']" class="text-4xl text-gray-300 dark:text-gray-600 mb-4" />
                                                <p class="text-lg font-medium">No tickets found</p>
                                                <p class="text-sm mt-1">Try adjusting your filters or search query.</p>
                                                <button @click="resetFilters" class="mt-4 text-uh-red hover:text-red-700 font-medium text-sm">Clear all filters</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
