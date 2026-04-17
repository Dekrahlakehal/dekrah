<?php
require_once 'includes/auth.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ' . url('login.php'));
    exit();
}
$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();

$stmt = $pdo->prepare('
    SELECT m.code, m.intitule, n.note_tp, n.note_td, n.note_exam 
    FROM inscriptions i
    JOIN modules m ON m.id = i.module_id
    LEFT JOIN notes n ON n.etudiant_id = i.etudiant_id AND n.module_id = i.module_id
    WHERE i.etudiant_id = ?
    ORDER BY m.code ASC
');
$stmt->execute([$user_id]);
$modules = $stmt->fetchAll();

function calculateFinal($tp, $td, $exam) {
    if ($exam === null) return null;
    if ($tp === null && $td === null) {
        return round($exam * 0.6, 2);
    }
    if ($tp === null) {
        return round(($td * 0.4) + ($exam * 0.6), 2);
    }
    if ($td === null) {
        return round(($tp * 0.2) + ($exam * 0.8), 2);
    }
    return round(($tp * 0.2) + ($td * 0.2) + ($exam * 0.6), 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USTHB Grades - <?= htmlspecialchars($u['prenom']) ?></title>
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
        .module-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 22px 26px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); margin-bottom: 18px; }
        .module-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .module-code { background: #d0e8f7; color: #1a4a80; font-weight: 700; padding: 8px 14px; border-radius: 10px; font-size: 13px; }
        .final-score { font-size: 32px; font-weight: 700; }
        .comp-row { display: grid; grid-template-columns: 90px 1fr 80px; align-items: center; gap: 14px; margin-bottom: 12px; }
        .comp-track { background: #d0e8f7; border-radius: 10px; height: 32px; overflow: hidden; }
        .comp-fill { height: 100%; display: flex; align-items: center; justify-content: flex-end; padding-right: 12px; font-size: 12px; font-weight: 700; color: white; transition: 0.3s; }
        .print-button { background: #2563eb; color: white; border: none; padding: 12px 18px; border-radius: 12px; cursor: pointer; font-size: 14px; font-weight: 700; }
        .print-button:hover { background: #1d4ed8; }
        .report-container { background: #ffffff; padding: 18px; border: 1px solid #000; margin-bottom: 24px; }
        .report-header { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .report-header .header-line { font-family: Arial, Helvetica, sans-serif; font-size: 12px; font-weight: bold; line-height: 1.4; }
        .report-title { font-family: Arial, Helvetica, sans-serif; font-size: 26px; margin: 8px 0 4px; }
        .metadata-table { border-collapse: collapse; width: 100%; margin-bottom: 16px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .metadata-table td { border: none; padding: 2px 6px; vertical-align: top; }
        .report-table { border-collapse: collapse; width: 100%; margin-bottom: 16px; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
        .report-table th, .report-table td, .summary-table td { border: 1px solid #000; padding: 6px 4px; }
        .report-table th { font-weight: bold; text-align: center; }
        .summary-table { border-collapse: collapse; width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        .summary-table td { padding: 6px 8px; }
        .print-header { display: none; }
        @media print {
            body { background: white; color: #0f172a; }
            .sidebar, .print-button, nav, .nav-logout, header { display: none !important; }
            main { margin-left: 0; width: 100%; padding: 0; }
            .module-card { box-shadow: none; border: none; border-radius: 0; padding: 0; margin-bottom: 18px; }
            .module-top { margin-bottom: 12px; }
            .comp-row { gap: 10px; }
            .comp-track { background: #f3f4f6; }
            .comp-fill { padding-right: 8px; font-size: 11px; }
            .print-header { display: block; margin-bottom: 24px; }
            .print-header h2 { margin-bottom: 8px; font-size: 24px; }
            .print-header p { margin-bottom: 4px; font-size: 13px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo"><img src="usthb.png" class="logo-img" alt="Logo"><span>USTHB</span></div>
        <nav>
            <a href="student.php" class="nav-item">Dashboard</a>
            <a href="classes.php" class="nav-item">My Classes</a>
            <a href="assignments.php" class="nav-item">Assignments</a>
            <a href="grades.php" class="nav-item active">Grades</a>
            <a href="logout.php" class="nav-logout">Logout</a>
        </nav>
    </aside>
    <main>
        <header><div class="spacer"></div><div class="user"><span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span><div class="avatar"></div></div></header>
        <div class="content">
            <h1 style="margin-bottom:20px;">My Grades</h1>
            <?php foreach ($modules as $m): 
                $f = calculateFinal($m['note_tp'], $m['note_td'], $m['note_exam']); 
            ?>
            <div class="module-card">
                <div class="module-top">
                    <div><span class="module-code"><?= htmlspecialchars($m['code']) ?></span><h3 style="margin-top:8px;"><?= htmlspecialchars($m['intitule']) ?></h3></div>
                    <div style="text-align:right;"><span class="final-score" style="color:<?= ($f !== null && $f >= 10) ? '#16a34a' : '#dc2626' ?>"><?= $f !== null ? number_format($f, 2) : 'N/A' ?></span></div>
                </div>
                <div class="comp-row"><span>TP</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_tp'] !== null ? (($m['note_tp']/20)*100) : 0 ?>%; background:#60a5fa;"><?= $m['note_tp'] !== null ? htmlspecialchars(number_format($m['note_tp'], 2)) : '-' ?>/20</div></div><span>20%</span></div>
                <div class="comp-row"><span>TD</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_td'] !== null ? (($m['note_td']/20)*100) : 0 ?>%; background:#3b82f6;"><?= $m['note_td'] !== null ? htmlspecialchars(number_format($m['note_td'], 2)) : '-' ?>/20</div></div><span><?= ($m['note_tp'] === null) ? '40%' : '20%' ?></span></div>
                <div class="comp-row"><span>Exam</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_exam'] !== null ? (($m['note_exam']/20)*100) : 0 ?>%; background:#1e4f8c;"><?= $m['note_exam'] !== null ? htmlspecialchars(number_format($m['note_exam'], 2)) : '-' ?>/20</div></div><span>60%</span></div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
