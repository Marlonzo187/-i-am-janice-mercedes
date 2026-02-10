<?php
// AJAX API fuer Status-Updates und Notizen
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF pruefen
if (!validateCSRF()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']);
    exit;
}

$pdo = getDB();
$aktion = $_POST['aktion'] ?? '';

switch ($aktion) {
    case 'status':
        $anfrage_id = intval($_POST['anfrage_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $erlaubt = ['neu', 'bestaetigt', 'abgeschlossen', 'storniert'];

        if (!$anfrage_id || !in_array($status, $erlaubt)) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Parameter']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE anfragen SET status = ? WHERE id = ?");
        $stmt->execute([$status, $anfrage_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'admin_notizen':
        $anfrage_id = intval($_POST['anfrage_id'] ?? 0);
        $notizen = $_POST['notizen'] ?? '';

        if (!$anfrage_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Anfrage-ID']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE anfragen SET admin_notizen = ? WHERE id = ?");
        $stmt->execute([$notizen, $anfrage_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'kunden_notizen':
        $kunden_id = intval($_POST['kunden_id'] ?? 0);
        $notizen = $_POST['notizen'] ?? '';

        if (!$kunden_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Kunden-ID']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE kunden SET notizen = ? WHERE id = ?");
        $stmt->execute([$notizen, $kunden_id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
        break;
}
