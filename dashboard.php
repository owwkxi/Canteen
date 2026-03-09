<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Dashboard – Canteen Management';
$db = getDB();

// Sales chart data
$chartData = [];
$branches = $db->query("SELECT id, name, code FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$dates = $db->query("SELECT DISTINCT sale_date FROM sales ORDER BY sale_date DESC LIMIT 7")->fetch_all(MYSQLI_ASSOC);
$dates = array_reverse($dates);

foreach ($dates as $d) {
    $row = ['date' => date('m-d', strtotime($d['sale_date']))];
    foreach ($branches as $b) {
        $r = $db->query("SELECT COALESCE(SUM(total_amount),0) as total FROM sales WHERE branch_id={$b['id']} AND sale_date='{$d['sale_date']}'")->fetch_assoc();
        $row[$b['code']] = (float)$r['total'];
    }
    $chartData[] = $row;
}

// Stock overview per branch
$stockOverview = [];
foreach ($branches as $b) {
    $items = $db->query("SELECT p.product_id, p.name, s.quantity FROM stocks s JOIN products p ON p.id=s.product_id WHERE s.branch_id={$b['id']} ORDER BY s.quantity ASC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    $stockOverview[$b['id']] = ['branch' => $b, 'items' => $items];
}

include 'includes/header.php';
?>
<!-- Dashboard -->
<div class="mb-4 d-flex align-items-center justify-content-between">
    <div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Overview of all canteen branches</div>
    </div>
</div>

<div class="row g-4">
    <!-- Chart -->
    <div class="col-lg-9">
        <div class="c-card p-4">
            <div class="mb-3 text-center" style="font-size:.9rem;font-weight:600;color:#555;">Sales Comparison Trend Across Branches</div>
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-3">
        <div class="c-card p-4">
            <div class="section-title mb-3">Quick Actions</div>
            <a href="invoice.php" class="quick-btn primary"><i class="bi bi-receipt me-2"></i>Create Invoice</a>
            <a href="add_product.php" class="quick-btn secondary">Add Product</a>
            <a href="stock.php" class="quick-btn secondary">Update Stock</a>
            <a href="add_staff.php" class="quick-btn secondary">Add Staff</a>
        </div>
    </div>
</div>

<!-- Stock Overview -->
<div class="row g-3 mt-2">
    <?php foreach ($stockOverview as $so): ?>
    <div class="col-md-4">`
        <div class="stock-card">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="branch-name"><?= htmlspecialchars($so['branch']['name']) ?></span>
                <a href="stock.php?branch=<?= $so['branch']['id'] ?>" style="font-size:.8rem;color:var(--maroon);text-decoration:none;">→</a>
            </div>
            <div class="last-updated">Last updated: Today, <?= date('h:iA') ?></div>
            <?php foreach ($so['items'] as $item):
                $qty = (int)$item['quantity'];
                if ($qty === 0) { $cls = 'status-out'; $label = 'out of stock'; $hint = 'order stocks now'; }
                elseif ($qty <= 5) { $cls = 'status-low'; $label = 'low in stock'; $hint = 'refill stocks now'; }
                else { $cls = 'status-high'; $label = 'high stock'; $hint = 'stocks are high'; }
            ?>
            <div class="stock-item">
                <div class="d-flex align-items-center gap-3">
                    <div class="id-badge"><?= htmlspecialchars($item['product_id']) ?></div>
                    <div class="item-info">
                        <div class="name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="qty"><?= $qty ?> items</div>
                    </div>
                </div>
                <div class="status-wrap">
                    <span class="status-pill <?= $cls ?>"><?= $label ?></span>
                    <div class="hint"><?= $hint ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartData = <?= json_encode($chartData) ?>;
const labels = chartData.map(d => d.date);
const colors = ['#5C7AEA','#FF8C42','#C77DFF'];
const branchKeys = <?= json_encode(array_column($branches, 'code')) ?>;
const branchNames = <?= json_encode(array_column($branches, 'name')) ?>;

new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: branchKeys.map((key, i) => ({
            label: branchNames[i],
            data: chartData.map(d => d[key] || 0),
            backgroundColor: colors[i],
            borderRadius: 4,
            barPercentage: 0.7,
        }))
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'right' } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php include 'includes/footer.php'; ?>
