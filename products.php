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

$page_title = 'Products – Canteen Management';
$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['product_id'] ?? 0);
    if ($del_id) {
        // Fetch name for the toast message before deleting
        $p = $db->query("SELECT product_id, name FROM products WHERE id=$del_id")->fetch_assoc();
        $db->query("DELETE FROM products WHERE id=$del_id");
        $label = $p ? htmlspecialchars($p['product_id'] . ' – ' . $p['name']) : "Product #$del_id";
        $_SESSION['toast'] = ['msg' => "<strong>$label</strong> deleted.", 'type' => 'success'];
    }
    header('Location: products.php'); exit;
}

$per_page = 7;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = sanitize($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

$where  = "WHERE 1=1";
$params = [];
$types  = "";
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.product_id LIKE ?)";
    $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= "ss";
}
if ($cat_filter) {
    $where .= " AND p.category_id=?";
    $params[] = $cat_filter; $types .= "i";
}

$count_sql = "SELECT COUNT(*) FROM products p $where";
$stmt = $db->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_row()[0];
$pages = max(1, ceil($total / $per_page));

$sql = "SELECT p.*, c.name as cat_name,
        (SELECT GROUP_CONCAT(b.code SEPARATOR ', ')
         FROM product_branches pb JOIN branches b ON b.id=pb.branch_id
         WHERE pb.product_id=p.id) as canteens
        FROM products p LEFT JOIN categories c ON c.id=p.category_id
        $where ORDER BY p.id LIMIT ? OFFSET ?";
$stmt2   = $db->prepare($sql);
$params2 = $params; $params2[] = $per_page; $params2[] = $offset;
$types2  = $types . "ii";
$stmt2->bind_param($types2, ...$params2);
$stmt2->execute();
$products = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$total_cats  = count($categories);

include 'includes/header.php';
?>
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <div class="page-title">Products</div>
        <div class="page-subtitle">Total Products: <?= $total ?> &nbsp;&nbsp; Categories: <?= $total_cats ?></div>
    </div>
    <a href="add_product.php" class="btn-maroon btn d-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i> Add Product
    </a>
</div>

<!-- Filters -->
<div class="d-flex align-items-center gap-3 mb-3">
    <form method="GET" class="d-flex align-items-center gap-3">
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
            <ul class="dropdown-menu">
                <li><a class="dropdown-item <?= !$cat_filter ? 'active' : '' ?>" href="products.php">All</a></li>
                <?php foreach ($categories as $cat): ?>
                <li>
                    <a class="dropdown-item <?= $cat_filter == $cat['id'] ? 'active' : '' ?>"
                       href="?cat=<?= $cat['id'] ?>">
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
                <th>Category</th>
                <th>Selling Price</th>
                <th>Cost Price</th>
                <th>Canteen</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr><td colspan="7" class="text-center py-5 text-muted">No products found.</td></tr>
            <?php else: foreach ($products as $p): ?>
            <tr>
                <td><span class="id-badge"><?= htmlspecialchars($p['product_id']) ?></span></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                <td>PHP <?= number_format($p['selling_price'], 0) ?></td>
                <td>PHP <?= number_format($p['cost_price'], 0) ?></td>
                <td><?= htmlspecialchars($p['canteens'] ?? '—') ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-edit btn btn-sm">Edit</a>

                        <!-- Delete button triggers confirmation modal -->
                        <button type="button" class="btn btn-sm"
                                style="background:#FFCDD2;color:#C62828;border-radius:6px;border:none;"
                                onclick="confirmDelete(<?= $p['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($p['product_id'])) ?>',
                                    '<?= addslashes(htmlspecialchars($p['name'])) ?>')">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
    <div class="c-pagination">
        <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>"
           class="<?= $page <= 1 ? 'disabled' : '' ?>">
            <i class="bi bi-arrow-left"></i> Previous
        </a>
        <span class="page-info">Page <?= $page ?> of <?= $pages ?></span>
        <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>"
           class="<?= $page >= $pages ? 'disabled' : '' ?>">
            Next <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="color:#C62828;">
                    <i class="bi bi-trash3 me-2"></i>Delete Product
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:.9rem;color:#555;">Are you sure you want to delete:</p>
                <p id="deleteLabel" style="font-weight:700;color:var(--maroon);font-size:.95rem;"></p>
                <p style="font-size:.8rem;color:#e53935;background:#FFF3E0;border-radius:6px;padding:8px 10px;margin-bottom:0;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This will also remove all stock records for this product.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteForm" style="display:inline;">
                    <input type="hidden" name="action"     value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId">
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
function confirmDelete(id, pid, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteLabel').textContent = pid + ' – ' + name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php include 'includes/footer.php'; ?>
