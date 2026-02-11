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

    case 'kunde_speichern':
        $vorname = trim($_POST['vorname'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $strasse = trim($_POST['strasse'] ?? '');
        $plz = trim($_POST['plz'] ?? '');
        $ort = trim($_POST['ort'] ?? '');

        if (!$vorname || !$nachname || !$telefon) {
            echo json_encode(['ok' => false, 'error' => 'Vorname, Nachname und Telefon sind Pflichtfelder']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO kunden (vorname, nachname, telefon, email, strasse, plz, ort)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$vorname, $nachname, $telefon, $email, $strasse, $plz, $ort]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'kunde_bearbeiten':
        $kunden_id = intval($_POST['kunden_id'] ?? 0);
        $vorname = trim($_POST['vorname'] ?? '');
        $nachname = trim($_POST['nachname'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $strasse = trim($_POST['strasse'] ?? '');
        $plz = trim($_POST['plz'] ?? '');
        $ort = trim($_POST['ort'] ?? '');

        if (!$kunden_id || !$vorname || !$nachname || !$telefon) {
            echo json_encode(['ok' => false, 'error' => 'Vorname, Nachname und Telefon sind Pflichtfelder']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE kunden SET vorname = ?, nachname = ?, telefon = ?, email = ?, strasse = ?, plz = ?, ort = ?
            WHERE id = ?
        ");
        $stmt->execute([$vorname, $nachname, $telefon, $email, $strasse, $plz, $ort, $kunden_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'datenschutz_upload':
        $kunden_id = intval($_POST['kunden_id'] ?? 0);

        if (!$kunden_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Kunden-ID']);
            exit;
        }

        if (!isset($_FILES['datei']) || $_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'Keine Datei hochgeladen']);
            exit;
        }

        $datei = $_FILES['datei'];
        $erlaubte_typen = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($datei['tmp_name']);

        if (!in_array($mime, $erlaubte_typen)) {
            echo json_encode(['ok' => false, 'error' => 'Nur JPEG, PNG, WebP und PDF erlaubt']);
            exit;
        }

        if ($datei['size'] > 10 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'error' => 'Datei zu gross (max. 10 MB)']);
            exit;
        }

        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        $ext = $ext_map[$mime];
        $dateiname = 'dse_' . $kunden_id . '_' . uniqid() . '.' . $ext;

        $upload_dir = __DIR__ . '/../uploads/datenschutz';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ziel = $upload_dir . '/' . $dateiname;
        if (!move_uploaded_file($datei['tmp_name'], $ziel)) {
            echo json_encode(['ok' => false, 'error' => 'Datei konnte nicht gespeichert werden']);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO datenschutz_dokumente (kunden_id, dateiname, original_name)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$kunden_id, $dateiname, $datei['name']]);

        echo json_encode([
            'ok' => true,
            'id' => $pdo->lastInsertId(),
            'dateiname' => $dateiname,
            'original_name' => $datei['name'],
            'hochgeladen_am' => date('d.m.Y, H:i'),
            'ist_bild' => str_starts_with($mime, 'image/'),
        ]);
        break;

    case 'datenschutz_loeschen':
        $dok_id = intval($_POST['dok_id'] ?? 0);

        if (!$dok_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Dokument-ID']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT dateiname FROM datenschutz_dokumente WHERE id = ?");
        $stmt->execute([$dok_id]);
        $dok = $stmt->fetch();

        if (!$dok) {
            echo json_encode(['ok' => false, 'error' => 'Dokument nicht gefunden']);
            exit;
        }

        $dateipfad = __DIR__ . '/../uploads/datenschutz/' . $dok['dateiname'];
        if (file_exists($dateipfad)) {
            unlink($dateipfad);
        }

        $stmt = $pdo->prepare("DELETE FROM datenschutz_dokumente WHERE id = ?");
        $stmt->execute([$dok_id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
        break;
}
