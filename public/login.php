<?php
require_once '../includes/auth.php';
if (is_logged_in()) { header('Location: ' . url(get_dashboard_url())); exit; }
$error = ''; $pre_role = in_array($_GET['role'] ?? 'etudiant', ['etudiant', 'enseignant', 'admin']) ? $_GET['role'] : 'etudiant';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login(trim($_POST['identifiant'] ?? ''), $_POST['password'] ?? '', $_POST['role'] ?? '');
    if (!$result['success']) { $error = $result['message']; } else { header('Location: ' . url(get_dashboard_url())); exit; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-logo">
        <div class="nav-logo-box"><img src="../img/usthb.png" alt="USTHB Logo" width="34" height="34"></div>
        <div>
            <div class="nav-brand"><?= APP_NAME ?></div>
            <div class="nav-brand-sub"><?= APP_SUB ?></div>
        </div>
    </div>
    <div class="nav-links">
        <a href="../index.php" class="nav-link">Home</a>
        <a href="login.php" class="nav-link active">Login</a>
    </div>
</nav>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-header">
            <div style="font-size:28px; margin-bottom:10px;">🔐</div>
            <div class="auth-title">Login</div>
            <div class="auth-sub">Access your USTHB Scolarité personal space</div>
        </div>

        <?php if ($error): echo alert('error', $error); endif; ?>

        <form method="POST" action="login.php">
            <div class="rs-label">Login as</div>
            <div class="role-selector">
                <?php
                $roles = ['etudiant' => 'Student', 'enseignant' => 'Teacher', 'admin' => 'Admin'];
                foreach ($roles as $key => $label):
                    $sel = ($key === $pre_role) ? 'sel' : '';
                ?>
                <div class="rs-btn <?= $sel ?>" id="lr-<?= $key ?>" onclick="selRole('<?= $key ?>')">
                    <?= $label ?>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="role" id="role-input" value="<?= h($pre_role) ?>">

            <div class="form-group">
                <label id="identifiant-label">Email or Registration Number</label>
                <input type="text" name="identifiant" id="identifiant-input"
                       placeholder="e.g. 12345 or email@usthb.dz"
                       value="<?= h($_POST['identifiant'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-full">Login</button>
        </form>

        <div style="margin-top:20px; padding:12px; background:var(--color-bg-secondary); border-radius:var(--radius-md); font-size:11px; color:var(--color-text-secondary); line-height:1.8;">
            <strong style="display:block; margin-bottom:4px;">Information</strong>
            Accounts are created only by the scolarité administrator.<br>
            Contact administration if you don't yet have your credentials.
        </div>

        <div style="margin-top:14px; padding-top:14px; border-top:1px solid var(--color-border-light);">
            <div style="font-size:10px;font-weight:700;color:var(--color-text-tertiary);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em;">Demo Accounts</div>
            <div style="font-size:11px;color:var(--color-text-secondary);line-height:2;">
                <strong>Student:</strong> 12345 / password123<br>
                <strong>Teacher:</strong> laachemi@usthb.dz / password123<br>
                <strong>Admin:</strong> admin@usthb.dz / password123
            </div>
        </div>
    </div>
</div>

<script>
function selRole(r) {
    document.querySelectorAll('.rs-btn').forEach(b => b.classList.remove('sel'));
    document.getElementById('lr-' + r).classList.add('sel');
    document.getElementById('role-input').value = r;
    var label = document.getElementById('identifiant-label');
    var input = document.getElementById('identifiant-input');
    if (r === 'etudiant') {
        label.textContent = 'Email or Registration Number';
        input.placeholder = 'e.g. 12345 or email@usthb.dz';
    } else {
        label.textContent = 'Email';
        input.placeholder = 'email@usthb.dz';
    }
}
selRole('<?= h($pre_role) ?>');
</script>
</body>
</html>
