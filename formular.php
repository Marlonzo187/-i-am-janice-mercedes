<?php
// Formular-Handler fuer iamjanicemercedes.de
// Sendet per SMTP ueber IONOS Mailserver (kein Spam mehr)

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

// PHPMailer mit SMTP einrichten
$mail = new PHPMailer(true);

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

    // Referenzbild als Anhang
    if (isset($_FILES['referenzbild']) && $_FILES['referenzbild']['error'] === UPLOAD_ERR_OK) {
        $erlaubte_typen = ['image/jpeg', 'image/png', 'image/webp'];
        $max_groesse = 5 * 1024 * 1024; // 5MB

        if (in_array($_FILES['referenzbild']['type'], $erlaubte_typen)
            && $_FILES['referenzbild']['size'] <= $max_groesse) {
            $mail->addAttachment(
                $_FILES['referenzbild']['tmp_name'],
                $_FILES['referenzbild']['name']
            );
        }
    }

    $mail->send();
    header('Location: danke.html');
} catch (Exception $e) {
    header('Location: fehler.html');
}
exit;
