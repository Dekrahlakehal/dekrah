<?php
// ============================================================
//  auth.php — Fonctions d'authentification & session
// ============================================================

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Connexion ────────────────────────────────────────────── */
function login(string $identifiant, string $password, string $role): array {
    $pdo = get_pdo();

    $allowed_tables = ['etudiant' => 'etudiants', 'enseignant' => 'enseignants', 'admin' => 'admins'];
    if (!array_key_exists($role, $allowed_tables)) {
        return ['success' => false, 'message' => 'Rôle invalide.'];
    }

    $table = $allowed_tables[$role];

    // FIX: basic brute-force protection via session counter
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] > 5) {
        return ['success' => false, 'message' => 'Trop de tentatives. Veuillez réessayer plus tard.'];
    }

    // Pour l'étudiant on accepte email OU matricule
    if ($role === 'etudiant') {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE (email = ? OR matricule = ?) AND actif = 1");
        $stmt->execute([$identifiant, $identifiant]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE email = ? AND actif = 1");
        $stmt->execute([$identifiant]);
    }

    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        return ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect.'];
    }

    // Reset attempt counter on success
    $_SESSION['login_attempts'] = 0;

    // Stocker dans la session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $role;
    $_SESSION['user_nom']  = $user['prenom'] . ' ' . $user['nom'];

    if ($role === 'etudiant') {
        $_SESSION['user_niveau']    = $user['niveau'] ?? '';
        $_SESSION['user_matricule'] = $user['matricule'] ?? '';
    }

    // FIX: regenerate session ID on login to prevent session fixation
    session_regenerate_id(true);

    return ['success' => true];
}

/* ── Inscription ──────────────────────────────────────────── */
function register(array $data): array {
    $pdo = get_pdo();

    $role = $data['role'] ?? '';

    // Validation basique
    if (empty($data['nom']) || empty($data['prenom']) || empty($data['email']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.'];
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse email invalide.'];
    }
    // FIX: raised minimum password length from 6 to 8
    if (strlen($data['password']) < 8) {
        return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT);

    try {
        if ($role === 'etudiant') {
            $stmt = $pdo->prepare("SELECT id FROM etudiants WHERE matricule = ? OR email = ?");
            $stmt->execute([$data['matricule'], $data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Ce matricule ou cet email est déjà utilisé.'];
            }
            $stmt = $pdo->prepare("INSERT INTO etudiants (nom, prenom, email, matricule, niveau, date_naissance, mot_de_passe) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['email'],
                $data['matricule'], $data['niveau'],
                $data['date_naissance'] ?? null, $hash
            ]);

        } elseif ($role === 'enseignant') {
            $stmt = $pdo->prepare("SELECT id FROM enseignants WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
            }
            $stmt = $pdo->prepare("INSERT INTO enseignants (nom, prenom, email, grade, departement, specialite, mot_de_passe) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['email'],
                $data['grade'], $data['departement'], $data['specialite'] ?? '', $hash
            ]);

        } elseif ($role === 'admin') {
            // FIX: admin secret moved out of source code — set ADMIN_SECRET in your .env or config.php
            $admin_secret = defined('ADMIN_SECRET') ? ADMIN_SECRET : getenv('ADMIN_SECRET');
            if (empty($admin_secret) || ($data['code_admin'] ?? '') !== $admin_secret) {
                return ['success' => false, 'message' => "Code d'accès administrateur invalide."];
            }
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
            }
            $stmt = $pdo->prepare("INSERT INTO admins (nom, prenom, email, service, mot_de_passe) VALUES (?,?,?,?,?)");
            $stmt->execute([
                $data['nom'], $data['prenom'], $data['email'],
                $data['service'] ?? '', $hash
            ]);

        } else {
            return ['success' => false, 'message' => 'Rôle invalide.'];
        }

    } catch (\PDOException $e) {
        // FIX: log the real error server-side, return a generic message to the user
        error_log('[auth] PDO error in register(): ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.'];
    }

    return ['success' => true, 'message' => 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.'];
}

/* ── Utilitaires ──────────────────────────────────────────── */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function get_role(): string {
    return $_SESSION['user_role'] ?? '';
}

function get_user_name(): string {
    return $_SESSION['user_nom'] ?? '';
}

function get_dashboard_url(): string {
    return match (get_role()) {
        'etudiant'   => 'student.php',
        'enseignant' => 'teacher.php',
        'admin'      => 'admin.php',
        default      => 'login.php',
    };
}

function require_login(string $expected_role = ''): void {
    if (!is_logged_in()) {
        header('Location: ' . url('login.php'));
        exit;
    }
    if ($expected_role && get_role() !== $expected_role) {
        header('Location: ' . url(get_dashboard_url()));
        exit;
    }
}

function logout(): void {
    // FIX: properly destroy session and regenerate ID to prevent session fixation
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
    header('Location: ' . url('index.php'));
    exit;
}

/* ── Helpers HTML ─────────────────────────────────────────── */
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function alert(string $type, string $msg): string {
    $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-error' : 'alert-info');
    return '<div class="alert ' . $class . '">' . h($msg) . '</div>';
}