<?php
// Kundenliste: Alle Kunden unabhaengig von Anfragen
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();

$suche = trim($_GET['suche'] ?? '');
$seite = max(1, intval($_GET['seite'] ?? 1));
$pro_seite = 20;
$offset = ($seite - 1) * $pro_seite;

$where = [];
$params = [];

if ($suche) {
    $where[] = "(k.vorname LIKE ? OR k.nachname LIKE ? OR k.telefon LIKE ? OR k.email LIKE ?)";
    $suche_param = '%' . $suche . '%';
    $params = [$suche_param, $suche_param, $suche_param, $suche_param];
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Gesamtanzahl
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM kunden k $where_sql");
$count_stmt->execute($params);
$gesamt = $count_stmt->fetchColumn();
$seiten_gesamt = max(1, ceil($gesamt / $pro_seite));

// Kunden laden mit Anfragen-Zaehler
$stmt = $pdo->prepare("
    SELECT k.*, COUNT(a.id) as anfragen_count
    FROM kunden k
    LEFT JOIN anfragen a ON a.kunden_id = k.id
    $where_sql
    GROUP BY k.id
    ORDER BY k.nachname ASC, k.vorname ASC
    LIMIT $pro_seite OFFSET $offset
");
$stmt->execute($params);
$kunden = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kunden - Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="kunden.php" class="active">Kunden</a>
                <a href="papierkorb.php">Papierkorb</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="kunde-edit.php" class="btn btn-small btn-primary">+ Neuer Kunde</a>
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1 class="page-title">Kunden <span class="badge-count"><?= $gesamt ?></span></h1>

        <!-- Suche -->
        <form method="GET" class="search-bar">
            <input type="text" name="suche" placeholder="Name, Telefon oder E-Mail suchen..."
                   value="<?= e($suche) ?>" class="search-input">
            <button type="submit" class="btn btn-primary">Suchen</button>
        </form>

        <!-- Kundenliste -->
        <?php if (empty($kunden)): ?>
            <div class="empty-state">
                <p>Keine Kunden gefunden.</p>
                <?php if (!$suche): ?>
                    <a href="kunde-edit.php" class="btn btn-primary" style="margin-top:16px;">Ersten Kunden anlegen</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="kunden-list">
                <?php foreach ($kunden as $k): ?>
                    <a href="kunde.php?kunden_id=<?= $k['id'] ?>" class="kunde-card">
                        <div class="card-header">
                            <span class="card-name"><?= e($k['vorname'] . ' ' . $k['nachname']) ?></span>
                            <span class="badge-count"><?= $k['anfragen_count'] ?> Anfrage<?= $k['anfragen_count'] != 1 ? 'n' : '' ?></span>
                        </div>
                        <div class="card-footer">
                            <span class="card-phone"><?= e($k['telefon']) ?></span>
                            <?php if ($k['email']): ?>
                                <span class="card-email"><?= e($k['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Paginierung -->
            <?php if ($seiten_gesamt > 1): ?>
                <div class="pagination">
                    <?php if ($seite > 1): ?>
                        <a href="?<?= http_build_query(array_filter(['seite' => $seite - 1, 'suche' => $suche])) ?>"
                           class="btn btn-small btn-outline">&laquo; Zur&uuml;ck</a>
                    <?php endif; ?>
                    <span class="page-info">Seite <?= $seite ?> von <?= $seiten_gesamt ?></span>
                    <?php if ($seite < $seiten_gesamt): ?>
                        <a href="?<?= http_build_query(array_filter(['seite' => $seite + 1, 'suche' => $suche])) ?>"
                           class="btn btn-small btn-outline">Weiter &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
