<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'canteen_db');
define('BASE_URL', '/canteen/');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:sans-serif;padding:40px;color:#C62828;">
                <h2>Database Connection Failed</h2>
                <p>' . $conn->connect_error . '</p>
                <p>Please check your <code>includes/config.php</code> credentials.</p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

session_start();

// ── Auth helpers ──────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function userRole() {
    return $_SESSION['role'] ?? '';
}

// Check if current user has at least the given role level
// Hierarchy: staff < admin < super_admin
function hasRole(string $min_role): bool {
    $levels = ['staff' => 1, 'admin' => 2, 'super_admin' => 3];
    $current = $levels[userRole()] ?? 0;
    $required = $levels[$min_role] ?? 99;
    return $current >= $required;
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// Redirect to dashboard with error if role insufficient
function requireRole(string $min_role) {
    requireLogin();
    if (!hasRole($min_role)) {
        $_SESSION['toast'] = ['msg' => 'Access denied. You do not have permission to view that page.', 'type' => 'error'];
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Role display labels
function roleBadge(string $role): string {
    return match($role) {
        'super_admin' => 'Super Admin',
        'admin'       => 'Admin',
        'staff'       => 'Staff',
        default       => ucfirst($role),
    };
}
?>
