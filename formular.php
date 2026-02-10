<?php
// Formular-Handler fuer iamjanicemercedes.de
// Ersetzt Formspree - laeuft direkt auf IONOS

$empfaenger = 'info@iamjanicemercedes.de';
$absender_name = 'I am Janice Mercedes';
$absender_email = 'info@iamjanicemercedes.de';

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
$betreff = "Neue Anfrage: $formular_typ - $vorname $nachname";

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

// Referenzbild pruefen
$hat_bild = false;
$bild_fehler = '';
if (isset($_FILES['referenzbild']) && $_FILES['referenzbild']['error'] === UPLOAD_ERR_OK) {
    $erlaubte_typen = ['image/jpeg', 'image/png', 'image/webp'];
    $max_groesse = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['referenzbild']['type'], $erlaubte_typen)) {
        $bild_fehler = 'Nur JPG, PNG oder WEBP erlaubt.';
    } elseif ($_FILES['referenzbild']['size'] > $max_groesse) {
        $bild_fehler = 'Datei zu gross (max. 5MB).';
    } else {
        $hat_bild = true;
    }
}

// E-Mail senden
$boundary = md5(time());
$headers = "From: $absender_name <$absender_email>\r\n";
if ($email) {
    $headers .= "Reply-To: $vorname $nachname <$email>\r\n";
}

if ($hat_bild) {
    // E-Mail mit Anhang (MIME multipart)
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $datei_name = $_FILES['referenzbild']['name'];
    $datei_inhalt = chunk_split(base64_encode(file_get_contents($_FILES['referenzbild']['tmp_name'])));
    $datei_typ = $_FILES['referenzbild']['type'];

    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $nachricht . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: $datei_typ; name=\"$datei_name\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"$datei_name\"\r\n\r\n";
    $body .= $datei_inhalt . "\r\n";
    $body .= "--$boundary--";

    $erfolg = mail($empfaenger, $betreff, $body, $headers);
} else {
    // Einfache Text-E-Mail
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $erfolg = mail($empfaenger, $betreff, $nachricht, $headers);
}

// Weiterleitung zur Bestaetigungsseite
if ($erfolg) {
    header('Location: danke.html');
} else {
    header('Location: fehler.html');
}
exit;
