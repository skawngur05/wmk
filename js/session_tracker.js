// Session Activity Tracker - Microsoft Teams-like functionality
class SessionTracker {
    constructor() {
        this.updateInterval = 30000; // Update every 30 seconds
        this.activityTimeout = 60000; // Consider user active if any activity in last 60 seconds
        this.lastActivity = Date.now();
        this.isActive = true;
        this.intervalId = null;
        this.sessionUpdateId = null;
        
        this.init();
    }
    
    init() {
        // Start session tracking
        this.startSession();
        
        // Track user activity
        this.trackUserActivity();
        
        // Start periodic updates
        this.startPeriodicUpdates();
        
        // Handle page visibility changes
        this.handleVisibilityChange();
        
        // Handle beforeunload (when user leaves)
        this.handlePageUnload();
    }
    
    startSession() {
        fetch('handlers/session_tracker.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: 'action=start_session'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Session tracking started');
            }
        })
        .catch(error => {
            console.error('Error starting session:', error);
        });
    }
    
    trackUserActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.updateActivity();
            }, true);
        });
    }
    
    updateActivity() {
        this.lastActivity = Date.now();
        
        if (!this.isActive) {
            this.isActive = true;
            this.sendActivityUpdate();
        }
    }
    
    sendActivityUpdate() {
        fetch('handlers/session_tracker.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: 'action=update_activity'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Activity updated');
            }
        })
        .catch(error => {
            console.error('Error updating activity:', error);
        });
    }
    
    startPeriodicUpdates() {
        // Update activity every 30 seconds if user is active
        this.intervalId = setInterval(() => {
            const timeSinceActivity = Date.now() - this.lastActivity;
            
            if (timeSinceActivity < this.activityTimeout) {
                // User is still active
                this.sendActivityUpdate();
            } else if (this.isActive) {
                // User became inactive
                this.isActive = false;
                console.log('User is now inactive');
            }
        }, this.updateInterval);
        
        // Update session display every 10 seconds
        this.sessionUpdateId = setInterval(() => {
            this.updateSessionDisplay();
        }, 10000);
    }
    
    updateSessionDisplay() {
        // Only update if we're on the settings page
        if (!document.getElementById('activeSessionsTable')) {
            return;
        }
        
        fetch('handlers/session_tracker.php?action=get_active_sessions', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.renderActiveSessions(data.sessions);
            }
        })
        .catch(error => {
            console.error('Error fetching sessions:', error);
        });
    }
    
    renderActiveSessions(sessions) {
        const tbody = document.querySelector('#activeSessionsTable tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        sessions.forEach(session => {
            const row = document.createElement('tr');
            
            // Format login time in EST
            const loginTime = new Date(session.login_time);
            const loginTimeEST = loginTime.toLocaleString('en-US', {
                timeZone: 'America/New_York',
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }) + ' EST';
            
            // Format last activity
            const lastActivity = new Date(session.last_activity);
            const activityTimeEST = lastActivity.toLocaleString('en-US', {
                timeZone: 'America/New_York',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            }) + ' EST';
            
            row.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="status-indicator status-${session.status}"></div>
                        </div>
                        <div>
                            <strong>${this.escapeHtml(session.full_name)}</strong>
                            ${session.is_current ? '<span class="badge bg-primary ms-2">You</span>' : ''}
                            <br>
                            <small class="text-muted">${this.escapeHtml(session.username)}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <small>${loginTimeEST}</small>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-circle me-2 text-${session.status_class}" style="font-size: 8px;"></i>
                        <span class="text-${session.status_class}">${session.status_text}</span>
                    </div>
                    <small class="text-muted d-block">${activityTimeEST}</small>
                </td>
                <td>
                    <span class="badge bg-${session.status_class}">
                        ${session.status === 'online' ? 'Online' : session.status === 'away' ? 'Away' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <small class="text-muted">${session.ip_address}</small>
                </td>
            `;
            
            tbody.appendChild(row);
        });
        
        // Update the count
        const activeCount = sessions.filter(s => s.status === 'online').length;
        const awayCount = sessions.filter(s => s.status === 'away').length;
        
        const sessionCountElement = document.getElementById('sessionCount');
        if (sessionCountElement) {
            sessionCountElement.textContent = `${activeCount} online, ${awayCount} away`;
        }
    }
    
    handleVisibilityChange() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is now hidden (user switched tabs/minimized)
                this.isActive = false;
            } else {
                // Page is now visible again
                this.updateActivity();
            }
        });
    }
    
    handlePageUnload() {
        window.addEventListener('beforeunload', () => {
            // Send synchronous request to end session
            navigator.sendBeacon('handlers/session_tracker.php', 
                new URLSearchParams({action: 'end_session'}));
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    destroy() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        if (this.sessionUpdateId) {
            clearInterval(this.sessionUpdateId);
        }
    }
}

// CSS for status indicators
const style = document.createElement('style');
style.textContent = `
    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        position: relative;
    }
    
    .status-online {
        background-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }
    
    .status-away {
        background-color: #ffc107;
        box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
    }
    
    .status-offline {
        background-color: #6c757d;
        box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.3);
    }
    
    .status-online::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 6px;
        height: 6px;
        background-color: white;
        border-radius: 50%;
    }
`;
document.head.appendChild(style);

// Initialize session tracker when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.sessionTracker = new SessionTracker();
});

// Clean up on page unload
window.addEventListener('unload', function() {
    if (window.sessionTracker) {
        window.sessionTracker.destroy();
    }
});
