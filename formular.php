<?php
// Formular-Handler fuer iamjanicemercedes.de
// Sendet per SMTP ueber IONOS Mailserver + speichert in Datenbank

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Nur POST-Anfragen akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// Formulardaten sammeln
$formular_typ = htmlspecialchars($_POST['formular_typ'] ?? 'Unbekannt');
$vorname = htmlspecialchars($_POST['vorname'] ?? '');
$nachname = htmlspecialchars($_POST['nachname'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$telefon = htmlspecialchars($_POST['telefon'] ?? '');
$strasse = htmlspecialchars($_POST['strasse'] ?? '');
$plz = htmlspecialchars($_POST['plz'] ?? '');
$ort = htmlspecialchars($_POST['ort'] ?? '');
$behandlung = htmlspecialchars($_POST['behandlung'] ?? '');
$koerperstelle = htmlspecialchars($_POST['koerperstelle'] ?? '');
$groesse = htmlspecialchars($_POST['groesse'] ?? '');
$motiv = htmlspecialchars($_POST['motiv'] ?? '');
$anmerkungen = htmlspecialchars($_POST['anmerkungen'] ?? '');
$bevorzugte_uhrzeit = htmlspecialchars($_POST['bevorzugte_uhrzeit'] ?? '');

// Wochentage als kommaseparierte Liste
$wochentage = '';
if (isset($_POST['wochentage']) && is_array($_POST['wochentage'])) {
    $wochentage = implode(', ', array_map('htmlspecialchars', $_POST['wochentage']));
}

// E-Mail Betreff
$betreff = "Kundenanfrage $formular_typ - $vorname $nachname";

// E-Mail Text aufbauen
$nachricht = "NEUE ANFRAGE: $formular_typ\n";
$nachricht .= str_repeat('=', 50) . "\n\n";
$nachricht .= "Name: $vorname $nachname\n";
if ($email) $nachricht .= "E-Mail: $email\n";
$nachricht .= "Telefon: $telefon\n";

if ($strasse) {
    $nachricht .= "\nAdresse:\n";
    $nachricht .= "$strasse\n";
    $nachricht .= "$plz $ort\n";
}

if ($behandlung) $nachricht .= "\nBehandlung: $behandlung\n";
if ($koerperstelle) $nachricht .= "\nKoerperstelle: $koerperstelle\n";
if ($groesse) $nachricht .= "Groesse: $groesse\n";
if ($motiv) $nachricht .= "\nMotiv-Beschreibung:\n$motiv\n";
if ($wochentage) $nachricht .= "\nVerfuegbare Wochentage: $wochentage\n";
if ($bevorzugte_uhrzeit) $nachricht .= "Bevorzugte Uhrzeit: $bevorzugte_uhrzeit\n";
if ($anmerkungen) $nachricht .= "\nAnmerkungen:\n$anmerkungen\n";

// Referenzbild verarbeiten (fuer E-Mail-Anhang + DB-Speicherung)
$referenzbild_tmp = null;
$referenzbild_name = null;
$referenzbild_pfad = null;

if (isset($_FILES['referenzbild']) && $_FILES['referenzbild']['error'] === UPLOAD_ERR_OK) {
    $erlaubte_typen = ['image/jpeg', 'image/png', 'image/webp'];
    $max_groesse = 5 * 1024 * 1024; // 5MB

    if (in_array($_FILES['referenzbild']['type'], $erlaubte_typen)
        && $_FILES['referenzbild']['size'] <= $max_groesse) {
        $referenzbild_tmp = $_FILES['referenzbild']['tmp_name'];
        $referenzbild_name = $_FILES['referenzbild']['name'];

        // Sicheren Dateinamen erzeugen
        $ext = pathinfo($referenzbild_name, PATHINFO_EXTENSION);
        $sicherer_name = uniqid('ref_', true) . '.' . strtolower($ext);
        $upload_dir = __DIR__ . '/uploads/referenzbilder/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (move_uploaded_file($referenzbild_tmp, $upload_dir . $sicherer_name)) {
            $referenzbild_pfad = 'uploads/referenzbilder/' . $sicherer_name;
        }
    }
}

// PHPMailer mit SMTP einrichten
$mail = new PHPMailer(true);
$email_gesendet = false;

try {
    // SMTP-Konfiguration (IONOS)
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    // Absender und Empfaenger
    $mail->setFrom(SMTP_USER, 'I am Janice Mercedes');
    $mail->addAddress(SMTP_USER);
    if ($email) {
        $mail->addReplyTo($email, "$vorname $nachname");
    }

    // Betreff und Inhalt
    $mail->Subject = $betreff;
    $mail->Body = $nachricht;

    // Referenzbild als Anhang (aus gespeicherter Datei oder tmp)
    if ($referenzbild_pfad) {
        $mail->addAttachment(__DIR__ . '/' . $referenzbild_pfad, $referenzbild_name);
    }

    $mail->send();
    $email_gesendet = true;
} catch (Exception $e) {
    // E-Mail-Fehler, aber DB-Speicherung trotzdem versuchen
}

// In Datenbank speichern (unabhaengig vom E-Mail-Erfolg)
try {
    require_once __DIR__ . '/db.php';
    $pdo = getDB();

    // Kunde suchen oder anlegen (Match ueber Telefonnummer)
    $stmt = $pdo->prepare("SELECT id FROM kunden WHERE telefon = ?");
    $stmt->execute([$telefon]);
    $kunde = $stmt->fetch();

    if ($kunde) {
        // Bestandskunde: Daten aktualisieren
        $kunden_id = $kunde['id'];
        $update = $pdo->prepare("
            UPDATE kunden SET
                vorname = ?, nachname = ?,
                email = COALESCE(NULLIF(?, ''), email),
                strasse = COALESCE(NULLIF(?, ''), strasse),
                plz = COALESCE(NULLIF(?, ''), plz),
                ort = COALESCE(NULLIF(?, ''), ort)
            WHERE id = ?
        ");
        $update->execute([$vorname, $nachname, $email, $strasse, $plz, $ort, $kunden_id]);
    } else {
        // Neukunde anlegen
        $insert = $pdo->prepare("
            INSERT INTO kunden (vorname, nachname, email, telefon, strasse, plz, ort)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$vorname, $nachname, $email ?: null, $telefon, $strasse ?: null, $plz ?: null, $ort ?: null]);
        $kunden_id = $pdo->lastInsertId();
    }

    // Anfrage speichern
    $stmt = $pdo->prepare("
        INSERT INTO anfragen (kunden_id, formular_typ, behandlung, koerperstelle, groesse, motiv,
            referenzbild_pfad, wochentage, bevorzugte_uhrzeit, anmerkungen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $kunden_id,
        $formular_typ,
        $behandlung ?: null,
        $koerperstelle ?: null,
        $groesse ?: null,
        $motiv ?: null,
        $referenzbild_pfad,
        $wochentage ?: null,
        $bevorzugte_uhrzeit ?: null,
        $anmerkungen ?: null,
    ]);
} catch (\Exception $e) {
    // DB-Fehler: E-Mail ging trotzdem raus (wenn erfolgreich)
    // Fehler wird still ignoriert, damit der Benutzer nicht beeintraechtigt wird
}

// Weiterleitung basierend auf E-Mail-Erfolg
if ($email_gesendet) {
    header('Location: danke.html');
} else {
    header('Location: fehler.html');
}
exit;
