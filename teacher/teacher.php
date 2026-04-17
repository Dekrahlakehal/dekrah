<?php
require_once 'includes/auth.php';
require_login('enseignant');

$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

// Infos enseignant
$stmt = $pdo->prepare('SELECT * FROM enseignants WHERE id = ?');
$stmt->execute([$user_id]);
$ens = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM modules WHERE enseignant_id = ? AND annee_univ = ? ORDER BY code ASC');
$stmt->execute([$user_id, '2025/2026']);
$modules = $stmt->fetchAll();

// Traitement : Sauvegarde de note
$notif = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $etudiant_id = (int)$_POST['etudiant_id'];
    $module_id   = (int)$_POST['module_id'];
    $note_tp     = isset($_POST['note_tp']) && $_POST['note_tp'] !== '' ? (float)str_replace(',', '.', $_POST['note_tp']) : null;
    $note_td     = isset($_POST['note_td']) && $_POST['note_td'] !== '' ? (float)str_replace(',', '.', $_POST['note_td']) : null;
    $note_exam   = isset($_POST['note_exam']) && $_POST['note_exam'] !== '' ? (float)str_replace(',', '.', $_POST['note_exam']) : null;

    $invalid_notes = [];
    if ($note_tp !== null && ($note_tp < 0 || $note_tp > 20)) $invalid_notes[] = 'TP';
    if ($note_td !== null && ($note_td < 0 || $note_td > 20)) $invalid_notes[] = 'TD';
    if ($note_exam !== null && ($note_exam < 0 || $note_exam > 20)) $invalid_notes[] = 'Exam';

    if (!empty($invalid_notes)) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Invalid grades for ' . implode(', ', $invalid_notes) . ' (must be 0–20).</div>';
    } else {
        // Verify module belongs to teacher
        $chk = $pdo->prepare("SELECT id FROM modules WHERE id = ? AND enseignant_id = ?");
        $chk->execute([$module_id, $user_id]);
        if ($chk->fetch()) {
            // Insert or update grades
            $stmt = $pdo->prepare("
                INSERT INTO notes (etudiant_id, module_id, enseignant_id, note_tp, note_td, note_exam, annee_univ)
                VALUES (?, ?, ?, ?, ?, ?, '2025/2026')
                ON DUPLICATE KEY UPDATE
                    enseignant_id = VALUES(enseignant_id),
                    note_tp = VALUES(note_tp),
                    note_td = VALUES(note_td),
                    note_exam = VALUES(note_exam)
            ");
            $stmt->execute([$etudiant_id, $module_id, $user_id, $note_tp, $note_td, $note_exam]);
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Grades saved successfully.</div>';
        }
    }
}

// Email all students in a module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_all_students'])) {
    $module_id = (int)$_POST['module_id'];
    
    try {
        // Get all students in this module
        $stmt = $pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.email FROM inscriptions i
            JOIN etudiants e ON i.etudiant_id = e.id
            WHERE i.module_id = ? AND i.annee_univ = '2025/2026'
        ");
        $stmt->execute([$module_id]);
        $all_students = $stmt->fetchAll();
        
        $sent_count = 0;
        foreach ($all_students as $student) {
            // Get student's grades
            $stmt2 = $pdo->prepare("
                SELECT 
                    m.code, m.intitule,
                    n.note_tp, n.note_td, n.note_exam,
                    ROUND((n.note_tp * 0.2) + (n.note_td * 0.3) + (n.note_exam * 0.5), 2) as average
                FROM notes n
                JOIN modules m ON n.module_id = m.id
                WHERE n.etudiant_id = ? AND n.annee_univ = '2025/2026'
                ORDER BY m.code
            ");
            $stmt2->execute([$student['id']]);
            $grades = $stmt2->fetchAll();
            
            // Build email
            $to = $student['email'];
            $subject = "Your Academic Grades - USTHB Scolarité (Batch Send)";
            $overall_avg = 0;
            $count = 0;
            foreach ($grades as $g) {
                if ($g['average'] !== null) {
                    $overall_avg += $g['average'];
                    $count++;
                }
            }
            $overall_avg = $count > 0 ? number_format($overall_avg / $count, 2) : 'N/A';
            
            $message = "
            <html>
                <body style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #1e4f8c;'>Academic Grades Report (Batch Send)</h2>
                    <p>Dear <strong>" . htmlspecialchars($student['prenom'] . ' ' . $student['nom']) . "</strong>,</p>
                    <p>Please find below your grades for the academic year 2025/2026:</p>
                    
                    <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                        <thead>
                            <tr style='background: #dbeaf5;'>
                                <th style='border: 1px solid #b3cfe8; padding: 10px; text-align: left;'>Module</th>
                                <th style='border: 1px solid #b3cfe8; padding: 10px; text-align: center;'>TP</th>
                                <th style='border: 1px solid #b3cfe8; padding: 10px; text-align: center;'>TD</th>
                                <th style='border: 1px solid #b3cfe8; padding: 10px; text-align: center;'>Exam</th>
                                <th style='border: 1px solid #b3cfe8; padding: 10px; text-align: center;'>Average</th>
                            </tr>
                        </thead>
                        <tbody>
            ";
            
            foreach ($grades as $g) {
                $message .= "
                            <tr>
                                <td style='border: 1px solid #e2e8f0; padding: 10px;'><strong>" . htmlspecialchars($g['code']) . "</strong> - " . htmlspecialchars($g['intitule']) . "</td>
                                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center;'>" . ($g['note_tp'] !== null ? number_format($g['note_tp'], 2) : '—') . "</td>
                                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center;'>" . ($g['note_td'] !== null ? number_format($g['note_td'], 2) : '—') . "</td>
                                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center;'>" . ($g['note_exam'] !== null ? number_format($g['note_exam'], 2) : '—') . "</td>
                                <td style='border: 1px solid #e2e8f0; padding: 10px; text-align: center; font-weight: bold; color: " . ($g['average'] !== null && $g['average'] >= 10 ? '#10b981' : '#dc2626') . ";'>" . ($g['average'] !== null ? number_format($g['average'], 2) : '—') . "</td>
                            </tr>
                ";
            }
            
            $message .= "
                        </tbody>
                    </table>
                    <p style='font-size: 14px; color: #666;'><strong>Overall Average:</strong> <span style='color: #1e4f8c; font-weight: bold;'>" . $overall_avg . "</span></p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #666;'>This is an automated batch email from USTHB Scolarité system.</p>
                </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: noreply@usthb-scolarite.dz\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                $sent_count++;
            }
        }
        
        $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Grades emails sent to ' . $sent_count . ' student(s).</div>';
    } catch (\PDOException $e) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Failed to send emails.</div>';
    }
}

// Send to Admin (notify admin that grades are ready)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_admin'])) {
    $module_id = (int)$_POST['module_id'];
    
    try {
        // Get module and teacher info
        $stmt = $pdo->prepare("
            SELECT m.code, m.intitule FROM modules m 
            WHERE m.id = ? AND m.enseignant_id = ?
        ");
        $stmt->execute([$module_id, $user_id]);
        $module = $stmt->fetch();
        
        // Get admin emails
        $stmt = $pdo->prepare("SELECT email FROM admins LIMIT 5");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        if ($module && !empty($admins)) {
            $teacher_name = h($ens['prenom'] . ' ' . $ens['nom']);
            $subject = "Grades Ready for Review - " . $module['code'];
            
            $message = "
            <html>
                <body style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #1e4f8c;'>Grades Ready for Review</h2>
                    <p>Teacher <strong>" . $teacher_name . "</strong> has submitted grades for review.</p>
                    <div style='background: #f0f9ff; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                        <p><strong>Module:</strong> " . htmlspecialchars($module['code']) . " - " . htmlspecialchars($module['intitule']) . "</p>
                        <p><strong>Teacher:</strong> " . $teacher_name . "</p>
                        <p><strong>Status:</strong> <span style='color: #10b981; font-weight: bold;'>Ready for Review</span></p>
                    </div>
                    <p><a href='http://localhost/my_backend/admin.php?panel=grade-review' style='background: #3b82f6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block;'>View Grades in Admin Panel</a></p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #666;'>This is an automated notification from USTHB Scolarité system.</p>
                </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: noreply@usthb-scolarite.dz\r\n";
            
            $sent = 0;
            foreach ($admins as $admin) {
                if (mail($admin['email'], $subject, $message, $headers)) {
                    $sent++;
                }
            }
            
            $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Notification sent to ' . $sent . ' admin(s).</div>';
        }
    } catch (\PDOException $e) {
        $notif = '<div style="background:#fee2e2;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Failed to send notification.</div>';
    }
}

// Handle absence recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_absence'])) {
    $etudiant_id = (int)$_POST['etudiant_id'];
    $module_id = (int)$_POST['module_id'];
    $absence_count = isset($_POST['absence_count']) ? max((int)$_POST['absence_count'], 0) : 0;

    // Verify module belongs to teacher
    $chk = $pdo->prepare("SELECT id FROM modules WHERE id = ? AND enseignant_id = ?");
    $chk->execute([$module_id, $user_id]);
    if ($chk->fetch()) {
        // Insert or update absence count
        $stmt = $pdo->prepare("
            INSERT INTO absences (etudiant_id, module_id, nombre)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)
        ");
        $stmt->execute([$etudiant_id, $module_id, $absence_count]);
        $notif = '<div style="background:#d1fae5;color:#166534;padding:12px 16px;border-radius:10px;font-size:14px;margin-bottom:16px;">Absence recorded successfully.</div>';
    }
}

$panel = $_GET['panel'] ?? 'grades';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard - <?= htmlspecialchars($ens['prenom']) ?></title>
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
        input[type="number"] { padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; width: 70px; }
        .btn { padding: 8px 16px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-blue { background: #3b82f6; color: white; }
        .btn-blue:hover { background: #2563eb; }
        h1 { font-size: 26px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #64748b; margin-bottom: 24px; }
        .module-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .module-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px; }
        .module-code { font-size: 12px; font-weight: 700; color: #1e4f8c; }
        .module-name { font-size: 16px; font-weight: 600; margin: 8px 0; }
        .module-info { font-size: 12px; color: #64748b; margin-top: 12px; }
        @media (max-width: 768px) { .sidebar { display: none; } main { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="logo"><img src="usthb.png" class="logo-img" alt="Logo"><span>USTHB</span></div>
            <nav>
                <a href="?panel=grades" class="nav-item <?= $panel === 'grades' ? 'active' : '' ?>">Grades</a>
                <a href="?panel=absences" class="nav-item <?= $panel === 'absences' ? 'active' : '' ?>">Absences</a>
                <a href="?panel=modules" class="nav-item <?= $panel === 'modules' ? 'active' : '' ?>">My Modules</a>
                <a href="?panel=profile" class="nav-item <?= $panel === 'profile' ? 'active' : '' ?>">Profile</a>
                <a href="logout.php" class="nav-logout">Logout</a>
            </nav>
        </aside>
        <main>
            <header>
                <div class="spacer"></div>
                <div class="user">
                    <span><?= htmlspecialchars($ens['prenom'] . ' ' . $ens['nom']) ?></span>
                    <div class="avatar"></div>
                </div>
            </header>

            <?php if ($panel === 'grades'): ?>
                <h1>Grade Management</h1>
                <p class="subtitle">Input and manage student grades for your modules</p>
                <?= $notif ?>

                <?php if (empty($modules)): ?>
                    <div class="card" style="text-align: center; padding: 40px;">
                        <p style="color: #64748b; font-size: 14px;">No modules assigned for this academic year.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($modules as $mod): 
                        $stmt = $pdo->prepare("
                            SELECT e.id, e.nom, e.prenom, e.matricule, e.niveau, n.note_tp, n.note_td, n.note_exam
                            FROM inscriptions i
                            JOIN etudiants e ON e.id = i.etudiant_id
                            LEFT JOIN notes n ON n.etudiant_id = e.id AND n.module_id = i.module_id AND n.annee_univ = '2025/2026'
                            WHERE i.module_id = ? AND i.annee_univ = '2025/2026'
                            ORDER BY e.nom, e.prenom
                        ");
                        $stmt->execute([$mod['id']]);
                        $students = $stmt->fetchAll();
                    ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div>
                                <div style="font-size: 12px; color: #1e4f8c; font-weight: 700;"><?= h($mod['code']) ?></div>
                                <div style="font-size: 16px; font-weight: 600; color: #0f172a;"><?= h($mod['intitule']) ?></div>
                            </div>
                            <span style="background: #d0e8f7; color: #1e4f8c; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 700;">Credits: <?= h($mod['coefficient']) ?></span>
                        </div>

                        <?php if (empty($students)): ?>
                            <p style="color: #64748b; font-size: 12px;">No students enrolled in this module.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Student Name</th>
                                            <th>Section</th>
                                            <th>TP</th>
                                            <th>TD</th>
                                            <th>Exam</th>
                                            <th>Average</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $std):
                                            $tp = $std['note_tp'];
                                            $td = $std['note_td'];
                                            $exam = $std['note_exam'];
                                            $avg = null;
                                            if ($tp !== null && $td !== null && $exam !== null) {
                                                $avg = ($tp * 0.2) + ($td * 0.3) + ($exam * 0.5);
                                            }
                                        ?>
                                        <tr>
                                            <td><?= h($std['matricule']) ?></td>
                                            <td><?= h($std['nom'] . ', ' . $std['prenom']) ?></td>
                                            <td><span style="background:#d0e8f7;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;"><?= h($std['niveau']) ?></span></td>
                                            <td style="font-weight: 600; color: <?= $tp !== null ? ($tp >= 10 ? '#10b981' : '#dc2626') : '#64748b' ?>"><?= $tp !== null ? number_format($tp, 2) : '—' ?></td>
                                            <td style="font-weight: 600; color: <?= $td !== null ? ($td >= 10 ? '#10b981' : '#dc2626') : '#64748b' ?>"><?= $td !== null ? number_format($td, 2) : '—' ?></td>
                                            <td style="font-weight: 600; color: <?= $exam !== null ? ($exam >= 10 ? '#10b981' : '#dc2626') : '#64748b' ?>">
                                                <?= $exam !== null ? number_format($exam, 2) : '—' ?>
                                            </td>
                                            <td style="font-weight: 600; color: <?= $avg !== null ? ($avg >= 10 ? '#10b981' : '#dc2626') : '#64748b' ?>">
                                                <?= $avg !== null ? number_format($avg, 2) : '—' ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: flex; gap: 6px;">
                                                    <input type="hidden" name="etudiant_id" value="<?= $std['id'] ?>">
                                                    <input type="hidden" name="module_id" value="<?= $mod['id'] ?>">
                                                    <input type="number" name="note_tp" min="0" max="20" step="0.25" placeholder="TP" value="<?= $tp !== null ? number_format($tp, 2) : '' ?>">
                                                    <input type="number" name="note_td" min="0" max="20" step="0.25" placeholder="TD" value="<?= $td !== null ? number_format($td, 2) : '' ?>">
                                                    <input type="number" name="note_exam" min="0" max="20" step="0.25" placeholder="Ex" value="<?= $exam !== null ? number_format($exam, 2) : '' ?>">
                                                    <button type="submit" name="save_note" class="btn btn-blue">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Bulk Actions for Module -->
                        <?php if (!empty($students)): ?>
                        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="module_id" value="<?= $mod['id'] ?>">
                                <button type="submit" name="email_all_students" class="btn btn-blue" style="background: #3b82f6; padding: 10px 16px;">
                                    Email All Students
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="module_id" value="<?= $mod['id'] ?>">
                                <button type="submit" name="send_to_admin" class="btn btn-blue" style="background: #8b5cf6; padding: 10px 16px;">
                                    Send to Admin
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php elseif ($panel === 'modules'): ?>
                <h1>My Modules</h1>
                <p class="subtitle">List of modules assigned to you</p>

                <?php if (empty($modules)): ?>
                    <div class="card" style="text-align: center; padding: 40px;">
                        <p style="color: #64748b; font-size: 14px;">No modules assigned for this academic year.</p>
                    </div>
                <?php else: ?>
                    <div class="stat-row">
                        <div class="stat-box">
                            <div class="stat-label">Total Modules</div>
                            <div class="stat-val"><?= count($modules) ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Academic Year</div>
                            <div class="stat-val">2025/26</div>
                        </div>
                    </div>

                    <div class="module-grid">
                        <?php foreach ($modules as $mod):
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE module_id = ? AND annee_univ = '2025/2026'");
                            $stmt->execute([$mod['id']]);
                            $enrolled = $stmt->fetchColumn();

                            $stmt = $pdo->prepare("SELECT AVG(COALESCE(note_exam, note_td, note_tp)) FROM notes WHERE module_id = ? AND annee_univ = '2025/2026'");
                            $stmt->execute([$mod['id']]);
                            $avg_grade = $stmt->fetchColumn();
                        ?>
                        <div class="module-card">
                            <div class="module-code"><?= h($mod['code']) ?></div>
                            <div class="module-name"><?= h($mod['intitule']) ?></div>
                            <div class="module-info">
                                <div style="margin-top: 12px;">
                                    <strong>Credits:</strong> <?= $mod['coefficient'] ?><br>
                                    <strong>Level:</strong> <?= h($mod['niveau']) ?><br>
                                    <strong>Enrolled:</strong> <?= $enrolled ?> students<br>
                                    <strong>Avg Grade:</strong> <?= $avg_grade ? number_format($avg_grade, 2) : '—' ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($panel === 'absences'): ?>
                <h1>Absences</h1>
                <p class="subtitle">Track and manage student absences by module</p>
                <?= $notif ?>

                <?php if (empty($modules)): ?>
                    <div class="card" style="text-align: center; padding: 40px;">
                        <p style="color: #64748b; font-size: 14px;">No modules assigned for this academic year.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($modules as $mod): 
                        $stmt = $pdo->prepare("
                            SELECT e.id, e.nom, e.prenom, e.matricule
                            FROM inscriptions i
                            JOIN etudiants e ON e.id = i.etudiant_id
                            WHERE i.module_id = ? AND i.annee_univ = '2025/2026'
                            ORDER BY e.nom, e.prenom
                        ");
                        $stmt->execute([$mod['id']]);
                        $students = $stmt->fetchAll();
                    ?>
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div>
                                <div style="font-size: 12px; color: #1e4f8c; font-weight: 700;"><?= h($mod['code']) ?></div>
                                <div style="font-size: 16px; font-weight: 600; color: #0f172a;"><?= h($mod['intitule']) ?></div>
                            </div>
                            <span style="background: #d0e8f7; color: #1e4f8c; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 700;">Credits: <?= h($mod['coefficient']) ?></span>
                        </div>

                        <?php if (empty($students)): ?>
                            <p style="color: #64748b; font-size: 12px;">No students enrolled in this module.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Student Name</th>
                                            <th>Absences</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $std):
                                            // Get absence count for this student and module
                                            $absStmt = $pdo->prepare("SELECT nombre FROM absences WHERE etudiant_id = ? AND module_id = ?");
                                            $absStmt->execute([$std['id'], $mod['id']]);
                                            $abs_row = $absStmt->fetch();
                                            $absence_count = $abs_row ? $abs_row['nombre'] : 0;
                                        ?>
                                        <tr>
                                            <td><?= h($std['matricule']) ?></td>
                                            <td><?= h($std['nom'] . ', ' . $std['prenom']) ?></td>
                                            <td style="text-align: center; font-weight: 600; color: <?= $absence_count >= 5 ? '#dc2626' : '#1e4f8c' ?>">
                                                <?= $absence_count ?>/5
                                            </td>
                                            <td>
                                                <form method="POST" style="display: flex; gap: 4px;">
                                                    <input type="hidden" name="etudiant_id" value="<?= $std['id'] ?>">
                                                    <input type="hidden" name="module_id" value="<?= $mod['id'] ?>">
                                                    <input type="number" name="absence_count" min="0" max="5" value="<?= $absence_count ?>"
                                                        style="width: 50px; padding: 6px;">
                                                    <button type="submit" name="save_absence" class="btn btn-blue" style="padding: 6px 10px;">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php else: // profile panel ?>
                <h1>My Profile</h1>
                <p class="subtitle">Personal information and details</p>

                <div class="card" style="max-width: 500px;">
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                        <div class="avatar" style="width: 56px; height: 56px;"></div>
                        <div>
                            <div style="font-size: 16px; font-weight: 700;"><?= h($ens['nom'] . ' ' . $ens['prenom']) ?></div>
                            <div style="font-size: 12px; color: #64748b;"><?= h($ens['departement']) ?></div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; margin-bottom: 14px;">
                            <strong style="font-size: 12px; color: #64748b; text-transform: uppercase;">Email</strong>
                            <div style="font-size: 13px;"><?= h($ens['email']) ?></div>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; margin-bottom: 14px;">
                            <strong style="font-size: 12px; color: #64748b; text-transform: uppercase;">Title</strong>
                            <div style="font-size: 13px;"><?= h($ens['grade']) ?></div>
                        </div>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; margin-bottom: 14px;">
                            <strong style="font-size: 12px; color: #64748b; text-transform: uppercase;">Department</strong>
                            <div style="font-size: 13px;"><?= h($ens['departement']) ?></div>
                        </div>
                        <?php if ($ens['specialite']): ?>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; margin-bottom: 14px;">
                            <strong style="font-size: 12px; color: #64748b; text-transform: uppercase;">Specialty</strong>
                            <div style="font-size: 13px;"><?= h($ens['specialite']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px;">
                            <strong style="font-size: 12px; color: #64748b; text-transform: uppercase;">Modules</strong>
                            <div style="font-size: 13px;"><?= count($modules) ?> assigned</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>