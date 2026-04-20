<?php
require_once '../includes/auth.php';
require_login('etudiant');
$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $titre = trim($_POST['titre'] ?? '');
        $echeance = $_POST['echeance'] ?? '';
        $priorite = $_POST['priorite'] ?? 'NORMAL';
        if ($titre !== '' && $echeance !== '') {
            $stmt = $pdo->prepare('INSERT INTO tasks (etudiant_id, titre, echeance, priorite, statut, annee_univ) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user_id, $titre, $echeance, $priorite, 'PENDING', '2025/2026']);
        }
    }
    if ($action === 'toggle') {
        $task_id = (int)$_POST['task_id'];
        $stmt = $pdo->prepare('SELECT statut FROM tasks WHERE id = ?');
        $stmt->execute([$task_id]);
        $t = $stmt->fetch();
        $new = ($t['statut'] === 'DONE') ? 'PENDING' : 'DONE';
        $pdo->prepare('UPDATE tasks SET statut = ? WHERE id = ?')->execute([$new, $task_id]);
    }
    if ($action === 'delete') {
        $task_id = (int)$_POST['task_id'];
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$task_id]);
    }
    header('Location: ' . url('assignments.php'));
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM tasks WHERE etudiant_id = ? ORDER BY FIELD(statut, "PENDING", "DONE"), echeance ASC');
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();
$pending = array_filter($tasks, fn($t) => $t['statut'] === 'PENDING');
$done    = array_filter($tasks, fn($t) => $t['statut'] === 'DONE');
$today   = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USTHB Assignments - <?= htmlspecialchars($u['prenom']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #e8f0f5; color: #0f172a; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 240px; background: #dbeaf5; border-right: 1px solid #b3cfe8; padding: 24px 20px; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .logo { display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 18px; color: #1e4f8c; margin-bottom: 32px; }
        .logo-img { width: 42px; height: 42px; object-fit: contain; }
        nav { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { padding: 12px 16px; border-radius: 10px; color: #374151; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .nav-item:hover { background: #c3d9ef; color: #1e4f8c; }
.nav-logout:hover { background: #fee2e2; color: #b91c1c; }
        .nav-item.active { background: #a8c8e8; color: #1e4f8c; font-weight: 600; }
        main { margin-left: 240px; padding: 28px 32px; width: calc(100% - 240px); }
        header { display: flex; align-items: center; margin-bottom: 26px; }
        .spacer { flex: 1; }
        .user { display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #b3cfe8; border-radius: 18px; padding: 8px 14px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #93c5fd); }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 24px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04); margin-bottom: 22px; }
        .form-row { display: grid; grid-template-columns: 1fr 180px 140px auto; gap: 12px; align-items: end; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #bfdbf7; border-radius: 10px; outline: none; }
        .btn-add { background: #1e4f8c; color: white; border: none; padding: 11px 22px; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .task-row { display: flex; align-items: center; gap: 15px; background: white; padding: 16px; border-radius: 18px; border: 1px solid #e2e8f0; margin-bottom: 10px; }
        .task-row.done { opacity: 0.6; }
        .check-btn { width: 22px; height: 22px; border: 2px solid #b3cfe8; border-radius: 6px; cursor: pointer; background: white; }
        .btn-delete { background: #fee2e2; color: #dc2626; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; margin-left: auto; }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="logo">
                <img src="../img/usthb.png" class="logo-img" alt="USTHB Logo">
                <span>USTHB</span>
            </div>
            <nav>
                <a href="student.php" class="nav-item">Dashboard</a>
                <a href="classes.php" class="nav-item">My Classes</a>
                <a href="assignments.php" class="nav-item active">Assignments</a>
                <a href="grades.php" class="nav-item">Grades</a>
                <a href="../public/logout.php" class="nav-item" style="margin-top: auto; color:#dc2626; text-decoration:none; font-weight:600; padding:12px 16px; border-radius:10px;">Logout</a>
            </nav>
        </aside>
        <main>
            <header>
                <div class="spacer"></div>
                <div class="user">
                    <span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span>
                    <div class="avatar"></div>
                </div>
            </header>
            <div class="content">
                <h1 style="margin-bottom:24px;">📝 Assignments</h1>
                <div class="card">
                    <h4 style="margin-bottom:15px; color:#1e4f8c;">+ New Assignment</h4>
                    <form method="POST" class="form-row">
                        <input type="hidden" name="action" value="add"><div class="form-group"><label>Title</label><input type="text" name="titre" required></div>
                        <div class="form-group"><label>Due Date</label><input type="date" name="echeance" min="<?= $today ?>" required></div>
                        <div class="form-group"><label>Priority</label><select name="priorite"><option value="LOW">Low</option><option value="NORMAL" selected>Normal</option><option value="HIGH">High</option></select></div>
                        <button type="submit" class="btn-add">Add Task</button>
                    </form>
                </div>
                <h3 style="margin: 20px 0 10px; color: #1e4f8c;">Pending (<?= count($pending) ?>)</h3>
                <?php foreach($pending as $t): ?>
                <div class="task-row">
                    <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><button type="submit" class="check-btn"></button></form>
                    <div style="flex:1"><strong><?= htmlspecialchars($t['titre']) ?></strong><br><small style="color:#64748b;">Due: <?= $t['echeance'] ?></small></div>
                    <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="task_id" value="<?= $t['id'] ?>"><button type="submit" class="btn-delete" onclick="return confirm('Delete?')">×</button></form>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>
