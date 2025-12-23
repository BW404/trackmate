// TrackMate Calendar JavaScript

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

let currentDate = new Date();
let selectedDate = null;

// Mock activity data
const mockActivities = {
    '2025-12-20': [
        { type: 'study', duration: 120, notes: 'Math homework' },
        { type: 'meditation', duration: 30, notes: 'Morning meditation' },
        { type: 'exercise', duration: 45, notes: 'Gym workout' }
    ],
    '2025-12-21': [
        { type: 'study', duration: 180, notes: 'Physics revision' },
        { type: 'code', duration: 90, notes: 'Web development project' }
    ],
    '2025-12-22': [
        { type: 'study', duration: 150, notes: 'Chemistry lab report' },
        { type: 'meditation', duration: 20, notes: 'Evening meditation' },
        { type: 'screen', duration: 60, notes: 'Social media' }
    ]
};

// Update date display
function updateDate() {
    const now = new Date();
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    document.getElementById('currentDate').textContent = dateStr;
}

// Update calendar month display
function updateCalendarMonth() {
    const options = { month: 'long', year: 'numeric' };
    const monthStr = currentDate.toLocaleDateString('en-US', options);
    document.getElementById('calendarMonth').textContent = monthStr;
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
    grid.innerHTML = '';
    
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
    if (mockActivities[dateStr]) {
        const activitiesContainer = document.createElement('div');
        activitiesContainer.className = 'day-activities';
        
        mockActivities[dateStr].forEach(activity => {
            const dot = document.createElement('div');
            dot.className = `activity-dot ${activity.type}`;
            activitiesContainer.appendChild(dot);
        });
        
        dayEl.appendChild(activitiesContainer);
    }
    
    // Click handler
    dayEl.addEventListener('click', () => selectDate(date));
    
    return dayEl;
}

// Format date to YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Select date
function selectDate(date) {
    selectedDate = date;
    generateCalendar();
    displayActivities(date);
}

// Display activities for selected date
function displayActivities(date) {
    const dateStr = formatDate(date);
    const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    const formattedDate = date.toLocaleDateString('en-US', options);
    
    document.getElementById('selectedDate').textContent = formattedDate;
    
    const activities = mockActivities[dateStr] || [];
    const detailsContent = document.getElementById('detailsContent');
    const dailyStats = document.getElementById('dailyStats');
    
    if (activities.length === 0) {
        detailsContent.innerHTML = `
            <div class="no-selection">
                <span class="no-selection-icon">📝</span>
                <p>No activities recorded for this date</p>
            </div>
        `;
        dailyStats.style.display = 'none';
    } else {
        const activityList = document.createElement('div');
        activityList.className = 'activity-list';
        
        activities.forEach(activity => {
            const item = document.createElement('div');
            item.className = `activity-item ${activity.type}`;
            
            const activityIcons = {
                study: '📚',
                meditation: '🧘',
                exercise: '💪',
                sleep: '😴',
                screen: '📱',
                code: '💻'
            };
            
            const activityNames = {
                study: 'Study',
                meditation: 'Meditation',
                exercise: 'Exercise',
                sleep: 'Sleep',
                screen: 'Screen Time',
                code: 'Coding'
            };
            
            item.innerHTML = `
                <div class="activity-header">
                    <div class="activity-title">
                        <span>${activityIcons[activity.type]}</span>
                        ${activityNames[activity.type]}
                    </div>
                    <div class="activity-duration">${activity.duration} min</div>
                </div>
                <div class="activity-notes">${activity.notes}</div>
            `;
            
            activityList.appendChild(item);
        });
        
        detailsContent.innerHTML = '';
        detailsContent.appendChild(activityList);
        
        // Update stats
        updateDailyStats(activities);
        dailyStats.style.display = 'block';
    }
}

// Update daily stats
function updateDailyStats(activities) {
    const stats = {
        study: 0,
        meditation: 0,
        exercise: 0,
        sleep: 0
    };
    
    activities.forEach(activity => {
        if (stats.hasOwnProperty(activity.type)) {
            stats[activity.type] += activity.duration;
        }
    });
    
    document.getElementById('studyHours').textContent = `${Math.floor(stats.study / 60)}h ${stats.study % 60}m`;
    document.getElementById('meditationTime').textContent = `${stats.meditation}m`;
    document.getElementById('exerciseTime').textContent = `${stats.exercise}m`;
    document.getElementById('sleepHours').textContent = `${Math.floor(stats.sleep / 60)}h ${stats.sleep % 60}m`;
}

// Navigation functions
function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendarMonth();
    generateCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendarMonth();
    generateCalendar();
}

function goToToday() {
    currentDate = new Date();
    selectedDate = new Date();
    updateCalendarMonth();
    generateCalendar();
    displayActivities(selectedDate);
}

// Add activity
function addActivity() {
    if (!selectedDate) {
        alert('Please select a date first');
        return;
    }
    alert('Add activity feature coming soon!');
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
    updateCalendarMonth();
    generateCalendar();
});
