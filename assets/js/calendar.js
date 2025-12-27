// TrackMate Calendar JavaScript - Complete Rewrite

// Authentication Check
let user = null;
try {
    const userData = localStorage.getItem('trackmate_user');
    if (userData) {
        user = JSON.parse(userData);
        console.log('User logged in:', user.username);
    } else {
        console.log('No user found, redirecting to login');
        window.location.href = 'login.html';
    }
} catch (e) {
    console.error('Auth error:', e);
    window.location.href = 'login.html';
}

// Global Variables
let currentDate = new Date();
let selectedDate = null;
let activityData = {};

// Mobile Menu Toggle
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

// Format date as YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Update current date display in header
function updateDate() {
    const now = new Date();
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    const dateEl = document.getElementById('currentDate');
    if (dateEl) {
        dateEl.textContent = dateStr;
    }
}

// Update calendar month title
function updateCalendarMonth() {
    const options = { month: 'long', year: 'numeric' };
    const monthStr = currentDate.toLocaleDateString('en-US', options);
    document.getElementById('calendarMonth').textContent = monthStr;
}

// Load activity data from backend
async function loadActivityData() {
    try {
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const monthStr = `${year}-${month}`;
        
        console.log('Loading calendar activities for month:', monthStr);
        
        const response = await fetch(`api/get-calendar-activities.php?month=${monthStr}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Calendar API response:', result);
        
        if (result.success && result.data) {
            // Convert array to object keyed by date
            activityData = {};
            if (result.data.calendar && Array.isArray(result.data.calendar)) {
                result.data.calendar.forEach(day => {
                    activityData[day.date] = day.categories;
                });
            }
            console.log('Activity data loaded for', Object.keys(activityData).length, 'days');
        } else {
            console.error('API returned error:', result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading calendar activities:', error);
    }
}

// Generate calendar grid
function generateCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const prevLastDay = new Date(year, month, 0);
    
    const firstDayOfWeek = firstDay.getDay();
    const lastDate = lastDay.getDate();
    const prevLastDate = prevLastDay.getDate();
    
    const grid = document.getElementById('calendarGrid');
    if (!grid) {
        console.error('Calendar grid element not found!');
        return;
    }
    
    grid.innerHTML = '';
    
    console.log('Generating calendar for', year, month + 1);
    
    // Previous month days
    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
        const day = createDayElement(prevLastDate - i, true, new Date(year, month - 1, prevLastDate - i));
        grid.appendChild(day);
    }
    
    // Current month days
    for (let i = 1; i <= lastDate; i++) {
        const day = createDayElement(i, false, new Date(year, month, i));
        grid.appendChild(day);
    }
    
    // Next month days
    const remainingDays = 42 - (firstDayOfWeek + lastDate);
    for (let i = 1; i <= remainingDays; i++) {
        const day = createDayElement(i, true, new Date(year, month + 1, i));
        grid.appendChild(day);
    }
    
    console.log('Calendar generated with', grid.children.length, 'days');
}

// Create day element
function createDayElement(dayNumber, isOtherMonth, date) {
    const dayEl = document.createElement('div');
    dayEl.className = 'calendar-day';
    
    if (isOtherMonth) {
        dayEl.classList.add('other-month');
    }
    
    // Check if today
    const today = new Date();
    if (date.toDateString() === today.toDateString()) {
        dayEl.classList.add('today');
    }
    
    // Check if selected
    if (selectedDate && date.toDateString() === selectedDate.toDateString()) {
        dayEl.classList.add('selected');
    }
    
    const dayNum = document.createElement('div');
    dayNum.className = 'day-number';
    dayNum.textContent = dayNumber;
    dayEl.appendChild(dayNum);
    
    // Add activity dots
    const dateStr = formatDate(date);
    if (activityData[dateStr] && activityData[dateStr].length > 0) {
        const activitiesContainer = document.createElement('div');
        activitiesContainer.className = 'day-activities';
        
        // Category colors
        const categoryColors = {
            1: 'phone',      // Phone Usage - Cyan
            2: 'work',       // Working - Orange
            3: 'phone-work', // Phone + Work - Yellow
            4: 'sleep',      // Sleeping - Purple
            5: 'food',       // Eating - Green
            6: 'drink',      // Drinking - Teal
            7: 'other'       // Other - Gray
        };
        
        // Show up to 3 activity dots
        const categories = activityData[dateStr].slice(0, 3);
        categories.forEach(cat => {
            const dot = document.createElement('div');
            const colorClass = categoryColors[cat.category] || 'other';
            dot.className = `activity-dot ${colorClass}`;
            activitiesContainer.appendChild(dot);
        });
        
        dayEl.appendChild(activitiesContainer);
    }
    
    // Click handler
    dayEl.addEventListener('click', () => {
        selectDate(date);
    });
    
    return dayEl;
}

// Select date
function selectDate(date) {
    selectedDate = date;
    generateCalendar(); // Refresh to show selection
    displayActivities(date);
}

// Display activities for selected date
async function displayActivities(date) {
    const dateStr = formatDate(date);
    const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    const formattedDate = date.toLocaleDateString('en-US', options);
    
    document.getElementById('selectedDate').textContent = formattedDate;
    
    const detailsContent = document.getElementById('detailsContent');
    const dailyStats = document.getElementById('dailyStats');
    
    // Show loading
    detailsContent.innerHTML = '<div style="text-align:center; padding:20px;">Loading...</div>';
    
    try {
        console.log('Fetching activities for date:', dateStr);
        
        const response = await fetch(`api/get-activity-stats.php?period=today&date=${dateStr}`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log('Activity stats response:', result);
        
        if (!result.success) {
            throw new Error(result.error || 'API returned error');
        }
        
        if (result.data && result.data.recent_activities && result.data.recent_activities.length > 0) {
            const activities = result.data.recent_activities.sort((a, b) => 
                new Date(b.detected_at) - new Date(a.detected_at)
            );
            
            const activityIcons = {
                1: '📱', 2: '💻', 3: '📱💻', 4: '😴', 5: '🍽️', 6: '🥤', 7: '🔄'
            };
            
            detailsContent.innerHTML = `
                <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    ${activities.map(activity => {
                        const time = new Date(activity.detected_at);
                        const icon = activityIcons[activity.category] || '🔄';
                        const description = activity.description ? 
                            activity.description.substring(0, 100) + (activity.description.length > 100 ? '...' : '') : '';
                        
                        return `
                            <div class="activity-entry">
                                <div class="activity-time">${time.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</div>
                                <div class="activity-details">
                                    <div class="activity-name">${icon} ${activity.activity_type}</div>
                                    ${description ? `<div class="activity-note">${description}</div>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
            
            // Show stats
            if (result.data.summary && result.data.summary.length > 0) {
                dailyStats.style.display = 'block';
                const statsHTML = result.data.summary.map(stat => {
                    const icon = activityIcons[stat.category] || '🔄';
                    return `
                        <div class="stat-item">
                            <span class="stat-icon">${icon}</span>
                            <div class="stat-info">
                                <div class="stat-value">${stat.hours}h</div>
                                <div class="stat-label">${stat.name}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                dailyStats.innerHTML = `
                    <h4>Daily Summary</h4>
                    <div class="stats-grid">${statsHTML}</div>
                `;
            } else {
                dailyStats.style.display = 'none';
            }
        } else {
            detailsContent.innerHTML = `
                <div class="no-selection">
                    <span class="no-selection-icon">📭</span>
                    <p class="no-activities">No activities recorded for this day</p>
                </div>
            `;
            dailyStats.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading day activities:', error);
        detailsContent.innerHTML = `
            <div class="no-selection">
                <span class="no-selection-icon">⚠️</span>
                <p class="no-activities">Error loading activities: ${error.message}</p>
            </div>
        `;
        dailyStats.style.display = 'none';
    }
}

// Navigation functions
function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendarMonth();
    loadActivityData().then(() => generateCalendar());
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendarMonth();
    loadActivityData().then(() => generateCalendar());
}

function goToToday() {
    currentDate = new Date();
    selectedDate = new Date();
    updateCalendarMonth();
    loadActivityData().then(() => {
        generateCalendar();
        displayActivities(selectedDate);
    });
}

// Add activity placeholder
function addActivity() {
    if (!selectedDate) {
        alert('Please select a date first');
        return;
    }
    alert('Add activity feature coming soon!');
}

// Logout
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Calendar initializing...');
    
    updateDate();
    updateCalendarMonth();
    
    // Load activity data first
    await loadActivityData();
    
    // Generate calendar with data
    generateCalendar();
    
    // Auto-select today and display activities
    selectedDate = new Date();
    displayActivities(selectedDate);
    
    // Close mobile menu when clicking nav links
    const navLinks = document.querySelectorAll('.sidebar .nav-item');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768 && typeof toggleMobileMenu === 'function') {
                toggleMobileMenu();
            }
        });
    });
    
    console.log('Calendar initialized successfully');
});
