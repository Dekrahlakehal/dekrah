<?php
// ============================================================
//  register.php — Page d'inscription
// ============================================================
require_once 'includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . url(get_dashboard_url()));
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'role'             => $_POST['role']             ?? '',
        'nom'              => trim($_POST['nom']         ?? ''),
        'prenom'           => trim($_POST['prenom']      ?? ''),
        'email'            => trim($_POST['email']       ?? ''),
        'password'         => $_POST['password']         ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        // Étudiant
        'matricule'        => trim($_POST['matricule']   ?? ''),
        'niveau'           => $_POST['niveau']           ?? 'L1 Info',
        'date_naissance'   => $_POST['date_naissance']   ?? null,
        // Enseignant
        'grade'            => $_POST['grade']            ?? 'Dr.',
        'departement'      => $_POST['departement']      ?? 'Informatique',
        'specialite'       => trim($_POST['specialite']  ?? ''),
        // Admin
        'code_admin'       => $_POST['code_admin']       ?? '',
        'service'          => trim($_POST['service']     ?? ''),
    ];

    $result = register($data);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

$post_role = $_POST['role'] ?? 'etudiant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reg-extra { display: none; }
        .reg-extra.show { display: block; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="nav-logo">
        <div class="nav-logo-box"><span>FI</span></div>
        <div>
            <div class="nav-brand"><?= APP_NAME ?></div>
            <div class="nav-brand-sub"><?= APP_SUB ?></div>
        </div>
    </div>
    <div class="nav-links">
        <a href="index.php"    class="nav-link">Accueil</a>
        <a href="login.php"    class="nav-link">Connexion</a>
        <a href="register.php" class="nav-link active">Inscription</a>
    </div>
    <a href="login.php"><button class="btn-nav">Se connecter</button></a>
</nav>

<div class="auth-wrap" style="padding: 24px 32px;">
    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-header">
            <div class="auth-title">Créer un compte</div>
            <div class="auth-sub">Inscription sur la plateforme USTHB Scolarité</div>
        </div>

        <?php if ($error):   echo alert('error',   $error);   endif; ?>
        <?php if ($success): echo alert('success', $success); ?>
            <div class="switch-link" style="margin-top:12px;">
                <a href="login.php">← Aller à la connexion</a>
            </div>
        <?php else: ?>

        <form method="POST" action="register.php">
            <!-- Rôle -->
            <div class="rs-label">Je suis un(e)</div>
            <div class="role-selector" style="margin-bottom:20px;">
                <?php
                $roles = ['etudiant' => 'Étudiant', 'enseignant' => 'Enseignant', 'admin' => 'Admin'];
                foreach ($roles as $key => $label):
                    $sel = ($key === $post_role) ? 'sel' : '';
                ?>
                <div class="rs-btn <?= $sel ?>" id="rr-<?= $key ?>" onclick="selRegRole('<?= $key ?>')">
                    <?= $label ?>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="role" id="reg-role-input" value="<?= h($post_role) ?>">

            <!-- Commun -->
            <div class="grid-2">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="nom" placeholder="Nom de famille" value="<?= h($_POST['nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <input type="text" name="prenom" placeholder="Prénom" value="<?= h($_POST['prenom'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="email@usthb.dz" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>

            <!-- Étudiant extras -->
            <div id="extra-etudiant" class="reg-extra <?= $post_role === 'etudiant' ? 'show' : '' ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Matricule</label>
                        <input type="text" name="matricule" placeholder="ex : 12345" value="<?= h($_POST['matricule'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Niveau</label>
                        <select name="niveau">
                            <?php foreach (['L1 Info', 'L2 ISIL', 'L3 Info', 'M1 ISIL', 'M2 ISIL'] as $niv): ?>
                            <option value="<?= $niv ?>" <?= (($_POST['niveau'] ?? '') === $niv) ? 'selected' : '' ?>><?= $niv ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= h($_POST['date_naissance'] ?? '') ?>">
                </div>
            </div>

            <!-- Enseignant extras -->
            <div id="extra-enseignant" class="reg-extra <?= $post_role === 'enseignant' ? 'show' : '' ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Grade</label>
                        <select name="grade">
                            <?php foreach (['Assistant', 'Dr.', 'Pr.', 'MCA', 'MCB'] as $g): ?>
                            <option value="<?= $g ?>" <?= (($_POST['grade'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Département</label>
                        <select name="departement">
                            <option value="Informatique">Informatique</option>
                            <option value="Math &amp; Info">Math &amp; Info</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Spécialité</label>
                    <input type="text" name="specialite" placeholder="ex : Bases de données, Réseaux…" value="<?= h($_POST['specialite'] ?? '') ?>">
                </div>
            </div>

            <!-- Admin extras -->
            <div id="extra-admin" class="reg-extra <?= $post_role === 'admin' ? 'show' : '' ?>">
                <div class="form-group">
                    <label>Code d'accès administrateur</label>
                    <input type="password" name="code_admin" placeholder="Code fourni par l'administration">
                </div>
                <div class="form-group">
                    <label>Département / Service</label>
                    <input type="text" name="service" placeholder="ex : Scolarité centrale" value="<?= h($_POST['service'] ?? '') ?>">
                </div>
            </div>

            <!-- Mot de passe -->
            <div class="grid-2" style="margin-top: 4px;">
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-full">Créer mon compte</button>
        </form>

        <div class="switch-link">Déjà inscrit ? <a href="login.php">Se connecter</a></div>

        <?php endif; ?>
    </div>
</div>

<script>
function selRegRole(r) {
    document.querySelectorAll('.rs-btn').forEach(b => b.classList.remove('sel'));
    document.getElementById('rr-' + r).classList.add('sel');
    document.getElementById('reg-role-input').value = r;
    ['etudiant', 'enseignant', 'admin'].forEach(x => {
        var el = document.getElementById('extra-' + x);
        if (el) el.classList.toggle('show', x === r);
    });
}
</script>
</body>
</html>