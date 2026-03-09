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

$page_title = 'Add Product – Canteen Management';
$db = getDB();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

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
                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"
                                <?= ($_POST['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Product ID is automatically assigned based on category.</div>
                </div>

                <div class="section-title mt-4">Availability</div>
                <p class="text-muted" style="font-size:.875rem;">Select where this product is sold</p>
                <div class="d-flex flex-wrap gap-4">
                    <?php foreach ($branches as $b): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="branches[]"
                               value="<?= $b['id'] ?>" id="b<?= $b['id'] ?>"
                               <?= in_array($b['id'], $_POST['branches'] ?? []) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="b<?= $b['id'] ?>">
                            <?= htmlspecialchars($b['name']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-5">
                <div class="section-title">Pricing</div>
                <div class="mb-3">
                    <label class="form-label">Cost Price <span class="text-danger">*</span></label>
                    <input type="number" name="cost_price" class="form-control"
                           step="0.01" min="0"
                           value="<?= htmlspecialchars($_POST['cost_price'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Selling Price <span class="text-danger">*</span></label>
                    <input type="number" name="selling_price" class="form-control"
                           step="0.01" min="0"
                           value="<?= htmlspecialchars($_POST['selling_price'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-4">
            <a href="products.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Product</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
