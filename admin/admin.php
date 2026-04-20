<?php
require_once '../includes/auth.php';
require_login('admin');

$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

// Get admin info
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
$stmt->execute([$user_id]);
$admin = $stmt->fetch();

$notif = '';
$panel = $_GET['panel'] ?? 'dashboard';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $student_id = (int)$_POST['student_id'];
    $stmt = $pdo->prepare("DELETE FROM etudiants WHERE id = ?");
    $stmt->execute([$student_id]);
    $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Student deleted successfully.</div>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $stmt = $pdo->prepare("DELETE FROM enseignants WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Teacher deleted successfully.</div>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matricule = trim($_POST['matricule'] ?? '');
    $niveau = $_POST['niveau'] ?? 'L2 ISIL';
    $password = $_POST['password'] ?? uniqid('pwd_');

    if (!$nom || !$prenom || !$email || !$matricule) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Please fill in all required fields.</div>';
    } else {
        $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO etudiants (nom, prenom, email, matricule, niveau, mot_de_passe) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$nom, $prenom, $email, $matricule, $niveau, $hashed_pw]);
            $student_id = $pdo->lastInsertId();
            
            // Auto-enroll in all modules for this year
            $stmt = $pdo->prepare("INSERT IGNORE INTO inscriptions (etudiant_id, module_id, annee_univ) SELECT ?, id, '2025/2026' FROM modules WHERE niveau = ? AND annee_univ = '2025/2026'");
            $stmt->execute([$student_id, $niveau]);
            
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Student created and automatically enrolled in all ' . h($niveau) . ' modules. Temp password: <strong>' . h($password) . '</strong></div>';
        } catch (\PDOException $e) {
            $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Email or Matricule already exists.</div>';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $student_id = (int)$_POST['student_id'];
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matricule = trim($_POST['matricule'] ?? '');
    $niveau = $_POST['niveau'] ?? 'L2 ISIL';

    if (!$nom || !$prenom || !$email || !$matricule) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Please fill in all required fields.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE etudiants SET nom = ?, prenom = ?, email = ?, matricule = ?, niveau = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $email, $matricule, $niveau, $student_id]);
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Student updated successfully.</div>';
        } catch (\PDOException $e) {
            $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Update failed.</div>';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $grade = $_POST['grade'] ?? 'Dr.';
    $departement = $_POST['departement'] ?? 'Informatique';
    $password = $_POST['password'] ?? uniqid('pwd_');

    if (!$nom || !$prenom || !$email) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Please fill in all required fields.</div>';
    } else {
        $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO enseignants (nom, prenom, email, grade, departement, mot_de_passe) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$nom, $prenom, $email, $grade, $departement, $hashed_pw]);
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Teacher created. Temp password: <strong>' . h($password) . '</strong></div>';
        } catch (\PDOException $e) {
            $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Email already exists.</div>';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $grade = $_POST['grade'] ?? 'Dr.';
    $departement = $_POST['departement'] ?? 'Informatique';

    if (!$nom || !$prenom || !$email) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Please fill in all required fields.</div>';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE enseignants SET nom = ?, prenom = ?, email = ?, grade = ?, departement = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $email, $grade, $departement, $teacher_id]);
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Teacher updated successfully.</div>';
        } catch (\PDOException $e) {
            $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Update failed.</div>';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_module'])) {
    $code = trim($_POST['code'] ?? '');
    $intitule = trim($_POST['intitule'] ?? '');
    $coefficient = (int)($_POST['coefficient'] ?? 1);
    $niveau = $_POST['niveau'] ?? 'L1 Info';
    $enseignant_id = (int)($_POST['enseignant_id'] ?? 0);

    if (!$code || !$intitule) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Module code and name are required.</div>';
    } else {
        try {
            if ($enseignant_id) {
                $chk = $pdo->prepare("SELECT id FROM modules WHERE enseignant_id = ? AND annee_univ = '2025/2026'");
                $chk->execute([$enseignant_id]);
                if ($chk->fetch()) {
                    throw new \PDOException('Teacher already assigned to a module for this year.');
                }
            }
            $stmt = $pdo->prepare("INSERT INTO modules (code, intitule, coefficient, niveau, enseignant_id, annee_univ) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$code, $intitule, $coefficient, $niveau, $enseignant_id ?: null, '2025/2026']);
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Module created successfully.</div>';
        } catch (\PDOException $e) {
            $message = strpos($e->getMessage(), 'Teacher already assigned') !== false
                ? 'This teacher already has a module assigned for 2025/2026.'
                : 'Module code already exists or invalid module assignment.';
            $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">' . $message . '</div>';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_module'])) {
    $module_id = (int)$_POST['module_id'];
    $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
    $stmt->execute([$module_id]);
    $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Module deleted successfully.</div>';
}
$students = $pdo->query("SELECT *, last_online FROM etudiants WHERE niveau LIKE 'L2%' ORDER BY nom, prenom")->fetchAll();
$teachers = $pdo->query("SELECT *, last_online FROM enseignants ORDER BY nom, prenom")->fetchAll();
$modules = $pdo->query("SELECT * FROM modules WHERE annee_univ = '2025/2026' ORDER BY code")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - USTHB</title>
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
        .nav-item.active { background: #a8c8e8; color: #1e4f8c; font-weight: 600; }
        .nav-logout { margin-top: auto; padding: 12px 16px; border-radius: 10px; color: #dc2626; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .nav-logout:hover { background: #fee2e2; }
        main { margin-left: 240px; padding: 28px 32px; width: calc(100% - 240px); }
        header { display: flex; align-items: center; margin-bottom: 26px; }
        .spacer { flex: 1; }
        .user { display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #b3cfe8; border-radius: 18px; padding: 8px 14px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #93c5fd); }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 24px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04); margin-bottom: 22px; }
        .stat-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px; }
        .stat-label { font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 8px; }
        .stat-val { font-size: 28px; font-weight: 700; color: #1e4f8c; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 12px; font-weight: 700; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        tr:hover { background: #f8fafc; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; }
        .btn { padding: 8px 16px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-blue { background: #3b82f6; color: white; }
        .btn-blue:hover { background: #2563eb; }
        .btn-red { background: #dc2626; color: white; }
        .btn-red:hover { background: #b91c1c; }
        h1 { font-size: 26px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px; }
        @media (max-width: 768px) { .sidebar { display: none; } main { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="logo"><img src="../img/usthb.png" class="logo-img" alt="USTHB Logo"><span>USTHB</span></div>
            <nav>
                <a href="?panel=dashboard" class="nav-item <?= $panel === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="?panel=students" class="nav-item <?= $panel === 'students' ? 'active' : '' ?>">Students</a>
                <a href="?panel=teachers" class="nav-item <?= $panel === 'teachers' ? 'active' : '' ?>">Teachers</a>
                <a href="?panel=modules" class="nav-item <?= $panel === 'modules' ? 'active' : '' ?>">Modules</a>
                <a href="?panel=grade-review" class="nav-item <?= $panel === 'grade-review' ? 'active' : '' ?>">Grade Review</a>
                <a href="../public/logout.php" class="nav-logout">Logout</a>
            </nav>
        </aside>

        <main>
            <header>
                <div class="spacer"></div>
                <div class="user">
                    <span><?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></span>
                    <div class="avatar"></div>
                </div>
            </header>

            <?php if ($panel === 'dashboard'): ?>
                <h1>Admin Dashboard</h1>
                <p class="subtitle">School management system overview</p>
                <?= $notif ?>

                <div class="card">
                    <div class="stat-box">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-val"><?= count($students) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Teachers</div>
                        <div class="stat-val"><?= count($teachers) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Modules</div>
                        <div class="stat-val"><?= count($modules) ?></div>
                    </div>
                </div>

            <?php elseif ($panel === 'students'): ?>
                <h1>Manage Students</h1>
                <p class="subtitle">Create, edit, and delete student accounts</p>
                <?= $notif ?>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Add New Student</div>
                    <form method="POST">
                        <div class="form-row">
                            <input type="text" name="nom" placeholder="Last Name" required>
                            <input type="text" name="prenom" placeholder="First Name" required>
                            <input type="email" name="email" placeholder="Email" required>
                            <input type="text" name="matricule" placeholder="Matricule" required>
                        </div>
                        <div class="form-row">
                            <input type="hidden" name="niveau" value="L2 ISIL">
                            <div style="display: flex; align-items: center; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc;">L2 ISIL</div>
                            <input type="password" name="password" placeholder="Temporary Password">
                            <button type="submit" name="add_student" class="btn btn-blue">Add Student</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Students List</div>
                    <input type="text" id="student-search" placeholder="Search students by name..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="students-tbody">
                                <?php foreach ($students as $s): ?>
                                <tr>
                                    <td><?= h($s['matricule']) ?></td>
                                    <td><?= h($s['nom'] . ', ' . $s['prenom']) ?></td>
                                    <td><?= h($s['email']) ?></td>
                                    <td><?= h($s['niveau']) ?></td>
                                    <td>
                                        <?php
                                        $last_online = $s['last_online'] ? strtotime($s['last_online']) : null;
                                        $is_online = $last_online && (time() - $last_online) < 300;
                                        echo $is_online ? '<span style="color: green; font-size: 18px;">●</span>' : '<span style="color: red; font-size: 18px;">●</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                                            <button type="submit" name="delete_student" class="btn btn-red" onclick="return confirm('Delete this student?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($panel === 'teachers'): ?>
                <h1>Manage Teachers</h1>
                <p class="subtitle">Create, edit, and delete teacher accounts</p>
                <?= $notif ?>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Add New Teacher</div>
                    <form method="POST">
                        <div class="form-row">
                            <input type="text" name="nom" placeholder="Last Name" required>
                            <input type="text" name="prenom" placeholder="First Name" required>
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                        <div class="form-row">
                            <input type="text" name="grade" placeholder="Grade (Dr., Prof., etc.)">
                            <input type="text" name="departement" placeholder="Department">
                            <input type="password" name="password" placeholder="Temporary Password">
                            <button type="submit" name="add_teacher" class="btn btn-blue">Add Teacher</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Teachers List</div>
                    <input type="text" id="teacher-search" placeholder="Search teachers by name..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Grade</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="teachers-tbody">
                                <?php foreach ($teachers as $t): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $last_online = $t['last_online'] ? strtotime($t['last_online']) : null;
                                        $now = time();
                                        $is_online = $last_online && ($now - $last_online) < 300;
                                        echo h($t['nom'] . ', ' . $t['prenom']);
                                        if ($is_online) {
                                            echo ' <span style="color: green;">●</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= h($t['email']) ?></td>
                                    <td><?= h($t['grade']) ?></td>
                                    <td><?= h($t['departement']) ?></td>
                                    <td>
                                        <?php
                                        echo $is_online ? '<span style="color: green; font-size: 18px;">●</span>' : '<span style="color: red; font-size: 18px;">●</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                            <button type="submit" name="delete_teacher" class="btn btn-red" onclick="return confirm('Delete this teacher?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($panel === 'modules'): ?>
                <h1>Manage Modules</h1>
                <p class="subtitle">Create and manage course modules</p>
                <?= $notif ?>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Add New Module</div>
                    <form method="POST">
                        <div class="form-row">
                            <input type="text" name="code" placeholder="Module Code (e.g., PWEB)" required>
                            <input type="text" name="intitule" placeholder="Module Name" required>
                            <input type="number" name="coefficient" placeholder="Coefficient" value="1">
                        </div>
                        <div class="form-row">
                            <select name="niveau"><option>L1 Info</option><option>L2 Info</option><option>L3 Info</option><option>M1 Info</option><option>M2 Info</option></select>
                            <select name="enseignant_id"><option value="">No Teacher Assigned</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['nom'] . ' ' . $t['prenom']) ?></option><?php endforeach; ?></select>
                            <button type="submit" name="add_module" class="btn btn-blue">Add Module</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Modules List</div>
                    <input type="text" id="module-search" placeholder="Search modules by code or name..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Coefficient</th>
                                    <th>Level</th>
                                    <th>Teacher</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="modules-tbody">
                                <?php foreach ($modules as $m): 
                                    $teacher = null;
                                    if ($m['enseignant_id']) {
                                        $stmt = $pdo->prepare("SELECT nom, prenom FROM enseignants WHERE id = ?");
                                        $stmt->execute([$m['enseignant_id']]);
                                        $teacher = $stmt->fetch();
                                    }
                                ?>
                                <tr>
                                    <td><?= h($m['code']) ?></td>
                                    <td><?= h($m['intitule']) ?></td>
                                    <td><?= $m['coefficient'] ?></td>
                                    <td><?= h($m['niveau']) ?></td>
                                    <td><?= $teacher ? h($teacher['nom'] . ' ' . $teacher['prenom']) : '—' ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                                            <button type="submit" name="delete_module" class="btn btn-red" onclick="return confirm('Delete this module?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($panel === 'grade-review'): ?>
                <h1>Grade Review</h1>
                <p class="subtitle">Review all submitted grades from teachers</p>
                <?= $notif ?>

                <?php 
                $selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
                
                if ($selected_student_id): ?>
                    <!-- Student Details View -->
                    <div style="margin-bottom: 16px;">
                        <a href="?panel=grade-review" class="btn btn-outline" style="display: inline-block; padding: 8px 16px; background: #e8f0f5; border: 1px solid #3b82f6; color: #1e4f8c; border-radius: 8px; text-decoration: none; font-weight: 500;">← Back to Students</a>
                    </div>
                    
                    <?php
                    try {
                        // Get student name
                        $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiants WHERE id = ?");
                        $stmt->execute([$selected_student_id]);
                        $student = $stmt->fetch();
                        
                        if (!$student) {
                            echo '<div style="text-align:center;color:#dc2626;padding:20px;">Student not found.</div>';
                        } else {
                            echo '<h2 style="margin-top:0;">' . h($student['nom'] . ' ' . $student['prenom']) . '</h2>';
                            
                            // Get all grades for this student
                            $stmt = $pdo->prepare("
                                SELECT 
                                    m.code as module_code,
                                    m.intitule as module_name,
                                    ens.nom as teacher_nom,
                                    ens.prenom as teacher_prenom,
                                    n.note_tp,
                                    n.note_td,
                                    n.note_exam,
                                    ROUND((n.note_tp * 0.2) + (n.note_td * 0.3) + (n.note_exam * 0.5), 2) as average
                                FROM inscriptions i
                                JOIN modules m ON i.module_id = m.id
                                LEFT JOIN notes n ON n.module_id = i.module_id AND n.etudiant_id = i.etudiant_id AND n.annee_univ = i.annee_univ
                                LEFT JOIN enseignants ens ON m.enseignant_id = ens.id
                                WHERE i.etudiant_id = ? AND i.annee_univ = '2025/2026'
                                ORDER BY m.code
                            ");
                            $stmt->execute([$selected_student_id]);
                            $student_grades = $stmt->fetchAll();
                            
                            if (empty($student_grades)) {
                                echo '<div style="text-align:center;padding:20px;">No grades submitted yet.</div>';
                            } else {
                                ?>
                                <div class="card">
                                    <div style="overflow-x: auto;">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Module</th>
                                                    <th>Teacher</th>
                                                    <th>TP</th>
                                                    <th>TD</th>
                                                    <th>Exam</th>
                                                    <th>Average</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($student_grades as $g):
                                                    $has_any_grade = ($g['note_tp'] !== null || $g['note_td'] !== null || $g['note_exam'] !== null);
                                                    
                                                    if (!$has_any_grade) {
                                                        $status = 'No Grades';
                                                        $status_color = '#9ca3af';
                                                    } elseif ($g['note_exam'] !== null) {
                                                        $status = ($g['average'] !== null && $g['average'] >= 10) ? 'Passed' : 'Failed';
                                                        $status_color = ($status === 'Passed') ? '#10b981' : '#ef4444';
                                                    } else {
                                                        $status = 'Incomplete';
                                                        $status_color = '#f59e0b';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><?= h($g['module_code'] . ' — ' . $g['module_name']) ?></td>
                                                    <td><?= h(($g['teacher_nom'] ?? '') . ' ' . ($g['teacher_prenom'] ?? '')) ?></td>
                                                    <td><?= $g['note_tp'] !== null ? number_format($g['note_tp'], 2) : '—' ?></td>
                                                    <td><?= $g['note_td'] !== null ? number_format($g['note_td'], 2) : '—' ?></td>
                                                    <td><?= $g['note_exam'] !== null ? number_format($g['note_exam'], 2) : '—' ?></td>
                                                    <td><strong><?= $g['average'] !== null ? number_format($g['average'], 2) : '—' ?></strong></td>
                                                    <td><span style="color: <?= $status_color ?>;font-weight:600;"><?= $status ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    } catch (\PDOException $e) {
                        echo '<div style="text-align:center;color:#dc2626;">Error loading student grades.</div>';
                    }
                    ?>
                
                <?php else: ?>
                    <!-- Students List View -->
                    <div class="card">
                        <div style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #0f172a;">Click a student to see their grades</div>
                        <input type="text" id="grade-student-search" placeholder="Search students by name..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Matricule</th>
                                        <th>Level</th>
                                        <th>Total Average</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="grade-students-tbody">
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT 
                                                e.id,
                                                e.nom,
                                                e.prenom,
                                                e.matricule,
                                                e.niveau,
                                                ROUND(AVG(ROUND((n.note_tp * 0.2) + (n.note_td * 0.3) + (n.note_exam * 0.5), 2)), 2) as total_average
                                            FROM etudiants e
                                            LEFT JOIN notes n ON e.id = n.etudiant_id AND n.annee_univ = '2025/2026'
                                            GROUP BY e.id
                                            ORDER BY e.nom, e.prenom
                                        ");
                                        $stmt->execute();
                                        $all_students = $stmt->fetchAll();
                                        
                                        if (empty($all_students)) {
                                            echo '<tr><td colspan="5" style="text-align:center;padding:20px;">No students found.</td></tr>';
                                        } else {
                                            foreach ($all_students as $s):
                                        ?>
                                        <tr style="cursor:pointer;" onclick="window.location.href='?panel=grade-review&student_id=<?= $s['id'] ?>'">
                                            <td><?= h($s['nom'] . ' ' . $s['prenom']) ?></td>
                                            <td><?= h($s['matricule']) ?></td>
                                            <td><?= h($s['niveau']) ?></td>
                                            <td><span style="background:#dbeaf5;padding:4px 8px;border-radius:4px;font-weight:600;color:#1e4f8c;"><?= $s['total_average'] !== null ? number_format($s['total_average'], 2) : '—' ?></span></td>
                                            <td style="text-align:center;"><span style="color:#3b82f6;font-weight:600;">View →</span></td>
                                        </tr>
                                        <?php
                                            endforeach;
                                        }
                                    } catch (\PDOException $e) {
                                        echo '<tr><td colspan="5" style="text-align:center;color:#dc2626;">Error loading students.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>
    <script>
        // Students search
        const studentSearch = document.getElementById('student-search');
        if (studentSearch) {
            studentSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const tbody = document.getElementById('students-tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const name = row.cells[1].textContent.toLowerCase();
                        row.style.display = name.includes(query) ? '' : 'none';
                    });
                }
            });
        }

        // Teachers search
        const teacherSearch = document.getElementById('teacher-search');
        if (teacherSearch) {
            teacherSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const tbody = document.getElementById('teachers-tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const name = row.cells[0].textContent.toLowerCase();
                        row.style.display = name.includes(query) ? '' : 'none';
                    });
                }
            });
        }

        // Modules search
        const moduleSearch = document.getElementById('module-search');
        if (moduleSearch) {
            moduleSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const tbody = document.getElementById('modules-tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const code = row.cells[0].textContent.toLowerCase();
                        const name = row.cells[1].textContent.toLowerCase();
                        row.style.display = (code.includes(query) || name.includes(query)) ? '' : 'none';
                    });
                }
            });
        }

        // Grade review students search
        const gradeStudentSearch = document.getElementById('grade-student-search');
        if (gradeStudentSearch) {
            gradeStudentSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const tbody = document.getElementById('grade-students-tbody');
                if (tbody) {
                    const rows = tbody.querySelectorAll('tr');
                    rows.forEach(row => {
                        const name = row.cells[0].textContent.toLowerCase();
                        row.style.display = name.includes(query) ? '' : 'none';
                    });
                }
            });
        }
    </script>
</body>
</html>
