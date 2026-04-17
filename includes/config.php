<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'usthb_scolarite');
define('DB_USER', 'root');
define('DB_PASS', '1234');

function get_app_base_path(): string {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
    $appRoot = realpath(__DIR__ . '/..');

    if ($documentRoot && $appRoot && str_starts_with($appRoot, $documentRoot)) {
        $base = substr($appRoot, strlen($documentRoot));
        $base = str_replace('\\', '/', $base);
        $base = rtrim($base, '/');
        return $base === '' ? '' : $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function app_url(string $path = ''): string {
    $path = ltrim($path, '/');
    $base = get_app_base_path();
    return $base === '' ? '/' . $path : $base . '/' . $path;
}

function url(string $path = ''): string {
    return app_url($path);
}

function get_pdo() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

define('APP_NAME', 'USTHB Scolarité');
define('APP_SUB', 'Faculté d\'Informatique');
define('APP_YEAR', '2025/2026');
?>
