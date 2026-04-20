<?php
require_once '../includes/auth.php';
require_login('etudiant');
$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();

$year = '2025/2026';
$stmt = $pdo->prepare('SELECT m.code, m.intitule, m.niveau, n.note_tp, n.note_td, n.note_exam
    FROM inscriptions i
    JOIN modules m ON m.id = i.module_id
    LEFT JOIN notes n ON n.module_id = m.id AND n.etudiant_id = i.etudiant_id AND n.annee_univ = ?
    WHERE i.etudiant_id = ? AND i.annee_univ = ?
    ORDER BY m.code ASC
');
$stmt->execute([$year, $user_id, $year]);
$modules = $stmt->fetchAll();
foreach ($modules as &$module) {
    $module['final'] = calculateFinal($module['note_tp'], $module['note_td'], $module['note_exam']);
}
unset($module);
$allGraded = true;
$finalSum = 0;
foreach ($modules as $module) {
    if ($module['final'] === null) {
        $allGraded = false;
        break;
    }
    $finalSum += $module['final'];
}
$overallAverage = $allGraded && count($modules) ? round($finalSum / count($modules), 2) : null;

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
        .transcript-card { display: none; }
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
        .Style3 {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
        }
        .Style4 {font-family: Arial, Helvetica, sans-serif}
        .stack {
            clear: both;
        }
        @media print {
            body { background: white; color: #0f172a; font-family: Arial, Helvetica, sans-serif; }
            .sidebar, nav, .nav-logout, header, .print-button, .grades-view { display: none !important; }
            .transcript-card { display: block !important; }
            main { margin-left: 0; width: 100%; padding: 20px; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo"><img src="../img/usthb.png" class="logo-img" alt="USTHB Logo"><span>USTHB</span></div>
        <nav>
            <a href="student.php" class="nav-item">Dashboard</a>
            <a href="classes.php" class="nav-item">My Classes</a>
            <a href="assignments.php" class="nav-item">Assignments</a>
            <a href="grades.php" class="nav-item active">Grades</a>
            <a href="../public/logout.php" class="nav-logout">Logout</a>
        </nav>
    </aside>
    <main>
        <header><div class="spacer"></div><div class="user"><span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span><div class="avatar"></div></div></header>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:16px;">
                <h1 style="margin:0;">My Grades</h1>
                <button class="print-button" onclick="window.print()" title="Download transcript as PDF" style="display:flex;flex-direction:column;align-items:center;justify-content:center;width:52px;height:52px;padding:0;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 2H14L18 6V22H6V2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2V6H18" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 13H16" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <path d="M8 17H16" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 9V17" stroke="white" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span style="font-size:10px;color:white;margin-top:4px;">PDF</span>
                </button>
            </div>
            <div class="transcript-card">
                <div align="left">
                    <table width="100%" border="0" bordercolor="#FFFFFF">
                        <tr>
                            <td width="73%" height="165">
                                <h2 class="Style4"><strong>République Algérienne Démocratique et populaire</strong></h2>
                                <h2 class="Style4"><strong>Ministère de l'Enseignement Supérieur et de la Recherche Scientifique</strong></h2>
                                <h2 class="Style4"><strong>université des sciences et de la technologie houari boumediène alger </strong></h2>
                                <h2 class="Style4"><strong>Faculté d'Informatique </strong></h2>
                                <h2 class="Style4"><strong>Département des Systèmes Informatiques</strong></h2>
                            </td>
                            <td width="27%"><img src="../img/usthb.png" width="268" height="300" align="right" alt="USTHB Logo" /></td>
                        </tr>
                    </table>
                </div>
                <hr align="Left" width="100%">
                <table width="100%" border="0">
                    <tr>
                        <td width="1039" height="27">&nbsp;</td>
                        <td width="828" class="Style3"><h1>RELEVE DE NOTES </h1></td>
                        <td width="592">&nbsp;</td>
                    </tr>
                </table>
                <table width="100%" style="border:none; width:1000; margin-bottom:6px;">
                    <colgroup>
                        <col style="width:8%"><col style="width:24%">
                        <col style="width:8%"><col style="width:22%">
                        <col style="width:10%"><col style="width:28%">
                    </colgroup>
                    <tbody>
                        <tr>
                            <td style="border:none; font-weight:700; padding:1px 2px;">Année</td>
                            <td class="Style3" style="border:none;  padding:1px 4px;"><span class="Style4"><?= htmlspecialchars($year) ?></span></td>
                            <td style="border:none; font-weight:700; padding:1px 2px; text-align:center;">Prénom :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px; font-weight:700;"><span class="Style4"><?= htmlspecialchars($u['prenom']) ?></span></td>
                            <td style="border:none; font-weight:700; padding:1px 2px;">N° (e) Le :</td>
                            <td style="border:none;  padding:1px 4px;"><span class="Style3"><?= $u['date_naissance'] ? date('d/m/Y', strtotime($u['date_naissance'])) : 'N/A' ?> </span></td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight:700; padding:1px 2px;">Nom :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px; font-weight:700;"><span class="Style4"><?= htmlspecialchars($u['nom']) ?></span></td>
                            <td style="border:none; font-weight:700; padding:1px 2px; text-align:center;">Niveau :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px;"><span class="Style4"><?= htmlspecialchars($u['niveau']) ?></span></td>
                            <td><span class="Style4"></span></td>
                            <td><span class="Style4"></span></td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight:700; padding:1px 2px; white-space:nowrap;">N° d'inscription :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px;"><span class="Style4"><?= htmlspecialchars($u['matricule']) ?></span></td>
                            <td style="border:none; font-weight:700; padding:1px 2px; text-align:center;">Spécialité :</td>
                            <td class="Style3" style="border:none; padding:1px 4px;"><span class="Style4">/</span></td>
                            <td><span class="Style4"></span></td>
                            <td><div align="left"><span class="Style4"></span></div></td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight:700; padding:1px 2px;">Domaine :</td>
                            <td class="Style3" style="border:none;; padding:1px 4px;"><span class="Style4">Mathématiques et Informatique</span></td>
                            <td style="border:none; font-weight:700; padding:1px 2px; text-align:center;">Filière :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px; font-weight:700;"><span class="Style4">informatique</span></td>
                            <td><span class="Style4"></span></td>
                            <td><span class="Style4"></span></td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight:700; padding:1px 2px; white-space:nowrap;">Diplôme préparé :</td>
                            <td class="Style3" style="border:none;  padding:1px 4px;"><span class="Style4">Licence (Académique)</span></td>
                            <td>&nbsp;</td>
                            <td class="Style3"><span class="Style4"></span></td>
                            <td><span class="Style4"></span></td>
                            <td><span class="Style4"></span></td>
                        </tr>
                    </tbody>
                </table>
                <table width="100%" border="1">
                    <tr>
                        <td colspan="7" align="center" valign="middle"><span class="Style4">Unité d'enseignement (U.E) </span></td>
                        <td colspan="6" align="center" valign="middle"><span class="Style4">Matière(s) consécutive(s) de l'unité d'enseignement </span></td>
                    </tr>
                    <tr>
                        <td width="5%" align="center" valign="middle"><span class="Style4">Nature</span></td>
                        <td width="11%" align="center" valign="middle"><span class="Style4">Code Ue </span></td>
                        <td width="6%" align="center" valign="middle"><span class="Style4">Crédits</span></td>
                        <td width="6%" align="center" valign="middle"><span class="Style4">Coef</span></td>
                        <td width="4%" align="center" valign="middle"><span class="Style4">Moy</span></td>
                        <td width="6%" align="center" valign="middle"><span class="Style4">Crédits</span></td>
                        <td width="8%" align="center" valign="middle"><span class="Style4">Sess</span></td>
                        <td width="27%" align="center" valign="middle"><span class="Style4">Intitulé(s)</span></td>
                        <td width="6%" align="center" valign="middle"><span class="Style4">Crédits</span></td>
                        <td width="5%" align="center" valign="middle"><span class="Style4">Coef</span></td>
                        <td width="5%" align="center" valign="middle"><span class="Style4">Moy</span></td>
                        <td width="6%" align="center" valign="middle"><span class="Style4">Crédits</span></td>
                        <td width="5%" align="center" valign="middle"><span class="Style4">Sess</span></td>
                    </tr>
                    <?php foreach ($modules as $m): ?>
                    <tr>
                        <td><span class="Style4">U.E.F</span></td>
                        <td><span class="Style4"><?= htmlspecialchars($m['code']) ?></span></td>
                        <td><span class="Style4">C</span></td>
                        <td><span class="Style4">6.0</span></td>
                        <td><span class="Style4"><?= $m['final'] !== null ? htmlspecialchars(number_format($m['final'], 2)) : 'N/A' ?></span></td>
                        <td><span class="Style4">cred</span></td>
                        <td><span class="Style4">N</span></td>
                        <td><span class="Style4"><?= htmlspecialchars($m['intitule']) ?></span></td>
                        <td><span class="Style4">cred</span></td>
                        <td><span class="Style4">2</span></td>
                        <td><span class="Style4"><?= $m['final'] !== null ? htmlspecialchars(number_format($m['final'], 2)) : 'N/A' ?></span></td>
                        <td><span class="Style4">cred</span></td>
                        <td><span class="Style4">N</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td height="72" colspan="13">
                            <table width="100%" height="58" border="0">
                                <tr>
                                    <td width="13%" height="42">Moyenne du Semestre 1 : </td>
                                    <td width="18%"><?= $overallAverage !== null ? htmlspecialchars(number_format($overallAverage, 2)) : 'N/A' ?></td>
                                    <td width="14%">Crédit de semestre 1 : </td>
                                    <td width="11%">cred </td>
                                    <td width="12%">Session : </td>
                                    <td width="32%">N</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table width="100%" border="1">
                    <tr>
                        <td width="8%">Moyenne : </td>
                        <td width="10%"><?= $overallAverage !== null ? htmlspecialchars(number_format($overallAverage, 2)) : 'N/A' ?></td>
                        <td width="8%">décision : </td>
                        <td width="47%"><?= $allGraded ? 'Validé' : 'En cours' ?></td>
                        <td width="27%">N: Session Normal R:Session Rattrapage</td>
                    </tr>
                </table>
            </div>
            <?php foreach ($modules as $m): 
                $f = $m['final']; 
            ?>
            <div class="grades-view">
            <div class="module-card">
                <div class="module-top">
                    <div><span class="module-code"><?= htmlspecialchars($m['code']) ?></span><h3 style="margin-top:8px;"><?= htmlspecialchars($m['intitule']) ?></h3></div>
                    <div style="text-align:right;"><span class="final-score" style="color:<?= ($f !== null && $f >= 10) ? '#16a34a' : ($f !== null ? '#dc2626' : '#64748b') ?>"><?= $f !== null ? number_format($f, 2) : '&mdash;' ?></span></div>
                </div>
                <div class="comp-row"><span>TP</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_tp'] !== null ? (($m['note_tp']/20)*100) : 2 ?>%; background:<?= $m['note_tp'] !== null ? '#60a5fa' : '#cbd5e1' ?>;"><?= $m['note_tp'] !== null ? htmlspecialchars(number_format($m['note_tp'], 2)) : '&mdash;' ?>/20</div></div><span>20%</span></div>
                <div class="comp-row"><span>TD</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_td'] !== null ? (($m['note_td']/20)*100) : 2 ?>%; background:<?= $m['note_td'] !== null ? '#3b82f6' : '#cbd5e1' ?>;"><?= $m['note_td'] !== null ? htmlspecialchars(number_format($m['note_td'], 2)) : '&mdash;' ?>/20</div></div><span><?= ($m['note_tp'] === null) ? '40%' : '20%' ?></span></div>
                <div class="comp-row"><span>Exam</span><div class="comp-track"><div class="comp-fill" style="width:<?= $m['note_exam'] !== null ? (($m['note_exam']/20)*100) : 2 ?>%; background:<?= $m['note_exam'] !== null ? '#1e4f8c' : '#cbd5e1' ?>;"><?= $m['note_exam'] !== null ? htmlspecialchars(number_format($m['note_exam'], 2)) : '&mdash;' ?>/20</div></div><span>60%</span></div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
