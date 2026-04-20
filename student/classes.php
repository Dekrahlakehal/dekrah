<?php
require_once '../includes/auth.php';
require_login('etudiant');
$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();

$days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$today_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'][date('l')];

$stmt = $pdo->prepare('SELECT m.code, m.intitule FROM inscriptions i JOIN modules m ON m.id = i.module_id WHERE i.etudiant_id = ? AND i.annee_univ = ? ORDER BY m.code ASC');
$stmt->execute([$user_id, '2025/2026']);
$module_list = $stmt->fetchAll();

function buildSharedSchedule(array $modules) {
    $days = ['Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi'];
    $times = ['08:00:00', '10:00:00', '12:00:00', '14:00:00', '16:00:00', '18:00:00'];
    $rooms = ['Salle 101', 'Salle 102', 'Salle 201', 'Salle 202', 'Amphi A', 'Amphi B'];
    $schedule = [];
    foreach ($modules as $index => $module) {
        $day = $days[$index % count($days)];
        $time = $times[$index % count($times)];
        $room = $rooms[$index % count($rooms)];
        $schedule[$day][] = [
            'jour' => $day,
            'module_name' => $module['code'].' — '.$module['intitule'], 
            'heure_debut' => $time,
            'salle' => $room,
        ];
    }
    return $schedule;
}

$schedule = buildSharedSchedule($module_list);

function endTime($heure_debut) {
    $t = strtotime($heure_debut);
    return date('H:i', $t + 90 * 60);
}
$today_count = isset($schedule[$today_fr]) ? count($schedule[$today_fr]) : 0;
$total_count = 0;
foreach ($schedule as $daySchedule) { $total_count += count($daySchedule); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>USTHB Classes - <?= htmlspecialchars($u['prenom']) ?></title>
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
        main { margin-left: 240px; padding: 28px 32px; width: calc(100% - 240px); }
        header { display: flex; align-items: center; margin-bottom: 26px; }
        .spacer { flex: 1; }
        .user { display: flex; align-items: center; gap: 14px; background: #ffffff; border: 1px solid #b3cfe8; border-radius: 18px; padding: 8px 14px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #93c5fd); }
        .summary-strip { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 18px 24px; display: flex; gap: 28px; align-items: center; box-shadow: 0 8px 24px rgba(15,23,42,0.04); margin-bottom: 24px; }
        .summary-item { text-align: center; }
        .summary-item .sv { display: block; font-size: 26px; font-weight: 700; color: #1e4f8c; }
        .summary-item .sl { font-size: 11px; color: #64748b; text-transform: uppercase; }
        .day-block { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 22px; overflow: hidden; margin-bottom: 20px; }
        .day-block.today { border: 2px solid #3b82f6; }
        .day-header { padding: 16px 24px; background: #f0f7ff; display: flex; justify-content: space-between; align-items: center; }
        .day-body { padding: 16px 24px; display: flex; flex-direction: column; gap: 12px; }
        .class-row { display: grid; grid-template-columns: 140px 1fr 120px; align-items: center; background: #f8fafc; padding: 14px 18px; border-radius: 14px; }
        .time-main { font-weight: 700; color: #1e4f8c; }
        .class-salle { text-align: right; font-weight: 600; color: #1e4f8c; background: #dbeaf5; padding: 6px 12px; border-radius: 8px; }
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
            <a href="classes.php" class="nav-item active">My Classes</a>
            <a href="assignments.php" class="nav-item">Assignments</a>
            <a href="grades.php" class="nav-item">Grades</a>
            <a href="../public/logout.php" class="nav-item" style="color:#dc2626; margin-top: auto;">Logout</a>
        </nav>
    </aside>
    <main>
        <header>
            <div class="spacer"></div>
            <div class="user">
                <span style="font-weight:600; font-size:14px;"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span>
                <div class="avatar"></div>
            </div>
        </header>
        <div class="content">
            <h1 style="margin-bottom:20px;">My Classes</h1>
            <div class="summary-strip">
                <div class="summary-item"><span class="sv"><?= max($total_count, count($module_list)) ?></span><span class="sl">Classes / Week</span></div>
                <div class="summary-item"><span class="sv"><?= $today_count ?></span><span class="sl">Today</span></div>
                <div class="summary-item"><span class="sv"><?= max(count($schedule), $total_count ? 1 : 0) ?></span><span class="sl">Active Days</span></div>
            </div>
            <?php if (empty($schedule)): ?>
            <div class="day-block">
                <div class="day-header"><span style="font-weight:700;">L2 Section Classes</span><span><?= count($module_list) ?> modules</span></div>
                <div class="day-body">
                    <?php foreach ($module_list as $mod): ?>
                    <div class="class-row">
                        <div class="class-time"><span class="time-main">--:--</span></div>
                        <div><strong><?= htmlspecialchars($mod['code'].' — '.$mod['intitule']) ?></strong></div>
                        <span class="class-salle">Section L2</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach ($days as $day): if (!isset($schedule[$day])) continue; $isToday = ($day === $today_fr); ?>
            <div class="day-block <?= $isToday ? 'today' : '' ?>">
                <div class="day-header"><span style="font-weight:700;"><?= $day ?></span><span><?= count($schedule[$day]) ?> classes</span></div>
                <div class="day-body">
                    <?php foreach ($schedule[$day] as $class): ?>
                    <div class="class-row">
                        <div class="class-time"><span class="time-main"><?= substr($class['heure_debut'], 0, 5) ?></span></div>
                        <div><strong><?= htmlspecialchars($class['module_name']) ?></strong></div>
                        <span class="class-salle"><?= htmlspecialchars($class['salle']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>
