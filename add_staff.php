<?php
require_once 'includes/config.php';
requireLogin();
// ── Access guard: admin only ─────────────────────────────────────────────
$_role = $_SESSION["role"] ?? "";
if ($_role !== "admin" && $_role !== "super_admin") {
    header("Location: dashboard.php"); exit;
}

$page_title = 'Add Staff – Canteen Management';
$db = getDB();

$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Pre-compute next Staff ID for display preview
$prefix = 'SF';
$plen   = strlen($prefix) + 1;
$last   = $db->query("SELECT staff_id FROM staff
                       WHERE staff_id LIKE '{$prefix}%'
                       ORDER BY CAST(SUBSTRING(staff_id, $plen) AS UNSIGNED) DESC
                       LIMIT 1")->fetch_assoc();
$num           = $last ? ((int)substr($last['staff_id'], strlen($prefix)) + 1) : 1;
$next_staff_id = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-generate at submit time to handle race conditions
    $last2    = $db->query("SELECT staff_id FROM staff
                             WHERE staff_id LIKE '{$prefix}%'
                             ORDER BY CAST(SUBSTRING(staff_id, $plen) AS UNSIGNED) DESC
                             LIMIT 1")->fetch_assoc();
    $num2     = $last2 ? ((int)substr($last2['staff_id'], strlen($prefix)) + 1) : 1;
    $staff_id = $prefix . str_pad($num2, 3, '0', STR_PAD_LEFT);

    // Username is always the staff ID (lowercase)
    $username  = strtolower($staff_id);

    $full_name = sanitize($_POST['full_name']);
    $role      = sanitize($_POST['role']);
    $branch_id = (int)$_POST['branch_id'];
    $email     = sanitize($_POST['email']);
    $phone     = sanitize($_POST['phone']);
    $status    = sanitize($_POST['status']);
    $password  = $_POST['password'] ?? '';
    $error     = null;

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    }

    if (!$error) {
        $chk = $db->prepare("SELECT id FROM users WHERE username=?");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'A user account with this Staff ID already exists. Please try again.';
        }
    }

    if (!$error) {
        $ins = $db->prepare("INSERT INTO staff (staff_id, full_name, role, branch_id, email, phone, status)
                              VALUES (?,?,?,?,?,?,?)");
        $ins->bind_param("sssisss", $staff_id, $full_name, $role, $branch_id, $email, $phone, $status);
        if ($ins->execute()) {
            $hashed   = password_hash($password, PASSWORD_DEFAULT);
            $user_ins = $db->prepare("INSERT INTO users (username, full_name, role, password) VALUES (?,?,'staff',?)");
            $user_ins->bind_param("sss", $username, $full_name, $hashed);
            $user_ins->execute();
            $_SESSION['toast'] = [
                'msg'  => "Staff <strong>{$full_name}</strong> added — ID &amp; username: <strong>{$staff_id}</strong>.",
                'type' => 'success'
            ];
            header('Location: staff.php'); exit;
        } else {
            $error = 'Failed to add staff. Please try again.';
        }
    }
}

include 'includes/header.php';
?>
<div class="page-title mb-4">Add Staff</div>
<div class="c-card p-4">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger mb-3"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-3">
            <div class="col-12">
                <div class="section-title">Staff Information</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control" required
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <?php foreach (['Cashier','Manager','Cook','Helper'] as $r): ?>
                    <option <?= ($_POST['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select">
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"
                            <?= ($_POST['branch_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Active"   <?= ($_POST['status'] ?? 'Active') === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= ($_POST['status'] ?? '')        === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="col-12 mt-2">
                <div class="section-title">Login Credentials</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Username</label>
                <div class="form-control d-flex align-items-center justify-content-between"
                     style="background:#f5f5f5;color:var(--maroon);font-weight:700;">
                    <span><?= htmlspecialchars(strtolower($next_staff_id)) ?></span>
                    <span style="font-size:.75rem;color:#aaa;font-weight:400;">Same as Staff ID</span>
                </div>
                <div class="form-text">Automatically set to the staff's assigned ID.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           required minlength="6" placeholder="Min. 6 characters">
                    <button type="button" class="btn btn-outline-secondary" id="togglePw"
                            style="border-left:none;">
                        <i class="bi bi-eye" id="togglePwIcon"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3 mt-4">
            <a href="staff.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Staff</button>
        </div>
    </form>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function () {
    const pw = document.getElementById('passwordInput');
    const ic = document.getElementById('togglePwIcon');
    pw.type      = pw.type === 'password' ? 'text' : 'password';
    ic.className = pw.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});
</script>
<?php include 'includes/footer.php'; ?>
