<?php
require_once 'includes/config.php';
requireLogin();
// ── Access guard: admin only ─────────────────────────────────────────────
$_role = $_SESSION["role"] ?? "";
if ($_role !== "admin" && $_role !== "super_admin") {
    header("Location: dashboard.php"); exit;
}

$page_title = 'Staff Management – Canteen Management';
$db = getDB();

// ── Handle Delete ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_staff') {
    if (hasRole('admin') || hasRole('super_admin')) {
        $del_id = (int)($_POST['staff_db_id'] ?? 0);
        if ($del_id) {
            $s = $db->query("SELECT staff_id, full_name FROM staff WHERE id=$del_id")->fetch_assoc();
            if ($s) {
                $sid_val = $db->real_escape_string($s['staff_id']);
                $fn_val  = $db->real_escape_string($s['full_name']);
                $db->query("DELETE FROM users WHERE username='$sid_val' OR (role='staff' AND full_name='$fn_val')");
                $db->query("DELETE FROM staff WHERE id=$del_id");
                $_SESSION['toast'] = [
                    'msg'  => "Staff <strong>{$s['full_name']}</strong> and their login account have been deleted.",
                    'type' => 'success'
                ];
            }
        }
    }
    header('Location: staff.php'); exit;
}

$per_page = 7;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;
$search   = sanitize($_GET['q'] ?? '');

// FIX: use prepared statement for search to prevent SQL injection
$where_sql  = "WHERE 1=1";
$params     = [];
$types      = "";
if ($search) {
    $where_sql .= " AND (s.full_name LIKE ? OR s.staff_id LIKE ?)";
    $s_like = "%$search%";
    $params[] = $s_like;
    $params[] = $s_like;
    $types   .= "ss";
}

// Count
$cnt_stmt = $db->prepare("SELECT COUNT(*) FROM staff s $where_sql");
if ($params) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total = $cnt_stmt->get_result()->fetch_row()[0];
$pages = max(1, ceil($total / $per_page));

// Fetch
$list_params   = $params;
$list_params[] = $per_page;
$list_params[] = $offset;
$list_types    = $types . "ii";
$list_stmt = $db->prepare(
    "SELECT s.*, b.name as branch_name, b.code as branch_code
     FROM staff s LEFT JOIN branches b ON b.id=s.branch_id
     $where_sql ORDER BY s.id LIMIT ? OFFSET ?"
);
if ($list_params) $list_stmt->bind_param($list_types, ...$list_params);
$list_stmt->execute();
$staff = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$can_delete = hasRole('admin') || hasRole('super_admin');

include 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="page-title">Staff Management</div>
        <div class="page-subtitle">Total: <?= $total ?> staff member<?= $total != 1 ? 's' : '' ?></div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-flex">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search staff" value="<?= htmlspecialchars($search) ?>">
            </div>
        </form>
        <a href="add_staff.php" class="btn btn-maroon d-flex align-items-center gap-2">
            <i class="bi bi-plus-lg"></i> Add Staff
        </a>
    </div>
</div>

<div class="c-card">
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Staff Name</th>
                <th>Role</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staff)): ?>
            <tr><td colspan="6" class="text-center py-5 text-muted">No staff found.</td></tr>
            <?php else: foreach ($staff as $s): ?>
            <tr>
                <td><span class="id-badge"><?= htmlspecialchars($s['staff_id']) ?></span></td>
                <td style="font-weight:500;"><?= htmlspecialchars($s['full_name']) ?></td>
                <td><?= htmlspecialchars($s['role']) ?></td>
                <td><?= htmlspecialchars($s['branch_name'] ?? '—') ?></td>
                <td>
                    <span class="status-pill <?= $s['status'] === 'Active' ? 'status-high' : '' ?>"
                          style="<?= $s['status'] !== 'Active' ? 'background:#F5F5F5;color:#888;' : '' ?>">
                        <?= $s['status'] ?>
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="view_staff.php?id=<?= $s['id'] ?>" class="btn btn-view btn-sm">View</a>
                        <a href="edit_staff.php?id=<?= $s['id'] ?>" class="btn btn-edit2 btn-sm">Edit</a>
                        <?php if ($can_delete): ?>
                        <button type="button" class="btn btn-sm"
                                style="background:#FFCDD2;color:#C62828;border-radius:6px;border:none;"
                                onclick="confirmDeleteStaff(<?= $s['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($s['full_name'])) ?>',
                                    '<?= addslashes(htmlspecialchars($s['staff_id'])) ?>')">
                            Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <div class="c-pagination">
        <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="<?= $page<=1?'disabled':'' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
        <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="<?= $page>=$pages?'disabled':'' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<?php if ($can_delete): ?>
<div class="modal fade" id="deleteStaffModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-3">
                <h5 class="modal-title" style="color:#FFFFFF;">
                    <i class="bi bi-person-x me-2"></i>Delete Staff
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.875rem;color:#555;">You are about to permanently delete:</p>
                <p id="deleteStaffLabel" style="font-weight:700;color:var(--maroon);font-size:.95rem;"></p>
                <div style="font-size:.8rem;background:#FFF3E0;border-radius:8px;padding:10px 12px;color:#E65100;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This will also delete their <strong>login account</strong> and all <strong>attendance records</strong>.
                    This cannot be undone.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteStaffForm" style="display:inline;">
                    <input type="hidden" name="action"      value="delete_staff">
                    <input type="hidden" name="staff_db_id" id="deleteStaffDbId">
                    <button type="submit" class="btn btn-sm"
                            style="background:#C62828;color:#fff;border-radius:6px;border:none;">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteStaff(dbId, name, sid) {
    document.getElementById('deleteStaffDbId').value     = dbId;
    document.getElementById('deleteStaffLabel').textContent = sid + ' – ' + name;
    new bootstrap.Modal(document.getElementById('deleteStaffModal')).show();
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

