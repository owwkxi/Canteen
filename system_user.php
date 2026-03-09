<?php
require_once 'includes/config.php';
requireRole('super_admin');
$page_title = 'System Users – Canteen Management';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = sanitize($_POST['username']);
        $full_name = sanitize($_POST['full_name']);
        $role      = in_array($_POST['role'], ['admin','super_admin']) ? $_POST['role'] : 'admin';
        $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, full_name, role, password) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $username, $full_name, $role, $password);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['msg' => 'User "'.$username.'" added successfully!', 'type' => 'success'];
        } else {
            $_SESSION['toast'] = ['msg' => 'Username already exists.', 'type' => 'error'];
        }
        header('Location: system_user.php'); exit;
    }

    if ($action === 'edit') {
        $uid       = (int)$_POST['user_id'];
        $full_name = sanitize($_POST['full_name']);
        $role      = in_array($_POST['role'], ['admin','super_admin']) ? $_POST['role'] : 'admin';
        if ($uid === (int)$_SESSION['user_id'] && $role !== 'super_admin') {
            $_SESSION['toast'] = ['msg' => 'You cannot change your own role.', 'type' => 'error'];
            header('Location: system_user.php'); exit;
        }
        $upd = $db->prepare("UPDATE users SET full_name=?, role=? WHERE id=?");
        $upd->bind_param("ssi", $full_name, $role, $uid);
        $upd->execute();
        if (!empty($_POST['new_password'])) {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pw = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $pw->bind_param("si", $hashed, $uid);
            $pw->execute();
        }
        $_SESSION['toast'] = ['msg' => 'User updated!', 'type' => 'success'];
        header('Location: system_user.php'); exit;
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid === (int)$_SESSION['user_id']) {
            $_SESSION['toast'] = ['msg' => 'You cannot delete your own account.', 'type' => 'error'];
        } else {
            $db->query("DELETE FROM users WHERE id=$uid");
            $_SESSION['toast'] = ['msg' => 'User deleted.', 'type' => 'success'];
        }
        header('Location: system_user.php'); exit;
    }
}

$users = $db->query("SELECT id, username, full_name, role, created_at FROM users
    WHERE role IN ('admin','super_admin')
    ORDER BY FIELD(role,'super_admin','admin'), full_name")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?> <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="page-title">System Users</div>
        <div class="page-subtitle">Manage user accounts and access levels</div>
    </div>
    <button class="btn btn-maroon d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg"></i> Add User
    </button>
</div>

<!-- Role legend cards -->
<div class="d-flex flex-wrap gap-3 mb-4">
    <div class="c-card px-3 py-2 d-flex align-items-center gap-2">
        <span class="role-badge super_admin">Super Admin</span>
        <span style="font-size:.8rem;color:#666;">Full access — everything including System Users</span>
    </div>
    <div class="c-card px-3 py-2 d-flex align-items-center gap-2">
        <span class="role-badge admin">Admin</span>
        <span style="font-size:.8rem;color:#666;">Full access except System Users management</span>
    </div>
</div>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4" style="font-size:.85rem;border-radius:10px;">
    <i class="bi bi-info-circle-fill"></i>
    <span>Staff accounts are managed in <a href="staff.php" style="color:var(--maroon);font-weight:600;">Staff Management</a>. Add staff there to assign them a login.</span>
</div>

<div class="c-card">
    <div style="overflow-x:auto;">
    <table class="c-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><span class="role-badge <?= $u['role'] ?>"><?= roleBadge($u['role']) ?></span></td>
                <td style="color:#888;font-size:.85rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-edit2"
                            onclick='openEdit(<?= json_encode(['id'=>$u['id'],'username'=>$u['username'],'full_name'=>$u['full_name'],'role'=>$u['role']]) ?>)'>
                            Edit
                        </button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return confirm('Delete user <?= addslashes(htmlspecialchars($u['username'])) ?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm" style="background:#FFCDD2;color:#C62828;border-radius:6px;">Delete</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.78rem;color:#aaa;padding:6px 0;">You</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add System User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="e.g. Maria Santos">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required placeholder="e.g. maria.santos">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min. 6 characters">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Role</label>
                            <select name="role" id="addRoleSelect" class="form-select" onchange="updateRoleHint(this,'addRoleHint')">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                            <small id="addRoleHint" class="text-muted mt-1 d-block"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Username</label>
                            <input type="text" id="editUsername" class="form-control" readonly style="background:#f5f5f5;">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Role</label>
                            <select name="role" id="editRole" class="form-select" onchange="updateRoleHint(this,'editRoleHint')">
                                <option value="admin">Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                            <small id="editRoleHint" class="text-muted mt-1 d-block"></small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">New Password <span style="color:#aaa;font-weight:400;">(leave blank to keep current)</span></label>
                            <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Enter new password to change">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-maroon">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.role-badge {
    display:inline-block; padding:3px 12px; border-radius:20px;
    font-size:.78rem; font-weight:700; letter-spacing:.03em;
}
.role-badge.super_admin { background:#EDE7F6; color:#4527A0; }
.role-badge.admin       { background:#E3F2FD; color:#1565C0; }
.role-badge.staff       { background:#E8F5E9; color:#2E7D32; }
</style>

<script>
const roleHints = {
    admin:       'Can access: Dashboard, Products, Stock, Reports, Staff Management — cannot manage System Users',
    super_admin: 'Can access: Everything including System Users management'
};
function updateRoleHint(sel, hintId) {
    document.getElementById(hintId).textContent = roleHints[sel.value] || '';
}
function openEdit(user) {
    document.getElementById('editUserId').value   = user.id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editFullName').value = user.full_name;
    const roleEl = document.getElementById('editRole');
    roleEl.value = user.role;
    updateRoleHint(roleEl, 'editRoleHint');
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
updateRoleHint(document.getElementById('addRoleSelect'), 'addRoleHint');
</script>

<?php include 'includes/footer.php'; ?>
