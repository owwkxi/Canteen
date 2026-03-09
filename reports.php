<?php
require_once 'includes/config.php';
requireLogin();
// ── Access guard ─────────────────────────────────────────────────────────
if (($_SESSION["role"] ?? "") === "staff") {
    // Resolve job_role if not in session (handles users logged in before this update)
    if (!isset($_SESSION["job_role"]) || $_SESSION["job_role"] === null) {
        $db = getDB();
        if (!empty($_SESSION["staff_db_id"])) {
            $sid = (int)$_SESSION["staff_db_id"];
            $jr  = $db->query("SELECT role FROM staff WHERE id=$sid")->fetch_assoc();
        } else {
            $uid    = (int)$_SESSION["user_id"];
            $urow   = $db->query("SELECT username, full_name FROM users WHERE id=$uid")->fetch_assoc();
            $un_esc = $db->real_escape_string(strtolower($urow["username"] ?? ""));
            $fn_esc = $db->real_escape_string($urow["full_name"] ?? "");
            $jr     = $db->query("SELECT id, role FROM staff WHERE LOWER(staff_id)=\"$un_esc\" OR full_name=\"$fn_esc\" LIMIT 1")->fetch_assoc();
            $_SESSION["staff_db_id"] = $jr["id"] ?? null;
        }
        $_SESSION["job_role"] = $jr["role"] ?? null;
    }
}
if (($_SESSION["role"] ?? "") === "staff") {
    if (($_SESSION["job_role"] ?? "") !== "Cashier") {
        header("Location: staff_attendance.php"); exit;
    }
}

$page_title = 'Daily Reports – Canteen Management';
$db = getDB();

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$branch_id = (int)($_GET['branch'] ?? 0);
$branches  = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Sanitize dates
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? $date_from : date('Y-m-d');
$date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)   ? $date_to   : date('Y-m-d');
if ($date_to < $date_from) $date_to = $date_from;

$where = "WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'";
if ($branch_id) $where .= " AND s.branch_id=$branch_id";

// ── Stats ──────────────────────────────────────────────────
$stats = $db->query("SELECT
    COUNT(s.id)              AS total_invoices,
    COALESCE(SUM(s.total_amount), 0) AS total_revenue,
    COALESCE(SUM(si.quantity), 0)    AS units_sold
    FROM sales s
    LEFT JOIN sale_items si ON si.sale_id = s.id
    $where")->fetch_assoc();

// Top branch
$top = $db->query("SELECT b.name, SUM(s.total_amount) AS rev
    FROM sales s JOIN branches b ON b.id=s.branch_id
    $where GROUP BY s.branch_id ORDER BY rev DESC LIMIT 1")->fetch_assoc();

// ── Summary per branch ─────────────────────────────────────
$summary = $db->query("SELECT b.name AS branch_name,
    SUM(s.total_amount) AS total,
    COUNT(s.id)         AS txn
    FROM sales s JOIN branches b ON b.id=s.branch_id
    $where GROUP BY s.branch_id ORDER BY b.id")->fetch_all(MYSQLI_ASSOC);

$grand = array_sum(array_column($summary, 'total'));

// ── Chart data — daily totals per branch in range ──────────
$chart_dates = $db->query("SELECT DISTINCT sale_date FROM sales
    WHERE sale_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY sale_date")->fetch_all(MYSQLI_ASSOC);

$chartLabels  = [];
$chartDatasets = [];
$colors = ['#7B1416','#C0392B','#E88080'];

foreach ($chart_dates as $d) {
    $chartLabels[] = date('M j', strtotime($d['sale_date']));
}
foreach ($branches as $i => $b) {
    $bid  = $b['id'];
    $data = [];
    foreach ($chart_dates as $d) {
        $r = $db->query("SELECT COALESCE(SUM(total_amount),0) AS t
                          FROM sales WHERE branch_id=$bid
                          AND sale_date='{$d['sale_date']}'")->fetch_assoc();
        $data[] = (float)$r['t'];
    }
    $chartDatasets[] = [
        'label'           => $b['name'],
        'data'            => $data,
        'backgroundColor' => $colors[$i % count($colors)],
        'borderRadius'    => 4,
        'barPercentage'   => 0.7,
    ];
}

// ── Sales table ────────────────────────────────────────────
$per_page = 10;
$pg       = max(1, (int)($_GET['pg'] ?? 1));
$offset   = ($pg - 1) * $per_page;

$total_rows = $db->query("SELECT COUNT(*) FROM sales s $where")->fetch_row()[0];
$pages      = max(1, ceil($total_rows / $per_page));

$sales = $db->query("SELECT s.invoice_no, b.name AS branch_name,
    s.total_amount, s.sale_date, s.created_at,
    (SELECT COUNT(*) FROM sale_items WHERE sale_id=s.id) AS item_count,
    (SELECT SUM(quantity) FROM sale_items WHERE sale_id=s.id) AS units
    FROM sales s JOIN branches b ON b.id=s.branch_id
    $where ORDER BY s.sale_date DESC, s.created_at DESC
    LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

// Build query string for pagination links
$qs = http_build_query(['date_from'=>$date_from,'date_to'=>$date_to,'branch'=>$branch_id]);

include 'includes/header.php';
?>

<!-- ── Page header ─────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="page-title">Daily Reports</div>
        <div class="page-subtitle">
            Sales summary &nbsp;·&nbsp;
            <?= date('M j, Y', strtotime($date_from)) ?>
            <?= $date_from !== $date_to ? ' — ' . date('M j, Y', strtotime($date_to)) : '' ?>
        </div>
    </div>

    <!-- Filter form -->
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        <div>
            <label class="form-label mb-1" style="font-size:.75rem;font-weight:600;color:#888;">FROM</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:.75rem;font-weight:600;color:#888;">TO</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:.75rem;font-weight:600;color:#888;">BRANCH</label>
            <select name="branch" class="form-select form-select-sm" style="min-width:140px;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="align-self-end">
            <button class="btn btn-maroon btn-sm">Filter</button>
        </div>
    </form>
</div>

<!-- ── Stats cards ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="c-card p-3">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#999;">
                Total Sales
            </div>
            <div style="font-size:1.9rem;font-weight:700;color:var(--maroon);margin-top:6px;line-height:1;">
                <?= number_format($stats['total_invoices']) ?>
            </div>
            <div style="font-size:.75rem;color:#bbb;margin-top:4px;">transactions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="c-card p-3">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#999;">
                Total Revenue
            </div>
            <div style="font-size:1.9rem;font-weight:700;color:var(--maroon);margin-top:6px;line-height:1;">
                ₱<?= number_format($stats['total_revenue'], 0) ?>
            </div>
            <div style="font-size:.75rem;color:#bbb;margin-top:4px;">all branches</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="c-card p-3">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#999;">
                Units Sold
            </div>
            <div style="font-size:1.9rem;font-weight:700;color:var(--maroon);margin-top:6px;line-height:1;">
                <?= number_format($stats['units_sold']) ?>
            </div>
            <div style="font-size:.75rem;color:#bbb;margin-top:4px;">items</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="c-card p-3">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#999;">
                Top Branch
            </div>
            <div style="font-size:1.25rem;font-weight:700;color:var(--maroon);margin-top:6px;line-height:1.2;">
                <?= $top ? htmlspecialchars($top['name']) : '—' ?>
            </div>
            <div style="font-size:.75rem;color:#bbb;margin-top:4px;">
                <?= $top ? '₱'.number_format($top['rev'],0) : 'no data' ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart + Branch summary ──────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Bar chart -->
    <div class="col-lg-8">
        <div class="c-card p-4" style="height:100%;">
            <div style="font-size:.8rem;font-weight:600;color:#777;margin-bottom:12px;">
                Revenue by Branch
                <span style="color:#bbb;font-weight:400;margin-left:8px;">
                    <?= date('M j', strtotime($date_from)) ?>
                    <?= $date_from !== $date_to ? ' – '.date('M j', strtotime($date_to)) : '' ?>
                </span>
            </div>
            <?php if (empty($chartLabels)): ?>
            <div class="text-center py-5 text-muted" style="font-size:.875rem;">
                No sales data for this period.
            </div>
            <?php else: ?>
            <canvas id="revenueChart" height="120"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Per-branch breakdown -->
    <div class="col-lg-4">
        <div class="c-card p-4" style="height:100%;">
            <div style="font-size:.8rem;font-weight:600;color:#777;margin-bottom:14px;">Branch Breakdown</div>
            <?php if (empty($summary)): ?>
            <div class="text-muted" style="font-size:.875rem;">No data.</div>
            <?php else: ?>
            <?php foreach ($summary as $i => $s):
                $pct = $grand > 0 ? round(($s['total']/$grand)*100) : 0;
                $bar_color = $colors[$i % count($colors)];
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between mb-1" style="font-size:.82rem;">
                    <span style="font-weight:600;"><?= htmlspecialchars($s['branch_name']) ?></span>
                    <span style="color:var(--maroon);font-weight:700;">₱<?= number_format($s['total'],0) ?></span>
                </div>
                <!-- Progress bar -->
                <div style="height:6px;background:#f0eeec;border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $bar_color ?>;border-radius:4px;transition:width .4s;"></div>
                </div>
                <div style="font-size:.72rem;color:#bbb;margin-top:3px;"><?= $s['txn'] ?> sale<?= $s['txn']!=1?'s':'' ?> &nbsp;·&nbsp; <?= $pct ?>%</div>
            </div>
            <?php endforeach; ?>
            <div class="mt-3 pt-3" style="border-top:1px solid #f0eeec;">
                <div class="d-flex justify-content-between" style="font-size:.85rem;font-weight:700;">
                    <span>Total</span>
                    <span style="color:var(--maroon);">₱<?= number_format($grand, 2) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Sales table ─────────────────────────────────────── -->
<div class="c-card">
    <div style="padding:16px 20px 0;border-bottom:1px solid #f0eeec;">
        <span style="font-size:.85rem;font-weight:700;color:#444;">
            Sales
        </span>
        <span style="font-size:.8rem;color:#bbb;margin-left:8px;"><?= $total_rows ?> record<?= $total_rows!=1?'s':'' ?></span>
    </div>
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>Sale No</th>
                <th>Branch</th>
                <th>Date</th>
                <th>Items</th>
                <th>Units</th>
                <th>Amount</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sales)): ?>
            <tr><td colspan="7" class="text-center py-5 text-muted">No sales data for this period.</td></tr>
            <?php else: foreach ($sales as $sale): ?>
            <tr>
                <td style="font-weight:600;color:var(--maroon);">
                    <?= htmlspecialchars($sale['invoice_no']) ?>
                </td>
                <td><?= htmlspecialchars($sale['branch_name']) ?></td>
                <td><?= date('M j, Y', strtotime($sale['sale_date'])) ?></td>
                <td style="color:#888;"><?= $sale['item_count'] ?> product<?= $sale['item_count']!=1?'s':'' ?></td>
                <td style="color:#888;"><?= number_format($sale['units']) ?> pcs</td>
                <td style="font-weight:700;">₱<?= number_format($sale['total_amount'], 2) ?></td>
                <td style="color:#aaa;font-size:.82rem;"><?= date('g:iA', strtotime($sale['created_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <div class="c-pagination">
        <a href="?<?= $qs ?>&pg=<?= $pg-1 ?>" class="<?= $pg<=1?'disabled':'' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $pg ?> of <?= $pages ?></span>
        <a href="?<?= $qs ?>&pg=<?= $pg+1 ?>" class="<?= $pg>=$pages?'disabled':'' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php if (!empty($chartLabels)): ?>
<script>
const labels   = <?= json_encode($chartLabels) ?>;
const datasets = <?= json_encode($chartDatasets) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels, datasets },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 }, boxWidth: 12 } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ₱' + ctx.parsed.y.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => '₱' + v.toLocaleString() },
                grid: { color: '#f0eeec' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
