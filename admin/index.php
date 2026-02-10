<?php
// Admin Login-Seite
session_start();

// Bereits eingeloggt? Weiterleiten
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'Sitzung abgelaufen. Bitte erneut einloggen.';
}
if (isset($_GET['error'])) {
    $error = 'Benutzername oder Passwort falsch.';
}
if (isset($_GET['locked'])) {
    $error = 'Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - I am Janice Mercedes</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-logo">I am Janice Mercedes</div>
        <h1>Admin</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">
            <div class="form-group">
                <label for="username">Benutzername</label>
                <input type="text" id="username" name="username" required autocomplete="username" autofocus>
            </div>
            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Einloggen</button>
        </form>
    </div>
</body>
</html>
