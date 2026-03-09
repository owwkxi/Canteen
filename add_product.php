<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Add Product – Canteen Management';
$db = getDB();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Build next-ID preview for each category (used by JS live preview)
$next_ids = [];
foreach ($categories as $c) {
    $prefix = $c['prefix'] ?? null;
    if ($prefix) {
        $p      = $db->real_escape_string($prefix);
        $plen   = strlen($prefix) + 1;
        $last   = $db->query("SELECT product_id FROM products
                               WHERE product_id LIKE '{$p}%'
                               ORDER BY CAST(SUBSTRING(product_id, $plen) AS UNSIGNED) DESC
                               LIMIT 1")->fetch_assoc();
        $num    = $last ? ((int)substr($last['product_id'], strlen($prefix)) + 1) : 1;
        $next_ids[$c['id']] = $prefix . str_pad($num, 2, '0', STR_PAD_LEFT);
    } else {
        $next_ids[$c['id']] = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = sanitize($_POST['name']);
    $cat_id     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $cost       = (float)$_POST['cost_price'];
    $sell       = (float)$_POST['selling_price'];
    $branch_ids = $_POST['branches'] ?? [];

    // Auto-generate product_id from category prefix
    $product_id = null;
    if ($cat_id) {
        $cat    = $db->query("SELECT prefix FROM categories WHERE id=$cat_id")->fetch_assoc();
        $prefix = $cat['prefix'] ?? null;
        if ($prefix) {
            $p    = $db->real_escape_string($prefix);
            $plen = strlen($prefix) + 1;
            $last = $db->query("SELECT product_id FROM products
                                 WHERE product_id LIKE '{$p}%'
                                 ORDER BY CAST(SUBSTRING(product_id, $plen) AS UNSIGNED) DESC
                                 LIMIT 1")->fetch_assoc();
            $num        = $last ? ((int)substr($last['product_id'], strlen($prefix)) + 1) : 1;
            $product_id = $prefix . str_pad($num, 2, '0', STR_PAD_LEFT);
        }
    }
    // Fallback for categories with no prefix configured
    if (!$product_id) {
        $last_gen   = $db->query("SELECT product_id FROM products WHERE product_id LIKE 'GEN%'
                                   ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $num        = $last_gen ? ((int)substr($last_gen['product_id'], 3) + 1) : 1;
        $product_id = 'GEN' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    $insert = $db->prepare("INSERT INTO products (product_id, name, category_id, cost_price, selling_price)
                             VALUES (?,?,?,?,?)");
    $insert->bind_param("ssidd", $product_id, $name, $cat_id, $cost, $sell);

    if ($insert->execute()) {
        $new_id = $insert->insert_id;
        foreach ($branch_ids as $bid) {
            $bid = (int)$bid;
            $db->query("INSERT IGNORE INTO product_branches (product_id, branch_id) VALUES ($new_id, $bid)");
            $db->query("INSERT IGNORE INTO stocks (product_id, branch_id, quantity) VALUES ($new_id, $bid, 0)");
        }
        $_SESSION['toast'] = ['msg' => "Product <strong>$product_id</strong> added successfully!", 'type' => 'success'];
        header('Location: products.php'); exit;
    } else {
        $error = 'Failed to add product. Please try again.';
    }
}
include 'includes/header.php';
?>
<div class="page-title mb-4">Add Product</div>

<div class="c-card p-4">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="row g-4">
            <div class="col-md-7">
                <div class="section-title">Basic Information</div>
                <div class="mb-3">
                    <label class="form-label">Product Name :</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Product ID :</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="product_id_preview" class="form-control"
                               style="background:#f5f5f5;font-weight:600;max-width:150px;"
                               value="Select category first" readonly>
                        <span style="font-size:.8rem;color:#999;"><i class="bi bi-magic"></i> Auto-generated</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Category: <span class="text-danger">*</span></label>
                    <select name="category_id" id="category_select" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                data-next="<?= htmlspecialchars($next_ids[$c['id']] ?? 'GEN-series') ?>">
                            <?= htmlspecialchars($c['name']) ?>
                            <?php if (!empty($c['prefix'])): ?>
                                (<?= htmlspecialchars($c['prefix']) ?>-series)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="section-title mt-4">Availability</div>
                <p class="text-muted" style="font-size:.875rem;">Select where this product is sold</p>
                <div class="d-flex flex-wrap gap-4">
                    <?php foreach ($branches as $b): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="branches[]"
                               value="<?= $b['id'] ?>" id="b<?= $b['id'] ?>">
                        <label class="form-check-label" for="b<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-5">
                <div class="section-title">Pricing</div>
                <div class="mb-3">
                    <label class="form-label">Cost Price :</label>
                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Selling Price :</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0" required>
                </div>

                <!-- ID series reference card -->
                <div class="mt-4 p-3" style="background:#faf8f6;border-radius:10px;border:1px solid #e8e3de;">
                    <div style="font-size:.75rem;font-weight:700;color:#888;text-transform:uppercase;
                                letter-spacing:.06em;margin-bottom:10px;">ID Series Reference</div>
                    <?php foreach ($categories as $c): if (empty($c['prefix'])) continue; ?>
                    <div class="d-flex justify-content-between" style="font-size:.82rem;color:#555;padding:3px 0;">
                        <span>
                            <strong style="color:var(--maroon);min-width:28px;display:inline-block;">
                                <?= htmlspecialchars($c['prefix']) ?>
                            </strong>
                            <?= htmlspecialchars($c['name']) ?>
                        </span>
                        <span style="color:#aaa;">next: <?= htmlspecialchars($next_ids[$c['id']] ?? '—') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-4">
            <a href="products.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Product</button>
        </div>
    </form>
</div>

<script>
// Live preview: show what ID will be assigned when category is selected
const nextIds = <?= json_encode($next_ids) ?>;

document.getElementById('category_select').addEventListener('change', function () {
    const preview = document.getElementById('product_id_preview');
    const catId   = this.value;
    if (catId && nextIds[catId]) {
        preview.value = nextIds[catId];
        preview.style.color = 'var(--maroon)';
    } else if (catId) {
        preview.value = 'GEN-series';
        preview.style.color = '#888';
    } else {
        preview.value = 'Select category first';
        preview.style.color = '#999';
    }
});
</script>
<?php include 'includes/footer.php'; ?>
