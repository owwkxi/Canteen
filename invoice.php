<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Create Invoice – Canteen Management';
$db = getDB();
$branches  = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$products  = $db->query("SELECT p.id, p.product_id, p.name, p.selling_price FROM products p ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)$_POST['branch_id'];
    $items     = $_POST['items'] ?? [];
    $date      = sanitize($_POST['sale_date']);
    $invoice   = 'INV-' . strtoupper(uniqid());

    $total = 0;
    $valid = [];
    foreach ($items as $item) {
        if (!empty($item['product_id']) && (int)$item['qty'] > 0) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['qty'];
            $pr  = $db->query("SELECT selling_price FROM products WHERE id=$pid")->fetch_assoc();
            if ($pr) {
                $sub = $pr['selling_price'] * $qty;
                $total += $sub;
                $valid[] = ['pid' => $pid, 'qty' => $qty, 'price' => $pr['selling_price']];
            }
        }
    }

    if ($total > 0 && $valid) {
        $stmt = $db->prepare("INSERT INTO sales (invoice_no, branch_id, total_amount, sale_date) VALUES (?,?,?,?)");
        $stmt->bind_param("sids", $invoice, $branch_id, $total, $date);
        $stmt->execute();
        $sale_id = $db->insert_id;
        foreach ($valid as $v) {
            $db->query("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) VALUES ($sale_id, {$v['pid']}, {$v['qty']}, {$v['price']})");
            $db->query("UPDATE stocks SET quantity = GREATEST(0, quantity - {$v['qty']}) WHERE product_id={$v['pid']} AND branch_id=$branch_id");
        }
        $_SESSION['toast'] = ['msg' => "Invoice $invoice created! Total: ₱" . number_format($total, 2), 'type' => 'success'];
        header('Location: reports.php'); exit;
    } else {
        $error = 'Please add at least one valid item.';
    }
}
include 'includes/header.php';
?>
<div class="page-title mb-4">Create Invoice</div>
<div class="c-card p-4">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sale Date</label>
                <input type="date" name="sale_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="section-title">Items</div>
        <div id="items-container">
            <div class="row g-2 mb-2 item-row">
                <div class="col-md-6">
                    <select name="items[0][product_id]" class="form-select">
                        <option value="">Select product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                            <?= htmlspecialchars($p['product_id'] . ' – ' . $p['name']) ?> (₱<?= number_format($p['selling_price'],2) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="items[0][qty]" class="form-control" placeholder="Quantity" min="1">
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control subtotal" readonly placeholder="Subtotal" style="background:#f5f5f5;">
                </div>
            </div>
        </div>

        <button type="button" id="addRow" class="btn btn-outline-secondary btn-sm mt-2">+ Add Row</button>

        <div class="mt-4 d-flex align-items-center gap-4">
            <div style="font-size:1.1rem;font-weight:700;">Total: <span id="grandTotal" style="color:var(--maroon);">₱0.00</span></div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-crimson">Cancel</a>
                <button type="submit" class="btn btn-maroon">Save Invoice</button>
            </div>
        </div>
    </form>
</div>

<script>
let rowCount = 1;
const products = <?= json_encode($products) ?>;

function bindRow(row, idx) {
    const sel = row.querySelector('select');
    const qty = row.querySelector('input[type=number]');
    const sub = row.querySelector('.subtotal');
    sel.name = `items[${idx}][product_id]`;
    qty.name = `items[${idx}][qty]`;
    function calc() {
        const opt = sel.selectedOptions[0];
        const price = opt ? parseFloat(opt.dataset.price || 0) : 0;
        const q = parseInt(qty.value) || 0;
        sub.value = q && price ? '₱' + (price * q).toFixed(2) : '';
        updateTotal();
    }
    sel.addEventListener('change', calc);
    qty.addEventListener('input', calc);
}

function updateTotal() {
    let t = 0;
    document.querySelectorAll('.subtotal').forEach(s => {
        if (s.value) t += parseFloat(s.value.replace('₱', '')) || 0;
    });
    document.getElementById('grandTotal').textContent = '₱' + t.toFixed(2);
}

document.querySelectorAll('.item-row').forEach((r, i) => bindRow(r, i));

document.getElementById('addRow').addEventListener('click', () => {
    const tmpl = document.querySelector('.item-row').cloneNode(true);
    tmpl.querySelectorAll('input').forEach(i => i.value = '');
    tmpl.querySelector('select').value = '';
    document.getElementById('items-container').appendChild(tmpl);
    bindRow(tmpl, rowCount++);
});
</script>
<?php include 'includes/footer.php'; ?>
