<?php
// Admin Dashboard: Alle Anfragen als Liste mit Filter/Suche
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();

// Filter-Parameter
$status_filter = $_GET['status'] ?? '';
$typ_filter = $_GET['typ'] ?? '';
$suche = trim($_GET['suche'] ?? '');
$seite = max(1, intval($_GET['seite'] ?? 1));
$pro_seite = 20;
$offset = ($seite - 1) * $pro_seite;

// Query aufbauen
$where = ['a.geloescht_am IS NULL'];
$params = [];

if ($status_filter && in_array($status_filter, ['neu', 'bestaetigt', 'abgeschlossen', 'storniert'])) {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($typ_filter) {
    $where[] = "a.formular_typ LIKE ?";
    $params[] = '%' . $typ_filter . '%';
}

if ($suche) {
    $where[] = "(k.vorname LIKE ? OR k.nachname LIKE ? OR k.telefon LIKE ? OR k.email LIKE ?)";
    $suche_param = '%' . $suche . '%';
    $params[] = $suche_param;
    $params[] = $suche_param;
    $params[] = $suche_param;
    $params[] = $suche_param;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Gesamtanzahl fuer Paginierung
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM anfragen a
    JOIN kunden k ON a.kunden_id = k.id
    $where_sql
");
$count_stmt->execute($params);
$gesamt = $count_stmt->fetchColumn();
$seiten_gesamt = max(1, ceil($gesamt / $pro_seite));

// Anfragen laden
$stmt = $pdo->prepare("
    SELECT a.*, k.vorname, k.nachname, k.telefon, k.email
    FROM anfragen a
    JOIN kunden k ON a.kunden_id = k.id
    $where_sql
    ORDER BY a.erstellt_am DESC
    LIMIT $pro_seite OFFSET $offset
");
$stmt->execute($params);
$anfragen = $stmt->fetchAll();

// Status-Zaehler fuer Filter-Badges
$status_counts = $pdo->query("
    SELECT status, COUNT(*) as anzahl FROM anfragen WHERE geloescht_am IS NULL GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$papierkorb_count = $pdo->query("
    SELECT COUNT(*) FROM anfragen WHERE geloescht_am IS NOT NULL
")->fetchColumn();

$status_labels = [
    'neu' => 'Neu',
    'bestaetigt' => 'Best&auml;tigt',
    'abgeschlossen' => 'Abgeschlossen',
    'storniert' => 'Storniert',
];

$status_colors = [
    'neu' => '#B59E7D',
    'bestaetigt' => '#6AAF6A',
    'abgeschlossen' => '#7A7A7A',
    'storniert' => '#C07070',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <nav class="admin-nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="kunden.php">Kunden</a>
                <a href="statistiken.php">Statistiken</a>
                <a href="papierkorb.php">Papierkorb<?php if ($papierkorb_count): ?> <span class="badge">(<?= $papierkorb_count ?>)</span><?php endif; ?></a>
            </nav>
        </div>
        <div class="header-right">
            <a href="kunde-edit.php" class="btn btn-small btn-primary">+ Neuer Kunde</a>
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <!-- Suche -->
        <form method="GET" class="search-bar">
            <input type="text" name="suche" placeholder="Name, Telefon oder E-Mail suchen..."
                   value="<?= e($suche) ?>" class="search-input">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?= e($status_filter) ?>">
            <?php endif; ?>
            <?php if ($typ_filter): ?>
                <input type="hidden" name="typ" value="<?= e($typ_filter) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Suchen</button>
        </form>

        <!-- Filter -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Status:</span>
                <a href="?<?= http_build_query(array_filter(['suche' => $suche, 'typ' => $typ_filter])) ?>"
                   class="filter-chip <?= !$status_filter ? 'active' : '' ?>">
                    Alle <span class="badge"><?= array_sum($status_counts ?? []) ?></span>
                </a>
                <?php foreach ($status_labels as $key => $label): ?>
                    <a href="?<?= http_build_query(array_filter(['status' => $key, 'suche' => $suche, 'typ' => $typ_filter])) ?>"
                       class="filter-chip <?= $status_filter === $key ? 'active' : '' ?>"
                       style="<?= $status_filter === $key ? 'border-color:' . $status_colors[$key] : '' ?>">
                        <?= $label ?> <span class="badge"><?= $status_counts[$key] ?? 0 ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="filter-group">
                <span class="filter-label">Typ:</span>
                <a href="?<?= http_build_query(array_filter(['status' => $status_filter, 'suche' => $suche])) ?>"
                   class="filter-chip <?= !$typ_filter ? 'active' : '' ?>">Alle</a>
                <a href="?<?= http_build_query(array_filter(['typ' => 'Beauty', 'status' => $status_filter, 'suche' => $suche])) ?>"
                   class="filter-chip <?= $typ_filter === 'Beauty' ? 'active' : '' ?>">Beauty</a>
                <a href="?<?= http_build_query(array_filter(['typ' => 'Tattoo', 'status' => $status_filter, 'suche' => $suche])) ?>"
                   class="filter-chip <?= $typ_filter === 'Tattoo' ? 'active' : '' ?>">Tattoo</a>
            </div>
        </div>

        <!-- Anfragen-Liste -->
        <?php if (empty($anfragen)): ?>
            <div class="empty-state">
                <p>Keine Anfragen gefunden.</p>
            </div>
        <?php else: ?>
            <div class="anfragen-list">
                <?php foreach ($anfragen as $a): ?>
                    <a href="kunde.php?id=<?= $a['id'] ?>" class="anfrage-card">
                        <div class="card-header">
                            <span class="status-dot" style="background:<?= $status_colors[$a['status']] ?>"></span>
                            <span class="card-name"><?= e($a['vorname'] . ' ' . $a['nachname']) ?></span>
                            <span class="card-date"><?= date('d.m.Y', strtotime($a['erstellt_am'])) ?></span>
                        </div>
                        <div class="card-body">
                            <span class="card-typ"><?= e($a['formular_typ']) ?></span>
                            <?php if ($a['behandlung']): ?>
                                <span class="card-detail"><?= e($a['behandlung']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <span class="card-phone"><?= e($a['telefon']) ?></span>
                            <span class="card-status status-<?= $a['status'] ?>"><?= $status_labels[$a['status']] ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Paginierung -->
            <?php if ($seiten_gesamt > 1): ?>
                <div class="pagination">
                    <?php if ($seite > 1): ?>
                        <a href="?<?= http_build_query(array_filter(['seite' => $seite - 1, 'status' => $status_filter, 'typ' => $typ_filter, 'suche' => $suche])) ?>"
                           class="btn btn-small btn-outline">&laquo; Zurück</a>
                    <?php endif; ?>
                    <span class="page-info">Seite <?= $seite ?> von <?= $seiten_gesamt ?></span>
                    <?php if ($seite < $seiten_gesamt): ?>
                        <a href="?<?= http_build_query(array_filter(['seite' => $seite + 1, 'status' => $status_filter, 'typ' => $typ_filter, 'suche' => $suche])) ?>"
                           class="btn btn-small btn-outline">Weiter &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
