<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Attendance History – Canteen Management';
$db = getDB();

// Ensure table exists
$db->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME, time_out TIME,
    status ENUM('Present','Absent','Late','Half Day') DEFAULT 'Present',
    notes VARCHAR(255), recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (staff_id, date),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
)");

$month        = sanitize($_GET['month'] ?? date('Y-m'));
$branch_filter = (int)($_GET['branch'] ?? 0);
$branches      = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$per_page = 10;
$page     = max(1,(int)($_GET['page'] ?? 1));
$offset   = ($page-1)*$per_page;

$where = "WHERE a.date LIKE '".substr($month,0,7)."%'";
if ($branch_filter) $where .= " AND s.branch_id=$branch_filter";
if (!hasRole('admin')) {
    $where .= " AND s.full_name='".$db->real_escape_string($_SESSION['full_name'])."'";
}

$total = $db->query("SELECT COUNT(*) FROM attendance a JOIN staff s ON s.id=a.staff_id $where")->fetch_row()[0];
$pages = max(1, ceil($total/$per_page));

$records = $db->query("SELECT a.*, s.staff_id as sid, s.full_name, s.role as job_role,
    b.name as branch_name
    FROM attendance a
    JOIN staff s ON s.id=a.staff_id
    LEFT JOIN branches b ON b.id=s.branch_id
    $where ORDER BY a.date DESC, s.full_name LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="page-title">Attendance History</div>
        <div class="page-subtitle">View past attendance records by month</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="month" name="month" class="form-control" style="width:170px;" value="<?= $month ?>">
            <?php if (hasRole('admin')): ?>
            <select name="branch" class="form-select" style="width:160px;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-maroon">Filter</button>
        </form>
        <a href="staff_attendance.php" class="btn btn-outline-secondary" style="border-radius:8px;">
            <i class="bi bi-calendar-day me-1"></i> Today
        </a>
    </div>
</div>

<div class="c-card">
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Branch</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No attendance records for this period.</td></tr>
            <?php else: foreach ($records as $r):
                $acls = match($r['status']) {
                    'Present'  => 'status-high',
                    'Absent'   => 'status-out',
                    'Late','Half Day' => 'status-low',
                    default    => ''
                };
            ?>
            <tr>
                <td style="font-weight:500;"><?= date('M j, Y', strtotime($r['date'])) ?></td>
                <td><?= htmlspecialchars($r['sid']) ?></td>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['branch_name'] ?? '—') ?></td>
                <td><?= $r['time_in']  ? date('h:i A', strtotime($r['time_in']))  : '—' ?></td>
                <td><?= $r['time_out'] ? date('h:i A', strtotime($r['time_out'])) : '—' ?></td>
                <td><span class="status-pill <?= $acls ?>"><?= $r['status'] ?></span></td>
                <td style="color:#888;font-size:.85rem;"><?= htmlspecialchars($r['notes'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <div class="c-pagination">
        <a href="?month=<?= $month ?>&branch=<?= $branch_filter ?>&page=<?= $page-1 ?>" class="<?= $page<=1?'disabled':'' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
        <a href="?month=<?= $month ?>&branch=<?= $branch_filter ?>&page=<?= $page+1 ?>" class="<?= $page>=$pages?'disabled':'' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
