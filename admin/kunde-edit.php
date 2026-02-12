<?php
// Kunde anlegen oder bearbeiten
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();
$kunden_id = intval($_GET['id'] ?? 0);
$kunde = null;
$fehler = '';
$erfolg = '';

// Bestehenden Kunden laden
if ($kunden_id) {
    $stmt = $pdo->prepare("SELECT * FROM kunden WHERE id = ?");
    $stmt->execute([$kunden_id]);
    $kunde = $stmt->fetch();
    if (!$kunde) {
        header('Location: kunden.php');
        exit;
    }
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $fehler = 'CSRF-Token ungueltig. Bitte Seite neu laden.';
    } else {
        $vorname = trim($_POST['vorname'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $strasse = trim($_POST['strasse'] ?? '');
        $plz = trim($_POST['plz'] ?? '');
        $ort = trim($_POST['ort'] ?? '');

        if (!$vorname || !$nachname || !$telefon) {
            $fehler = 'Vorname, Nachname und Telefon sind Pflichtfelder.';
        } else {
            if ($kunden_id) {
                $stmt = $pdo->prepare("
                    UPDATE kunden SET vorname = ?, nachname = ?, telefon = ?, email = ?, strasse = ?, plz = ?, ort = ?
                    WHERE id = ?
                ");
                $stmt->execute([$vorname, $nachname, $telefon, $email, $strasse, $plz, $ort, $kunden_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO kunden (vorname, nachname, telefon, email, strasse, plz, ort)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$vorname, $nachname, $telefon, $email, $strasse, $plz, $ort]);
                $kunden_id = $pdo->lastInsertId();
            }
            header('Location: kunden.php');
            exit;
        }

        // Bei Fehler: eingegebene Werte beibehalten
        $kunde = [
            'vorname' => $vorname, 'nachname' => $nachname, 'telefon' => $telefon,
            'email' => $email, 'strasse' => $strasse, 'plz' => $plz, 'ort' => $ort,
        ];
    }
}

$titel = $kunden_id ? 'Kunde bearbeiten' : 'Neuer Kunde';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titel ?> - Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="kunden.php">Kunden</a>
                <a href="statistiken.php">Statistiken</a>
                <a href="papierkorb.php">Papierkorb</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1 class="page-title"><?= $titel ?></h1>

        <?php if ($fehler): ?>
            <div class="alert alert-error"><?= e($fehler) ?></div>
        <?php endif; ?>

        <form method="POST" class="detail-section">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="detail-grid">
                <div class="form-group">
                    <label for="vorname">Vorname *</label>
                    <input type="text" id="vorname" name="vorname" required
                           value="<?= e($kunde['vorname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="nachname">Nachname *</label>
                    <input type="text" id="nachname" name="nachname" required
                           value="<?= e($kunde['nachname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="telefon">Telefon *</label>
                    <input type="text" id="telefon" name="telefon" required
                           value="<?= e($kunde['telefon'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <input type="email" id="email" name="email"
                           value="<?= e($kunde['email'] ?? '') ?>">
                </div>
                <div class="form-group detail-full">
                    <label for="strasse">Stra&szlig;e</label>
                    <input type="text" id="strasse" name="strasse"
                           value="<?= e($kunde['strasse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="plz">PLZ</label>
                    <input type="text" id="plz" name="plz"
                           value="<?= e($kunde['plz'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="ort">Ort</label>
                    <input type="text" id="ort" name="ort"
                           value="<?= e($kunde['ort'] ?? '') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="kunden.php" class="btn btn-outline">Abbrechen</a>
            </div>
        </form>
    </main>
</body>
</html>
