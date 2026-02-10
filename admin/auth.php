<?php
// Session-Check: wird in jede Admin-Seite eingebunden
session_start();

// Session-Timeout: 30 Minuten Inaktivitaet
$timeout = 30 * 60;
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: index.php?timeout=1');
    exit;
}
$_SESSION['admin_last_activity'] = time();

// Login pruefen
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// CSRF-Token erzeugen falls nicht vorhanden
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * CSRF-Token validieren
 */
function validateCSRF(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Alle Ausgaben mit htmlspecialchars escapen
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
