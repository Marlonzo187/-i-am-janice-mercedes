<?php
// Login-Handler (POST)
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Rate-Limiting: max 5 Versuche in 15 Minuten
$max_versuche = 5;
$sperr_zeit = 15 * 60;

if (!isset($_SESSION['login_versuche'])) {
    $_SESSION['login_versuche'] = 0;
    $_SESSION['login_erste_zeit'] = time();
}

// Sperrzeit abgelaufen? Zaehler zuruecksetzen
if (time() - $_SESSION['login_erste_zeit'] > $sperr_zeit) {
    $_SESSION['login_versuche'] = 0;
    $_SESSION['login_erste_zeit'] = time();
}

if ($_SESSION['login_versuche'] >= $max_versuche) {
    header('Location: index.php?locked=1');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS_HASH)) {
    // Login erfolgreich
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['login_versuche'] = 0;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: dashboard.php');
} else {
    // Login fehlgeschlagen
    $_SESSION['login_versuche']++;
    header('Location: index.php?error=1');
}
exit;
