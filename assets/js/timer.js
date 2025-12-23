// TrackMate Timer JavaScript

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

// Timer variables
let timerInterval = null;
let timerSeconds = 1500; // 25 minutes default
let timerRunning = false;

// Stopwatch variables
let stopwatchInterval = null;
let stopwatchMs = 0;
let stopwatchRunning = false;
let lapCount = 0;

// Pomodoro variables
let pomodoroInterval = null;
let pomodoroSeconds = 1500;
let pomodoroRound = 1;
let pomodoroPhase = 'work'; // 'work', 'shortBreak', 'longBreak'
let pomodoroRunning = false;

// Update date
function updateDate() {
    const now = new Date();
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    document.getElementById('currentDate').textContent = dateStr;
}

// Switch tabs
function switchTab(tab) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(`${tab}Tab`).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
}

// === TIMER FUNCTIONS ===

function setQuickTimer(minutes) {
    timerSeconds = minutes * 60;
    updateTimerDisplay();
    
    // Update active state
    document.querySelectorAll('.quick-timer-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

function setCustomTimer() {
    const minutes = parseInt(document.getElementById('customMinutes').value);
    if (minutes && minutes > 0) {
        timerSeconds = minutes * 60;
        updateTimerDisplay();
        document.getElementById('customMinutes').value = '';
    }
}

function startTimer() {
    if (!timerRunning && timerSeconds > 0) {
        timerRunning = true;
        document.getElementById('timerStartBtn').disabled = true;
        document.getElementById('timerPauseBtn').disabled = false;
        
        timerInterval = setInterval(() => {
            timerSeconds--;
            updateTimerDisplay();
            
            if (timerSeconds <= 0) {
                clearInterval(timerInterval);
                timerRunning = false;
                playNotification();
                alert('Timer finished!');
                resetTimer();
            }
        }, 1000);
    }
}

function pauseTimer() {
    if (timerRunning) {
        clearInterval(timerInterval);
        timerRunning = false;
        document.getElementById('timerStartBtn').disabled = false;
        document.getElementById('timerPauseBtn').disabled = true;
    }
}

function resetTimer() {
    clearInterval(timerInterval);
    timerRunning = false;
    timerSeconds = 1500;
    updateTimerDisplay();
    document.getElementById('timerStartBtn').disabled = false;
    document.getElementById('timerPauseBtn').disabled = true;
}

function updateTimerDisplay() {
    const minutes = Math.floor(timerSeconds / 60);
    const seconds = timerSeconds % 60;
    document.getElementById('timerDisplay').textContent = 
        `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// === STOPWATCH FUNCTIONS ===

function startStopwatch() {
    if (!stopwatchRunning) {
        stopwatchRunning = true;
        document.getElementById('stopwatchStartBtn').disabled = true;
        document.getElementById('stopwatchPauseBtn').disabled = false;
        document.getElementById('stopwatchLapBtn').disabled = false;
        
        stopwatchInterval = setInterval(() => {
            stopwatchMs += 10;
            updateStopwatchDisplay();
        }, 10);
    }
}

function pauseStopwatch() {
    if (stopwatchRunning) {
        clearInterval(stopwatchInterval);
        stopwatchRunning = false;
        document.getElementById('stopwatchStartBtn').disabled = false;
        document.getElementById('stopwatchPauseBtn').disabled = true;
    }
}

function resetStopwatch() {
    clearInterval(stopwatchInterval);
    stopwatchRunning = false;
    stopwatchMs = 0;
    lapCount = 0;
    updateStopwatchDisplay();
    document.getElementById('stopwatchStartBtn').disabled = false;
    document.getElementById('stopwatchPauseBtn').disabled = true;
    document.getElementById('stopwatchLapBtn').disabled = true;
    document.getElementById('lapsList').innerHTML = '<h4>Lap Times</h4><div class="laps-empty">No laps recorded yet</div>';
}

function lapStopwatch() {
    if (stopwatchRunning) {
        lapCount++;
        const lapsList = document.getElementById('lapsList');
        
        // Remove empty message if exists
        const emptyMsg = lapsList.querySelector('.laps-empty');
        if (emptyMsg) {
            emptyMsg.remove();
        }
        
        const lapItem = document.createElement('div');
        lapItem.className = 'lap-item';
        
        const hours = Math.floor(stopwatchMs / 3600000);
        const minutes = Math.floor((stopwatchMs % 3600000) / 60000);
        const seconds = Math.floor((stopwatchMs % 60000) / 1000);
        const ms = Math.floor((stopwatchMs % 1000) / 10);
        
        lapItem.innerHTML = `
            <span>Lap ${lapCount}</span>
            <span>${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${String(ms).padStart(2, '0')}</span>
        `;
        
        lapsList.appendChild(lapItem);
    }
}

function updateStopwatchDisplay() {
    const hours = Math.floor(stopwatchMs / 3600000);
    const minutes = Math.floor((stopwatchMs % 3600000) / 60000);
    const seconds = Math.floor((stopwatchMs % 60000) / 1000);
    const ms = Math.floor((stopwatchMs % 1000) / 10);
    
    document.getElementById('stopwatchDisplay').textContent = 
        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    document.getElementById('stopwatchMs').textContent = String(ms).padStart(2, '0');
}

// === ALARM FUNCTIONS ===

function addAlarm() {
    alert('Add alarm feature coming soon!');
}

// === POMODORO FUNCTIONS ===

function startPomodoro() {
    if (!pomodoroRunning && pomodoroSeconds > 0) {
        pomodoroRunning = true;
        document.getElementById('pomodoroStartBtn').disabled = true;
        document.getElementById('pomodoroPauseBtn').disabled = false;
        
        pomodoroInterval = setInterval(() => {
            pomodoroSeconds--;
            updatePomodoroDisplay();
            
            if (pomodoroSeconds <= 0) {
                clearInterval(pomodoroInterval);
                pomodoroRunning = false;
                playNotification();
                nextPomodoroPhase();
            }
        }, 1000);
    }
}

function pausePomodoro() {
    if (pomodoroRunning) {
        clearInterval(pomodoroInterval);
        pomodoroRunning = false;
        document.getElementById('pomodoroStartBtn').disabled = false;
        document.getElementById('pomodoroPauseBtn').disabled = true;
    }
}

function skipPomodoro() {
    clearInterval(pomodoroInterval);
    pomodoroRunning = false;
    nextPomodoroPhase();
    document.getElementById('pomodoroStartBtn').disabled = false;
    document.getElementById('pomodoroPauseBtn').disabled = true;
}

function resetPomodoro() {
    clearInterval(pomodoroInterval);
    pomodoroRunning = false;
    pomodoroRound = 1;
    pomodoroPhase = 'work';
    const workDuration = parseInt(document.getElementById('pomodoroWork').value) || 25;
    pomodoroSeconds = workDuration * 60;
    updatePomodoroDisplay();
    document.getElementById('pomodoroPhase').textContent = 'Work Session';
    document.getElementById('pomodoroRound').textContent = '1';
    document.getElementById('pomodoroStartBtn').disabled = false;
    document.getElementById('pomodoroPauseBtn').disabled = true;
}

function nextPomodoroPhase() {
    const workDuration = parseInt(document.getElementById('pomodoroWork').value) || 25;
    const shortBreak = parseInt(document.getElementById('pomodoroShortBreak').value) || 5;
    const longBreak = parseInt(document.getElementById('pomodoroLongBreak').value) || 15;
    
    if (pomodoroPhase === 'work') {
        if (pomodoroRound >= 4) {
            pomodoroPhase = 'longBreak';
            pomodoroSeconds = longBreak * 60;
            document.getElementById('pomodoroPhase').textContent = 'Long Break';
        } else {
            pomodoroPhase = 'shortBreak';
            pomodoroSeconds = shortBreak * 60;
            document.getElementById('pomodoroPhase').textContent = 'Short Break';
        }
    } else {
        if (pomodoroPhase === 'longBreak') {
            pomodoroRound = 1;
        } else {
            pomodoroRound++;
        }
        pomodoroPhase = 'work';
        pomodoroSeconds = workDuration * 60;
        document.getElementById('pomodoroPhase').textContent = 'Work Session';
        document.getElementById('pomodoroRound').textContent = pomodoroRound;
    }
    
    updatePomodoroDisplay();
    alert(`${pomodoroPhase === 'work' ? 'Break' : 'Work session'} finished! Starting ${pomodoroPhase === 'work' ? 'work session' : 'break'}.`);
}

function updatePomodoroDisplay() {
    const minutes = Math.floor(pomodoroSeconds / 60);
    const seconds = pomodoroSeconds % 60;
    document.getElementById('pomodoroDisplay').textContent = 
        `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// Play notification sound
function playNotification() {
    // Browser notification
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('TrackMate Timer', {
            body: 'Your timer has finished!',
            icon: 'assets/icons/icon-192.png'
        });
    }
}

// Request notification permission
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
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
    updateTimerDisplay();
    updateStopwatchDisplay();
    updatePomodoroDisplay();
    requestNotificationPermission();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    clearInterval(timerInterval);
    clearInterval(stopwatchInterval);
    clearInterval(pomodoroInterval);
});
