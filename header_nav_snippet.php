<?php
/**
 * ─────────────────────────────────────────────────────────────
 *  PASTE THIS BLOCK INTO YOUR includes/header.php
 *  Replace your existing sidebar <nav> / menu links section.
 *
 *  Requires:  includes/access.php  (already auto-loaded by each page)
 *  If access.php is not loaded yet at header time, add this line
 *  near the top of header.php:
 *      require_once __DIR__ . '/access.php';
 * ─────────────────────────────────────────────────────────────
 */

// Load access helpers if not already loaded

require_once __DIR__ . '/config.php';

if (!function_exists('canAccessCashier')) {
    require_once __DIR__ . '/access.php';
}

$current_file = basename($_SERVER['PHP_SELF']);
?>

<!-- ── Sidebar Navigation ───────────────────────── -->

<!-- Dashboard – visible to everyone -->
<a href="dashboard.php"
   class="nav-link <?= $current_file === 'dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-speedometer2"></i> Dashboard
</a>

<!-- Product Sold – visible to ALL logged-in users (including non-cashier staff) -->
<a href="invoice.php"
   class="nav-link <?= $current_file === 'invoice.php' ? 'active' : '' ?>">
    <i class="bi bi-receipt"></i> Product Sold
</a>

<?php if (canAccessCashier() || hasRole('super_admin')): ?>
<!-- Products – cashier + admin + super_admin only -->
<a href="products.php"
   class="nav-link <?= $current_file === 'products.php' ? 'active' : '' ?>">
    <i class="bi bi-box-seam"></i> Products
</a>

<!-- Stock – cashier + admin + super_admin only -->
<a href="stock.php"
   class="nav-link <?= in_array($current_file, ['stock.php','stock_branch.php','update_stock.php']) ? 'active' : '' ?>">
    <i class="bi bi-archive"></i> Stock
</a>

<!-- Reports group (invoice + reports treated as one nav item) -->
<a href="reports.php"
   class="nav-link <?= in_array($current_file, ['reports.php', 'invoice.php']) ? 'active' : '' ?>">
    <i class="bi bi-bar-chart-line"></i> Reports
</a>
<?php endif; ?>

<!-- Attendance – visible to everyone -->
<a href="staff_attendance.php"
   class="nav-link <?= in_array($current_file, ['staff_attendance.php','attendance_history.php']) ? 'active' : '' ?>">
    <i class="bi bi-calendar-check"></i> Attendance
</a>

<?php if (isAdmin()): ?>
<!-- Staff Management – admin only -->
<a href="staff.php"
   class="nav-link <?= in_array($current_file, ['staff.php','add_staff.php','edit_staff.php','view_staff.php']) ? 'active' : '' ?>">
    <i class="bi bi-people"></i> Staff
</a>
<?php endif; ?>

<?php if (hasRole('super_admin')): ?>
<!-- System Users – super_admin only -->
<a href="system_user.php"
   class="nav-link <?= $current_file === 'system_user.php' ? 'active' : '' ?>">
    <i class="bi bi-shield-lock"></i> System Users
</a>
<?php endif; ?>

<!-- Change Password – everyone -->
<a href="change_password.php"
   class="nav-link <?= $current_file === 'change_password.php' ? 'active' : '' ?>">
    <i class="bi bi-key"></i> Change Password
</a>

<!-- Logout -->
<a href="logout.php" class="nav-link">
    <i class="bi bi-box-arrow-right"></i> Logout
</a>
