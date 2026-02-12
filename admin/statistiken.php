<?php
// Besucher-Statistiken: Seitenaufrufe, Unique Visitors, beliebte Seiten
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

$pdo = getDB();

// Zeitraum-Filter
$zeitraum = $_GET['zeitraum'] ?? '30';
if (!in_array($zeitraum, ['7', '30', '90', '365'])) {
    $zeitraum = '30';
}

$zeitraum_labels = [
    '7' => 'Letzte 7 Tage',
    '30' => 'Letzte 30 Tage',
    '90' => 'Letzte 90 Tage',
    '365' => 'Letztes Jahr',
];

// === KENNZAHLEN ===

// Heute
$heute = $pdo->query("
    SELECT COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche WHERE DATE(zeitpunkt) = CURDATE()
")->fetch();

// Gestern
$gestern = $pdo->query("
    SELECT COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche WHERE DATE(zeitpunkt) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
")->fetch();

// Diese Woche (Mo-So)
$woche = $pdo->query("
    SELECT COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche WHERE zeitpunkt >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
")->fetch();

// Dieser Monat
$monat = $pdo->query("
    SELECT COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche WHERE YEAR(zeitpunkt) = YEAR(CURDATE()) AND MONTH(zeitpunkt) = MONTH(CURDATE())
")->fetch();

// Gesamt
$gesamt = $pdo->query("
    SELECT COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche
")->fetch();

// === TAEGLICH (fuer Chart) ===
$taeglich_stmt = $pdo->prepare("
    SELECT DATE(zeitpunkt) as tag,
           COUNT(*) as aufrufe,
           COUNT(DISTINCT besucher_id) as besucher
    FROM besuche
    WHERE zeitpunkt >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(zeitpunkt)
    ORDER BY tag ASC
");
$taeglich_stmt->execute([$zeitraum]);
$taeglich = $taeglich_stmt->fetchAll();

// Alle Tage auffuellen (auch Tage ohne Besuche)
$chart_daten = [];
$start = new DateTime("-{$zeitraum} days");
$ende = new DateTime('today');
$interval = new DateInterval('P1D');
$periode = new DatePeriod($start, $interval, $ende->modify('+1 day'));

$taeglich_map = [];
foreach ($taeglich as $t) {
    $taeglich_map[$t['tag']] = $t;
}

foreach ($periode as $tag) {
    $datum = $tag->format('Y-m-d');
    $chart_daten[] = [
        'tag' => $datum,
        'label' => $tag->format('d.m.'),
        'aufrufe' => (int)($taeglich_map[$datum]['aufrufe'] ?? 0),
        'besucher' => (int)($taeglich_map[$datum]['besucher'] ?? 0),
    ];
}

// === BELIEBTE SEITEN ===
$seiten_stmt = $pdo->prepare("
    SELECT seite, COUNT(*) as aufrufe, COUNT(DISTINCT besucher_id) as besucher
    FROM besuche
    WHERE zeitpunkt >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY seite
    ORDER BY aufrufe DESC
    LIMIT 10
");
$seiten_stmt->execute([$zeitraum]);
$beliebte_seiten = $seiten_stmt->fetchAll();

// Seitennamen schoener machen
$seiten_namen = [
    '/' => 'Startseite',
    '/index.html' => 'Startseite',
    '/beauty.html' => 'Beauty',
    '/tattoo.html' => 'Tattoo',
    '/tattoo-events.html' => 'Tattoo Events',
    '/hochzeit-tattoo.html' => 'Hochzeit Tattoo',
    '/agb.html' => 'AGB',
    '/datenschutz.html' => 'Datenschutz',
    '/impressum.html' => 'Impressum',
    '/danke.html' => 'Danke-Seite',
    '/fehler.html' => 'Fehler-Seite',
    '/visitenkarte-janice.html' => 'Visitenkarte',
];

// === TOP REFERRER ===
$referrer_stmt = $pdo->prepare("
    SELECT referrer, COUNT(*) as aufrufe
    FROM besuche
    WHERE zeitpunkt >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
      AND referrer != ''
      AND referrer NOT LIKE '%iamjanicemercedes.de%'
    GROUP BY referrer
    ORDER BY aufrufe DESC
    LIMIT 10
");
$referrer_stmt->execute([$zeitraum]);
$top_referrer = $referrer_stmt->fetchAll();

// Papierkorb-Count fuer Navigation
$papierkorb_count = $pdo->query("
    SELECT COUNT(*) FROM anfragen WHERE geloescht_am IS NOT NULL
")->fetchColumn();

// Chart-Daten als JSON
$chart_json = json_encode($chart_daten);
$max_aufrufe = max(array_column($chart_daten, 'aufrufe') ?: [0]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiken - Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="admin-header">
        <div class="header-left">
            <nav class="admin-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="kunden.php">Kunden</a>
                <a href="statistiken.php" class="active">Statistiken</a>
                <a href="papierkorb.php">Papierkorb<?php if ($papierkorb_count): ?> <span class="badge">(<?= $papierkorb_count ?>)</span><?php endif; ?></a>
            </nav>
        </div>
        <div class="header-right">
            <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
        </div>
    </header>

    <main class="admin-main">
        <h1 class="page-title">Besucher-Statistiken</h1>

        <!-- Zeitraum-Filter -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Zeitraum:</span>
                <?php foreach ($zeitraum_labels as $key => $label): ?>
                    <a href="?zeitraum=<?= $key ?>"
                       class="filter-chip <?= $zeitraum === $key ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kennzahlen -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Heute</div>
                <div class="stat-value"><?= $heute['besucher'] ?></div>
                <div class="stat-detail"><?= $heute['aufrufe'] ?> Aufrufe</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Gestern</div>
                <div class="stat-value"><?= $gestern['besucher'] ?></div>
                <div class="stat-detail"><?= $gestern['aufrufe'] ?> Aufrufe</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Diese Woche</div>
                <div class="stat-value"><?= $woche['besucher'] ?></div>
                <div class="stat-detail"><?= $woche['aufrufe'] ?> Aufrufe</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Dieser Monat</div>
                <div class="stat-value"><?= $monat['besucher'] ?></div>
                <div class="stat-detail"><?= $monat['aufrufe'] ?> Aufrufe</div>
            </div>
        </div>

        <!-- Gesamt-Info -->
        <div class="stat-total">
            Gesamt: <strong><?= $gesamt['besucher'] ?></strong> Besucher &middot; <strong><?= $gesamt['aufrufe'] ?></strong> Seitenaufrufe
        </div>

        <!-- Chart: Besucher pro Tag -->
        <div class="detail-section">
            <h3>Besucher pro Tag</h3>
            <div class="chart-container" id="chart">
                <canvas id="besucherChart"></canvas>
            </div>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot legend-besucher"></span> Besucher</span>
                <span class="legend-item"><span class="legend-dot legend-aufrufe"></span> Seitenaufrufe</span>
            </div>
        </div>

        <!-- Beliebte Seiten -->
        <div class="detail-section">
            <h3>Beliebte Seiten</h3>
            <?php if (empty($beliebte_seiten)): ?>
                <p class="empty-hint">Noch keine Daten vorhanden.</p>
            <?php else: ?>
                <div class="stats-table">
                    <?php
                    $max_seiten_aufrufe = $beliebte_seiten[0]['aufrufe'] ?? 1;
                    foreach ($beliebte_seiten as $s):
                        $name = $seiten_namen[$s['seite']] ?? $s['seite'];
                        $prozent = round(($s['aufrufe'] / $max_seiten_aufrufe) * 100);
                    ?>
                        <div class="stats-row">
                            <div class="stats-row-header">
                                <span class="stats-row-name"><?= e($name) ?></span>
                                <span class="stats-row-values"><?= $s['besucher'] ?> Besucher &middot; <?= $s['aufrufe'] ?> Aufrufe</span>
                            </div>
                            <div class="stats-bar-bg">
                                <div class="stats-bar" style="width: <?= $prozent ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Referrer -->
        <div class="detail-section">
            <h3>Woher kommen die Besucher?</h3>
            <?php if (empty($top_referrer)): ?>
                <p class="empty-hint">Noch keine externen Verweise erfasst.</p>
            <?php else: ?>
                <div class="stats-table">
                    <?php
                    $max_ref_aufrufe = $top_referrer[0]['aufrufe'] ?? 1;
                    foreach ($top_referrer as $r):
                        // Domain aus Referrer extrahieren
                        $ref_domain = parse_url($r['referrer'], PHP_URL_HOST) ?: $r['referrer'];
                        $prozent = round(($r['aufrufe'] / $max_ref_aufrufe) * 100);
                    ?>
                        <div class="stats-row">
                            <div class="stats-row-header">
                                <span class="stats-row-name"><?= e($ref_domain) ?></span>
                                <span class="stats-row-values"><?= $r['aufrufe'] ?> Besuche</span>
                            </div>
                            <div class="stats-bar-bg">
                                <div class="stats-bar stats-bar-referrer" style="width: <?= $prozent ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    // Einfacher Canvas-Chart ohne externe Libraries
    (function() {
        const daten = <?= $chart_json ?>;
        const canvas = document.getElementById('besucherChart');
        if (!canvas || !daten.length) return;

        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;

        function zeichneChart() {
            const container = canvas.parentElement;
            const w = container.offsetWidth;
            const h = 220;

            canvas.style.width = w + 'px';
            canvas.style.height = h + 'px';
            canvas.width = w * dpr;
            canvas.height = h * dpr;
            ctx.scale(dpr, dpr);

            // Farben
            const farbe_besucher = '#B59E7D';
            const farbe_aufrufe = 'rgba(181, 158, 125, 0.3)';
            const farbe_grid = 'rgba(90, 77, 65, 0.3)';
            const farbe_text = '#7E6957';

            // Padding
            const pl = 40, pr = 10, pt = 10, pb = 30;
            const cw = w - pl - pr;
            const ch = h - pt - pb;

            ctx.clearRect(0, 0, w, h);

            // Max-Werte
            const maxAufrufe = Math.max(...daten.map(d => d.aufrufe), 1);
            const maxBesucher = Math.max(...daten.map(d => d.besucher), 1);
            const maxY = Math.max(maxAufrufe, maxBesucher);

            // Grid-Linien
            const gridSteps = 4;
            ctx.strokeStyle = farbe_grid;
            ctx.lineWidth = 1;
            ctx.font = '11px "Segoe UI", sans-serif';
            ctx.fillStyle = farbe_text;
            ctx.textAlign = 'right';

            for (let i = 0; i <= gridSteps; i++) {
                const y = pt + (ch / gridSteps) * i;
                const val = Math.round(maxY - (maxY / gridSteps) * i);
                ctx.beginPath();
                ctx.moveTo(pl, y);
                ctx.lineTo(w - pr, y);
                ctx.stroke();
                ctx.fillText(val, pl - 6, y + 4);
            }

            // X-Achsen-Labels
            ctx.textAlign = 'center';
            const labelInterval = Math.max(1, Math.floor(daten.length / 8));
            daten.forEach(function(d, i) {
                if (i % labelInterval === 0 || i === daten.length - 1) {
                    const x = pl + (cw / (daten.length - 1 || 1)) * i;
                    ctx.fillText(d.label, x, h - 6);
                }
            });

            if (daten.length < 2) return;

            // Aufrufe-Flaeche
            ctx.beginPath();
            daten.forEach(function(d, i) {
                const x = pl + (cw / (daten.length - 1)) * i;
                const y = pt + ch - (d.aufrufe / maxY) * ch;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            // Flaeche schliessen
            ctx.lineTo(pl + cw, pt + ch);
            ctx.lineTo(pl, pt + ch);
            ctx.closePath();
            ctx.fillStyle = farbe_aufrufe;
            ctx.fill();

            // Besucher-Linie
            ctx.beginPath();
            ctx.strokeStyle = farbe_besucher;
            ctx.lineWidth = 2.5;
            ctx.lineJoin = 'round';
            daten.forEach(function(d, i) {
                const x = pl + (cw / (daten.length - 1)) * i;
                const y = pt + ch - (d.besucher / maxY) * ch;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.stroke();

            // Punkte auf Besucher-Linie (nur wenn weniger als 40 Tage)
            if (daten.length <= 40) {
                daten.forEach(function(d, i) {
                    const x = pl + (cw / (daten.length - 1)) * i;
                    const y = pt + ch - (d.besucher / maxY) * ch;
                    ctx.beginPath();
                    ctx.arc(x, y, 3, 0, Math.PI * 2);
                    ctx.fillStyle = farbe_besucher;
                    ctx.fill();
                });
            }
        }

        zeichneChart();
        window.addEventListener('resize', zeichneChart);
    })();
    </script>
</body>
</html>
