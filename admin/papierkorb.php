<?php
// Papierkorb: Geloeschte Anfragen anzeigen, wiederherstellen, endgueltig loeschen
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();

// Geloeschte Anfragen laden
$stmt = $pdo->query("
    SELECT a.*, k.vorname, k.nachname, k.telefon, k.email
    FROM anfragen a
    JOIN kunden k ON a.kunden_id = k.id
    WHERE a.geloescht_am IS NOT NULL
    ORDER BY a.geloescht_am DESC
");
$anfragen = $stmt->fetchAll();

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
    <title>Papierkorb - Admin</title>
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
                <a href="papierkorb.php" class="active">Papierkorb</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <div class="papierkorb-header">
            <h1 class="page-title">Papierkorb <span class="badge-count">(<?= count($anfragen) ?>)</span></h1>
            <?php if (!empty($anfragen)): ?>
                <button class="btn btn-small btn-danger-outline" id="papierkorb-leeren">Papierkorb leeren</button>
            <?php endif; ?>
        </div>

        <?php if (empty($anfragen)): ?>
            <div class="empty-state">
                <p>Der Papierkorb ist leer.</p>
            </div>
        <?php else: ?>
            <div class="anfragen-list">
                <?php foreach ($anfragen as $a): ?>
                    <div class="anfrage-card papierkorb-card" data-id="<?= $a['id'] ?>">
                        <div class="card-header">
                            <span class="status-dot" style="background:<?= $status_colors[$a['status']] ?>"></span>
                            <span class="card-name"><?= e($a['vorname'] . ' ' . $a['nachname']) ?></span>
                            <span class="card-date">Gel&ouml;scht: <?= date('d.m.Y, H:i', strtotime($a['geloescht_am'])) ?></span>
                        </div>
                        <div class="card-body">
                            <span class="card-typ"><?= e($a['formular_typ']) ?></span>
                            <?php if ($a['behandlung']): ?>
                                <span class="card-detail"><?= e($a['behandlung']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <span class="card-phone"><?= e($a['telefon']) ?></span>
                            <button class="btn btn-small btn-outline btn-wiederherstellen" data-id="<?= $a['id'] ?>">Wiederherstellen</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

    // Wiederherstellen
    document.querySelectorAll('.btn-wiederherstellen').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.dataset.id;
            const res = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: `aktion=anfrage_wiederherstellen&anfrage_id=${id}`
            });
            const data = await res.json();
            if (data.ok) {
                const card = this.closest('.papierkorb-card');
                card.style.opacity = '0';
                card.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    card.remove();
                    // Zaehler aktualisieren
                    const remaining = document.querySelectorAll('.papierkorb-card').length;
                    document.querySelector('.badge-count').textContent = `(${remaining})`;
                    if (remaining === 0) {
                        document.querySelector('.anfragen-list').innerHTML = '<div class="empty-state"><p>Der Papierkorb ist leer.</p></div>';
                        const leeren = document.getElementById('papierkorb-leeren');
                        if (leeren) leeren.remove();
                    }
                }, 300);
            }
        });
    });

    // Papierkorb leeren
    const leerenBtn = document.getElementById('papierkorb-leeren');
    if (leerenBtn) {
        leerenBtn.addEventListener('click', async function() {
            if (!confirm('Papierkorb wirklich leeren? Alle Anfragen werden endgueltig geloescht!')) return;

            const res = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: 'aktion=papierkorb_leeren'
            });
            const data = await res.json();
            if (data.ok) {
                window.location.reload();
            }
        });
    }
    </script>
</body>
</html>
