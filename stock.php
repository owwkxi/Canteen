<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Stock Management – Canteen Management';
$db = getDB();

$search   = sanitize($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$total_products = $db->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$total_cats     = count($categories);

// Build stock preview per branch (3 rows each, matching search/category)
$branch_stocks = [];
foreach ($branches as $b) {
    $bid   = $b['id'];
    $where = "WHERE s.branch_id=$bid";
    $params = [];
    if ($search)     { $where .= " AND (p.name LIKE '%$search%' OR p.product_id LIKE '%$search%')"; }
    if ($cat_filter) { $where .= " AND p.category_id=$cat_filter"; }
    $rows = $db->query("SELECT s.id, p.product_id, p.name, c.name as cat_name, s.quantity, s.last_updated
        FROM stocks s
        JOIN products p ON p.id=s.product_id
        LEFT JOIN categories c ON c.id=p.category_id
        $where ORDER BY s.last_updated DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    $branch_stocks[$bid] = $rows;
}

include 'includes/header.php';
?>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-1">
    <div>
        <div class="page-title">Stock Management</div>
        <div class="page-subtitle">Total Products: <?= $total_products ?> &nbsp;&nbsp; Categories: <?= $total_cats ?></div>
    </div>
    <!-- Search + Categories (top right) -->
    <div class="d-flex align-items-center gap-3">
        <form method="GET" class="d-flex align-items-center gap-2">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" name="q" placeholder="Search product" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="dropdown">
                <button class="btn btn-sm d-flex align-items-center gap-2" style="background:var(--maroon);color:#fff;border-radius:8px;padding:8px 16px;" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-caret-down-fill" style="font-size:.7rem;"></i> Categories
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item <?= !$cat_filter ? 'active' : '' ?>" href="stock.php">All</a></li>
                    <?php foreach ($categories as $cat): ?>
                    <li><a class="dropdown-item <?= $cat_filter == $cat['id'] ? 'active' : '' ?>" href="?cat=<?= $cat['id'] ?>&q=<?= urlencode($search) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </form>
    </div>
</div>

<div class="mt-4">
<?php foreach ($branches as $b):
    $rows = $branch_stocks[$b['id']];
?>
<!-- Branch Section -->
<div class="mb-5">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="section-title mb-0" style="font-size:1.1rem;letter-spacing:.01em;"><?= strtoupper(htmlspecialchars($b['name'])) ?></div>
        <a href="stock_branch.php?branch=<?= $b['id'] ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>" style="color:var(--text-main);text-decoration:none;font-size:1.1rem;">→</a>
    </div>

    <div class="c-card">
        <div style="overflow-x:auto;">
        <table class="c-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Quantity</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted" style="font-size:.875rem;">No stock records found.</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $s):
                    $qty = (int)$s['quantity'];
                    if ($qty === 0)    { $cls = 'status-out';  $label = 'out of stock'; }
                    elseif ($qty <= 5) { $cls = 'status-low';  $label = 'low in stock'; }
                    else               { $cls = 'status-high'; $label = 'high stock'; }
                ?>
                <tr>
                    <td><span class="id-badge"><?= htmlspecialchars($s['product_id']) ?></span></td>
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td><?= $qty ?></td>
                    <td><?= htmlspecialchars($s['cat_name'] ?? '—') ?></td>
                    <td><span class="status-pill <?= $cls ?>"><?= $label ?></span></td>
                    <td><?= date('F j, Y g:iA', strtotime($s['last_updated'])) ?></td>
                    <td><a href="update_stock.php?id=<?= $s['id'] ?>" class="btn-edit btn btn-sm">Update</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
