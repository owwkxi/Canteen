<?php
require_once 'includes/config.php';
requireLogin();
$db = getDB();

$bid = (int)($_GET['branch'] ?? 0);
$branch = $db->query("SELECT * FROM branches WHERE id=$bid")->fetch_assoc();
if (!$branch) { header('Location: stock.php'); exit; }

$page_title = $branch['name'] . ' Stock – Canteen Management';
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$per_page   = 8;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $per_page;
$search     = sanitize($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

$where = "WHERE s.branch_id=$bid";
if ($search)     $where .= " AND (p.name LIKE '%$search%' OR p.product_id LIKE '%$search%')";
if ($cat_filter) $where .= " AND p.category_id=$cat_filter";

$total = $db->query("SELECT COUNT(*) FROM stocks s JOIN products p ON p.id=s.product_id $where")->fetch_row()[0];
$pages = max(1, ceil($total / $per_page));

$stocks = $db->query("SELECT s.id, p.product_id, p.name, s.quantity, s.last_updated
    FROM stocks s
    JOIN products p ON p.id=s.product_id
    $where
    ORDER BY s.last_updated DESC
    LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<!-- Branch header row -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="stock.php" style="color:var(--text-main);text-decoration:none;font-size:1.1rem;font-weight:600;">←</a>
        <div class="page-title mb-0"><?= strtoupper(htmlspecialchars($branch['name'])) ?></div>
    </div>
    <!-- Search + Categories -->
    <form method="GET" class="d-flex align-items-center gap-2">
        <input type="hidden" name="branch" value="<?= $bid ?>">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Search product" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2"
                style="background:var(--maroon);color:#fff;border-radius:8px;padding:8px 16px;"
                type="button" data-bs-toggle="dropdown">
                <i class="bi bi-caret-down-fill" style="font-size:.7rem;"></i> Categories
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item <?= !$cat_filter ? 'active' : '' ?>" href="stock_branch.php?branch=<?= $bid ?>">All</a></li>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <a class="dropdown-item <?= $cat_filter == $cat['id'] ? 'active' : '' ?>"
                       href="?branch=<?= $bid ?>&cat=<?= $cat['id'] ?>&q=<?= urlencode($search) ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </form>
</div>

<div class="c-card">
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($stocks)): ?>
            <tr><td colspan="6" class="text-center py-5 text-muted">No stock records found.</td></tr>
            <?php else: ?>
            <?php foreach ($stocks as $s):
                $qty = (int)$s['quantity'];
                if ($qty === 0)    { $cls = 'status-out';  $label = 'out of stock'; }
                elseif ($qty <= 5) { $cls = 'status-low';  $label = 'low in stock'; }
                else               { $cls = 'status-high'; $label = 'high stock'; }
            ?>
            <tr>
                <td><span class="id-badge"><?= htmlspecialchars($s['product_id']) ?></span></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= $qty ?></td>
                <td><span class="status-pill <?= $cls ?>"><?= $label ?></span></td>
                <td><?= date('F j, Y g:iA', strtotime($s['last_updated'])) ?></td>
                <td>
                    <a href="update_stock.php?id=<?= $s['id'] ?>&back=<?= $bid ?>" class="btn-edit btn btn-sm">Update</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div class="c-pagination">
        <a href="?branch=<?= $bid ?>&page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>"
           class="<?= $page <= 1 ? 'disabled' : '' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
        <a href="?branch=<?= $bid ?>&page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>"
           class="<?= $page >= $pages ? 'disabled' : '' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
