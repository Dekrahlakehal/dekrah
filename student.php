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

$days_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$today_fr = $days_fr[date('l')];

$stmt = $pdo->prepare('SELECT * FROM planning WHERE etudiant_id = ? AND jour = ? ORDER BY heure_debut ASC');
$stmt->execute([$user_id, $today_fr]);
$today_schedule = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT m.code, n.note_tp, n.note_td, n.note_exam FROM inscriptions i JOIN modules m ON m.id = i.module_id LEFT JOIN notes n ON n.etudiant_id = i.etudiant_id AND n.module_id = i.module_id WHERE i.etudiant_id = ?');
$stmt->execute([$user_id]);
$all_notes = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT m.code, a.nombre FROM absences a JOIN modules m ON m.id = a.module_id WHERE a.etudiant_id = ?');
$stmt->execute([$user_id]);
$all_absences = $stmt->fetchAll();

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
    <title>USTHB Dashboard - <?= htmlspecialchars($u['prenom']) ?></title>
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
        .card-label { display: inline-flex; padding: 6px 12px; border-radius: 999px; background: #d0e8f7; color: #1e4f8c; font-size: 11px; font-weight: 700; margin-bottom: 16px; }
        .grading-flex { display: flex; justify-content: space-between; align-items: center; }
        .policy-main { display: flex; gap: 40px; }
        .policy-item .big { font-size: 42px; color: #1e4f8c; font-weight: 700; line-height: 1.1; }
        .policy-item .small { font-size: 12px; color: #64748b; font-weight: 500; text-transform: uppercase; }
        .tp-box { background: #eff6ff; border-radius: 18px; padding: 16px 20px; border: 1px solid #dbeafe; }
        .tp-box h4 { font-size: 11px; color: #1e4f8c; margin-bottom: 10px; font-weight: 700; }
        .tp-grid { display: flex; gap: 20px; text-align: center; }
        .tp-col span { display: block; font-size: 16px; font-weight: 700; color: #1e4f8c; }
        .tp-col small { font-size: 10px; color: #64748b; font-weight: 600; }
        .main-row { display: grid; grid-template-columns: 1.3fr 0.85fr; gap: 24px; }
        .schedule-item, .absence-item { display: flex; flex-direction: column; gap: 8px; padding: 16px; border-radius: 18px; background: #f8fafc; margin-bottom: 12px; }
        .schedule-date { width: 64px; text-align: center; padding: 10px 0; background: #dbeaf5; border-radius: 14px; color: #1e4f8c; font-weight: 700; }
        .grade-row { margin-bottom: 18px; }
        .grade-info { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; font-weight: 600; }
        .grade-track { background: #d0e8f7; border-radius: 14px; height: 32px; overflow: hidden; position: relative; }
        .grade-fill { height: 100%; background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%); }
        .dots { display: flex; gap: 6px; margin-top: 6px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; }
        .dot.filled { background: #3b82f6; }
        .excluded-msg { color: #dc2626; font-size: 11px; font-weight: 700; margin-top: 4px; border-top: 1px solid #fee2e2; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="logo"><img src="usthb.png" class="logo-img" alt="Logo"><span>USTHB</span></div>
            <nav>
                <a href="student.php" class="nav-item active">Dashboard</a>
                <a href="classes.php" class="nav-item">My Classes</a>
                <a href="assignments.php" class="nav-item">Assignments</a>
                <a href="grades.php" class="nav-item">Grades</a>
                <a href="logout.php" class="nav-logout">Logout</a>
            </nav>
        </aside>
        <main>
            <header><div class="spacer"></div><div class="user"><span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span><div class="avatar"></div></div></header>
            <div class="content">
                <h1 style="font-size: 28px; margin-bottom: 8px;">Welcome back, <?= htmlspecialchars($u['prenom']) ?> 👋</h1>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;">It's <?= $today_fr ?>, <?= date('F jS') ?></p>
                <div class="card">
                    <span class="card-label">GRADING POLICY</span>
                    <div class="grading-flex">
                        <div class="policy-main">
                            <div class="policy-item"><span class="big">40%</span><br><span class="small">TD (Continuous)</span></div>
                            <div class="policy-item"><span class="big">60%</span><br><span class="small">Final Exam</span></div>
                        </div>
                        <div class="tp-box">
                            <h4>Modules with TP</h4>
                            <div class="tp-grid">
                                <div class="tp-col"><span>20%</span><small>TP</small></div>
                                <div class="tp-col"><span>20%</span><small>TD</small></div>
                                <div class="tp-col"><span>60%</span><small>Exam</small></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="main-row">
                    <div class="left-col">
                        <div class="card">
                            <h3 style="margin-bottom:20px; color:#1e4f8c; font-size:16px;">Today's Schedule</h3>
                            <?php if (empty($today_schedule)): ?>
                                <div style="padding:20px; border-radius:16px; background:#ecfdf5; color:#166534; font-weight:600; border:1px solid #d1fae5;">
                                    You don't have classes today — enjoy your day!
                                </div>
                            <?php else: ?>
                                <?php foreach($today_schedule as $item): ?>
                                <div class="schedule-item" style="flex-direction:row;"><div class="schedule-date"><span><?= strtoupper(date('M')) ?></span><strong><?= date('d') ?></strong></div><div style="flex:1"><h4><?= htmlspecialchars($item['module_name']) ?></h4><p><?= htmlspecialchars($item['heure_debut']) ?> • <?= htmlspecialchars($item['salle']) ?></p></div></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="card">
                            <h3 style="margin-bottom:20px; color:#1e4f8c; font-size:16px;">Performance</h3>
                            <?php foreach($all_notes as $n): 
                                $fNote = calculateFinal($n['note_tp'], $n['note_td'], $n['note_exam']);
                            ?>
                            <div class="grade-row">
                                <div class="grade-info"><span><?= htmlspecialchars($n['code']) ?></span><span><?= $fNote !== null ? number_format($fNote, 2) . '/20' : 'N/A' ?></span></div>
                                <div class="grade-track"><div class="grade-fill" style="width:<?= $fNote !== null ? (($fNote/20)*100) : 0 ?>%;"></div></div>
                                <div style="display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:10px; gap:12px;">
                                    <span>TP: <?= $n['note_tp'] !== null ? htmlspecialchars($n['note_tp']) . '/20' : '-' ?></span>
                                    <span>TD: <?= $n['note_td'] !== null ? htmlspecialchars($n['note_td']) . '/20' : '-' ?></span>
                                    <span>Exam: <?= $n['note_exam'] !== null ? htmlspecialchars($n['note_exam']) . '/20' : '-' ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="right-col">
                        <div class="card">
                            <h3 style="margin-bottom:20px; color:#1e4f8c; font-size:16px;">Absence Guard</h3>
                            <?php foreach($all_absences as $abs): ?>
                            <div class="absence-item">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div style="flex:1"><h4><?= htmlspecialchars($abs['code']) ?></h4><p style="font-size:11px; color:#64748b;">Remaining Limit</p></div>
                                    <div style="text-align:right;">
                                        <span style="font-weight:700; color: <?= ($abs['nombre'] >= 5) ? '#dc2626' : 'inherit' ?>;"><?= $abs['nombre'] ?>/5</span>
                                        <div class="dots"><span class="dot <?= ($abs['nombre'] >= 1) ? 'filled' : '' ?>"></span><span class="dot <?= ($abs['nombre'] >= 2) ? 'filled' : '' ?>"></span><span class="dot <?= ($abs['nombre'] >= 3) ? 'filled' : '' ?>"></span><span class="dot <?= ($abs['nombre'] >= 4) ? 'filled' : '' ?>"></span><span class="dot <?= ($abs['nombre'] >= 5) ? 'filled' : '' ?>"></span></div>
                                    </div>
                                </div>
                                <?php if($abs['nombre'] >= 5): ?><div class="excluded-msg">⚠️ You are excluded from this module</div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
