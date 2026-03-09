<?php
require_once 'includes/config.php';
requireLogin();
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
