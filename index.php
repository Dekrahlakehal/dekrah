<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . url(get_dashboard_url()));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ── Navbar ────────────────────────────────────────────── -->
<nav class="navbar">
    <div class="nav-logo">
        <div class="nav-logo-box blue"><span>FI</span></div>
        <div>
            <div class="nav-brand"><?= APP_NAME ?></div>
            <div class="nav-brand-sub"><?= APP_SUB ?></div>
        </div>
    </div>
    <div class="nav-links">
        <a href="index.php" class="nav-link active">Home</a>
        <a href="login.php" class="nav-link">Login</a>
    </div>
</nav>

<!-- ── Hero Section ──────────────────────────────────────── -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-title">Welcome to USTHB Scolarité Platform</div>
        <div class="hero-sub">Centralized scolarité management for the Faculty of Computing</div>
        <div class="hero-text">
            Access your personal space to view your grades, manage your modules and academic records.
        </div>
        <div class="hero-btns">
            <a href="login.php">
                <button class="btn-primary">Login</button>
            </a>
        </div>
    </div>
</section>

<!-- ── Features ────────────────────────────────────────────── -->
<section class="features">
    <div class="feat-card">
        <div class="feat-icon blue">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="5" r="3" fill="currentColor" />
                <path d="M2 13c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.5" fill="none" />
            </svg>
        </div>
        <div class="feat-title">Student Management</div>
        <div class="feat-desc">Add, edit, delete and view student records. Complete tracking of academic progress.</div>
    </div>

    <div class="feat-card">
        <div class="feat-icon green">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="none">
                <path d="M3 3h10v10H3z" fill="none" stroke="currentColor" stroke-width="1.2" />
                <path d="M6 7l2 2 4-4" stroke="currentColor" stroke-width="1.5" fill="none" />
            </svg>
        </div>
        <div class="feat-title">Grades and Averages</div>
        <div class="feat-desc">Grade entry by teachers, automatic weighted average calculation and transcript generation.</div>
    </div>

    <div class="feat-card">
        <div class="feat-icon amber">
            <svg width="20" height="20" viewBox="0 0 16 16" fill="none">
                <rect x="2" y="2" width="12" height="2.5" rx="1" fill="currentColor" />
                <rect x="2" y="6.5" width="12" height="2.5" rx="1" fill="currentColor" opacity=".6" />
                <rect x="2" y="11" width="8" height="2.5" rx="1" fill="currentColor" opacity=".3" />
            </svg>
        </div>
        <div class="feat-title">Statistics and Reports</div>
        <div class="feat-desc">Dashboards with statistics by year, module and class. Downloadable grade transcripts.</div>
    </div>
</section>

<!-- ── Rôles ────────────────────────────────────────────────── -->
<section class="roles-section">
    <div class="section-label">Available Spaces</div>
    <div class="roles-grid">
        <a href="login.php?role=etudiant" class="role-card">
            <div class="role-dot blue"></div>
            <div class="role-name">Student</div>
            <div class="role-desc">View your grades, transcripts and academic results.</div>
        </a>
        <a href="login.php?role=enseignant" class="role-card">
            <div class="role-dot green"></div>
            <div class="role-name">Teacher</div>
            <div class="role-desc">Enter grades, manage your modules and track results.</div>
        </a>
        <a href="login.php?role=admin" class="role-card">
            <div class="role-dot amber"></div>
            <div class="role-name">Administrator</div>
            <div class="role-desc">Complete management: students, teachers, modules and enrollments.</div>
        </a>
    </div>
</section>

<!-- ── About ──────────────────────────────────────────────── -->
<section class="about-section">
    <div class="about-title">About the Platform</div>
    <div class="about-text">
        This platform is developed for the Faculty of Computing at USTHB (Houari Boumediere University of Science and Technology). It allows complete and centralized scolarité management: student tracking, grade entry, module management and academic transcript generation.
    </div>
    <div class="stats-bar">
        <div>
            <div class="stat-n">+1200</div>
            <div class="stat-l">Enrolled Students</div>
        </div>
        <div>
            <div class="stat-n">80+</div>
            <div class="stat-l">Teachers</div>
        </div>
        <div>
            <div class="stat-n">30+</div>
            <div class="stat-l">Active Modules</div>
        </div>
        <div>
            <div class="stat-n">4</div>
            <div class="stat-l">Academic Levels</div>
        </div>
    </div>
</section>

<footer class="footer">
    © <?= date('Y') ?> <?= APP_NAME ?> · <?= APP_SUB ?>
</footer>

</body>
</html>
