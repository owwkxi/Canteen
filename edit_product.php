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

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) { header('Location: products.php'); exit; }

$page_title = 'Edit Product – Canteen Management';
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$assigned   = array_column($db->query("SELECT branch_id FROM product_branches WHERE product_id=$id")->fetch_all(MYSQLI_ASSOC), 'branch_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = sanitize($_POST['name']);
    $cat_id = (int)$_POST['category_id'];
    $cost   = (float)$_POST['cost_price'];
    $sell   = (float)$_POST['selling_price'];
    $bids   = $_POST['branches'] ?? [];

    $upd = $db->prepare("UPDATE products SET name=?, category_id=?, cost_price=?, selling_price=? WHERE id=?");
    $upd->bind_param("siddi", $name, $cat_id, $cost, $sell, $id);
    $upd->execute();

    $db->query("DELETE FROM product_branches WHERE product_id=$id");
    $db->query("DELETE FROM stocks WHERE product_id=$id AND quantity=0 AND branch_id NOT IN (" . implode(',', array_map('intval', $bids ?: [0])) . ")");
    foreach ($bids as $bid) {
        $bid = (int)$bid;
        $db->query("INSERT IGNORE INTO product_branches (product_id, branch_id) VALUES ($id, $bid)");
        $db->query("INSERT IGNORE INTO stocks (product_id, branch_id, quantity) VALUES ($id, $bid, 0)");
    }
    $_SESSION['toast'] = ['msg' => 'Product updated successfully!', 'type' => 'success'];
    header('Location: products.php'); exit;
}
include 'includes/header.php';
?>
<div class="page-title mb-4">Edit Product</div>

<div class="c-card p-4">
    <form method="POST">
        <div class="row g-4">
            <div class="col-md-7">
                <div class="section-title">Basic Information</div>
                <div class="mb-3">
                    <label class="form-label">Product Name :</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Product ID :</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($product['product_id']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category:</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $product['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="section-title mt-4">Availability</div>
                <p class="text-muted" style="font-size:.875rem;">select where this is sold</p>
                <div class="d-flex flex-wrap gap-4">
                    <?php foreach ($branches as $b): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="branches[]" value="<?= $b['id'] ?>" id="b<?= $b['id'] ?>" <?= in_array($b['id'], $assigned) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="b<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-5">
                <div class="section-title">Pricing</div>
                <div class="mb-3">
                    <label class="form-label">Cost Price :</label>
                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="<?= $product['cost_price'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Selling Price :</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= $product['selling_price'] ?>" required>
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
