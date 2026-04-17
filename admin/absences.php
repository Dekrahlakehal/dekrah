<?php
require_once 'includes/auth.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'etudiant') {
    header('Location: ' . url('login.php'));
    exit();
}
$pdo = get_pdo();
$user_id = $_SESSION['user_id'];

// Fetch User Info
$stmt = $pdo->prepare('SELECT * FROM etudiants WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();

// Handle +/- AJAX update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_id'], $_POST['action'])) {
    $module_id = (int)$_POST['module_id'];
    $action    = $_POST['action'];

    // Get current count
    $stmt = $pdo->prepare('SELECT nombre FROM absences WHERE etudiant_id = ? AND module_id = ? AND annee_univ = ?');
    $stmt->execute([$user_id, $module_id, '2025/2026']);
    $row = $stmt->fetch();
    $current = $row ? (int)$row['nombre'] : 0;

    if ($action === 'increment' && $current < 3) $current++;
    if ($action === 'decrement' && $current > 0) $current--;

    // Upsert
    $stmt = $pdo->prepare('
        INSERT INTO absences (etudiant_id, module_id, nombre, annee_univ)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nombre = ?
    ');
    $stmt->execute([$user_id, $module_id, $current, '2025/2026', $current]);

    echo json_encode(['success' => true, 'nombre' => $current]);
    exit();
}

// Fetch all modules the student is enrolled in + their absence count
$stmt = $pdo->prepare('
    SELECT
        m.id,
        m.code,
        m.intitule,
        m.coefficient,
        COALESCE(a.nombre, 0) AS nombre
    FROM inscriptions i
    JOIN modules m ON m.id = i.module_id
    LEFT JOIN absences a ON a.etudiant_id = i.etudiant_id AND a.module_id = i.module_id AND a.annee_univ = ?
    WHERE i.etudiant_id = ?
    ORDER BY m.code ASC
');
$stmt->execute(['2025/2026', $user_id]);
$modules = $stmt->fetchAll();

$max_absences = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USTHB – Absence Guard – <?= htmlspecialchars($u['prenom']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #e8f0f5; color: #0f172a; }

        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 240px;
            background: #dbeaf5;
            border-right: 1px solid #b3cfe8;
            padding: 24px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 18px;
            color: #1e4f8c;
            margin-bottom: 32px;
        }
        .logo-img { width: 42px; height: 42px; object-fit: contain; }
        nav { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .nav-item {
            padding: 12px 16px;
            border-radius: 10px;
            color: #374151;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .nav-item:hover { background: #c3d9ef; color: #1e4f8c; }
        .nav-item.active { background: #a8c8e8; color: #1e4f8c; font-weight: 600; }
        .help-box {
            background: #eaf3fb;
            border-radius: 16px;
            padding: 18px;
            margin-top: 24px;
        }
        .help-box h4 { font-size: 12px; color: #1e4f8c; margin-bottom: 8px; }
        .help-box p { font-size: 13px; color: #4b5563; line-height: 1.6; margin-bottom: 14px; }
        .help-box .support-link {
            display: inline-flex;
            width: 100%;
            justify-content: center;
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            background: #3b82f6;
            color: white;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }

        main { margin-left: 240px; padding: 28px 32px; width: calc(100% - 240px); }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 26px;
        }
        .search-box { flex: 1; max-width: 640px; }
        .search-box input {
            width: 100%;
            border: 1px solid #bfdbf7;
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 14px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
        }
        .header-right { display: flex; align-items: center; gap: 18px; }
        .year-badge {
            background: #bdd9f0;
            color: #1e4f8c;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }
        .user {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #ffffff;
            border: 1px solid #b3cfe8;
            border-radius: 18px;
            padding: 10px 14px;
        }
        .user-text { display: flex; flex-direction: column; }
        .name { font-weight: 700; font-size: 14px; }
        .role { font-size: 12px; color: #4b5563; }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #93c5fd);
        }

        .content { display: flex; flex-direction: column; gap: 24px; }
        .page-header h1 { font-size: 28px; margin-bottom: 6px; }
        .page-header p { color: #475569; font-size: 14px; }

        /* Info banner */
        .info-banner {
            background: #fff8e1;
            border: 1px solid #fbbf24;
            border-radius: 16px;
            padding: 14px 20px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-banner strong { font-weight: 700; }

        /* Summary strip */
        .summary-strip {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 18px 24px;
            display: flex;
            gap: 28px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 8px 24px rgba(15,23,42,0.04);
        }
        .summary-item { text-align: center; }
        .summary-item .sv { display: block; font-size: 26px; font-weight: 700; color: #1e4f8c; }
        .summary-item .sl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-divider { width: 1px; height: 36px; background: #bdd9f0; }

        /* Module absence cards */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 18px;
        }

        .absence-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15,23,42,0.04);
            transition: border-color 0.2s;
        }
        .absence-card.danger { border-color: #fca5a5; background: #fff8f8; }
        .absence-card.warning { border-color: #fcd34d; background: #fffdf0; }
        .absence-card.safe { border-color: #e2e8f0; }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .module-code {
            background: #d0e8f7;
            color: #1a4a80;
            font-weight: 700;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 8px;
            letter-spacing: 0.04em;
        }
        .status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 999px;
        }
        .status-pill.safe { background: #dcfce7; color: #166534; }
        .status-pill.warning { background: #fef9c3; color: #854d0e; }
        .status-pill.danger { background: #fee2e2; color: #991b1b; }

        .module-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .module-coeff { font-size: 12px; color: #64748b; }

        /* Dots */
        .dots-row {
            display: flex;
            gap: 10px;
            margin: 16px 0;
        }
        .dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            background: #f0f7ff;
            transition: background 0.2s, border-color 0.2s;
        }
        .dot.filled-safe { background: #16a34a; border-color: #16a34a; }
        .dot.filled-warning { background: #f59e0b; border-color: #f59e0b; }
        .dot.filled-danger { background: #dc2626; border-color: #dc2626; }

        /* Controls */
        .controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
        }
        .count-display {
            font-size: 13px;
            color: #64748b;
        }
        .count-display strong {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }
        .btn-group { display: flex; gap: 8px; }
        .btn-abs {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid #b3cfe8;
            background: #f0f7ff;
            color: #1e4f8c;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, transform 0.1s;
            line-height: 1;
        }
        .btn-abs:hover { background: #dbeaf5; }
        .btn-abs:active { transform: scale(0.93); }
        .btn-abs.decrement { color: #dc2626; border-color: #fca5a5; background: #fff8f8; }
        .btn-abs.decrement:hover { background: #fee2e2; }
        .btn-abs:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }

        .toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #1e3a5f;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s, transform 0.3s;
            z-index: 999;
            pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">
            <img src="usthb.png" class="logo-img" alt="USTHB Logo">
            <span>USTHB</span>
        </div>
        <nav>
            <a href="student.php" class="nav-item">Dashboard</a>
            <a href="classes.php" class="nav-item">My Classes</a>
            <a href="#" class="nav-item">Assignments</a>
            <a href="grades.php" class="nav-item">Grades</a>
            <a href="absences.php" class="nav-item active">Absences</a>
            <a href="logout.php" class="nav-item" style="color:#b91c1c; margin-top: auto;">Logout</a>
        </nav>
        <div class="help-box">
            <h4>HELP CENTER</h4>
            <p>Having trouble with registration? Contact the office.</p>
            <a href="#" class="support-link">Contact Support</a>
        </div>
    </aside>

    <main>
        <header>
            <div class="search-box">
                <input type="text" placeholder="Search for courses, lecturers...">
            </div>
            <div class="header-right">
                <span class="year-badge">Academic Year 2025/2026</span>
                <div class="user">
                    <div class="user-text">
                        <span class="name"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></span>
                        <span class="role">CS Undergraduate</span>
                    </div>
                    <div class="avatar"></div>
                </div>
            </div>
        </header>

        <div class="content">
            <div class="page-header">
                <h1>Absence Guard</h1>
                <p>Track your absences per module. Maximum 3 absences allowed per module.</p>
            </div>

            <div class="info-banner">
                ⚠️ <span>This is a <strong>personal tracker</strong>. Update your count honestly after each missed class. At <strong>3 absences</strong> you risk being excluded from the exam.</span>
            </div>

            <?php
            $total_absences = array_sum(array_column($modules, 'nombre'));
            $danger_count   = count(array_filter($modules, fn($m) => $m['nombre'] >= 3));
            $warning_count  = count(array_filter($modules, fn($m) => $m['nombre'] == 2));
            $safe_count     = count(array_filter($modules, fn($m) => $m['nombre'] <= 1));
            ?>

            <!-- Summary -->
            <div class="summary-strip">
                <div class="summary-item">
                    <span class="sv"><?= $total_absences ?></span>
                    <span class="sl">Total Absences</span>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-item">
                    <span class="sv" style="color:#16a34a"><?= $safe_count ?></span>
                    <span class="sl">Safe</span>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-item">
                    <span class="sv" style="color:#f59e0b"><?= $warning_count ?></span>
                    <span class="sl">Warning</span>
                </div>
                <div class="summary-divider"></div>
                <div class="summary-item">
                    <span class="sv" style="color:#dc2626"><?= $danger_count ?></span>
                    <span class="sl">At Limit</span>
                </div>
            </div>

            <!-- Module cards -->
            <div class="modules-grid">
                <?php foreach ($modules as $m):
                    $n = (int)$m['nombre'];
                    if ($n >= 3)     { $cardClass = 'danger';  $pillClass = 'danger';  $pillText = 'At Limit'; }
                    elseif ($n == 2) { $cardClass = 'warning'; $pillClass = 'warning'; $pillText = 'Warning'; }
                    else             { $cardClass = 'safe';    $pillClass = 'safe';    $pillText = 'Safe'; }

                    // Dot color class
                    if ($n >= 3)     $dotClass = 'filled-danger';
                    elseif ($n == 2) $dotClass = 'filled-warning';
                    else             $dotClass = 'filled-safe';
                ?>
                <div class="absence-card <?= $cardClass ?>" id="card-<?= $m['id'] ?>">
                    <div class="card-top">
                        <span class="module-code"><?= htmlspecialchars($m['code']) ?></span>
                        <span class="status-pill <?= $pillClass ?>" id="pill-<?= $m['id'] ?>"><?= $pillText ?></span>
                    </div>
                    <div class="module-name"><?= htmlspecialchars($m['intitule']) ?></div>
                    <div class="module-coeff">Coefficient <?= htmlspecialchars($m['coefficient']) ?></div>

                    <!-- Dots -->
                    <div class="dots-row" id="dots-<?= $m['id'] ?>">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="dot <?= $i <= $n ? $dotClass : '' ?>"></div>
                        <?php endfor; ?>
                    </div>

                    <!-- Controls -->
                    <div class="controls">
                        <div class="count-display">
                            <strong id="count-<?= $m['id'] ?>"><?= $n ?></strong> / 3 absences
                        </div>
                        <div class="btn-group">
                            <button class="btn-abs decrement"
                                onclick="updateAbsence(<?= $m['id'] ?>, 'decrement')"
                                id="dec-<?= $m['id'] ?>"
                                <?= $n <= 0 ? 'disabled' : '' ?>>−</button>
                            <button class="btn-abs increment"
                                onclick="updateAbsence(<?= $m['id'] ?>, 'increment')"
                                id="inc-<?= $m['id'] ?>"
                                <?= $n >= 3 ? 'disabled' : '' ?>>+</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<div class="toast" id="toast"></div>

<script>
function updateAbsence(moduleId, action) {
    fetch('absences.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `module_id=${moduleId}&action=${action}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const n = data.nombre;

        // Update count text
        document.getElementById('count-' + moduleId).textContent = n;

        // Update dots
        const dotsContainer = document.getElementById('dots-' + moduleId);
        const dots = dotsContainer.querySelectorAll('.dot');
        let dotClass = n >= 3 ? 'filled-danger' : (n == 2 ? 'filled-warning' : 'filled-safe');
        dots.forEach((dot, i) => {
            dot.className = 'dot';
            if (i < n) dot.classList.add(dotClass);
        });

        // Update card border
        const card = document.getElementById('card-' + moduleId);
        card.className = 'absence-card';
        if (n >= 3)     card.classList.add('danger');
        else if (n == 2) card.classList.add('warning');
        else             card.classList.add('safe');

        // Update pill
        const pill = document.getElementById('pill-' + moduleId);
        pill.className = 'status-pill';
        if (n >= 3)     { pill.classList.add('danger');  pill.textContent = 'At Limit'; }
        else if (n == 2){ pill.classList.add('warning'); pill.textContent = 'Warning'; }
        else            { pill.classList.add('safe');    pill.textContent = 'Safe'; }

        // Update buttons
        document.getElementById('dec-' + moduleId).disabled = (n <= 0);
        document.getElementById('inc-' + moduleId).disabled = (n >= 3);

        // Toast
        const msg = action === 'increment' ? 'Absence added' : 'Absence removed';
        showToast(msg);
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}
</script>
</body>
</html>
