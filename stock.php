<?php
require_once 'includes/config.php';
requireLogin();

// ── Access guard ──────────────────────────────────────────────────────────────
if (($_SESSION["role"] ?? "") === "staff") {
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
    if (($_SESSION["job_role"] ?? "") !== "Cashier") {
        header("Location: staff_attendance.php"); exit;
    }
}

$page_title    = 'Stock Management – Canteen Management';
$db            = getDB();
$search        = sanitize($_GET['q'] ?? '');
$cat_filter    = (int)($_GET['cat'] ?? 0);
$rows_per_page = 5;

$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$total_products = $db->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$total_cats     = count($categories);

// ── Per-branch paginated stock ────────────────────────────────────────────────
$branch_stocks = [];
$branch_pages  = [];

foreach ($branches as $b) {
    $bid      = $b['id'];
    $page_key = "page_b{$bid}";
    $cur_page = max(1, (int)($_GET[$page_key] ?? 1));

    $where = "WHERE s.branch_id={$bid}";
    if ($search)     $where .= " AND (p.name LIKE '%{$search}%' OR p.product_id LIKE '%{$search}%')";
    if ($cat_filter) $where .= " AND p.category_id={$cat_filter}";

    $total_rows  = (int)$db->query("SELECT COUNT(*) FROM stocks s JOIN products p ON p.id=s.product_id LEFT JOIN categories c ON c.id=p.category_id {$where}")->fetch_row()[0];
    $total_pages = max(1, (int)ceil($total_rows / $rows_per_page));
    $cur_page    = min($cur_page, $total_pages);
    $offset      = ($cur_page - 1) * $rows_per_page;

    $branch_stocks[$bid] = $db->query(
        "SELECT s.id, p.product_id, p.name, c.name AS cat_name, s.quantity, s.last_updated
         FROM stocks s
         JOIN products p ON p.id = s.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         {$where}
         ORDER BY s.last_updated DESC
         LIMIT {$rows_per_page} OFFSET {$offset}"
    )->fetch_all(MYSQLI_ASSOC);

    $branch_pages[$bid] = [
        'current'    => $cur_page,
        'total'      => $total_pages,
        'total_rows' => $total_rows,
        'key'        => $page_key,
    ];
}

function paginationUrl(string $key, int $page, string $anchor = ''): string {
    $params = array_merge($_GET, [$key => $page]);
    $url = '?' . http_build_query($params);
    if ($anchor) $url .= '#' . $anchor;
    return $url;
}

include 'includes/header.php';
?>

<style>
/* Branch anchor offset for sticky header */
.branch-anchor {
    display: block;
    height: 0;
    visibility: hidden;
    margin-top: -80px;
    padding-top: 80px;
}
/* Clean table */
.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .875rem;
}
.stock-table thead tr {
    border-bottom: 1px solid #e5e7eb;
}
.stock-table thead th {
    padding: 10px 16px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .07em;
    color: #9ca3af;
    text-transform: uppercase;
    background: transparent;
    border: none;
    white-space: nowrap;
}
.stock-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background .12s;
}
.stock-table tbody tr:last-child { border-bottom: none; }
.stock-table tbody tr:hover { background: #fafafa; }
.stock-table tbody td {
    padding: 14px 16px;
    color: var(--text-main, #111827);
    vertical-align: middle;
    border: none;
}
/* Pagination plain-text style */
.stock-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 10px;
    border-top: 1px solid #f3f4f6;
    font-size: .85rem;
}
.stock-pagination a,
.stock-pagination span.pag-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-main, #374151);
    text-decoration: none;
    font-weight: 500;
    transition: opacity .15s;
}
.stock-pagination a:hover { opacity: .65; }
.stock-pagination span.pag-btn { color: #d1d5db; cursor: default; }
.stock-pagination .pag-center {
    font-weight: 600;
    color: var(--text-main, #374151);
}
</style>

<!-- ── Page Header ───────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-1">
    <div>
        <div class="page-title">Stock Management</div>
        <div class="page-subtitle">Total Products: <?= $total_products ?> &nbsp;&nbsp; Categories: <?= $total_cats ?></div>
    </div>

    <form method="GET" class="d-flex align-items-center gap-2">
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
                <li><a class="dropdown-item <?= !$cat_filter ? 'active' : '' ?>" href="stock.php">All</a></li>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <a class="dropdown-item <?= $cat_filter == $cat['id'] ? 'active' : '' ?>"
                       href="?cat=<?= $cat['id'] ?>&q=<?= urlencode($search) ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </form>
</div>

<!-- ── Branch Tables ─────────────────────────────────────────────────────────── -->
<div class="mt-4">
<?php foreach ($branches as $b):
    $bid    = $b['id'];
    $rows   = $branch_stocks[$bid];
    $pg     = $branch_pages[$bid];
    $anchor = 'branch-' . $bid;

    $cur        = $pg['current'];
    $total      = $pg['total'];
    $key        = $pg['key'];
    $total_rows = $pg['total_rows'];
    $from       = $total_rows > 0 ? ($cur - 1) * $rows_per_page + 1 : 0;
    $to         = min($cur * $rows_per_page, $total_rows);
?>
<div class="mb-5">
    <span class="branch-anchor" id="<?= $anchor ?>"></span>
    <div class="section-title mb-2" style="font-size:1.1rem;letter-spacing:.01em;">
        <?= strtoupper(htmlspecialchars($b['name'])) ?>
    </div>

    <div class="c-card" style="padding:0;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="stock-table">
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
                    <tr>
                        <td colspan="7" style="text-align:center;padding:32px 16px;color:#9ca3af;font-size:.875rem;">
                            No stock records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $s):
                        $qty = (int)$s['quantity'];
                        if ($qty === 0)     { $cls = 'status-out';  $label = 'out of stock'; }
                        elseif ($qty <= 15) { $cls = 'status-low';  $label = 'low in stock'; }
                        else               { $cls = 'status-high'; $label = 'high stock';   }
                    ?>
                    <tr>
                        <td><span class="id-badge"><?= htmlspecialchars($s['product_id']) ?></span></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= $qty ?></td>
                        <td><?= htmlspecialchars($s['cat_name'] ?? '—') ?></td>
                        <td><span class="status-pill <?= $cls ?>"><?= $label ?></span></td>
                        <td style="color:#6b7280;"><?= date('F j, Y g:iA', strtotime($s['last_updated'])) ?></td>
                        <td><a href="update_stock.php?id=<?= $s['id'] ?>" class="btn btn-edit2 btn-sm">Update</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="stock-pagination">
            <?php if ($cur > 1): ?>
                <a href="<?= paginationUrl($key, $cur - 1, $anchor) ?>">
                    <i class="bi bi-arrow-left"></i> Previous
                </a>
            <?php else: ?>
                <span class="pag-btn"><i class="bi bi-arrow-left"></i> Previous</span>
            <?php endif; ?>

            <span class="pag-center">Page <?= $cur ?> of <?= $total ?></span>

            <?php if ($cur < $total): ?>
                <a href="<?= paginationUrl($key, $cur + 1, $anchor) ?>">
                    Next <i class="bi bi-arrow-right"></i>
                </a>
            <?php else: ?>
                <span class="pag-btn">Next <i class="bi bi-arrow-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
