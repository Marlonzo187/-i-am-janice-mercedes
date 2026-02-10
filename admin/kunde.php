<?php
// Kunden-Detailansicht + Buchungshistorie
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();
$anfrage_id = intval($_GET['id'] ?? 0);

if (!$anfrage_id) {
    header('Location: dashboard.php');
    exit;
}

// Anfrage + Kundendaten laden
$stmt = $pdo->prepare("
    SELECT a.*, k.id as kid, k.vorname, k.nachname, k.email, k.telefon,
           k.strasse, k.plz, k.ort, k.notizen as kunden_notizen
    FROM anfragen a
    JOIN kunden k ON a.kunden_id = k.id
    WHERE a.id = ?
");
$stmt->execute([$anfrage_id]);
$anfrage = $stmt->fetch();

if (!$anfrage) {
    header('Location: dashboard.php');
    exit;
}

// Buchungshistorie des Kunden
$hist_stmt = $pdo->prepare("
    SELECT id, formular_typ, behandlung, status, erstellt_am
    FROM anfragen
    WHERE kunden_id = ?
    ORDER BY erstellt_am DESC
");
$hist_stmt->execute([$anfrage['kid']]);
$historie = $hist_stmt->fetchAll();

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
    <title>Anfrage #<?= $anfrage_id ?> - Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <a href="dashboard.php" class="back-link">&larr; Dashboard</a>
        </div>
        <div class="header-right">
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <!-- Kundendaten -->
        <section class="detail-section">
            <h2><?= e($anfrage['vorname'] . ' ' . $anfrage['nachname']) ?></h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Telefon</span>
                    <a href="tel:<?= e($anfrage['telefon']) ?>" class="detail-value detail-link"><?= e($anfrage['telefon']) ?></a>
                </div>
                <?php if ($anfrage['email']): ?>
                <div class="detail-item">
                    <span class="detail-label">E-Mail</span>
                    <a href="mailto:<?= e($anfrage['email']) ?>" class="detail-value detail-link"><?= e($anfrage['email']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['strasse']): ?>
                <div class="detail-item">
                    <span class="detail-label">Adresse</span>
                    <span class="detail-value"><?= e($anfrage['strasse']) ?>, <?= e($anfrage['plz']) ?> <?= e($anfrage['ort']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Kunde seit</span>
                    <span class="detail-value"><?= count($historie) ?> Anfrage<?= count($historie) !== 1 ? 'n' : '' ?></span>
                </div>
            </div>
        </section>

        <!-- Status aendern -->
        <section class="detail-section">
            <h3>Status</h3>
            <div class="status-buttons" data-anfrage-id="<?= $anfrage_id ?>">
                <?php foreach ($status_labels as $key => $label): ?>
                    <button class="btn btn-status <?= $anfrage['status'] === $key ? 'active' : '' ?>"
                            data-status="<?= $key ?>"
                            style="<?= $anfrage['status'] === $key ? 'background:' . $status_colors[$key] . ';color:#1E1B18;' : 'border-color:' . $status_colors[$key] ?>">
                        <?= $label ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Anfrage-Details -->
        <section class="detail-section">
            <h3>Anfrage #<?= $anfrage_id ?> &mdash; <?= e($anfrage['formular_typ']) ?></h3>
            <div class="detail-grid">
                <?php if ($anfrage['behandlung']): ?>
                <div class="detail-item">
                    <span class="detail-label">Behandlung</span>
                    <span class="detail-value"><?= e($anfrage['behandlung']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['koerperstelle']): ?>
                <div class="detail-item">
                    <span class="detail-label">K&ouml;rperstelle</span>
                    <span class="detail-value"><?= e($anfrage['koerperstelle']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['groesse']): ?>
                <div class="detail-item">
                    <span class="detail-label">Gr&ouml;&szlig;e</span>
                    <span class="detail-value"><?= e($anfrage['groesse']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['motiv']): ?>
                <div class="detail-item detail-full">
                    <span class="detail-label">Motiv</span>
                    <span class="detail-value"><?= nl2br(e($anfrage['motiv'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['wochentage']): ?>
                <div class="detail-item">
                    <span class="detail-label">Wochentage</span>
                    <span class="detail-value"><?= e($anfrage['wochentage']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['bevorzugte_uhrzeit']): ?>
                <div class="detail-item">
                    <span class="detail-label">Uhrzeit</span>
                    <span class="detail-value"><?= e($anfrage['bevorzugte_uhrzeit']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($anfrage['anmerkungen']): ?>
                <div class="detail-item detail-full">
                    <span class="detail-label">Anmerkungen (Kunde)</span>
                    <span class="detail-value"><?= nl2br(e($anfrage['anmerkungen'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Eingegangen am</span>
                    <span class="detail-value"><?= date('d.m.Y, H:i', strtotime($anfrage['erstellt_am'])) ?> Uhr</span>
                </div>
            </div>
        </section>

        <!-- Referenzbild -->
        <?php if ($anfrage['referenzbild_pfad']): ?>
        <section class="detail-section">
            <h3>Referenzbild</h3>
            <div class="referenzbild-container">
                <img src="../<?= e($anfrage['referenzbild_pfad']) ?>" alt="Referenzbild" class="referenzbild">
            </div>
        </section>
        <?php endif; ?>

        <!-- Admin-Notizen (pro Anfrage) -->
        <section class="detail-section">
            <h3>Notizen zur Anfrage</h3>
            <textarea class="notizen-textarea" id="admin-notizen"
                      data-anfrage-id="<?= $anfrage_id ?>"
                      placeholder="Notizen zu dieser Anfrage..."><?= e($anfrage['admin_notizen'] ?? '') ?></textarea>
            <button class="btn btn-primary btn-save" id="save-admin-notizen">Speichern</button>
        </section>

        <!-- Kunden-Notizen (allgemein) -->
        <section class="detail-section">
            <h3>Allgemeine Kundennotizen</h3>
            <textarea class="notizen-textarea" id="kunden-notizen"
                      data-kunden-id="<?= $anfrage['kid'] ?>"
                      placeholder="Allgemeine Notizen zum Kunden..."><?= e($anfrage['kunden_notizen'] ?? '') ?></textarea>
            <button class="btn btn-primary btn-save" id="save-kunden-notizen">Speichern</button>
        </section>

        <!-- Buchungshistorie -->
        <?php if (count($historie) > 1): ?>
        <section class="detail-section">
            <h3>Buchungshistorie</h3>
            <div class="historie-list">
                <?php foreach ($historie as $h): ?>
                    <a href="kunde.php?id=<?= $h['id'] ?>"
                       class="historie-item <?= $h['id'] == $anfrage_id ? 'current' : '' ?>">
                        <span class="status-dot" style="background:<?= $status_colors[$h['status']] ?>"></span>
                        <span class="historie-typ"><?= e($h['formular_typ']) ?></span>
                        <?php if ($h['behandlung']): ?>
                            <span class="historie-detail"><?= e($h['behandlung']) ?></span>
                        <?php endif; ?>
                        <span class="historie-date"><?= date('d.m.Y', strtotime($h['erstellt_am'])) ?></span>
                        <span class="card-status status-<?= $h['status'] ?>"><?= $status_labels[$h['status']] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <script>
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

    // Status-Buttons
    document.querySelectorAll('.btn-status').forEach(btn => {
        btn.addEventListener('click', async function() {
            const status = this.dataset.status;
            const id = this.closest('.status-buttons').dataset.anfrageId;

            const res = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: `aktion=status&anfrage_id=${id}&status=${status}`
            });
            const data = await res.json();
            if (data.ok) {
                document.querySelectorAll('.btn-status').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                // Styling aktualisieren
                const colors = <?= json_encode($status_colors) ?>;
                document.querySelectorAll('.btn-status').forEach(b => {
                    b.style.background = '';
                    b.style.color = '';
                    b.style.borderColor = colors[b.dataset.status];
                });
                this.style.background = colors[status];
                this.style.color = '#1E1B18';
            }
        });
    });

    // Admin-Notizen speichern
    document.getElementById('save-admin-notizen').addEventListener('click', async function() {
        const textarea = document.getElementById('admin-notizen');
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: `aktion=admin_notizen&anfrage_id=${textarea.dataset.anfrageId}&notizen=${encodeURIComponent(textarea.value)}`
        });
        const data = await res.json();
        if (data.ok) {
            this.textContent = 'Gespeichert!';
            this.classList.add('btn-success');
            setTimeout(() => {
                this.textContent = 'Speichern';
                this.classList.remove('btn-success');
            }, 2000);
        }
    });

    // Kunden-Notizen speichern
    document.getElementById('save-kunden-notizen').addEventListener('click', async function() {
        const textarea = document.getElementById('kunden-notizen');
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: `aktion=kunden_notizen&kunden_id=${textarea.dataset.kundenId}&notizen=${encodeURIComponent(textarea.value)}`
        });
        const data = await res.json();
        if (data.ok) {
            this.textContent = 'Gespeichert!';
            this.classList.add('btn-success');
            setTimeout(() => {
                this.textContent = 'Speichern';
                this.classList.remove('btn-success');
            }, 2000);
        }
    });
    </script>
</body>
</html>
