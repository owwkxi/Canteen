<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$_role        = userRole();   // 'staff' | 'admin' | 'super_admin'

// Pages each role can access
$staff_pages  = ['dashboard','products','add_product','edit_product',
                 'stock','stock_branch','update_stock',
                 'staff_attendance','attendance_history'];
$admin_pages  = array_merge($staff_pages, ['staff','add_staff','edit_staff','view_staff']);
$super_pages  = array_merge($admin_pages, ['reports','invoice','system_user']);

// Redirect if current page is not allowed for this role
$allowed = match($_role) {
    'super_admin' => $super_pages,
    'admin'       => $admin_pages,
    default       => $staff_pages,   // staff
};
if (!in_array($current_page, $allowed)) {
    $_SESSION['toast'] = ['msg' => 'Access denied.', 'type' => 'error'];
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Canteen Management System' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon:       #7B1416;
            --maroon-dark:  #5C0E10;
            --maroon-light: #A03030;
            --crimson:      #C1375A;
            --bg-body:      #F0EEEC;
            --sidebar-w:    240px;
            --header-h:     52px;
            --text-main:    #1a1a1a;
            --text-muted:   #6c757d;
            --card-bg:      #FFFFFF;
            --border:       #E4E0DC;
            --tbl-header:   #EAE6E2;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* ── TOP BAR ── */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            height: var(--header-h);
            background: var(--maroon-dark);
            display: flex; align-items: center; justify-content: flex-end;
            padding: 0 28px;
        }
        .topbar .admin-badge {
            color: #fff; font-size: .875rem; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
        }
        .topbar .admin-badge i { font-size: 1.1rem; opacity: .8; }
        .role-chip {
            font-size: .72rem; font-weight: 600; padding: 2px 9px;
            border-radius: 20px; background: rgba(255,255,255,.18);
            letter-spacing: .03em; text-transform: uppercase;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed; top: var(--header-h); left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: #fff;
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            overflow-y: auto; z-index: 900;
        }
        .sidebar .nav-list { list-style: none; padding: 18px 12px; flex: 1; }
        .sidebar .nav-item { margin-bottom: 2px; }

        .sidebar .nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 8px;
            color: var(--text-main); font-size: .9rem; font-weight: 500;
            text-decoration: none; transition: background .15s, color .15s;
        }
        .sidebar .nav-link i { font-size: 1.05rem; width: 20px; text-align: center; }
        .sidebar .nav-link:hover { background: #F5F0EE; }
        .sidebar .nav-link.active {
            background: var(--maroon); color: #fff;
        }

        /* sub-nav */
        .sidebar .sub-nav { list-style: none; padding-left: 10px; margin-top: 2px; }
        .sidebar .sub-nav .nav-link { font-size: .84rem; font-weight: 400; padding: 7px 14px; }
        .sidebar .sub-nav .nav-link i { font-size: .8rem; color: var(--maroon); }
        .sidebar .sub-nav .nav-link.active { background: #F5F0EE; color: var(--maroon); }

        .sidebar .logout-btn {
            padding: 16px 26px;
            color: var(--text-muted); font-size: .88rem; font-weight: 500;
            cursor: pointer; border: none; background: none; text-align: left;
            border-top: 1px solid var(--border);
        }
        .sidebar .logout-btn:hover { color: var(--maroon); }

        /* ── MAIN CONTENT ── */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--header-h);
            padding: 36px 40px;
            min-height: calc(100vh - var(--header-h));
        }

        /* ── PAGE HEADER ── */
        .page-title { font-size: 1.7rem; font-weight: 700; margin-bottom: 4px; }
        .page-subtitle { color: var(--text-muted); font-size: .875rem; }

        /* ── CARD ── */
        .c-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }

        /* ── TABLE ── */
        .c-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .c-table thead th {
            background: var(--tbl-header);
            padding: 13px 16px; font-size: .83rem; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em;
            border-bottom: 1px solid var(--border);
        }
        .c-table thead th:first-child { border-radius: 8px 0 0 0; }
        .c-table thead th:last-child  { border-radius: 0 8px 0 0; }
        .c-table tbody td {
            padding: 14px 16px; font-size: .9rem;
            border-bottom: 1px solid #F0EDEA; vertical-align: middle;
        }
        .c-table tbody tr:last-child td { border-bottom: none; }
        .c-table tbody tr:hover td { background: #FAFAF9; }

        /* ID badge */
        .id-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 44px; height: 44px; border-radius: 10px;
            background: #E8E5E2; font-size: .85rem; font-weight: 600;
            color: #444;
        }

        /* ── BUTTONS ── */
        .btn-maroon {
            background: var(--maroon); color: #fff; border: none;
            border-radius: 8px; padding: 8px 20px; font-size: .875rem; font-weight: 500;
            transition: background .15s;
        }
        .btn-maroon:hover { background: var(--maroon-dark); color: #fff; }

        .btn-crimson {
            background: var(--crimson); color: #fff; border: none;
            border-radius: 8px; padding: 8px 20px; font-size: .875rem; font-weight: 500;
            transition: background .15s;
        }
        .btn-crimson:hover { background: #a62b4b; color: #fff; }

        .btn-outline-maroon {
            background: #F9EEF0; color: var(--maroon); border: 1.5px solid #E8C4CC;
            border-radius: 8px; padding: 7px 20px; font-size: .875rem; font-weight: 500;
        }
        .btn-outline-maroon:hover { background: #F0D8DD; color: var(--maroon); }

        .btn-edit {
            background: #9E9E9E; color: #fff; border: none;
            border-radius: 6px; padding: 6px 16px; font-size: .82rem; font-weight: 500;
        }
        .btn-edit:hover { background: #757575; color: #fff; }

        .btn-view  { background: #4FC3F7; color: #fff; border: none; border-radius: 6px; padding: 6px 16px; font-size: .82rem; }
        .btn-edit2 { background: #FFD54F; color: #333; border: none; border-radius: 6px; padding: 6px 16px; font-size: .82rem; }

        /* ── STATUS PILLS ── */
        .status-pill {
            display: inline-flex; align-items: center; padding: 4px 12px;
            border-radius: 20px; font-size: .78rem; font-weight: 600; gap: 6px;
        }
        .status-out   { background: #FFCDD2; color: #C62828; }
        .status-low   { background: #FFF9C4; color: #F57F17; }
        .status-high  { background: #C8E6C9; color: #2E7D32; }
        .status-active   { color: #1a1a1a; }
        .status-inactive { color: var(--text-muted); }

        /* ── SEARCH ── */
        .search-box {
            display: flex; align-items: center; gap: 8px;
            border: 1.5px solid var(--border); border-radius: 8px;
            padding: 7px 14px; background: #fff; width: 240px;
        }
        .search-box input {
            border: none; outline: none; background: transparent;
            font-size: .875rem; width: 100%; color: var(--text-main);
        }
        .search-box i { color: var(--text-muted); font-size: .9rem; }

        /* ── PAGINATION ── */
        .c-pagination {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
        }
        .c-pagination .page-info { font-size: .875rem; color: var(--text-muted); font-weight: 500; }
        .c-pagination a {
            display: flex; align-items: center; gap: 6px;
            font-size: .875rem; font-weight: 500; color: var(--text-main);
            text-decoration: none; padding: 4px 0;
        }
        .c-pagination a:hover { color: var(--maroon); }
        .c-pagination a.disabled { color: #ccc; pointer-events: none; }

        /* ── FORM ── */
        .form-label { font-weight: 600; font-size: .875rem; margin-bottom: 6px; }
        .form-control, .form-select {
            border: 1.5px solid #D9D4CF; border-radius: 8px;
            padding: 9px 14px; font-size: .9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--maroon); box-shadow: 0 0 0 3px rgba(123,20,22,.08);
        }
        .section-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 20px; }

        /* ── TOAST ── */
        .c-toast {
            position: fixed; top: 70px; right: 24px; z-index: 9999;
            background: #fff; border: 1px solid var(--border);
            border-left: 4px solid var(--maroon); border-radius: 10px;
            padding: 14px 20px; box-shadow: 0 4px 20px rgba(0,0,0,.1);
            display: none; max-width: 320px;
        }
        .c-toast.show { display: block; animation: slideIn .3s ease; }
        .c-toast.success { border-left-color: #2E7D32; }
        .c-toast.error   { border-left-color: #C62828; }
        @keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* ── MODAL ── */
        .modal-content { border-radius: 14px; border: none; }
        .modal-header { background: var(--maroon); color: #fff; border-radius: 14px 14px 0 0; padding: 18px 24px; }
        .modal-header .btn-close { filter: invert(1); }
        .modal-body { padding: 28px 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); }

        /* ── CHECKBOX ── */
        .form-check-input:checked { background-color: var(--maroon); border-color: var(--maroon); }

        /* ── QUICK ACTION BTNS ── */
        .quick-btn {
            display: block; width: 100%; text-align: center;
            padding: 10px; border-radius: 8px; font-size: .875rem; font-weight: 500;
            text-decoration: none; margin-bottom: 10px; transition: all .15s;
        }
        .quick-btn.primary { background: var(--maroon); color: #fff; }
        .quick-btn.secondary { background: #F9EEF0; color: var(--maroon); }
        .quick-btn.primary:hover { background: var(--maroon-dark); color: #fff; }
        .quick-btn.secondary:hover { background: #F0D8DD; color: var(--maroon); }

        /* ── STOCK CARD ── */
        .stock-card { border: 1px solid var(--border); border-radius: 10px; padding: 16px; background: #fff; }
        .stock-card .branch-name { font-weight: 700; font-size: .95rem; }
        .stock-card .last-updated { font-size: .78rem; color: var(--text-muted); margin-bottom: 12px; }
        .stock-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #F0EDEA;
        }
        .stock-item:last-child { border-bottom: none; }
        .stock-item .item-info .name { font-weight: 600; font-size: .88rem; }
        .stock-item .item-info .qty  { font-size: .8rem; color: var(--text-muted); }
        .stock-item .status-wrap { text-align: right; }
        .stock-item .status-wrap .hint { font-size: .72rem; color: #bbb; margin-top: 3px; }

        /* Chart area */
        .chart-wrap { position: relative; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px 16px; }
            .sidebar { transform: translateX(-100%); transition: transform .3s; }
            .sidebar.show { transform: translateX(0); }
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="admin-badge">
        <i class="bi bi-person-circle"></i>
        <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>
        <span class="role-chip"><?= roleBadge($_role) ?></span>
    </div>
</div>

<!-- SIDEBAR -->
<nav class="sidebar">
    <ul class="nav-list">

        <!-- Dashboard — ALL roles -->
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i> Dashboard
            </a>
        </li>

        <!-- Products — ALL roles -->
        <li class="nav-item">
            <a href="products.php" class="nav-link <?= in_array($current_page, ['products','add_product','edit_product']) ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Products
            </a>
        </li>

        <!-- Stock Management — ALL roles -->
        <li class="nav-item">
            <a href="stock.php" class="nav-link <?= in_array($current_page, ['stock','stock_branch','update_stock']) ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i> Stock Management
            </a>
        </li>

        <!-- Staff Management — admin & super_admin only -->
        <?php if (hasRole('admin')): ?>
        <li class="nav-item">
            <a href="staff.php"
               class="nav-link <?= in_array($current_page, ['staff','add_staff','edit_staff','view_staff','staff_attendance','attendance_history']) ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Staff Management
            </a>
        </li>
        <?php if (in_array($current_page, ['staff','add_staff','edit_staff','view_staff','staff_attendance','attendance_history'])): ?>
        <ul class="sub-nav">
            <li><a href="staff.php" class="nav-link <?= in_array($current_page,['staff','add_staff','edit_staff','view_staff']) ? 'active' : '' ?>">
                <i class="bi bi-caret-right-fill"></i> Staff Details
            </a></li>
            <li><a href="staff_attendance.php" class="nav-link <?= $current_page === 'staff_attendance' ? 'active' : '' ?>">
                <i class="bi bi-caret-right-fill"></i> Staff Attendance
            </a></li>
            <li><a href="attendance_history.php" class="nav-link <?= $current_page === 'attendance_history' ? 'active' : '' ?>">
                <i class="bi bi-caret-right-fill"></i> Attendance History
            </a></li>
        </ul>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Attendance (for staff role — read-only own attendance) -->
        <?php if (!hasRole('admin')): ?>
        <li class="nav-item">
            <a href="staff_attendance.php" class="nav-link <?= in_array($current_page,['staff_attendance','attendance_history']) ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> Attendance
            </a>
        </li>
        <?php endif; ?>

       <!-- Daily Reports — super_admin only -->
        <?php if (hasRole('super_admin')): ?>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?= in_array($current_page, ['reports','invoice']) ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> Daily Reports
            </a>
        </li>
        <?php if (in_array($current_page, ['reports','invoice'])): ?>
        <ul class="sub-nav">
            <li><a href="reports.php" class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-caret-right-fill"></i> Reports
            </a></li>
            <li><a href="invoice.php" class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>">
                <i class="bi bi-caret-right-fill"></i> Invoice
            </a></li>
        </ul>
        <?php endif; ?>
        <?php endif; ?>

        <!-- System Users — super_admin only -->
        <?php if (hasRole('super_admin')): ?>
        <li class="nav-item">
            <a href="system_user.php" class="nav-link <?= $current_page === 'system_user' ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i> System Users
            </a>
        </li>
        <?php endif; ?>

    </ul>
    <form method="POST" action="logout.php">
        <button type="submit" class="logout-btn">
            <i class="bi bi-box-arrow-left me-2"></i>Logout
        </button>
    </form>
</nav>

<!-- MAIN CONTENT -->
<div class="main-content">
