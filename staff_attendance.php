<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Staff Attendance – Canteen Management';
$db = getDB();

// Create attendance table if not exists
$db->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('Present','Absent','Late','Half Day') DEFAULT 'Present',
    notes VARCHAR(255),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (staff_id, date),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
)");

$today      = date('Y-m-d');
$date_view  = sanitize($_GET['date'] ?? $today);
$branch_filter = (int)($_GET['branch'] ?? 0);

// Handle mark attendance (admin+ only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole('admin')) {
    $sid    = (int)$_POST['staff_id'];
    $date   = sanitize($_POST['date']);
    $status = sanitize($_POST['status']);
    $t_in   = sanitize($_POST['time_in']  ?? '');
    $t_out  = sanitize($_POST['time_out'] ?? '');
    $notes  = sanitize($_POST['notes']    ?? '');
    $by     = (int)$_SESSION['user_id'];

    $t_in  = $t_in  ?: null;
    $t_out = $t_out ?: null;

    $stmt = $db->prepare("INSERT INTO attendance (staff_id, date, time_in, time_out, status, notes, recorded_by)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE time_in=VALUES(time_in), time_out=VALUES(time_out),
        status=VALUES(status), notes=VALUES(notes), recorded_by=VALUES(recorded_by)");
    $stmt->bind_param("isssssi", $sid, $date, $t_in, $t_out, $status, $notes, $by);
    $stmt->execute();
    $_SESSION['toast'] = ['msg' => 'Attendance saved!', 'type' => 'success'];
    header("Location: staff_attendance.php?date=$date&branch=$branch_filter"); exit;
}

$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Get staff list with today's attendance
$where = "WHERE 1";
if ($branch_filter) $where .= " AND s.branch_id=$branch_filter";
// Staff role: only see their own record
if (!hasRole('admin')) {
    // Find staff linked to this user via username matching
    $uid = (int)$_SESSION['user_id'];
    $user = $db->query("SELECT username FROM users WHERE id=$uid")->fetch_assoc();
    $where .= " AND s.id=(SELECT id FROM staff WHERE full_name='".$db->real_escape_string($_SESSION['full_name'])."' LIMIT 1)";
}

$staff_list = $db->query("SELECT s.id, s.staff_id as sid, s.full_name, s.role as job_role,
    b.name as branch_name,
    a.status as att_status, a.time_in, a.time_out, a.notes
    FROM staff s
    LEFT JOIN branches b ON b.id=s.branch_id
    LEFT JOIN attendance a ON a.staff_id=s.id AND a.date='$date_view'
    $where ORDER BY s.full_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="page-title">Staff Attendance</div>
        <div class="page-subtitle">
            <?= hasRole('admin') ? 'Mark and view attendance records' : 'Your attendance record' ?>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <!-- Date picker -->
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="date" name="date" class="form-control" style="width:160px;" value="<?= $date_view ?>"
                <?= !hasRole('admin') ? 'max="'.date('Y-m-d').'"' : '' ?>>
            <?php if (hasRole('admin')): ?>
            <select name="branch" class="form-select" style="width:160px;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-maroon">View</button>
        </form>
        <?php if (hasRole('admin')): ?>
        <a href="attendance_history.php" class="btn btn-outline-secondary" style="border-radius:8px;">
            <i class="bi bi-clock-history me-1"></i> History
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Date header -->
<div class="mb-3 d-flex align-items-center gap-3">
    <div style="font-size:1rem;font-weight:700;color:var(--maroon);">
        <?= $date_view === $today ? 'Today — ' : '' ?><?= date('l, F j, Y', strtotime($date_view)) ?>
    </div>
    <?php
    $present = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Present'));
    $absent  = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Absent'));
    $total   = count($staff_list);
    if ($total && hasRole('admin')):
    ?>
    <span class="status-pill status-high"><?= $present ?> Present</span>
    <span class="status-pill status-out"><?= $absent ?> Absent</span>
    <span class="status-pill" style="background:#E3F2FD;color:#1565C0;"><?= $total - $present - $absent ?> Unmarked</span>
    <?php endif; ?>
</div>

<div class="c-card">
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>Branch</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Status</th>
                <?php if (hasRole('admin')): ?><th>Action</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staff_list)): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No staff found.</td></tr>
            <?php else: foreach ($staff_list as $s):
                $ast = $s['att_status'];
                $acls = match($ast) {
                    'Present'  => 'status-high',
                    'Absent'   => 'status-out',
                    'Late'     => 'status-low',
                    'Half Day' => 'status-low',
                    default    => ''
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($s['sid']) ?></td>
                <td style="font-weight:500;"><?= htmlspecialchars($s['full_name']) ?></td>
                <td><?= htmlspecialchars($s['job_role']) ?></td>
                <td><?= htmlspecialchars($s['branch_name'] ?? '—') ?></td>
                <td><?= $s['time_in'] ? date('h:i A', strtotime($s['time_in'])) : '—' ?></td>
                <td><?= $s['time_out'] ? date('h:i A', strtotime($s['time_out'])) : '—' ?></td>
                <td>
                    <?php if ($ast): ?>
                    <span class="status-pill <?= $acls ?>"><?= $ast ?></span>
                    <?php else: ?>
                    <span style="color:#bbb;font-size:.85rem;">—</span>
                    <?php endif; ?>
                </td>
                <?php if (hasRole('admin')): ?>
                <td>
                    <button class="btn-edit btn btn-sm"
                        onclick='openMark(<?= json_encode(["id"=>$s["id"],"name"=>$s["full_name"],"status"=>$s["att_status"]??"Present","time_in"=>$s["time_in"]??"","time_out"=>$s["time_out"]??"","notes"=>$s["notes"]??""]) ?>, "<?= $date_view ?>")'>
                        <?= $ast ? 'Edit' : 'Mark' ?>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if (hasRole('admin')): ?>
<!-- Mark Attendance Modal -->
<div class="modal fade" id="markModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="staff_id" id="markStaffId">
                <input type="hidden" name="date"     id="markDate">
                <div class="modal-body">
                    <p class="mb-3" style="font-weight:600;" id="markStaffName"></p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="markStatus" class="form-select">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="Half Day">Half Day</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <input type="time" name="time_in" id="markTimeIn" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out</label>
                            <input type="time" name="time_out" id="markTimeOut" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes <span style="color:#aaa;font-weight:400;">(optional)</span></label>
                            <input type="text" name="notes" id="markNotes" class="form-control" placeholder="e.g. sick leave, official business">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon">Save Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function openMark(s, date) {
    document.getElementById('markStaffId').value   = s.id;
    document.getElementById('markDate').value       = date;
    document.getElementById('markStaffName').textContent = s.name;
    document.getElementById('markStatus').value    = s.status || 'Present';
    document.getElementById('markTimeIn').value    = s.time_in  || '';
    document.getElementById('markTimeOut').value   = s.time_out || '';
    document.getElementById('markNotes').value     = s.notes    || '';
    new bootstrap.Modal(document.getElementById('markModal')).show();
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
