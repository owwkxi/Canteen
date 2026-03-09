<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Add Staff – Canteen Management';
$db = getDB();
$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id  = sanitize($_POST['staff_id']);
    $full_name = sanitize($_POST['full_name']);
    $role      = sanitize($_POST['role']);
    $branch_id = (int)$_POST['branch_id'];
    $email     = sanitize($_POST['email']);
    $phone     = sanitize($_POST['phone']);
    $status    = sanitize($_POST['status']);

    $stmt = $db->prepare("INSERT INTO staff (staff_id, full_name, role, branch_id, email, phone, status) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssiss s", $staff_id, $full_name, $role, $branch_id, $email, $phone, $status);

    $ins = $db->prepare("INSERT INTO staff (staff_id, full_name, role, branch_id, email, phone, status) VALUES (?,?,?,?,?,?,?)");
    $ins->bind_param("sssisss", $staff_id, $full_name, $role, $branch_id, $email, $phone, $status);
    if ($ins->execute()) {
        $_SESSION['toast'] = ['msg' => 'Staff added successfully!', 'type' => 'success'];
        header('Location: staff.php'); exit;
    } else {
        $error = 'Failed to add staff. Staff ID may already exist.';
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
            <div class="col-md-6">
                <label class="form-label">Staff ID</label>
                <input type="text" name="staff_id" class="form-control" required placeholder="e.g. SF01">
            </div>
            <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option>Cashier</option><option>Manager</option><option>Cook</option><option>Helper</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select">
                    <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="d-flex gap-3 mt-4">
            <a href="staff.php" class="btn btn-crimson">Cancel</a>
            <button type="submit" class="btn btn-maroon">Save Staff</button>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
