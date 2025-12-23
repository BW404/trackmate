// TrackMate Settings JavaScript

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

// Update date
function updateDate() {
    const now = new Date();
    const options = { month: 'long', day: 'numeric', year: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    document.getElementById('currentDate').textContent = dateStr;
}

// Show settings section
function showSettingsSection(section) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(sec => {
        sec.classList.remove('active');
    });
    
    // Remove active from all nav buttons
    document.querySelectorAll('.settings-nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(`${section}Section`).classList.add('active');
    
    // Add active to clicked button
    event.target.closest('.settings-nav-btn').classList.add('active');
}

// Theme selection
document.querySelectorAll('.theme-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.theme-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.classList.add('active');
        
        const theme = this.querySelector('.theme-preview').classList[1];
        applyTheme(theme);
    });
});

// Apply theme
function applyTheme(theme) {
    // Store theme preference
    localStorage.setItem('trackmate_theme', theme);
    
    // Apply theme (this would need CSS variables to work fully)
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
    } else {
        document.body.classList.remove('dark-theme');
    }
    
    console.log(`Theme changed to: ${theme}`);
}

// Color accent selection
document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.color-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.classList.add('active');
        
        // Store color preference
        const color = this.style.background;
        localStorage.setItem('trackmate_accent', color);
        
        console.log(`Accent color changed`);
    });
});

// Notification permission
document.getElementById('pushNotifications')?.addEventListener('change', function(e) {
    if (e.target.checked) {
        if ('Notification' in window) {
            Notification.requestPermission().then(permission => {
                if (permission !== 'granted') {
                    e.target.checked = false;
                    alert('Please allow notifications in your browser settings');
                }
            });
        } else {
            e.target.checked = false;
            alert('Your browser does not support notifications');
        }
    }
});

// Change password
function changePassword() {
    const currentPassword = prompt('Enter your current password:');
    if (!currentPassword) return;
    
    const newPassword = prompt('Enter your new password:');
    if (!newPassword) return;
    
    const confirmPassword = prompt('Confirm your new password:');
    if (!confirmPassword) return;
    
    if (newPassword !== confirmPassword) {
        alert('Passwords do not match');
        return;
    }
    
    if (newPassword.length < 8) {
        alert('Password must be at least 8 characters long');
        return;
    }
    
    // TODO: Call API to change password
    alert('Password change feature coming soon!');
}

// Manage backup codes
function manageBackupCodes() {
    window.location.href = 'backup-codes.html';
}

// Manage sessions
function manageSessions() {
    alert('Active sessions management coming soon!');
}

// Export data
function exportData() {
    const data = {
        user: user,
        settings: {
            theme: localStorage.getItem('trackmate_theme') || 'light',
            accent: localStorage.getItem('trackmate_accent') || '',
            notifications: true
        },
        activities: [],
        timestamp: new Date().toISOString()
    };
    
    const dataStr = JSON.stringify(data, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = `trackmate-data-${Date.now()}.json`;
    a.click();
    
    URL.revokeObjectURL(url);
    alert('Data exported successfully!');
}

// Import data
function importData() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(event) {
            try {
                const data = JSON.parse(event.target.result);
                
                if (confirm('This will overwrite your current data. Continue?')) {
                    // TODO: Import data to backend
                    console.log('Imported data:', data);
                    alert('Data import feature coming soon!');
                }
            } catch (error) {
                alert('Invalid data file');
            }
        };
        reader.readAsText(file);
    });
    
    input.click();
}

// Clear data
function clearData() {
    const confirmation = prompt('Type "DELETE" to confirm clearing all data:');
    
    if (confirmation === 'DELETE') {
        if (confirm('Are you absolutely sure? This action cannot be undone.')) {
            // TODO: Call API to clear data
            alert('This will clear all your activity data. Feature coming soon!');
        }
    }
}

// Manage storage
function manageStorage() {
    alert('Storage management feature coming soon!');
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

// Load saved settings
function loadSettings() {
    // Load theme
    const savedTheme = localStorage.getItem('trackmate_theme');
    if (savedTheme) {
        applyTheme(savedTheme);
    }
    
    // Load other settings from localStorage
    console.log('Settings loaded');
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateDate();
    loadSettings();
});
