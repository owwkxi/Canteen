<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Staff Management – Canteen Management';
$db = getDB();

$per_page = 7;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total = $db->query("SELECT COUNT(*) FROM staff")->fetch_row()[0];
$pages = max(1, ceil($total / $per_page));

$staff = $db->query("SELECT s.*, b.code as branch_code FROM staff s LEFT JOIN branches b ON b.id=s.branch_id ORDER BY s.id LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="page-title">Staff Management</div>
    <a href="add_staff.php" class="btn btn-maroon d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Add Staff
    </a>
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
            <?php else: ?>
            <?php foreach ($staff as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['staff_id']) ?></td>
                <td><?= htmlspecialchars($s['full_name']) ?></td>
                <td><?= htmlspecialchars($s['role']) ?></td>
                <td><?= htmlspecialchars($s['branch_code'] ?? '—') ?></td>
                <td>
                    <span class="<?= $s['status'] === 'Active' ? 'status-active' : 'status-inactive' ?>" style="font-weight:500;">
                        <?= $s['status'] ?>
                    </span>
                </td>
                <td class="d-flex gap-2">
                    <a href="view_staff.php?id=<?= $s['id'] ?>" class="btn btn-view btn-sm">View</a>
                    <a href="edit_staff.php?id=<?= $s['id'] ?>" class="btn btn-edit2 btn-sm">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="c-pagination">
        <a href="?page=<?= $page-1 ?>" class="<?= $page<=1?'disabled':'' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
        <a href="?page=<?= $page+1 ?>" class="<?= $page>=$pages?'disabled':'' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
