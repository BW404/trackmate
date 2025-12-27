// TrackMate Reports JavaScript

// Check authentication
let user = null;
try {
    const userData = localStorage.getItem('trackmate_user');
    if (userData) {
        user = JSON.parse(userData);
    } else {
        window.location.href = 'login.html';
    }
} catch (e) {
    window.location.href = 'login.html';
}

// Mobile menu toggle
function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileMenuOverlay');
    const hamburger = document.getElementById('hamburgerBtn');
    
    if (sidebar && overlay && hamburger) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        hamburger.classList.toggle('active');
        
        if (sidebar.classList.contains('mobile-open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

// Chart instances
let dailyChart = null;
let weeklyChart = null;
let monthlyChart = null;
let yearlyChart = null;

// Current period
let currentPeriod = 'daily';
let currentReportData = null;

// Activity category mapping
const ACTIVITY_CATEGORIES = {
    1: { name: 'Using phone', icon: '📱', color: '#FF9F66' },
    2: { name: 'Working', icon: '💻', color: '#48BB78' },
    3: { name: 'Using phone while working', icon: '📱💻', color: '#F6AD55' },
    4: { name: 'Sleeping', icon: '😴', color: '#9F7AEA' },
    5: { name: 'Eating', icon: '🍽️', color: '#FC8181' },
    6: { name: 'Drinking', icon: '🥤', color: '#63B3ED' },
    7: { name: 'Other', icon: '🤷', color: '#A0AEC0' }
};

// Update date
function updateDate() {
    const now = new Date();
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    document.getElementById('currentDate').textContent = dateStr;
}

// Initialize date inputs
function initializeDateInputs() {
    const today = new Date();
    
    // Daily date
    const dailyInput = document.getElementById('dailyDate');
    if (dailyInput) {
        dailyInput.value = today.toISOString().split('T')[0];
    }
    
    // Weekly date
    const weeklyInput = document.getElementById('weeklyDate');
    if (weeklyInput) {
        const year = today.getFullYear();
        const week = getWeekNumber(today);
        weeklyInput.value = `${year}-W${week.toString().padStart(2, '0')}`;
    }
    
    // Monthly date
    const monthlyInput = document.getElementById('monthlyDate');
    if (monthlyInput) {
        const year = today.getFullYear();
        const month = (today.getMonth() + 1).toString().padStart(2, '0');
        monthlyInput.value = `${year}-${month}`;
    }
    
    // Yearly date
    const yearlyInput = document.getElementById('yearlyDate');
    if (yearlyInput) {
        yearlyInput.value = today.getFullYear().toString();
    }
}

// Get week number
function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
}

// Load report data from backend
async function loadReportData(period = 'daily', compare = false) {
    try {
        const compareParam = compare ? '&compare=true' : '';
        const response = await fetch(`api/get-reports.php?period=${period}${compareParam}`, {
            credentials: 'include'
        });
        
        const result = await response.json();
        
        if (result.success && result.data) {
            currentReportData = result.data;
            updateSummaryCards(result.data);
            updateReportContent(period, result.data);
        } else {
            console.error('Failed to load report:', result.error);
            showError('Failed to load report data');
        }
    } catch (error) {
        console.error('Error loading report data:', error);
        showError('Connection error. Please try again.');
    }
}

// Update summary cards
function updateSummaryCards(data) {
    // Handle both single report and comparison data
    const reportData = data.current || data;
    
    // Total hours
    document.getElementById('totalHours').innerHTML = 
        `${reportData.total_hours} <span>hours</span>`;
    
    // Work hours
    const workItem = reportData.summary.find(s => s.category === 2);
    const workHours = workItem ? workItem.hours : 0;
    document.getElementById('workHours').innerHTML = 
        `${workHours} <span>hours</span>`;
    
    // Phone hours  
    const phoneItems = reportData.summary.filter(s => s.category === 1 || s.category === 3);
    const phoneHours = phoneItems.reduce((sum, item) => sum + item.hours, 0);
    document.getElementById('phoneHours').innerHTML = 
        `${phoneHours.toFixed(1)} <span>hours</span>`;
    
    // Productivity
    document.getElementById('productivityScore').innerHTML = 
        `${reportData.productivity.score}<span>%</span>`;
    
    // Total activities
    document.getElementById('totalActivities').innerHTML = 
        `${reportData.total_activities} <span>logged</span>`;
    
    // Update change indicators if comparison data available
    if (data.changes) {
        updateChangeIndicators(data.changes);
    }
}

// Update change indicators
function updateChangeIndicators(changes) {
    // Total hours change
    const hoursChangeEl = document.getElementById('totalHoursChange');
    if (hoursChangeEl) {
        const sign = changes.hours >= 0 ? '+' : '';
        const color = changes.hours >= 0 ? '#48BB78' : '#F56565';
        hoursChangeEl.innerHTML = `<small style="color: ${color}">${sign}${changes.hours}h (${sign}${changes.hours_percent}%)</small>`;
    }
    
    // Productivity change
    const prodChangeEl = document.getElementById('productivityChange');
    if (prodChangeEl) {
        const sign = changes.productivity >= 0 ? '+' : '';
        const color = changes.productivity >= 0 ? '#48BB78' : '#F56565';
        prodChangeEl.innerHTML = `<small style="color: ${color}">${sign}${changes.productivity}%</small>`;
    }
}

// Update report content based on period
function updateReportContent(period, data) {
    const reportData = data.current || data;
    
    switch (period) {
        case 'daily':
            updateDailyReport(reportData);
            break;
        case 'weekly':
            updateWeeklyReport(reportData);
            break;
        case 'monthly':
            updateMonthlyReport(reportData);
            break;
        case 'yearly':
            updateYearlyReport(reportData);
            break;
    }
}

// Update daily report
function updateDailyReport(data) {
    // Update chart
    if (dailyChart) {
        dailyChart.destroy();
    }
    
    const ctx = document.getElementById('dailyChart');
    if (ctx) {
        dailyChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.summary.map(s => ACTIVITY_CATEGORIES[s.category]?.name || s.name),
                datasets: [{
                    data: data.summary.map(s => s.hours),
                    backgroundColor: data.summary.map(s => ACTIVITY_CATEGORIES[s.category]?.color || '#A0AEC0'),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value}h (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Update activity list
    updateActivityList('dailyActivities', data.summary);
    
    // Update stats
    updateDailyStats(data);
}

// Update weekly report
function updateWeeklyReport(data) {
    if (weeklyChart) {
        weeklyChart.destroy();
    }
    
    const ctx = document.getElementById('weeklyChart');
    if (ctx && data.time_series) {
        const labels = Object.keys(data.time_series);
        const datasets = createTimeSeriesDatasets(data.time_series);
        
        weeklyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            callback: function(value) {
                                return value + 'h';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Update monthly report
function updateMonthlyReport(data) {
    if (monthlyChart) {
        monthlyChart.destroy();
    }
    
    const ctx = document.getElementById('monthlyChart');
    if (ctx && data.time_series) {
        const labels = Object.keys(data.time_series);
        const datasets = createTimeSeriesDatasets(data.time_series);
        
        monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + 'h';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Update yearly report
function updateYearlyReport(data) {
    if (yearlyChart) {
        yearlyChart.destroy();
    }
    
    const ctx = document.getElementById('yearlyChart');
    if (ctx && data.time_series) {
        const labels = Object.keys(data.time_series);
        const datasets = createTimeSeriesDatasets(data.time_series);
        
        yearlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        ticks: {
                            callback: function(value) {
                                return value + 'h';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Create datasets for time series charts
function createTimeSeriesDatasets(timeSeries) {
    const categoriesMap = new Map();
    
    // Collect all categories and their data
    Object.entries(timeSeries).forEach(([label, activities]) => {
        activities.forEach(activity => {
            if (!categoriesMap.has(activity.category)) {
                categoriesMap.set(activity.category, {
                    label: ACTIVITY_CATEGORIES[activity.category]?.name || activity.name,
                    data: [],
                    backgroundColor: ACTIVITY_CATEGORIES[activity.category]?.color || '#A0AEC0',
                    borderColor: ACTIVITY_CATEGORIES[activity.category]?.color || '#A0AEC0',
                    borderWidth: 2
                });
            }
        });
    });
    
    // Fill data for each category
    const labels = Object.keys(timeSeries);
    categoriesMap.forEach((dataset, category) => {
        dataset.data = labels.map(label => {
            const activity = timeSeries[label].find(a => a.category === category);
            return activity ? activity.hours : 0;
        });
    });
    
    return Array.from(categoriesMap.values());
}

// Update activity list
function updateActivityList(containerId, summary) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (summary.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #A0AEC0; padding: 2rem;">No activities logged yet</p>';
        return;
    }
    
    summary.forEach(item => {
        const category = ACTIVITY_CATEGORIES[item.category] || { icon: '🤷', name: item.name };
        
        const activityItem = document.createElement('div');
        activityItem.className = 'activity-item';
        activityItem.innerHTML = `
            <div class="activity-info">
                <span class="activity-icon">${category.icon}</span>
                <span class="activity-name">${category.name}</span>
            </div>
            <div class="activity-stats">
                <span class="activity-duration">${item.hours}h</span>
                <span class="activity-percentage">${item.percentage}%</span>
            </div>
        `;
        
        container.appendChild(activityItem);
    });
}

// Update daily stats
function updateDailyStats(data) {
    const statsGrid = document.querySelector('#dailyReport .stats-grid');
    if (statsGrid) {
        statsGrid.innerHTML = `
            <div class="stat-card">
                <div class="stat-label">Productive Time</div>
                <div class="stat-value">${data.productivity.productive_hours}h</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unproductive Time</div>
                <div class="stat-value">${data.productivity.unproductive_hours}h</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Activities</div>
                <div class="stat-value">${data.total_activities}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Focus Score</div>
                <div class="stat-value">${data.productivity.score}%</div>
            </div>
        `;
    }
}

// Show report tab
function showReportTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Hide all sections
    document.querySelectorAll('.report-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    const sectionId = `${tab}Report`;
    const section = document.getElementById(sectionId);
    if (section) {
        section.classList.add('active');
    }
    
    // Load data for this period
    currentPeriod = tab;
    loadReportData(tab, true);
}

// Navigate date
function navigateDate(period, direction) {
    const input = document.getElementById(`${period}Date`);
    if (!input) return;
    
    // Handle different input types
    switch (period) {
        case 'daily':
            const currentDaily = new Date(input.value);
            currentDaily.setDate(currentDaily.getDate() + direction);
            input.value = currentDaily.toISOString().split('T')[0];
            loadReportData('daily', true);
            break;
            
        case 'weekly':
            const [year, week] = input.value.split('-W').map(Number);
            const newWeek = week + direction;
            input.value = `${year}-W${newWeek.toString().padStart(2, '0')}`;
            loadReportData('weekly', true);
            break;
            
        case 'monthly':
            const [yearM, month] = input.value.split('-').map(Number);
            const newDate = new Date(yearM, month - 1 + direction, 1);
            input.value = `${newDate.getFullYear()}-${(newDate.getMonth() + 1).toString().padStart(2, '0')}`;
            loadReportData('monthly', true);
            break;
            
        case 'yearly':
            input.value = (parseInt(input.value) + direction).toString();
            loadReportData('yearly', true);
            break;
    }
}

// Export report
function exportReport() {
    if (!currentReportData) {
        alert('No data to export');
        return;
    }
    
    // Create CSV content
    let csv = 'Activity,Hours,Percentage,Count\n';
    const data = currentReportData.current || currentReportData;
    
    data.summary.forEach(item => {
        const category = ACTIVITY_CATEGORIES[item.category] || { name: item.name };
        csv += `"${category.name}",${item.hours},${item.percentage}%,${item.count}\n`;
    });
    
    csv += `\nTotal,${data.total_hours},100%,${data.total_activities}\n`;
    csv += `Productivity Score,${data.productivity.score}%,,\n`;
    
    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `trackmate-report-${currentPeriod}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
}

// Show error message
function showError(message) {
    console.error(message);
    alert(message);
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('api/logout.php', {
            method: 'POST',
            credentials: 'include'
        }).then(() => {
            localStorage.clear();
            window.location.href = 'login.html';
        }).catch(error => {
            console.error('Logout error:', error);
            localStorage.clear();
            window.location.href = 'login.html';
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateDate();
    initializeDateInputs();
    
    // Load initial report (daily with comparison)
    loadReportData('daily', true);
});
