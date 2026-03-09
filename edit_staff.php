<?php
require_once 'includes/config.php';
requireLogin();
// ── Access guard: admin only ─────────────────────────────────────────────
$_role = $_SESSION["role"] ?? "";
if ($_role !== "admin" && $_role !== "super_admin") {
    header("Location: dashboard.php"); exit;
}

$db = getDB();
$id     = (int)($_GET['id'] ?? 0);
$member = $db->query("SELECT * FROM staff WHERE id=$id")->fetch_assoc();
if (!$member) { header('Location: staff.php'); exit; }

$page_title = 'Edit Staff – Canteen Management';
$branches   = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $role      = sanitize($_POST['role']);
    $branch_id = (int)$_POST['branch_id'];
    $email     = sanitize($_POST['email']);
    $phone     = sanitize($_POST['phone']);
    $status    = sanitize($_POST['status']);

    // Update staff record
    $upd = $db->prepare("UPDATE staff SET full_name=?, role=?, branch_id=?, email=?, phone=?, status=? WHERE id=?");
    $upd->bind_param("ssisssi", $full_name, $role, $branch_id, $email, $phone, $status, $id);
    $upd->execute();

    // Sync full_name to linked user account (FIX: was missing execute())
    $sid_row = $db->query("SELECT staff_id FROM staff WHERE id=$id")->fetch_assoc();
    if ($sid_row) {
        $sid_val = $db->real_escape_string($sid_row['staff_id']);
        $upd_fn  = $db->prepare("UPDATE users SET full_name=? WHERE username=? OR (role='staff' AND full_name=?)");
        $old_fn  = $db->real_escape_string($member['full_name']);
        $upd_fn->bind_param("sss", $full_name, $sid_val, $member['full_name']);
        $upd_fn->execute();
    }

    // Admin: optional password reset
    if (hasRole('admin') && !empty($_POST['new_password'])) {
        $new_pw = $_POST['new_password'];
        if (strlen($new_pw) >= 6 && isset($sid_row)) {
            $hashed  = password_hash($new_pw, PASSWORD_DEFAULT);
            $sid_val = $db->real_escape_string($sid_row['staff_id']);
            $upd_pw  = $db->prepare("UPDATE users SET password=? WHERE username=? OR (role='staff' AND full_name=?)");
            $upd_pw->bind_param("sss", $hashed, $sid_val, $full_name);
            $upd_pw->execute();
        }
    }

    $_SESSION['toast'] = ['msg' => 'Staff updated!', 'type' => 'success'];
    header('Location: staff.php'); exit;
}

include 'includes/header.php';
?>
<div class="page-title mb-4">Edit Staff</div>
<div class="c-card p-4">
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Staff ID</label>
                <input type="text" class="form-control"
                       value="<?= htmlspecialchars($member['staff_id']) ?>"
                       readonly style="background:#f5f5f5;">
            </div>
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control"
                       value="<?= htmlspecialchars($member['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <?php foreach (['Cashier','Manager','Cook','Helper'] as $r): ?>
                    <option <?= $member['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select">
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $member['branch_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($member['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option <?= $member['status'] === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option <?= $member['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <?php if (hasRole('admin')): ?>
            <div class="col-12 mt-2">
                <div class="section-title">Reset Login Password</div>
                <p class="text-muted mb-0" style="font-size:.875rem;">Leave blank to keep the current password.</p>
            </div>
            <div class="col-md-6">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="resetPwInput" class="form-control"
                           minlength="6" placeholder="Min. 6 characters">
                    <button type="button" class="btn btn-outline-secondary" id="toggleResetPw"
                            style="border-left:none;">
                        <i class="bi bi-eye" id="toggleResetPwIcon"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-3 mt-4">
            <a href="staff.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Changes</button>
        </div>
    </form>
</div>

<script>
const rBtn = document.getElementById('toggleResetPw');
if (rBtn) {
    rBtn.addEventListener('click', function () {
        const pw = document.getElementById('resetPwInput');
        const ic = document.getElementById('toggleResetPwIcon');
        pw.type      = pw.type === 'password' ? 'text' : 'password';
        ic.className = pw.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
}
</script>
<?php include 'includes/footer.php'; ?>
