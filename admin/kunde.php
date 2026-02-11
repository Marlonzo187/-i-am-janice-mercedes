<?php
// Kunden-Detailansicht + Buchungshistorie + Datenschutz-Upload
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();
$anfrage_id = intval($_GET['id'] ?? 0);
$kunden_id = intval($_GET['kunden_id'] ?? 0);

$anfrage = null;
$kunde = null;

if ($anfrage_id) {
    // Anfrage + Kundendaten laden (bestehender Pfad)
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
    $kunden_id = $anfrage['kid'];
} elseif ($kunden_id) {
    // Direkter Kundenzugriff (aus Kundenliste)
    $stmt = $pdo->prepare("
        SELECT id as kid, vorname, nachname, email, telefon,
               strasse, plz, ort, notizen as kunden_notizen
        FROM kunden WHERE id = ?
    ");
    $stmt->execute([$kunden_id]);
    $kunde = $stmt->fetch();

    if (!$kunde) {
        header('Location: kunden.php');
        exit;
    }
} else {
    header('Location: dashboard.php');
    exit;
}

// Einheitliches Kunden-Array
$k = $anfrage ?: $kunde;

// Buchungshistorie des Kunden
$hist_stmt = $pdo->prepare("
    SELECT id, formular_typ, behandlung, status, erstellt_am
    FROM anfragen
    WHERE kunden_id = ? AND geloescht_am IS NULL
    ORDER BY erstellt_am DESC
");
$hist_stmt->execute([$kunden_id]);
$historie = $hist_stmt->fetchAll();

// Datenschutz-Dokumente laden
$dok_stmt = $pdo->prepare("
    SELECT * FROM datenschutz_dokumente
    WHERE kunden_id = ?
    ORDER BY hochgeladen_am DESC
");
$dok_stmt->execute([$kunden_id]);
$dokumente = $dok_stmt->fetchAll();

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

$seitentitel = $anfrage
    ? 'Anfrage #' . $anfrage_id . ' - Admin'
    : e($k['vorname'] . ' ' . $k['nachname']) . ' - Admin';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $seitentitel ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="kunden.php">Kunden</a>
                <a href="papierkorb.php">Papierkorb</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <!-- Kundendaten -->
        <section class="detail-section">
            <div class="section-header">
                <h2><?= e($k['vorname'] . ' ' . $k['nachname']) ?></h2>
                <a href="kunde-edit.php?id=<?= $kunden_id ?>" class="btn btn-small btn-outline">Bearbeiten</a>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Telefon</span>
                    <a href="tel:<?= e($k['telefon']) ?>" class="detail-value detail-link"><?= e($k['telefon']) ?></a>
                </div>
                <?php if ($k['email']): ?>
                <div class="detail-item">
                    <span class="detail-label">E-Mail</span>
                    <a href="mailto:<?= e($k['email']) ?>" class="detail-value detail-link"><?= e($k['email']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($k['strasse']): ?>
                <div class="detail-item">
                    <span class="detail-label">Adresse</span>
                    <span class="detail-value"><?= e($k['strasse']) ?>, <?= e($k['plz']) ?> <?= e($k['ort']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Anfragen</span>
                    <span class="detail-value"><?= count($historie) ?> Anfrage<?= count($historie) !== 1 ? 'n' : '' ?></span>
                </div>
            </div>
        </section>

        <?php if ($anfrage): ?>
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

        <!-- Anfrage loeschen -->
        <section class="detail-section anfrage-loeschen-section">
            <h3>Anfrage l&ouml;schen</h3>
            <p class="delete-hint">Verschiebt diese Anfrage in den Papierkorb. Sie kann dort wiederhergestellt werden.</p>
            <button class="btn btn-small btn-danger-outline" id="anfrage-loeschen" data-anfrage-id="<?= $anfrage_id ?>">In Papierkorb verschieben</button>
        </section>
        <?php endif; ?>

        <!-- Kunden-Notizen (allgemein) -->
        <section class="detail-section">
            <h3>Allgemeine Kundennotizen</h3>
            <textarea class="notizen-textarea" id="kunden-notizen"
                      data-kunden-id="<?= $kunden_id ?>"
                      placeholder="Allgemeine Notizen zum Kunden..."><?= e($k['kunden_notizen'] ?? '') ?></textarea>
            <button class="btn btn-primary btn-save" id="save-kunden-notizen">Speichern</button>
        </section>

        <!-- Datenschutzerklaerungen -->
        <section class="detail-section">
            <h3>Datenschutzerkl&auml;rungen</h3>
            <div class="upload-area">
                <label class="btn btn-outline upload-btn">
                    Foto / PDF hochladen
                    <input type="file" id="datenschutz-upload" accept="image/*,.pdf" capture="environment" hidden>
                </label>
                <span class="upload-hint">JPEG, PNG, WebP oder PDF (max. 10 MB)</span>
            </div>
            <div id="upload-status"></div>
            <div class="dokumente-list" id="dokumente-list">
                <?php if (empty($dokumente)): ?>
                    <p class="empty-hint" id="keine-dokumente">Noch keine Dokumente hochgeladen.</p>
                <?php endif; ?>
                <?php foreach ($dokumente as $dok):
                    $ist_bild = !str_ends_with($dok['dateiname'], '.pdf');
                    $pfad = '../uploads/datenschutz/' . e($dok['dateiname']);
                ?>
                    <div class="dokument-item" data-id="<?= $dok['id'] ?>">
                        <?php if ($ist_bild): ?>
                            <a href="<?= $pfad ?>" target="_blank" class="dokument-vorschau">
                                <img src="<?= $pfad ?>" alt="Datenschutzerklaerung">
                            </a>
                        <?php else: ?>
                            <a href="<?= $pfad ?>" target="_blank" class="dokument-vorschau dokument-pdf">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10 9 9 9 8 9"/>
                                </svg>
                                <span>PDF</span>
                            </a>
                        <?php endif; ?>
                        <div class="dokument-info">
                            <span class="dokument-name"><?= e($dok['original_name']) ?></span>
                            <span class="dokument-datum"><?= date('d.m.Y, H:i', strtotime($dok['hochgeladen_am'])) ?></span>
                        </div>
                        <button class="btn btn-small btn-danger dokument-loeschen" data-id="<?= $dok['id'] ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Buchungshistorie -->
        <?php if (count($historie) > 0): ?>
        <section class="detail-section">
            <h3>Buchungshistorie</h3>
            <div class="historie-list">
                <?php foreach ($historie as $h): ?>
                    <a href="kunde.php?id=<?= $h['id'] ?>"
                       class="historie-item <?= $anfrage && $h['id'] == $anfrage_id ? 'current' : '' ?>">
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
    const KUNDEN_ID = <?= $kunden_id ?>;

    <?php if ($anfrage): ?>
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

    // Anfrage loeschen (in Papierkorb)
    document.getElementById('anfrage-loeschen').addEventListener('click', async function() {
        if (!confirm('Anfrage wirklich in den Papierkorb verschieben?')) return;

        const id = this.dataset.anfrageId;
        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: `aktion=anfrage_loeschen&anfrage_id=${id}`
            });
            const data = await res.json();
            if (data.ok) {
                window.location.href = 'dashboard.php';
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            }
        } catch (err) {
            alert('Netzwerkfehler: ' + err.message);
        }
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
    <?php endif; ?>

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

    // Datenschutz-Upload
    document.getElementById('datenschutz-upload').addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        const statusEl = document.getElementById('upload-status');
        statusEl.textContent = 'Wird hochgeladen...';
        statusEl.className = 'upload-progress';

        const formData = new FormData();
        formData.append('aktion', 'datenschutz_upload');
        formData.append('kunden_id', KUNDEN_ID);
        formData.append('datei', file);

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                statusEl.textContent = 'Hochgeladen!';
                statusEl.className = 'upload-success';
                setTimeout(() => { statusEl.textContent = ''; statusEl.className = ''; }, 2000);

                // Kein-Dokumente-Hinweis entfernen
                const hint = document.getElementById('keine-dokumente');
                if (hint) hint.remove();

                // Neues Dokument in Liste einfuegen
                const list = document.getElementById('dokumente-list');
                const item = document.createElement('div');
                item.className = 'dokument-item';
                item.dataset.id = data.id;

                const pfad = '../uploads/datenschutz/' + data.dateiname;
                let vorschau;
                if (data.ist_bild) {
                    vorschau = `<a href="${pfad}" target="_blank" class="dokument-vorschau"><img src="${pfad}" alt="Datenschutzerklaerung"></a>`;
                } else {
                    vorschau = `<a href="${pfad}" target="_blank" class="dokument-vorschau dokument-pdf">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg><span>PDF</span></a>`;
                }

                item.innerHTML = `${vorschau}
                    <div class="dokument-info">
                        <span class="dokument-name">${escapeHtml(data.original_name)}</span>
                        <span class="dokument-datum">${data.hochgeladen_am}</span>
                    </div>
                    <button class="btn btn-small btn-danger dokument-loeschen" data-id="${data.id}">&times;</button>`;
                list.prepend(item);
            } else {
                statusEl.textContent = data.error || 'Fehler beim Hochladen';
                statusEl.className = 'upload-error';
            }
        } catch (err) {
            statusEl.textContent = 'Netzwerkfehler';
            statusEl.className = 'upload-error';
        }

        this.value = '';
    });

    // Dokument loeschen (delegiert)
    document.getElementById('dokumente-list').addEventListener('click', async function(e) {
        const btn = e.target.closest('.dokument-loeschen');
        if (!btn) return;

        if (!confirm('Dokument wirklich loeschen?')) return;

        const dokId = btn.dataset.id;
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: `aktion=datenschutz_loeschen&dok_id=${dokId}`
        });
        const data = await res.json();
        if (data.ok) {
            const item = btn.closest('.dokument-item');
            item.remove();
            // Pruefen ob Liste leer
            if (!document.querySelector('.dokument-item')) {
                const list = document.getElementById('dokumente-list');
                list.innerHTML = '<p class="empty-hint" id="keine-dokumente">Noch keine Dokumente hochgeladen.</p>';
            }
        }
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    </script>
</body>
</html>
