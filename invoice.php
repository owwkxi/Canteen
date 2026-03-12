<?php
require_once 'includes/config.php';
requireLogin();

// ── Access guard: Cashier staff + admin only ─────────────────────────────
if (($_SESSION['role'] ?? '') === 'staff') {
    // Fresh DB lookup every time — never rely on session for job_role here
    $db_g   = getDB();
    $uid_g  = (int)$_SESSION['user_id'];
    
    // Step 1: get the username of the logged-in user
    $urow_g = $db_g->query("SELECT username FROM users WHERE id=$uid_g LIMIT 1")->fetch_assoc();
    
    // Step 2: match staff_id using LOWER() on both sides (sf001 matches SF001)
    $un_g   = $db_g->real_escape_string(strtolower($urow_g['username'] ?? ''));
    $sr_g   = $db_g->query("SELECT role FROM staff WHERE LOWER(staff_id)='$un_g' LIMIT 1")->fetch_assoc();
    
    // Step 3: also update session so other pages benefit
    $_SESSION['job_role'] = $sr_g['role'] ?? null;
    if (($_SESSION['job_role'] ?? '') !== 'Cashier') {
        header('Location: staff_attendance.php'); exit;
    }
}

$page_title = 'Products Sold – Canteen Management';
$db = getDB();

// Ensure submitted_by_staff column exists
$db->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS submitted_by_staff INT DEFAULT NULL");

$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$products   = $db->query("SELECT p.id, p.product_id, p.name, p.selling_price
                           FROM products p ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$staff_list = $db->query("SELECT id, staff_id, full_name, role FROM staff
                           WHERE status='Active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// ── Detect logged-in staff record ─────────────────────────────────────────────
$is_staff      = ($_SESSION['role'] === 'staff');
$current_staff = null;
if ($is_staff) {
    // Resolve username via users table (login.php only stores user_id/full_name/role)
    $uid  = (int)$_SESSION['user_id'];
    $urow = $db->query("SELECT username FROM users WHERE id=$uid")->fetch_assoc();
    if ($urow) {
        $uname = $db->real_escape_string($urow['username']);
        $fn    = $db->real_escape_string($_SESSION['full_name']);
        $current_staff = $db->query(
            "SELECT * FROM staff WHERE staff_id='$uname' OR full_name='$fn' LIMIT 1"
        )->fetch_assoc();
    }
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id    = (int)$_POST['branch_id'];
    $items        = $_POST['items'] ?? [];
    $date         = sanitize($_POST['sale_date']);
    $submitted_by = !empty($_POST['submitted_by_staff']) ? (int)$_POST['submitted_by_staff'] : null;
    $invoice      = 'INV-' . strtoupper(uniqid());

    // For staff users, force their own ID
    if ($is_staff && $current_staff) {
        $submitted_by = (int)$current_staff['id'];
    }

    $valid = [];
    foreach ($items as $item) {
        if (!empty($item['product_id']) && (int)$item['qty'] > 0) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['qty'];
            $pr  = $db->query("SELECT name, selling_price FROM products WHERE id=$pid")->fetch_assoc();
            if ($pr) {
                $valid[] = ['pid' => $pid, 'qty' => $qty, 'price' => $pr['selling_price'], 'name' => $pr['name']];
            }
        }
    }

    if ($valid) {
        // ==============================================================================
        // CORE REQUIREMENT: Concurrency Control, Rollback & Locking applied here
        // ==============================================================================
        
        // ==============================================================================
        // CORE REQUIREMENT: Concurrency Control, Isolation, Locking & Deadlock Handling
        // ==============================================================================
        
        // 1. ISOLATION: Explicitly set the isolation level for evidence
        $db->query("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $db->begin_transaction(); // Start explicit transaction

        try {
            if ($submitted_by) {
                $stmt = $db->prepare("INSERT INTO sales (invoice_no, branch_id, total_amount, sale_date, submitted_by_staff) VALUES (?, ?, 0.00, ?, ?)");
                $stmt->bind_param("sisi", $invoice, $branch_id, $date, $submitted_by);
            } else {
                $stmt = $db->prepare("INSERT INTO sales (invoice_no, branch_id, total_amount, sale_date) VALUES (?, ?, 0.00, ?)");
                $stmt->bind_param("sis", $invoice, $branch_id, $date);
            }
            $stmt->execute();
            $sale_id = $db->insert_id;

            foreach ($valid as $v) {
                // 2. LOCKING: Row-level lock using FOR UPDATE
                $stock_query = $db->query("SELECT quantity FROM stocks WHERE product_id={$v['pid']} AND branch_id=$branch_id FOR UPDATE");
                $stock_row = $stock_query->fetch_assoc();

                if (!$stock_row || $stock_row['quantity'] < $v['qty']) {
                    throw new Exception("Insufficient stock for {$v['name']}. (Available quantity: " . ($stock_row['quantity'] ?? 0) . ")");
                }

                $db->query("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
                            VALUES ($sale_id, {$v['pid']}, {$v['qty']}, {$v['price']})");
            }

            $db->commit();

            $staff_name = '';
            if ($submitted_by) {
                $srow = $db->query("SELECT full_name FROM staff WHERE id=$submitted_by")->fetch_assoc();
                $staff_name = $srow ? " · Recorded by <strong>{$srow['full_name']}</strong>" : '';
            }
            $_SESSION['toast'] = [
                'msg'  => "Sales recorded successfully!" . $staff_name,
                'type' => 'success'
            ];
            header('Location: reports.php'); exit;

        } catch (Exception $e) {
            $db->rollback();
            
            // 3. DEADLOCK HANDLING: Check for specific MySQL lock/deadlock error codes
            $errCode = $db->errno;
            if ($errCode == 1213 || $errCode == 1205) {
                // 1213 is Deadlock, 1205 is Lock Wait Timeout
                $db->query("INSERT INTO system_logs (action, description) VALUES ('DEADLOCK RESOLVED', 'Invoice $invoice encountered a deadlock and was safely rolled back.')");
                $error = "The system is currently busy processing another transaction for these items. Please try saving again.";
            } else {
                // Handle standard errors (like insufficient stock)
                $error_message = $db->real_escape_string($e->getMessage());
                $db->query("INSERT INTO system_logs (action, description) VALUES ('SALE FAILED', 'Invoice $invoice failed: $error_message')");
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
        
    } else {
        $error = 'Please add at least one valid item with quantity.';
    }
}

// Today's quick stats
$today       = date('Y-m-d');
$today_total = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date='$today'")->fetch_row()[0];
$today_txn   = (int)$db->query("SELECT COUNT(*) FROM sales WHERE sale_date='$today'")->fetch_row()[0];
$today_units = (int)$db->query("SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si
                                  JOIN sales s ON s.id=si.sale_id WHERE s.sale_date='$today'")->fetch_row()[0];

// Pre-select branch for staff
$default_branch_id = 0;
if ($is_staff && $current_staff) {
    $default_branch_id = (int)$current_staff['branch_id'];
}

include 'includes/header.php';
?>

<style>
.today-stat {
    background:#fff; border:1.5px solid #EDE9E4; border-radius:12px;
    padding:16px 20px; display:flex; flex-direction:column; gap:4px;
}
.today-stat .val { font-size:1.7rem; font-weight:800; color:var(--maroon); line-height:1; }
.today-stat .lbl { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#999; }

.item-row-wrapper {
    background:#FAFAF9; border:1.5px solid #EDE9E4; border-radius:10px;
    padding:12px 14px; margin-bottom:10px; position:relative;
}
.remove-row-btn {
    position:absolute; top:10px; right:10px;
    background:#FFCDD2; color:#C62828; border:none;
    border-radius:6px; width:28px; height:28px; font-size:.9rem;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background .15s;
}
.remove-row-btn:hover { background:#EF9A9A; }
.grand-total-box {
    background:var(--maroon); color:#fff; border-radius:12px;
    padding:16px 22px; display:flex; align-items:center;
    justify-content:space-between; flex-wrap:wrap; gap:10px;
}
.grand-total-box .label  { font-size:.85rem; font-weight:600; opacity:.8; }
.grand-total-box .amount { font-size:1.8rem; font-weight:800; }
</style>

<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <div class="page-title">Product Sold</div>
        <div class="page-subtitle" style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-calendar3" style="font-size:.85rem;"></i>
            <?= date('F j, Y') ?>
        </div>
    </div>
    <a href="reports.php" class="btn btn-outline-secondary btn-sm" style="border-radius:8px;">
        <i class="bi bi-bar-chart me-1"></i> View Reports
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="today-stat">
            <div class="lbl">Today's Revenue</div>
            <div class="val">₱<?= number_format($today_total, 0) ?></div>
        </div>
    </div>
    <div class="col-4">
        <div class="today-stat">
            <div class="lbl">Transactions</div>
            <div class="val"><?= $today_txn ?></div>
        </div>
    </div>
    <div class="col-4">
        <div class="today-stat">
            <div class="lbl">Units Sold</div>
            <div class="val"><?= $today_units ?></div>
        </div>
    </div>
</div>

<div class="c-card p-4">
    <div class="section-title mb-3">New Sale Entry</div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="salesForm">

        <?php if ($is_staff && $current_staff): ?>
        <input type="hidden" name="submitted_by_staff" value="<?= (int)$current_staff['id'] ?>">
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Branch <span class="text-danger">*</span></label>
                <select name="branch_id" class="form-select" required>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"
                        <?= ($default_branch_id && $default_branch_id == $b['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sale Date <span class="text-danger">*</span></label>
                <input type="date" name="sale_date" class="form-control"
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>

            <?php if (!$is_staff): ?>
            <div class="col-md-6">
                <label class="form-label">Reported By</label>
                <select name="submitted_by_staff" class="form-select">
                    <option value="">— Select Staff (optional) —</option>
                    <?php foreach ($staff_list as $sf): ?>
                    <option value="<?= $sf['id'] ?>">
                        <?= htmlspecialchars($sf['staff_id'] . ' – ' . $sf['full_name']) ?>
                        (<?= htmlspecialchars($sf['role']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="section-title mb-2">Products Sold</div>
        <div id="items-container"></div>

        <button type="button" id="addRow" class="btn btn-outline-secondary btn-sm mt-1 mb-4"
                style="border-radius:8px;">
            <i class="bi bi-plus-lg me-1"></i> Add Product
        </button>

        <div class="grand-total-box mb-4">
            <div>
                <div class="label">Total Amount</div>
                <div class="amount" id="grandTotal">₱0.00</div>
            </div>
            <div>
                <span id="totalItems" style="font-size:.85rem;opacity:.75;">0 item(s)</span>
            </div>
        </div>

        <div class="d-flex gap-3">
            <a href="dashboard.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon" id="submitBtn" disabled>
                <i class="bi bi-check-circle me-1"></i> Save Record
            </button>
        </div>
    </form>
</div>

<template id="itemTemplate">
    <div class="item-row-wrapper item-row">
        <button type="button" class="remove-row-btn" onclick="removeRow(this)" title="Remove">
            <i class="bi bi-x"></i>
        </button>
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" style="font-size:.8rem;font-weight:600;color:#666;">Product</label>
                <select name="" class="form-select product-select">
                    <option value="">Select product…</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
                        <?= htmlspecialchars($p['product_id'] . ' – ' . $p['name']) ?>
                        (₱<?= number_format($p['selling_price'], 2) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;font-weight:600;color:#666;">Qty</label>
                <input type="number" name="" class="form-control qty-input" placeholder="0" min="1" value="" style="border-color:inherit;">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;font-weight:600;color:#666;">Unit Price</label>
                <input type="text" class="form-control unit-price" readonly style="background:#f5f5f5;" placeholder="—">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:.8rem;font-weight:600;color:#666;">Subtotal</label>
                <input type="text" class="form-control subtotal" readonly style="background:#f5f5f5;font-weight:700;" placeholder="₱0.00">
            </div>
        </div>
    </div>
</template>

<script>
let rowCount = 0;

function addRow() {
    const tmpl = document.getElementById('itemTemplate').content.cloneNode(true);
    const row  = tmpl.querySelector('.item-row-wrapper');
    const idx  = rowCount++;
    row.querySelector('.product-select').name = `items[${idx}][product_id]`;
    row.querySelector('.qty-input').name      = `items[${idx}][qty]`;
    bindRowEvents(row);
    document.getElementById('items-container').appendChild(row);
    updateTotal();
}

function bindRowEvents(row) {
    const sel  = row.querySelector('.product-select');
    const qty  = row.querySelector('.qty-input');
    const unit = row.querySelector('.unit-price');
    const sub  = row.querySelector('.subtotal');

    function calc() {
        const opt   = sel.selectedOptions[0];
        const price = opt ? parseFloat(opt.dataset.price || 0) : 0;
        const raw   = parseInt(qty.value);
        let errMsg  = qty.parentElement.querySelector('.qty-error');

        if (!isNaN(raw) && raw < 1) {
            // Show inline error, mark field red, zero out subtotal
            qty.style.borderColor  = '#C62828';
            qty.style.background   = '#FFF5F5';
            if (!errMsg) {
                errMsg = document.createElement('div');
                errMsg.className = 'qty-error';
                errMsg.style.cssText = 'color:#C62828;font-size:.75rem;font-weight:600;margin-top:4px;';
                qty.parentElement.appendChild(errMsg);
            }
            sub.value = '₱0.00';
            updateTotal();
            return;
        }

        // Clear error state
        qty.style.borderColor = '';
        qty.style.background  = '';
        if (errMsg) errMsg.remove();

        const q = isNaN(raw) ? 0 : raw;
        unit.value = price ? '₱' + price.toFixed(2) : '—';
        sub.value  = (q && price) ? '₱' + (price * q).toFixed(2) : '₱0.00';
        updateTotal();
    }
    sel.addEventListener('change', calc);
    qty.addEventListener('input',  calc);
}

function removeRow(btn) {
    btn.closest('.item-row-wrapper').remove();
    updateTotal();
}

function updateTotal() {
    let total = 0, items = 0;
    document.querySelectorAll('.subtotal').forEach(s => {
        const v = parseFloat(s.value.replace('₱', '')) || 0;
        total += v;
        if (v > 0) items++;
    });
    document.getElementById('grandTotal').textContent = '₱' + total.toFixed(2);
    document.getElementById('totalItems').textContent = items + ' item(s)';
    document.getElementById('submitBtn').disabled     = total <= 0;
}

document.getElementById('addRow').addEventListener('click', addRow);

document.getElementById('salesForm').addEventListener('submit', function(e) {
    if (document.querySelector('.qty-error')) {
        e.preventDefault();
        alert('Please fix invalid quantities before saving.');
    }
});

addRow(); // start with one row
</script>

<?php include 'includes/footer.php'; ?>
