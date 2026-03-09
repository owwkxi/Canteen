<?php
require_once 'includes/config.php';
requireLogin();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$member = $db->query("SELECT * FROM staff WHERE id=$id")->fetch_assoc();
if (!$member) { header('Location: staff.php'); exit; }

$page_title = 'Edit Staff – Canteen Management';
$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $role      = sanitize($_POST['role']);
    $branch_id = (int)$_POST['branch_id'];
    $email     = sanitize($_POST['email']);
    $phone     = sanitize($_POST['phone']);
    $status    = sanitize($_POST['status']);
    $upd = $db->prepare("UPDATE staff SET full_name=?, role=?, branch_id=?, email=?, phone=?, status=? WHERE id=?");
    $upd->bind_param("ssisssi", $full_name, $role, $branch_id, $email, $phone, $status, $id);
    $upd->execute();
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
                <input type="text" class="form-control" value="<?= htmlspecialchars($member['staff_id']) ?>" readonly style="background:#f5f5f5;">
            </div>
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($member['full_name']) ?>" required>
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
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $member['branch_id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($member['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option <?= $member['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option <?= $member['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-3 mt-4">
            <a href="staff.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Changes</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
