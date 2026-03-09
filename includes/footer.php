</div><!-- end main-content -->

<!-- Toast -->
<div class="c-toast" id="cToast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg, type = 'success') {
    const t = document.getElementById('cToast');
    t.className = 'c-toast show ' + type;
    t.innerHTML = msg;
    setTimeout(() => t.classList.remove('show'), 3500);
}
<?php if (isset($_SESSION['toast'])): ?>
showToast('<?= $_SESSION['toast']['msg'] ?>', '<?= $_SESSION['toast']['type'] ?? 'success' ?>');
<?php unset($_SESSION['toast']); endif; ?>
</script>
</body>
</html>
