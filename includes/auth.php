<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function login($username, $password, $pdo) {
    $stmt = $pdo->prepare("SELECT id, username, password, full_name FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        return true;
    }
    return false;
}

function logout($pdo = null) {
    // Clean up session tracking if database connection is available
    if ($pdo && isset($_SESSION['user_id'])) {
        try {
            $session_id = session_id();
            $stmt = $pdo->prepare("
                UPDATE user_sessions 
                SET is_active = FALSE, logout_time = NOW() 
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $session_id]);
        } catch (PDOException $e) {
            error_log("Error updating session on logout: " . $e->getMessage());
        }
    }
    
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
