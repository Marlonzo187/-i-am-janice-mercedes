<?php
// Besucher-Tracking: Seitenaufrufe in der Datenbank speichern
// Wird per JavaScript-Fetch von allen oeffentlichen Seiten aufgerufen

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://iamjanicemercedes.de');
header('Access-Control-Allow-Methods: POST');

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Bot-Erkennung: gaengige Bots ignorieren
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bot_patterns = '/bot|crawl|spider|slurp|baiduspider|yandex|sogou|duckduckbot|semrush|ahrefs|mj12bot|dotbot|petalbot|bytespider/i';
if (!$ua || preg_match($bot_patterns, $ua)) {
    echo json_encode(['ok' => true, 'tracked' => false]);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    // Daten aus dem Request
    $input = json_decode(file_get_contents('php://input'), true);
    $seite = trim($input['seite'] ?? '/');
    $referrer = trim($input['referrer'] ?? '');

    // Seite validieren (nur relative Pfade erlauben)
    $seite = '/' . ltrim(parse_url($seite, PHP_URL_PATH) ?? '/', '/');
    if (strlen($seite) > 255) {
        $seite = substr($seite, 0, 255);
    }

    // Referrer kuerzen
    if (strlen($referrer) > 500) {
        $referrer = substr($referrer, 0, 500);
    }

    // IP hashen (Datenschutz: keine Roh-IP speichern)
    $ip_raw = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip_hash = hash('sha256', $ip_raw . date('Y-m-d'));

    // Besucher-ID per Cookie (fuer Unique-Erkennung)
    $besucher_id = $_COOKIE['_jm_vid'] ?? '';
    if (!$besucher_id) {
        $besucher_id = bin2hex(random_bytes(16));
        setcookie('_jm_vid', $besucher_id, [
            'expires' => time() + 365 * 24 * 60 * 60,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    // Spam-Schutz: Max 1 Eintrag pro Seite pro IP pro Minute
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM besuche
        WHERE ip_hash = ? AND seite = ? AND zeitpunkt > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $check->execute([$ip_hash, $seite]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['ok' => true, 'tracked' => false]);
        exit;
    }

    // Besuch speichern
    $stmt = $pdo->prepare("
        INSERT INTO besuche (seite, referrer, ip_hash, besucher_id, user_agent, zeitpunkt)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $seite,
        $referrer,
        $ip_hash,
        $besucher_id,
        substr($ua, 0, 500),
    ]);

    echo json_encode(['ok' => true, 'tracked' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
