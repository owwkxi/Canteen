<?php
require_once 'includes/config.php';
requireLogin();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$member = $db->query("SELECT s.*, b.name as branch_name FROM staff s LEFT JOIN branches b ON b.id=s.branch_id WHERE s.id=$id")->fetch_assoc();
if (!$member) { header('Location: staff.php'); exit; }
$page_title = 'View Staff – Canteen Management';
include 'includes/header.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="staff.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;"><i class="bi bi-arrow-left"></i></a>
    <div class="page-title">Staff Details</div>
</div>
<div class="c-card p-4" style="max-width:600px;">
    <div class="d-flex align-items-center gap-4 mb-4">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--maroon);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-person-fill" style="color:#fff;font-size:2rem;"></i>
        </div>
        <div>
            <div style="font-size:1.3rem;font-weight:700;"><?= htmlspecialchars($member['full_name']) ?></div>
            <div style="color:#888;font-size:.875rem;"><?= htmlspecialchars($member['role']) ?> &bull; <?= htmlspecialchars($member['branch_name'] ?? '—') ?></div>
        </div>
    </div>
    <table style="width:100%;font-size:.9rem;">
        <tr><td style="padding:8px 0;color:#888;width:140px;">Staff ID</td><td style="font-weight:500;"><?= htmlspecialchars($member['staff_id']) ?></td></tr>
        <tr><td style="padding:8px 0;color:#888;">Email</td><td><?= htmlspecialchars($member['email'] ?? '—') ?></td></tr>
        <tr><td style="padding:8px 0;color:#888;">Phone</td><td><?= htmlspecialchars($member['phone'] ?? '—') ?></td></tr>
        <tr><td style="padding:8px 0;color:#888;">Status</td>
            <td><span style="font-weight:600;color:<?= $member['status']==='Active'?'#2E7D32':'#888' ?>"><?= $member['status'] ?></span></td>
        </tr>
        <tr><td style="padding:8px 0;color:#888;">Joined</td><td><?= date('F j, Y', strtotime($member['created_at'])) ?></td></tr>
    </table>
    <div class="mt-4">
        <a href="edit_staff.php?id=<?= $member['id'] ?>" class="btn btn-maroon">Edit Staff</a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
