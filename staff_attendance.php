<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Staff Attendance – Canteen Management';
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

$today         = date('Y-m-d');
$date_view     = sanitize($_GET['date'] ?? $today);
$branch_filter = (int)($_GET['branch'] ?? 0);
$is_admin      = hasRole('admin') || hasRole('super_admin');

// ── Find the staff record linked to the logged-in user ────────────────────────
$my_staff = null;
if (!$is_admin) {
    $fn = $db->real_escape_string($_SESSION['full_name']);
    $my_staff = $db->query("SELECT id, staff_id as sid, full_name, role as job_role FROM staff
                             WHERE full_name='$fn' LIMIT 1")->fetch_assoc();
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'mark';

    // Staff self-service: check-in / check-out
    if ($action === 'self_checkin' && $my_staff) {
        $sid  = (int)$my_staff['id'];
        $now  = date('H:i:s');
        $by   = (int)$_SESSION['user_id'];
        // Insert with Present + time_in; if record exists, only update time_in if null
        $db->query("INSERT INTO attendance (staff_id, date, time_in, status, recorded_by)
                    VALUES ($sid, '$today', '$now', 'Present', $by)
                    ON DUPLICATE KEY UPDATE
                        time_in = IF(time_in IS NULL, '$now', time_in),
                        status  = IF(status IS NULL OR status='', 'Present', status),
                        recorded_by = $by");
        $_SESSION['toast'] = ['msg' => 'Checked in at ' . date('h:i A'), 'type' => 'success'];
        header("Location: staff_attendance.php"); exit;
    }

    if ($action === 'self_checkout' && $my_staff) {
        $sid = (int)$my_staff['id'];
        $now = date('H:i:s');
        $by  = (int)$_SESSION['user_id'];
        $db->query("INSERT INTO attendance (staff_id, date, time_out, status, recorded_by)
                    VALUES ($sid, '$today', '$now', 'Present', $by)
                    ON DUPLICATE KEY UPDATE
                        time_out = '$now', recorded_by = $by");
        $_SESSION['toast'] = ['msg' => 'Checked out at ' . date('h:i A'), 'type' => 'success'];
        header("Location: staff_attendance.php"); exit;
    }

    // Admin mark / quick-mark
    if ($is_admin) {
        $sid   = (int)$_POST['staff_id'];
        $date  = sanitize($_POST['date']);
        $status= sanitize($_POST['status']);
        $t_in  = sanitize($_POST['time_in']  ?? '') ?: null;
        $t_out = sanitize($_POST['time_out'] ?? '') ?: null;
        $notes = sanitize($_POST['notes']    ?? '');
        $by    = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("INSERT INTO attendance (staff_id, date, time_in, time_out, status, notes, recorded_by)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE time_in=VALUES(time_in), time_out=VALUES(time_out),
            status=VALUES(status), notes=VALUES(notes), recorded_by=VALUES(recorded_by)");
        $stmt->bind_param("isssssi", $sid, $date, $t_in, $t_out, $status, $notes, $by);
        $stmt->execute();

        if (!empty($_POST['ajax'])) { echo json_encode(['ok'=>true]); exit; }
        $_SESSION['toast'] = ['msg' => 'Attendance saved!', 'type' => 'success'];
        header("Location: staff_attendance.php?date=$date&branch=$branch_filter"); exit;
    }
}

$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Build staff list
if ($is_admin) {
    $where = "WHERE 1";
    if ($branch_filter) $where .= " AND s.branch_id=$branch_filter";
    $staff_list = $db->query("SELECT s.id, s.staff_id as sid, s.full_name, s.role as job_role,
        b.name as branch_name,
        a.status as att_status, a.time_in, a.time_out, a.notes
        FROM staff s
        LEFT JOIN branches b ON b.id=s.branch_id
        LEFT JOIN attendance a ON a.staff_id=s.id AND a.date='$date_view'
        $where ORDER BY s.full_name")->fetch_all(MYSQLI_ASSOC);
} else {
    // Staff only sees their own record
    $staff_list = [];
    if ($my_staff) {
        $sid = (int)$my_staff['id'];
        $rec = $db->query("SELECT s.id, s.staff_id as sid, s.full_name, s.role as job_role,
            b.name as branch_name,
            a.status as att_status, a.time_in, a.time_out, a.notes
            FROM staff s
            LEFT JOIN branches b ON b.id=s.branch_id
            LEFT JOIN attendance a ON a.staff_id=s.id AND a.date='$today'
            WHERE s.id=$sid")->fetch_assoc();
        if ($rec) $staff_list[] = $rec;
    }
}

// Summary counts (admin only)
$count_present  = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Present'));
$count_absent   = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Absent'));
$count_late     = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Late'));
$count_half     = count(array_filter($staff_list, fn($s) => $s['att_status'] === 'Half Day'));
$count_unmarked = count($staff_list) - $count_present - $count_absent - $count_late - $count_half;

include 'includes/header.php';
?>

<style>
/* ── Attendance card styles ──────────────────────────── */
.att-summary { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:24px; }
.att-sum-pill {
    display:flex; align-items:center; gap:8px;
    padding:10px 18px; border-radius:40px; font-size:.82rem; font-weight:700;
    border:2px solid transparent;
}
.pill-present  { background:#E8F5E9; color:#2E7D32; border-color:#A5D6A7; }
.pill-absent   { background:#FFEBEE; color:#C62828; border-color:#EF9A9A; }
.pill-late     { background:#FFF8E1; color:#E65100; border-color:#FFE082; }
.pill-half     { background:#E3F2FD; color:#1565C0; border-color:#90CAF9; }
.pill-unmarked { background:#F5F5F5; color:#757575; border-color:#E0E0E0; }
.att-sum-pill .dot { width:9px; height:9px; border-radius:50%; display:inline-block; }
.dot-present  { background:#2E7D32; }
.dot-absent   { background:#C62828; }
.dot-late     { background:#E65100; }
.dot-half     { background:#1565C0; }
.dot-unmarked { background:#9E9E9E; }

.att-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap:16px;
}
.att-card {
    background:#fff; border:1.5px solid #EDE9E4; border-radius:14px;
    padding:18px 18px 14px; transition:box-shadow .2s, border-color .2s;
}
.att-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.07); }
.att-card.is-present { border-left:4px solid #43A047; }
.att-card.is-absent  { border-left:4px solid #E53935; }
.att-card.is-late    { border-left:4px solid #FB8C00; }
.att-card.is-half    { border-left:4px solid #1E88E5; }
.att-card.is-none    { border-left:4px solid #E0E0E0; }

/* Self-service card (staff own view) */
.self-card {
    background:#fff; border:1.5px solid #EDE9E4; border-radius:16px;
    padding:28px; max-width:520px; margin:0 auto;
}
.self-avatar {
    width:72px; height:72px; border-radius:50%; background:var(--maroon);
    display:flex; align-items:center; justify-content:center;
    font-size:1.6rem; font-weight:800; color:#fff; margin:0 auto 16px;
}
.self-time-display {
    font-size:2.8rem; font-weight:800; color:var(--maroon);
    text-align:center; letter-spacing:-.02em; line-height:1;
    margin-bottom:4px;
}
.self-date { font-size:.875rem; color:#888; text-align:center; margin-bottom:24px; }
.checkin-btn {
    width:100%; padding:13px; border-radius:10px; border:none;
    font-size:1rem; font-weight:700; cursor:pointer; transition:all .15s;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.checkin-btn.btn-in  { background:#43A047; color:#fff; }
.checkin-btn.btn-out { background:#E53935; color:#fff; }
.checkin-btn:hover   { opacity:.88; transform:translateY(-1px); }
.checkin-btn:disabled { opacity:.5; cursor:not-allowed; transform:none; }

.att-avatar {
    width:46px; height:46px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; font-weight:800; color:#fff; background:var(--maroon);
}
.att-badge {
    font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:20px;
    text-transform:uppercase; letter-spacing:.04em;
}
.badge-present  { background:#E8F5E9; color:#2E7D32; }
.badge-absent   { background:#FFEBEE; color:#C62828; }
.badge-late     { background:#FFF8E1; color:#E65100; }
.badge-half     { background:#E3F2FD; color:#1565C0; }
.badge-none     { background:#F5F5F5; color:#9E9E9E; }

.quick-mark-row { display:flex; gap:6px; flex-wrap:wrap; margin-top:12px; }
.qm-btn {
    flex:1; min-width:60px; padding:5px 4px; font-size:.72rem; font-weight:700;
    border:1.5px solid #E0E0E0; border-radius:8px; cursor:pointer;
    background:#F5F5F5; color:#555; transition:all .15s; text-align:center;
}
.qm-btn:hover { opacity:.85; transform:translateY(-1px); }
.qm-btn.active-present { background:#43A047; color:#fff; border-color:#43A047; }
.qm-btn.active-absent  { background:#E53935; color:#fff; border-color:#E53935; }
.qm-btn.active-late    { background:#FB8C00; color:#fff; border-color:#FB8C00; }
.qm-btn.active-half    { background:#1E88E5; color:#fff; border-color:#1E88E5; }

.time-chip {
    font-size:.75rem; color:#888; background:#F8F6F4; border-radius:6px;
    padding:2px 8px; display:inline-flex; align-items:center; gap:4px;
}
</style>

<!-- ── Page Header ─────────────────────────────────────── -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px;">
    <div>
        <div class="page-title">Staff Attendance</div>
        <div class="page-subtitle" style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-calendar3" style="font-size:.9rem;"></i>
            <?= date('l, F j, Y', strtotime($is_admin ? $date_view : $today)) ?>
            <?php if (($is_admin ? $date_view : $today) === $today): ?>
            <span style="background:var(--maroon);color:#fff;font-size:.68rem;font-weight:700;
                         padding:2px 8px;border-radius:20px;letter-spacing:.04em;">TODAY</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_admin): ?>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <select name="branch" class="form-select form-select-sm" style="width:155px;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-maroon btn-sm">View</button>
        </form>
        <a href="attendance_history.php" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;">
            <i class="bi bi-clock-history me-1"></i> History
        </a>
    </div>
    <?php else: ?>
    <a href="attendance_history.php" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;">
        <i class="bi bi-clock-history me-1"></i> My History
    </a>
    <?php endif; ?>
</div>

<?php if (!$is_admin): ?>
<!-- ════════════════════════════════════════════════════════
     STAFF SELF-SERVICE VIEW
═════════════════════════════════════════════════════════ -->
<?php if (!$my_staff): ?>
<div class="c-card p-5 text-center text-muted">
    <i class="bi bi-person-x" style="font-size:2rem;display:block;margin-bottom:12px;"></i>
    No staff profile linked to your account. Please contact an admin.
</div>
<?php else:
    $s   = $staff_list[0] ?? null;
    $ast = $s['att_status'] ?? null;
    $has_in  = !empty($s['time_in']);
    $has_out = !empty($s['time_out']);

    $initials = substr(implode('', array_map(fn($w) => strtoupper($w[0]),
                array_filter(explode(' ', $my_staff['full_name'])))), 0, 2);
    $badge_cls = match($ast) {
        'Present'  => 'badge-present',
        'Absent'   => 'badge-absent',
        'Late'     => 'badge-late',
        'Half Day' => 'badge-half',
        default    => 'badge-none',
    };
?>
<div class="self-card">
    <div class="self-avatar"><?= htmlspecialchars($initials) ?></div>
    <div style="text-align:center;font-size:1.15rem;font-weight:700;margin-bottom:2px;">
        <?= htmlspecialchars($my_staff['full_name']) ?>
    </div>
    <div style="text-align:center;color:#999;font-size:.8rem;margin-bottom:20px;">
        <?= htmlspecialchars($my_staff['sid']) ?> &bull; <?= htmlspecialchars($my_staff['job_role']) ?>
    </div>

    <!-- Live clock -->
    <div class="self-time-display" id="liveClock">--:--:--</div>
    <div class="self-date"><?= date('l, F j, Y') ?></div>

    <!-- Current status -->
    <div style="text-align:center;margin-bottom:20px;">
        <span class="att-badge <?= $badge_cls ?>" style="font-size:.82rem;padding:5px 16px;">
            <?= $ast ?: 'Not yet marked' ?>
        </span>
        <?php if ($has_in || $has_out): ?>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:10px;flex-wrap:wrap;">
            <?php if ($has_in): ?>
            <span class="time-chip">
                <i class="bi bi-box-arrow-in-right"></i>
                Checked in: <?= date('h:i A', strtotime($s['time_in'])) ?>
            </span>
            <?php endif; ?>
            <?php if ($has_out): ?>
            <span class="time-chip">
                <i class="bi bi-box-arrow-right"></i>
                Checked out: <?= date('h:i A', strtotime($s['time_out'])) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Check-in / Check-out buttons -->
    <div style="display:flex;flex-direction:column;gap:10px;">
        <form method="POST">
            <input type="hidden" name="action" value="self_checkin">
            <button type="submit" class="checkin-btn btn-in"
                    <?= $has_in ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-in-right"></i>
                <?= $has_in ? 'Already Checked In (' . date('h:i A', strtotime($s['time_in'])) . ')' : 'Check In Now' ?>
            </button>
        </form>
        <form method="POST">
            <input type="hidden" name="action" value="self_checkout">
            <button type="submit" class="checkin-btn btn-out"
                    <?= (!$has_in || $has_out) ? 'disabled' : '' ?>>
                <i class="bi bi-box-arrow-right"></i>
                <?= $has_out ? 'Checked Out (' . date('h:i A', strtotime($s['time_out'])) . ')' : 'Check Out Now' ?>
            </button>
        </form>
    </div>

    <?php if ($s && $s['notes']): ?>
    <div style="margin-top:16px;padding:10px 14px;background:#FFF8E1;border-radius:8px;font-size:.82rem;color:#888;">
        <i class="bi bi-sticky me-1"></i> <?= htmlspecialchars($s['notes']) ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Live clock
function tick() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h + ':' + m + ':' + s;
}
tick(); setInterval(tick, 1000);
</script>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════
     ADMIN / SUPER ADMIN VIEW — CARD GRID
═════════════════════════════════════════════════════════ -->

<!-- Summary pills -->
<?php if (count($staff_list)): ?>
<div class="att-summary">
    <div class="att-sum-pill pill-present"><span class="dot dot-present"></span><?= $count_present ?> Present</div>
    <div class="att-sum-pill pill-absent"><span class="dot dot-absent"></span><?= $count_absent ?> Absent</div>
    <div class="att-sum-pill pill-late"><span class="dot dot-late"></span><?= $count_late ?> Late</div>
    <div class="att-sum-pill pill-half"><span class="dot dot-half"></span><?= $count_half ?> Half Day</div>
    <div class="att-sum-pill pill-unmarked"><span class="dot dot-unmarked"></span><?= $count_unmarked ?> Unmarked</div>
</div>
<?php endif; ?>

<?php if (empty($staff_list)): ?>
<div class="c-card p-5 text-center text-muted">No staff found.</div>
<?php else: ?>
<div class="att-grid">
    <?php foreach ($staff_list as $s):
        $ast = $s['att_status'] ?? '';
        $initials = substr(implode('', array_map(fn($w) => strtoupper($w[0]),
                    array_filter(explode(' ', $s['full_name'])))), 0, 2);
        $card_cls  = match($ast) { 'Present'=>'is-present','Absent'=>'is-absent','Late'=>'is-late','Half Day'=>'is-half',default=>'is-none' };
        $badge_cls = match($ast) { 'Present'=>'badge-present','Absent'=>'badge-absent','Late'=>'badge-late','Half Day'=>'badge-half',default=>'badge-none' };
    ?>
    <div class="att-card <?= $card_cls ?>" id="card-<?= $s['id'] ?>">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <div class="att-avatar"><?= htmlspecialchars($initials) ?></div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= htmlspecialchars($s['full_name']) ?>
                </div>
                <div style="font-size:.75rem;color:#999;margin-top:1px;">
                    <?= htmlspecialchars($s['sid']) ?> &bull; <?= htmlspecialchars($s['job_role']) ?>
                    <?= $s['branch_name'] ? ' &bull; ' . htmlspecialchars($s['branch_name']) : '' ?>
                </div>
            </div>
            <span class="att-badge <?= $badge_cls ?>"><?= $ast ?: 'Unmarked' ?></span>
        </div>

        <?php if ($s['time_in'] || $s['time_out']): ?>
        <div style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
            <?php if ($s['time_in']): ?>
            <span class="time-chip"><i class="bi bi-box-arrow-in-right" style="font-size:.7rem;"></i>In: <?= date('h:i A', strtotime($s['time_in'])) ?></span>
            <?php endif; ?>
            <?php if ($s['time_out']): ?>
            <span class="time-chip"><i class="bi bi-box-arrow-right" style="font-size:.7rem;"></i>Out: <?= date('h:i A', strtotime($s['time_out'])) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($s['notes']): ?>
        <div style="font-size:.75rem;color:#888;margin-bottom:8px;font-style:italic;">"<?= htmlspecialchars($s['notes']) ?>"</div>
        <?php endif; ?>

        <div class="quick-mark-row">
            <?php
            $cls_map = ['Present'=>'active-present','Absent'=>'active-absent','Late'=>'active-late','Half Day'=>'active-half'];
            foreach (['Present','Absent','Late','Half Day'] as $st):
                $act = ($ast === $st) ? $cls_map[$st] : '';
            ?>
            <button type="button" class="qm-btn <?= $act ?>"
                    onclick="quickMark(<?= $s['id'] ?>, '<?= $st ?>', '<?= $date_view ?>', this)">
                <?= $st === 'Half Day' ? 'Half' : $st ?>
            </button>
            <?php endforeach; ?>
            <button type="button" class="qm-btn" style="flex:0;min-width:34px;"
                    onclick='openDetail(<?= json_encode(["id"=>$s["id"],"name"=>$s["full_name"],"status"=>$ast?:"Present","time_in"=>$s["time_in"]??"","time_out"=>$s["time_out"]??"","notes"=>$s["notes"]??""]) ?>, "<?= $date_view ?>")'>
                <i class="bi bi-pencil"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Detail / Edit Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-calendar-check me-2" style="color:var(--maroon);"></i>Edit Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="staff_id" id="detailStaffId">
                <input type="hidden" name="date"     id="detailDate">
                <div class="modal-body pt-2">
                    <p style="font-weight:700;color:var(--maroon);margin-bottom:16px;" id="detailName"></p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;" id="statusBtns">
                                <?php foreach (['Present','Absent','Late','Half Day'] as $st): ?>
                                <button type="button" class="btn btn-sm status-pick-btn"
                                        data-value="<?= $st ?>"
                                        style="border-radius:20px;border:1.5px solid #ddd;padding:5px 16px;">
                                    <?= $st ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="status" id="detailStatus" value="Present">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <input type="time" name="time_in" id="detailTimeIn" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out</label>
                            <input type="time" name="time_out" id="detailTimeOut" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes <span style="color:#aaa;font-weight:400;">(optional)</span></label>
                            <input type="text" name="notes" id="detailNotes" class="form-control"
                                   placeholder="e.g. sick leave, official business">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function quickMark(staffId, status, date, btn) {
    const row = btn.closest('.quick-mark-row');
    row.querySelectorAll('.qm-btn').forEach(b => b.classList.remove('active-present','active-absent','active-late','active-half'));
    const clsMap = {'Present':'active-present','Absent':'active-absent','Late':'active-late','Half Day':'active-half'};
    if (clsMap[status]) btn.classList.add(clsMap[status]);
    const card   = document.getElementById('card-' + staffId);
    const badge  = card.querySelector('.att-badge');
    const clsCls = {'Present':'is-present','Absent':'is-absent','Late':'is-late','Half Day':'is-half'};
    const bdgCls = {'Present':'badge-present','Absent':'badge-absent','Late':'badge-late','Half Day':'badge-half'};
    card.className = card.className.replace(/is-\S+/, clsCls[status] || 'is-none');
    badge.className = 'att-badge ' + (bdgCls[status] || 'badge-none');
    badge.textContent = status;
    const fd = new FormData();
    fd.append('staff_id', staffId); fd.append('date', date);
    fd.append('status', status);    fd.append('ajax', '1');
    fetch('staff_attendance.php', { method:'POST', body: fd }).catch(() => {});
}

const statusColors = { 'Present':'#43A047','Absent':'#E53935','Late':'#FB8C00','Half Day':'#1E88E5' };
function openDetail(s, date) {
    document.getElementById('detailStaffId').value = s.id;
    document.getElementById('detailDate').value    = date;
    document.getElementById('detailName').textContent = s.name;
    document.getElementById('detailTimeIn').value  = s.time_in  || '';
    document.getElementById('detailTimeOut').value = s.time_out || '';
    document.getElementById('detailNotes').value   = s.notes    || '';
    setStatus(s.status || 'Present');
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}
function setStatus(val) {
    document.getElementById('detailStatus').value = val;
    document.querySelectorAll('.status-pick-btn').forEach(b => {
        const a = b.dataset.value === val;
        b.style.background  = a ? statusColors[val] : '#fff';
        b.style.color       = a ? '#fff' : '#555';
        b.style.borderColor = a ? statusColors[val] : '#ddd';
        b.style.fontWeight  = a ? '700' : '500';
    });
}
document.querySelectorAll('.status-pick-btn').forEach(b => b.addEventListener('click', () => setStatus(b.dataset.value)));
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
