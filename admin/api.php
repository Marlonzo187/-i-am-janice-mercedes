<?php
// AJAX API fuer Status-Updates, Notizen und Terminbestaetigungen
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

    case 'anfrage_loeschen':
        $anfrage_id = intval($_POST['anfrage_id'] ?? 0);

        if (!$anfrage_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Anfrage-ID']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE anfragen SET geloescht_am = NOW() WHERE id = ? AND geloescht_am IS NULL");
        $stmt->execute([$anfrage_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'anfrage_wiederherstellen':
        $anfrage_id = intval($_POST['anfrage_id'] ?? 0);

        if (!$anfrage_id) {
            echo json_encode(['ok' => false, 'error' => 'Ungueltige Anfrage-ID']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE anfragen SET geloescht_am = NULL WHERE id = ?");
        $stmt->execute([$anfrage_id]);
        echo json_encode(['ok' => true]);
        break;

    case 'papierkorb_leeren':
        $pdo->exec("DELETE FROM anfragen WHERE geloescht_am IS NOT NULL");
        echo json_encode(['ok' => true]);
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

    case 'terminbestaetigung':
        $kunden_id = intval($_POST['kunden_id'] ?? 0);
        $typ = trim($_POST['typ'] ?? '');
        $datum = trim($_POST['datum'] ?? '');
        $uhrzeit = trim($_POST['uhrzeit'] ?? '');
        $betreff = trim($_POST['betreff'] ?? '');
        $nachricht = trim($_POST['nachricht'] ?? '');

        if (!$kunden_id || !$datum || !$uhrzeit || !$nachricht) {
            echo json_encode(['ok' => false, 'error' => 'Bitte alle Pflichtfelder ausfuellen']);
            exit;
        }

        // Kunde + E-Mail laden
        $stmt = $pdo->prepare("SELECT vorname, nachname, email FROM kunden WHERE id = ?");
        $stmt->execute([$kunden_id]);
        $kunde = $stmt->fetch();

        if (!$kunde || !$kunde['email']) {
            echo json_encode(['ok' => false, 'error' => 'Kunde nicht gefunden oder keine E-Mail hinterlegt']);
            exit;
        }

        // E-Mail senden
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../phpmailer/PHPMailer.php';
        require_once __DIR__ . '/../phpmailer/SMTP.php';
        require_once __DIR__ . '/../phpmailer/Exception.php';

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(SMTP_USER, 'I am Janice Mercedes');
            $mail->addAddress($kunde['email'], $kunde['vorname'] . ' ' . $kunde['nachname']);
            $mail->addReplyTo(SMTP_USER, 'I am Janice Mercedes');

            $mail->isHTML(true);
            $mail->Subject = $betreff ?: 'Deine Terminbestaetigung - I am Janice Mercedes';
            $mail->Body = terminmail_html($kunde['vorname'], $typ, $nachricht);
            $mail->AltBody = $nachricht;

            $mail->send();
            echo json_encode(['ok' => true]);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden: ' . $mail->ErrorInfo]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unbekannte Aktion']);
        break;
}

/**
 * HTML-Template fuer Terminbestaetigungs-E-Mails
 */
function terminmail_html(string $vorname, string $typ, string $nachricht): string {
    $typ_escaped = htmlspecialchars($typ);
    $vorname_escaped = htmlspecialchars($vorname);

    // Zeilen mit "- " am Anfang als Bullet-Points formatieren
    $zeilen = explode("\n", $nachricht);
    $nachricht_html = '';
    $in_liste = false;

    foreach ($zeilen as $zeile) {
        $zeile = trim($zeile);
        if (str_starts_with($zeile, '- ')) {
            if (!$in_liste) {
                $nachricht_html .= '<table cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;"><tbody>';
                $in_liste = true;
            }
            $text = htmlspecialchars(substr($zeile, 2));
            $nachricht_html .= '<tr><td style="padding:3px 10px 3px 0;vertical-align:top;color:#B59E7D;font-size:16px;">&#8226;</td><td style="padding:3px 0;color:#CEC1A8;font-size:15px;font-family:Segoe UI,Helvetica,Arial,sans-serif;">' . $text . '</td></tr>';
        } else {
            if ($in_liste) {
                $nachricht_html .= '</tbody></table>';
                $in_liste = false;
            }
            if ($zeile === '') {
                $nachricht_html .= '<div style="height:12px;"></div>';
            } else {
                $nachricht_html .= '<p style="margin:0 0 6px;color:#CEC1A8;font-size:15px;font-family:Segoe UI,Helvetica,Arial,sans-serif;line-height:1.6;">' . htmlspecialchars($zeile) . '</p>';
            }
        }
    }
    if ($in_liste) {
        $nachricht_html .= '</tbody></table>';
    }

    // Badge-Farbe je nach Typ
    $badge_bg = ($typ === 'Tattoo') ? 'rgba(181,158,125,0.25)' : 'rgba(206,193,168,0.2)';
    $badge_color = ($typ === 'Tattoo') ? '#B59E7D' : '#CEC1A8';

    return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#1E1B18;font-family:Segoe UI,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1E1B18;">
<tr><td align="center" style="padding:30px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#2A2520;border:1px solid #584738;border-radius:12px;overflow:hidden;">
    <!-- Header -->
    <tr><td style="padding:28px 30px 20px;text-align:center;border-bottom:1px solid #584738;">
        <div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#B59E7D;margin-bottom:6px;">I am Janice Mercedes</div>
        <div style="font-size:22px;font-weight:300;color:#F1EADA;letter-spacing:1px;">Terminbest&auml;tigung</div>
    </td></tr>
    <!-- Typ-Badge -->
    <tr><td style="padding:20px 30px 0;text-align:center;">
        <span style="display:inline-block;padding:6px 18px;background:' . $badge_bg . ';color:' . $badge_color . ';border-radius:20px;font-size:13px;letter-spacing:1px;text-transform:uppercase;">' . $typ_escaped . '</span>
    </td></tr>
    <!-- Nachricht -->
    <tr><td style="padding:20px 30px 28px;">
        <p style="margin:0 0 16px;color:#F1EADA;font-size:16px;font-family:Segoe UI,Helvetica,Arial,sans-serif;">Hallo ' . $vorname_escaped . ',</p>
        ' . $nachricht_html . '
    </td></tr>
    <!-- Footer -->
    <tr><td style="padding:20px 30px;border-top:1px solid #584738;text-align:center;">
        <p style="margin:0 0 8px;font-size:13px;color:#7E6957;">Janice Mercedes</p>
        <a href="https://iamjanicemercedes.de" style="font-size:12px;color:#B59E7D;text-decoration:none;letter-spacing:1px;">iamjanicemercedes.de</a>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}
