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
$row = $db->query("SELECT s.*, p.product_id as pid, p.name, c.name as cat_name FROM stocks s JOIN products p ON p.id=s.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE s.id=$id")->fetch_assoc();
if (!$row) { header('Location: stock.php'); exit; }

$page_title = 'Update Stock – Canteen Management';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty = (int)$_POST['quantity'];
    $db->query("UPDATE stocks SET quantity=$qty WHERE id=$id");
    $_SESSION['toast'] = ['msg' => 'Stock updated!', 'type' => 'success'];
    $back_bid = (int)($_POST['back'] ?? 0);
    $redirect = $back_bid ? "stock_branch.php?branch=$back_bid" : "stock.php";
    header("Location: $redirect"); exit;
}
include 'includes/header.php';
?>
<div class="page-title mb-4">Update Product Stock</div>

<div class="c-card p-4">
    <?php $back_bid = (int)($_GET['back'] ?? 0); ?>
    <form method="POST">
        <div class="row g-4">
            <div class="col-md-7">
                <div class="section-title">Basic Information</div>
                <div class="mb-3">
                    <label class="form-label">Product Name :</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['name']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Product ID :</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['pid']) ?>" readonly style="background:#f5f5f5;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category:</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['cat_name'] ?? '—') ?>" readonly style="background:#f5f5f5;">
                </div>
            </div>
            <div class="col-md-5">
                <div class="section-title">Stocks</div>
                <div class="mb-3">
                    <label class="form-label">Stock Quantity:</label>
                    <input type="number" name="quantity" class="form-control" value="<?= $row['quantity'] ?>" min="0" required>
                </div>
            </div>
        </div>
        <div class="d-flex gap-3 mt-2">
            <input type="hidden" name="back" value="<?= $back_bid ?>">
            <a href="<?= $back_bid ? "stock_branch.php?branch=$back_bid" : 'stock.php' ?>" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Product</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
