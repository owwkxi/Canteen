<?php
require_once 'includes/config.php';
requireLogin();
$page_title = 'Change Password – Canteen Management';
$db  = getDB();
$uid = (int)$_SESSION['user_id'];

$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new_pw   = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Fetch user record
    $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hashed, $uid);
        $upd->execute();
        $success = 'Password changed successfully!';
    }
}

include 'includes/header.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="page-title">Change Password</div>
</div>

<div class="c-card p-4" style="max-width:520px;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:52px;height:52px;border-radius:50%;background:var(--maroon);
                    display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-shield-lock-fill" style="color:#fff;font-size:1.4rem;"></i>
        </div>
        <div>
            <div style="font-size:1.05rem;font-weight:700;"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
            <div style="color:#888;font-size:.85rem;text-transform:capitalize;"><?= htmlspecialchars($_SESSION['role']) ?></div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-check-circle-fill"></i> <?= $success ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" style="border-radius:10px;font-size:.875rem;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Current Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="current_password" id="curPw" class="form-control" required
                       placeholder="Enter current password">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="curPw"
                        style="border-left:none;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="new_password" id="newPw" class="form-control" required
                       minlength="6" placeholder="Min. 6 characters">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="newPw"
                        style="border-left:none;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div id="strengthBar" class="mt-2" style="display:none;">
                <div style="height:4px;border-radius:4px;background:#eee;overflow:hidden;">
                    <div id="strengthFill" style="height:100%;width:0;transition:width .3s,background .3s;border-radius:4px;"></div>
                </div>
                <div id="strengthLabel" style="font-size:.75rem;color:#888;margin-top:4px;"></div>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confPw" class="form-control" required
                       placeholder="Re-enter new password">
                <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="confPw"
                        style="border-left:none;">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div id="matchMsg" style="font-size:.8rem;margin-top:4px;"></div>
        </div>
        <button type="submit" class="btn btn-maroon w-100">Update Password</button>
    </form>
</div>

<script>
// Toggle password visibility buttons
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', function () {
        const target = document.getElementById(this.dataset.target);
        target.type = target.type === 'password' ? 'text' : 'password';
        this.querySelector('i').className = target.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

// Strength meter
const newPwEl   = document.getElementById('newPw');
const strengthBar = document.getElementById('strengthBar');
const fill      = document.getElementById('strengthFill');
const label     = document.getElementById('strengthLabel');

newPwEl.addEventListener('input', function () {
    const val = this.value;
    strengthBar.style.display = val ? 'block' : 'none';
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { pct:'20%', color:'#e53935', text:'Very weak'  },
        { pct:'40%', color:'#fb8c00', text:'Weak'        },
        { pct:'60%', color:'#fdd835', text:'Fair'        },
        { pct:'80%', color:'#43a047', text:'Strong'      },
        { pct:'100%',color:'#2e7d32', text:'Very strong' },
    ];
    const lv = levels[Math.min(score, 4)];
    fill.style.width      = lv.pct;
    fill.style.background = lv.color;
    label.textContent     = lv.text;
    label.style.color     = lv.color;
});

// Match check
document.getElementById('confPw').addEventListener('input', function () {
    const msg = document.getElementById('matchMsg');
    if (!this.value) { msg.textContent = ''; return; }
    if (this.value === newPwEl.value) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#2e7d32';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#e53935';
    }
});
</script>
<?php include 'includes/footer.php'; ?>
