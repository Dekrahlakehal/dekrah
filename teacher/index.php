<?php
require_once __DIR__ . '/../includes/auth.php';
require_login('enseignant');

$user_id = $_SESSION['user_id'];
$pdo     = get_pdo();

$stmt = $pdo->prepare("SELECT * FROM enseignants WHERE id = ?");
$stmt->execute([$user_id]);
$ens = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM modules WHERE enseignant_id = ? AND annee_univ = '2025/2026'");
$stmt->execute([$user_id]);
$modules = $stmt->fetchAll();

$noteField = get_note_column($pdo);
seed_demo_students_for_teacher($pdo, $modules, $noteField);

$notif = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $etudiant_id = (int)$_POST['etudiant_id'];
    $module_id   = (int)$_POST['module_id'];
    $note_val    = (float)str_replace(',', '.', $_POST['note_val']);

    if ($note_val < 0 || $note_val > 20) {
        $notif = '<div class="notif notif-err">Note invalide (0–20).</div>';
    } else {
        $chk = $pdo->prepare("SELECT id FROM modules WHERE id = ? AND enseignant_id = ?");
        $chk->execute([$module_id, $user_id]);
        if ($chk->fetch()) {
            $insertSql = $noteField === 'note'
                ? "INSERT INTO notes (etudiant_id, module_id, note, annee_univ) VALUES (?, ?, ?, '2025/2026') ON DUPLICATE KEY UPDATE note = VALUES(note)"
                : "INSERT INTO notes (etudiant_id, module_id, note_exam, annee_univ) VALUES (?, ?, ?, '2025/2026') ON DUPLICATE KEY UPDATE note_exam = VALUES(note_exam)";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([$etudiant_id, $module_id, $note_val]);
            $notif = '<div class="notif notif-ok">Note enregistrée avec succès.</div>';
        }
    }
}

$panel = $_GET['panel'] ?? 'notes';
$initials = strtoupper(substr($ens['prenom'], 0, 1) . substr($ens['nom'], 0, 1));

function get_note_column(PDO $pdo): string {
    $stmt = $pdo->query("SHOW COLUMNS FROM notes LIKE 'note'");
    return $stmt && $stmt->fetch() ? 'note' : 'note_exam';
}

function seed_demo_students_for_teacher(PDO $pdo, array $modules, string $noteField): void {
    if (empty($modules)) { return; }
    $hash = '$2y$12$IQGznSs9ofVhk4R7VzHdge1Q3kN302qUvdUcbgSHiDd3dh7awq5Na';
    $demoStudents = [
        ['nom' => 'Samir', 'prenom' => 'Moussa', 'email' => 'samir.moussa@usthb.dz', 'matricule' => '11111', 'niveau' => 'L2 ISIL', 'date_naissance' => '2002-10-21'],
        ['nom' => 'Nadia', 'prenom' => 'Youssef', 'email' => 'nadia.youssef@usthb.dz', 'matricule' => '22222', 'niveau' => 'L2 ISIL', 'date_naissance' => '2002-04-19'],
    ];
    $insertStudent = $pdo->prepare("INSERT IGNORE INTO etudiants (nom, prenom, email, matricule, niveau, date_naissance, mot_de_passe) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($demoStudents as $student) {
        $insertStudent->execute([$student['nom'], $student['prenom'], $student['email'], $student['matricule'], $student['niveau'], $student['date_naissance'], $hash]);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Enseignant</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* Match screenshot layout precisely */
        html, body { height: 100%; }

        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ── Sidebar ── */
        .app-sidebar {
            width: 210px;
            flex-shrink: 0;
            background: var(--color-bg-white);
            border-right: 1px solid var(--color-border-light);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 16px 14px;
            border-bottom: 1px solid var(--color-border-light);
        }
        .sidebar-logo-img {
            width: 36px; height: 36px;
            border-radius: var(--radius-md);
            background: var(--color-primary-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; color: var(--color-primary);
        }
        .sidebar-logo-text { font-size: 13px; font-weight: 700; color: var(--color-text); }

        .sidebar-nav { flex: 1; padding: 10px 0; }

        .sidebar-nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 18px; font-size: 13px;
            color: var(--color-text-secondary);
            border-left: 3px solid transparent;
            text-decoration: none;
            transition: all .15s;
            cursor: pointer;
        }
        .sidebar-nav-item:hover { background: var(--color-bg-secondary); color: var(--color-text); }
        .sidebar-nav-item.active {
            color: var(--color-primary);
            border-left-color: var(--color-primary);
            background: var(--color-primary-light);
            font-weight: 500;
        }

        .sidebar-footer {
            padding: 12px 18px;
            border-top: 1px solid var(--color-border-light);
        }
        .sidebar-logout {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600;
            color: var(--color-red);
            text-decoration: none; cursor: pointer;
            padding: 6px 0;
        }
        .sidebar-logout:hover { opacity: .8; }

        /* ── Right side ── */
        .app-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: var(--color-bg);
        }

        /* Top bar (right side only — user info) */
        .app-topbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 10px 28px;
            background: var(--color-bg-white);
            border-bottom: 1px solid var(--color-border-light);
            flex-shrink: 0;
        }
        .app-topbar .uname { font-size: 13px; font-weight: 600; }
        .app-topbar .urole { font-size: 11px; color: var(--color-text-secondary); }
        .app-topbar .avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--color-primary);
            color: #fff; font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        /* Scrollable main area */
        .app-main {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
        }

        /* Welcome heading matches screenshot size */
        .welcome-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 4px;
        }
        .welcome-sub {
            font-size: 13px;
            color: var(--color-text-secondary);
            margin-bottom: 24px;
        }
    </style>
</head>
<body>

<div class="app-shell">

    <!-- ── SIDEBAR ── -->
    <aside class="app-sidebar">
        <div class="sidebar-logo">
            <div class="sidebar-logo-img">
                <!-- Simple USTHB-style stacked-blocks icon -->
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                    <rect x="2" y="2" width="7" height="7" rx="1.5" fill="var(--color-primary)"/>
                    <rect x="11" y="2" width="7" height="7" rx="1.5" fill="var(--color-primary)" opacity=".6"/>
                    <rect x="2" y="11" width="7" height="7" rx="1.5" fill="var(--color-primary)" opacity=".6"/>
                    <rect x="11" y="11" width="7" height="7" rx="1.5" fill="var(--color-primary)" opacity=".3"/>
                </svg>
            </div>
            <span class="sidebar-logo-text">USTHB</span>
        </div>

        <nav class="sidebar-nav">
            <a href="?panel=dashboard" class="sidebar-nav-item <?= (!isset($_GET['panel']) || $panel === 'dashboard') ? 'active' : '' ?>">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="13" y="3" width="8" height="7"/><rect x="13" y="13" width="8" height="8"/><rect x="3" y="13" width="7" height="8"/></svg>
                Dashboard
            </a>
            <a href="?panel=notes" class="sidebar-nav-item <?= $panel === 'notes' ? 'active' : '' ?>">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5"/><path d="M17.5 2.5a2.121 2.121 0 013 3L12 14l-4 1 1-4 8.5-8.5z"/></svg>
                Saisie des notes
            </a>
            <a href="?panel=modules" class="sidebar-nav-item <?= $panel === 'modules' ? 'active' : '' ?>">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                Mes modules
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="sidebar-logout">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- ── RIGHT SIDE ── -->
    <div class="app-right">

        <!-- Top bar -->
        <div class="app-topbar">
            <div style="text-align:right;">
                <div class="uname"><?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']) ?></div>
                <div class="urole">Enseignant</div>
            </div>
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        </div>

        <!-- Main scrollable content -->
        <div class="app-main">

            <!-- Welcome heading -->
            <div class="welcome-title">Bienvenue, <?= htmlspecialchars($ens['prenom']) ?> 👋</div>
            <div class="welcome-sub">
                <?php
                    $days = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi',
                             'Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
                    $months = ['January'=>'Janvier','February'=>'Février','March'=>'Mars',
                               'April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet',
                               'August'=>'Août','September'=>'Septembre','October'=>'Octobre',
                               'November'=>'Novembre','December'=>'Décembre'];
                    $dayName = $days[date('l')] ?? date('l');
                    $monthName = $months[date('F')] ?? date('F');
                    echo "C'est " . $dayName . ', ' . $monthName . ' ' . date('j');
                ?>
            </div>

            <!-- Notification -->
            <?= $notif ?>

            <!-- Stat cards -->
            <div class="stat-grid sg2" style="margin-bottom:18px; max-width:520px;">
                <div class="stat-card">
                    <div class="stat-card-label">Modules assignés</div>
                    <div class="stat-card-val val-blue"><?= count($modules) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Année universitaire</div>
                    <div class="stat-card-val" style="font-size:15px;color:var(--color-text);">2025 / 2026</div>
                </div>
            </div>

        </div><!-- /app-main -->
    </div><!-- /app-right -->
</div><!-- /app-shell -->

</body>
</html>
