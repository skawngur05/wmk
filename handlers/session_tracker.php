<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure user_sessions table exists
function ensureUserSessionsTable($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
        if ($stmt->rowCount() == 0) {
            $create_table_sql = "
                CREATE TABLE user_sessions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    session_id VARCHAR(128) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT TRUE,
                    logout_time TIMESTAMP NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_last_activity (last_activity),
                    INDEX idx_is_active (is_active)
                )
            ";
            $pdo->exec($create_table_sql);
            error_log("User sessions table created successfully");
        }
    } catch (PDOException $e) {
        error_log("Error ensuring user_sessions table: " . $e->getMessage());
        throw $e;
    }
}

try {
    ensureUserSessionsTable($pdo);
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    switch ($action) {
        case 'start_session':
            // Clean up old sessions for this user (mark as inactive)
            $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = FALSE, logout_time = NOW() WHERE user_id = ? AND session_id != ?");
            $stmt->execute([$user_id, $session_id]);
            
            // Check if current session already exists
            $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE user_id = ? AND session_id = ?");
            $stmt->execute([$user_id, $session_id]);
            
            if (!$stmt->fetch()) {
                // Insert new session
                $stmt = $pdo->prepare("
                    INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, login_time, last_activity) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$user_id, $session_id, $ip_address, $user_agent]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Session started']);
            break;
            
        case 'update_activity':
            // Update last activity for current session
            $stmt = $pdo->prepare("
                UPDATE user_sessions 
                SET last_activity = NOW() 
                WHERE user_id = ? AND session_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$user_id, $session_id]);
            
            echo json_encode(['success' => true, 'last_activity' => date('Y-m-d H:i:s')]);
            break;
            
        case 'end_session':
            // Mark session as inactive
            $stmt = $pdo->prepare("
                UPDATE user_sessions 
                SET is_active = FALSE, logout_time = NOW() 
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->execute([$user_id, $session_id]);
            
            echo json_encode(['success' => true, 'message' => 'Session ended']);
            break;
            
        case 'get_active_sessions':
            // Clean up inactive sessions (older than 5 minutes)
            $stmt = $pdo->prepare("
                UPDATE user_sessions 
                SET is_active = FALSE, logout_time = NOW() 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_active = TRUE
            ");
            $stmt->execute();
            
            // Get active sessions with user details
            $stmt = $pdo->prepare("
                SELECT 
                    us.*,
                    u.full_name,
                    u.username,
                    TIMESTAMPDIFF(SECOND, us.last_activity, NOW()) as seconds_since_activity
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.is_active = TRUE
                ORDER BY us.last_activity DESC
            ");
            $stmt->execute();
            $sessions = $stmt->fetchAll();
            
            // Format the sessions data
            $formatted_sessions = [];
            foreach ($sessions as $session) {
                $seconds_ago = $session['seconds_since_activity'];
                
                // Determine status based on last activity
                if ($seconds_ago < 30) {
                    $status = 'online';
                    $status_text = 'Active Now';
                    $status_class = 'success';
                } elseif ($seconds_ago < 300) { // 5 minutes
                    $minutes_ago = ceil($seconds_ago / 60);
                    $status = 'away';
                    $status_text = $minutes_ago . ' min ago';
                    $status_class = 'warning';
                } else {
                    $status = 'offline';
                    $status_text = 'Inactive';
                    $status_class = 'secondary';
                }
                
                $formatted_sessions[] = [
                    'user_id' => $session['user_id'],
                    'full_name' => $session['full_name'],
                    'username' => $session['username'],
                    'login_time' => $session['login_time'],
                    'last_activity' => $session['last_activity'],
                    'ip_address' => $session['ip_address'],
                    'is_current' => $session['user_id'] == $user_id && $session['session_id'] == $session_id,
                    'status' => $status,
                    'status_text' => $status_text,
                    'status_class' => $status_class,
                    'seconds_ago' => $seconds_ago
                ];
            }
            
            echo json_encode(['success' => true, 'sessions' => $formatted_sessions]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Session tracker database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Session tracker error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
